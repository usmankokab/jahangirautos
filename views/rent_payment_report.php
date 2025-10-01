<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('rent_payment_report', 'view');

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'due_date';

// Build WHERE clause
$where_conditions = ["r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)"];
$params = [$to_date, $from_date];
$types = "ss";

if ($status_filter !== 'all') {
    if ($status_filter === 'overdue') {
        $where_conditions[] = "r.end_date < CURDATE()";
    } elseif ($status_filter === 'active') {
        $where_conditions[] = "r.end_date >= CURDATE() OR r.end_date IS NULL";
    }
    // No additional type needed for these conditions
}

$where_clause = implode(" AND ", $where_conditions);

// Rent payment analysis
$payment_query = "
    SELECT
        r.id,
        r.customer_id,
        c.name as customer_name,
        c.phone as customer_phone,
        c.cnic as customer_cnic,
        r.product_name,
        r.rent_type,
        r.daily_rent,
        r.total_rent,
        r.start_date,
        r.end_date,
        DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1 as rental_days,
        CASE
            WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
            ELSE r.total_rent
        END as expected_amount,
        CASE
            WHEN r.end_date < CURDATE() THEN 'Overdue'
            WHEN r.end_date >= CURDATE() OR r.end_date IS NULL THEN 'Active'
            ELSE 'Completed'
        END as payment_status,
        CASE
            WHEN r.end_date < CURDATE() THEN DATEDIFF(CURDATE(), r.end_date)
            ELSE 0
        END as days_overdue,
        r.comments
    FROM rents r
    JOIN customers c ON r.customer_id = c.id
    WHERE $where_clause
    ORDER BY
        CASE
            WHEN ? = 'due_date' THEN COALESCE(r.end_date, CURDATE())
            WHEN ? = 'customer' THEN c.name
            WHEN ? = 'amount' THEN CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
            ELSE COALESCE(r.end_date, CURDATE())
        END ASC
";

$stmt = $conn->prepare($payment_query);
$stmt->bind_param($types . "sss", ...array_merge($params, [$sort_by, $sort_by, $sort_by]));
$stmt->execute();
$rent_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary statistics
$summary_query = "
    SELECT
        COUNT(*) as total_rents,
        COUNT(CASE WHEN r.end_date < CURDATE() THEN 1 END) as overdue_rents,
        COUNT(CASE WHEN r.end_date >= CURDATE() OR r.end_date IS NULL THEN 1 END) as active_rents,
        COALESCE(SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ), 0) as total_expected_amount,
        COALESCE(SUM(
            CASE
                WHEN r.end_date < CURDATE() THEN
                    CASE
                        WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                        ELSE r.total_rent
                    END
                ELSE 0
            END
        ), 0) as overdue_amount,
        AVG(
            CASE
                WHEN r.end_date < CURDATE() THEN DATEDIFF(CURDATE(), r.end_date)
                ELSE NULL
            END
        ) as avg_days_overdue
    FROM rents r
    WHERE r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("ss", $to_date, $from_date);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Payment status breakdown
$status_query = "
    SELECT
        CASE
            WHEN r.end_date < CURDATE() THEN 'Overdue'
            WHEN r.end_date >= CURDATE() OR r.end_date IS NULL THEN 'Active'
            ELSE 'Completed'
        END as status,
        COUNT(*) as count,
        COALESCE(SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ), 0) as total_amount
    FROM rents r
    WHERE r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
    GROUP BY status
";

$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param("ss", $to_date, $from_date);
$status_stmt->execute();
$payment_statuses = $status_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Overdue analysis by period
$overdue_periods_query = "
    SELECT
        CASE
            WHEN days_overdue <= 7 THEN '1-7 days'
            WHEN days_overdue <= 30 THEN '8-30 days'
            WHEN days_overdue <= 90 THEN '31-90 days'
            ELSE '90+ days'
        END as period,
        COUNT(*) as count,
        SUM(expected_amount) as amount
    FROM (
        SELECT
            DATEDIFF(CURDATE(), r.end_date) as days_overdue,
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END as expected_amount
        FROM rents r
        WHERE r.end_date < CURDATE() AND r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
    ) overdue_data
    GROUP BY period
    ORDER BY
        CASE period
            WHEN '1-7 days' THEN 1
            WHEN '8-30 days' THEN 2
            WHEN '31-90 days' THEN 3
            ELSE 4
        END
";

$overdue_stmt = $conn->prepare($overdue_periods_query);
$overdue_stmt->bind_param("ss", $to_date, $from_date);
$overdue_stmt->execute();
$overdue_periods = $overdue_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top overdue customers
$top_overdue_query = "
    SELECT
        c.id,
        c.name,
        c.phone,
        c.cnic,
        COUNT(r.id) as overdue_rents,
        SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ) as total_overdue_amount,
        AVG(DATEDIFF(CURDATE(), r.end_date)) as avg_days_overdue,
        GROUP_CONCAT(r.product_name SEPARATOR ', ') as products
    FROM customers c
    JOIN rents r ON c.id = r.customer_id
    WHERE r.end_date < CURDATE() AND r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
    GROUP BY c.id, c.name, c.phone, c.cnic
    ORDER BY total_overdue_amount DESC
    LIMIT 10
";

$top_overdue_stmt = $conn->prepare($top_overdue_query);
$top_overdue_stmt->bind_param("ss", $to_date, $from_date);
$top_overdue_stmt->execute();
$top_overdue_customers = $top_overdue_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-credit-card me-2"></i>Rent Payment Report</h2>
        <div class="d-flex gap-2 no-print">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button class="btn btn-outline-success" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel"></i> Export
            </button>
            <a href="reports_dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Rents</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_rents'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Active Rents</div>
                            <div class="h4 mb-0"><?= number_format($summary['active_rents'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-play-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Overdue Rents</div>
                            <div class="h4 mb-0"><?= number_format($summary['overdue_rents'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Overdue Amount</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['overdue_amount'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Expected</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['total_expected_amount'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calculator fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-secondary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Avg Days Overdue</div>
                            <div class="h4 mb-0"><?= number_format($summary['avg_days_overdue'] ?? 0, 1) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 no-print">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Rentals</option>
                        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="overdue" <?= $status_filter == 'overdue' ? 'selected' : '' ?>>Overdue Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort_by" class="form-select">
                        <option value="due_date" <?= $sort_by == 'due_date' ? 'selected' : '' ?>>Due Date</option>
                        <option value="customer" <?= $sort_by == 'customer' ? 'selected' : '' ?>>Customer</option>
                        <option value="amount" <?= $sort_by == 'amount' ? 'selected' : '' ?>>Amount</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="rent_payment_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Payment Status Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Payment Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Overdue Periods Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Overdue Analysis by Period</h5>
                </div>
                <div class="card-body">
                    <canvas id="overdueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Overdue Customers -->
    <?php if (!empty($top_overdue_customers)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Top Overdue Customers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Overdue Rents</th>
                                    <th>Total Overdue Amount</th>
                                    <th>Avg Days Overdue</th>
                                    <th>Products</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_overdue_customers as $customer): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                        <small class="text-muted">
                                            CNIC: <?= htmlspecialchars($customer['cnic']) ?><br>
                                            Phone: <?= htmlspecialchars($customer['phone']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><?= $customer['overdue_rents'] ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-danger">₨<?= number_format($customer['total_overdue_amount'], 0) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?= number_format($customer['avg_days_overdue'], 1) ?> days</span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($customer['products']) ?></small>
                                    </td>
                                    <td class="no-print">
                                        <?php if($customer['phone']): ?>
                                            <a href="tel:<?= $customer['phone'] ?>" class="btn btn-sm btn-outline-primary" title="Call Customer">
                                                <i class="bi bi-telephone"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="list_rents.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-info" title="View Customer Rents">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detailed Payment Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Rent Payment Status</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Rent Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Expected Amount</th>
                                    <th>Status</th>
                                    <th>Days Overdue</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($rent_payments as $rent): ?>
                                <tr class="<?= $rent['payment_status'] == 'Overdue' ? 'table-danger' : ($rent['payment_status'] == 'Active' ? 'table-success' : 'table-light') ?>">
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($rent['customer_name']) ?></div>
                                        <small class="text-muted">
                                            CNIC: <?= htmlspecialchars($rent['customer_cnic']) ?><br>
                                            Phone: <?= htmlspecialchars($rent['customer_phone']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($rent['product_name']) ?></div>
                                        <small class="text-muted">
                                            Type: <?= ucfirst($rent['rent_type']) ?><br>
                                            Days: <?= $rent['rental_days'] ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $rent['rent_type'] == 'daily' ? 'primary' : 'info' ?>">
                                            <?= ucfirst($rent['rent_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $rent['start_date'] ?></td>
                                    <td>
                                        <?= $rent['end_date'] ?: '<em>Ongoing</em>' ?>
                                    </td>
                                    <td>
                                        <strong>₨<?= number_format($rent['expected_amount'], 0) ?></strong>
                                        <?php if($rent['rent_type'] == 'daily'): ?>
                                            <br><small class="text-muted">₨<?= $rent['daily_rent'] ?>/day</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $rent['payment_status'] == 'Overdue' ? 'danger' : ($rent['payment_status'] == 'Active' ? 'success' : 'secondary') ?>">
                                            <?= $rent['payment_status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($rent['days_overdue'] > 0): ?>
                                            <span class="badge bg-danger"><?= $rent['days_overdue'] ?> days</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <?php if($rent['customer_phone']): ?>
                                            <a href="tel:<?= $rent['customer_phone'] ?>" class="btn btn-sm btn-outline-primary" title="Call Customer">
                                                <i class="bi bi-telephone"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="view_rent.php?rent_id=<?= $rent['id'] ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Payment Status Chart
const statusData = <?= json_encode($payment_statuses) ?>;
const statusLabels = statusData.map(item => item.status);
const statusCounts = statusData.map(item => parseInt(item.count));

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusCounts,
            backgroundColor: ['#28a745', '#dc3545', '#6c757d'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Overdue Periods Chart
const overdueData = <?= json_encode($overdue_periods) ?>;
const overdueLabels = overdueData.map(item => item.period);
const overdueCounts = overdueData.map(item => parseInt(item.count));

const overdueCtx = document.getElementById('overdueChart').getContext('2d');
new Chart(overdueCtx, {
    type: 'bar',
    data: {
        labels: overdueLabels,
        datasets: [{
            label: 'Number of Overdue Rents',
            data: overdueCounts,
            backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#6c757d'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Rentals'
                }
            }
        }
    }
});

function exportToExcel() {
    let csv = 'Rent Payment Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';

    csv += 'Summary:\n';
    csv += 'Total Rents: <?= $summary['total_rents'] ?? 0 ?>\n';
    csv += 'Active Rents: <?= $summary['active_rents'] ?? 0 ?>\n';
    csv += 'Overdue Rents: <?= $summary['overdue_rents'] ?? 0 ?>\n';
    csv += 'Overdue Amount: ₨<?= number_format($summary['overdue_amount'] ?? 0, 0) ?>\n\n';

    csv += 'Detailed Payment Status:\n';
    csv += 'Customer,CNIC,Phone,Product,Rent Type,Start Date,End Date,Expected Amount,Status,Days Overdue\n';
    <?php foreach($rent_payments as $rent): ?>
    csv += '<?= addslashes($rent['customer_name']) ?>,<?= $rent['customer_cnic'] ?>,<?= $rent['customer_phone'] ?>,<?= addslashes($rent['product_name']) ?>,<?= $rent['rent_type'] ?>,<?= $rent['start_date'] ?>,<?= $rent['end_date'] ?: 'Ongoing' ?>,<?= $rent['expected_amount'] ?>,<?= $rent['payment_status'] ?>,<?= $rent['days_overdue'] ?>\n';
    <?php endforeach; ?>

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'rent_payment_report_<?= date('Y-m-d') ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
}

.card {
    transition: transform 0.2s ease-in-out;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.table-danger {
    background-color: #f8d7da !important;
}

.table-success {
    background-color: #d4edda !important;
}

.table-light {
    background-color: #fefefe !important;
}

.bg-primary { background: linear-gradient(45deg, #007bff, #0056b3) !important; }
.bg-success { background: linear-gradient(45deg, #28a745, #1e7e34) !important; }
.bg-info { background: linear-gradient(45deg, #17a2b8, #117a8b) !important; }
.bg-warning { background: linear-gradient(45deg, #ffc107, #d39e00) !important; }
.bg-danger { background: linear-gradient(45deg, #dc3545, #bd2130) !important; }
.bg-secondary { background: linear-gradient(45deg, #6c757d, #545b62) !important; }

.table-dark th {
    color: #000000 !important;
    font-weight: 600 !important;
}
</style>

<?php include '../includes/footer.php'; ?>