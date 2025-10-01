<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('overdue_report', 'view');

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'days_overdue';

// Build WHERE clause
$where_conditions = ["i.due_date <= CURDATE()"];
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Overdue installments analysis
$overdue_query = "
    SELECT
        i.id as installment_id,
        ROW_NUMBER() OVER (PARTITION BY i.sale_id ORDER BY i.due_date) as installment_number,
        i.due_date,
        i.amount,
        i.paid_amount,
        i.status,
        i.amount - i.paid_amount as remaining_amount,
        DATEDIFF(CURDATE(), i.due_date) as days_overdue,
        s.id as sale_id,
        s.sale_date,
        s.total_amount as sale_total,
        p.name as product_name,
        p.model as product_model,
        c.name as customer_name,
        c.phone as customer_phone,
        c.cnic as customer_cnic
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN products p ON s.product_id = p.id
    JOIN customers c ON s.customer_id = c.id
    WHERE $where_clause
    ORDER BY
        CASE
            WHEN ? = 'days_overdue' THEN DATEDIFF(CURDATE(), i.due_date)
            WHEN ? = 'amount' THEN i.amount
            WHEN ? = 'remaining' THEN (i.amount - i.paid_amount)
            WHEN ? = 'customer' THEN c.name
            ELSE DATEDIFF(CURDATE(), i.due_date)
        END DESC
";

$stmt = $conn->prepare($overdue_query);
$stmt->bind_param($types . "ssss", ...array_merge($params, [$sort_by, $sort_by, $sort_by, $sort_by]));
$stmt->execute();
$overdue_installments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary statistics
$summary_query = "
    SELECT
        COUNT(*) as total_overdue,
        SUM(i.amount) as total_amount_due,
        SUM(i.paid_amount) as total_amount_paid,
        SUM(i.amount - i.paid_amount) as total_remaining,
        AVG(DATEDIFF(CURDATE(), i.due_date)) as avg_days_overdue,
        COUNT(CASE WHEN i.status = 'unpaid' THEN 1 END) as fully_unpaid,
        COUNT(CASE WHEN i.status = 'partial' THEN 1 END) as partially_paid,
        COUNT(DISTINCT c.id) as affected_customers,
        COUNT(DISTINCT s.id) as affected_sales
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    WHERE i.due_date <= CURDATE() AND i.status IN ('unpaid', 'partial')
";

$summary_result = $conn->query($summary_query);
$summary = $summary_result->fetch_assoc();

// Overdue by customer
$customer_overdue_query = "
    SELECT
        c.id,
        c.name,
        c.phone,
        c.cnic,
        COUNT(i.id) as overdue_installments,
        SUM(i.amount - i.paid_amount) as total_overdue_amount,
        AVG(DATEDIFF(CURDATE(), i.due_date)) as avg_days_overdue,
        MAX(DATEDIFF(CURDATE(), i.due_date)) as max_days_overdue,
        GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as products
    FROM customers c
    JOIN sales s ON c.id = s.customer_id
    JOIN installments i ON s.id = i.sale_id
    JOIN products p ON s.product_id = p.id
    WHERE i.due_date <= CURDATE() AND i.status IN ('unpaid', 'partial')
    GROUP BY c.id, c.name, c.phone, c.cnic
    ORDER BY total_overdue_amount DESC
";

$customer_overdue = $conn->query($customer_overdue_query)->fetch_all(MYSQLI_ASSOC);

// Recovery rate analysis
$recovery_query = "
    SELECT
        CASE
            WHEN days_overdue <= 7 THEN '1-7 days'
            WHEN days_overdue <= 30 THEN '8-30 days'
            WHEN days_overdue <= 90 THEN '31-90 days'
            WHEN days_overdue <= 180 THEN '91-180 days'
            ELSE '180+ days'
        END as overdue_range,
        COUNT(*) as installment_count,
        SUM(amount) as total_amount,
        SUM(paid_amount) as total_paid,
        SUM(amount - paid_amount) as total_remaining,
        ROUND((SUM(paid_amount) / SUM(amount)) * 100, 2) as recovery_rate
    FROM (
        SELECT
            i.amount,
            i.paid_amount,
            DATEDIFF(CURDATE(), i.due_date) as days_overdue
        FROM installments i
        WHERE i.due_date <= CURDATE()
    ) overdue_data
    GROUP BY overdue_range
    ORDER BY
        CASE overdue_range
            WHEN '1-7 days' THEN 1
            WHEN '8-30 days' THEN 2
            WHEN '31-90 days' THEN 3
            WHEN '91-180 days' THEN 4
            ELSE 5
        END
";

$recovery_data = $conn->query($recovery_query)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Overdue Analysis Report</h2>
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
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Overdue</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['total_remaining'], 0) ?></div>
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
                            <div class="small">Installments</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_overdue']) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-x fa-2x"></i>
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
                            <div class="small">Affected Customers</div>
                            <div class="h4 mb-0"><?= number_format($summary['affected_customers']) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fa-2x"></i>
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
                            <div class="h4 mb-0"><?= number_format($summary['avg_days_overdue'], 1) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock fa-2x"></i>
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
                            <div class="small">Fully Unpaid</div>
                            <div class="h4 mb-0"><?= number_format($summary['fully_unpaid']) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-x-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Partially Paid</div>
                            <div class="h4 mb-0"><?= number_format($summary['partially_paid']) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-dash-circle fa-2x"></i>
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
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Overdue</option>
                        <option value="unpaid" <?= $status_filter == 'unpaid' ? 'selected' : '' ?>>Fully Unpaid</option>
                        <option value="partial" <?= $status_filter == 'partial' ? 'selected' : '' ?>>Partially Paid</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort_by" class="form-select">
                        <option value="days_overdue" <?= $sort_by == 'days_overdue' ? 'selected' : '' ?>>Days Overdue</option>
                        <option value="amount" <?= $sort_by == 'amount' ? 'selected' : '' ?>>Amount</option>
                        <option value="remaining" <?= $sort_by == 'remaining' ? 'selected' : '' ?>>Remaining</option>
                        <option value="customer" <?= $sort_by == 'customer' ? 'selected' : '' ?>>Customer</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="overdue_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Recovery Rate Chart -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Recovery Rate by Overdue Period</h5>
                </div>
                <div class="card-body">
                    <canvas id="recoveryChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Overdue Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="overdueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Overdue Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Overdue Installments</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Installment</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Remaining</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($overdue_installments as $installment): ?>
                                <tr class="<?= $installment['days_overdue'] > 90 ? 'table-danger' : ($installment['days_overdue'] > 30 ? 'table-warning' : 'table-light') ?>">
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($installment['customer_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($installment['customer_cnic']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($installment['product_name']) ?></div>
                                        <small class="text-muted">Model: <?= htmlspecialchars($installment['product_model']) ?></small>
                                    </td>
                                    <td>#<?= $installment['installment_number'] ?></td>
                                    <td><?= $installment['due_date'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $installment['days_overdue'] > 90 ? 'danger' : ($installment['days_overdue'] > 30 ? 'warning' : 'secondary') ?>">
                                            <?= $installment['days_overdue'] ?> days
                                        </span>
                                    </td>
                                    <td>₨<?= number_format($installment['amount'], 0) ?></td>
                                    <td>₨<?= number_format($installment['paid_amount'], 0) ?></td>
                                    <td>
                                        <strong class="text-danger">₨<?= number_format($installment['remaining_amount'], 0) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $installment['status'] == 'paid' ? 'success' : ($installment['status'] == 'partial' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($installment['status']) ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <?php if($installment['customer_phone']): ?>
                                            <a href="tel:<?= $installment['customer_phone'] ?>" class="btn btn-sm btn-outline-primary" title="Call Customer">
                                                <i class="bi bi-telephone"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="view_installments.php?sale_id=<?= $installment['sale_id'] ?>" class="btn btn-sm btn-outline-info" title="View Sale Details">
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

    <!-- Customer Overdue Summary -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Customer Overdue Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Customer</th>
                                    <th>Overdue Installments</th>
                                    <th>Total Overdue Amount</th>
                                    <th>Avg Days Overdue</th>
                                    <th>Max Days Overdue</th>
                                    <th>Products</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customer_overdue as $customer): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($customer['cnic']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><?= $customer['overdue_installments'] ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-danger">₨<?= number_format($customer['total_overdue_amount'], 0) ?></strong>
                                    </td>
                                    <td><?= number_format($customer['avg_days_overdue'], 1) ?> days</td>
                                    <td>
                                        <span class="badge bg-<?= $customer['max_days_overdue'] > 90 ? 'danger' : ($customer['max_days_overdue'] > 30 ? 'warning' : 'secondary') ?>">
                                            <?= $customer['max_days_overdue'] ?> days
                                        </span>
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
                                        <a href="list_sales.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-info" title="View Customer Sales">
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
// Recovery Rate Chart
const recoveryData = <?= json_encode($recovery_data) ?>;
const recoveryLabels = recoveryData.map(item => item.overdue_range);
const recoveryRates = recoveryData.map(item => parseFloat(item.recovery_rate));

const recoveryCtx = document.getElementById('recoveryChart').getContext('2d');
new Chart(recoveryCtx, {
    type: 'bar',
    data: {
        labels: recoveryLabels,
        datasets: [{
            label: 'Recovery Rate (%)',
            data: recoveryRates,
            backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545', '#6c757d'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Recovery Rate (%)'
                }
            }
        }
    }
});

// Overdue Distribution Chart
const overdueData = <?= json_encode($recovery_data) ?>;
const overdueLabels = overdueData.map(item => item.overdue_range);
const overdueAmounts = overdueData.map(item => parseFloat(item.total_remaining));

const overdueCtx = document.getElementById('overdueChart').getContext('2d');
new Chart(overdueCtx, {
    type: 'doughnut',
    data: {
        labels: overdueLabels,
        datasets: [{
            data: overdueAmounts,
            backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545', '#6c757d'],
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

function exportToExcel() {
    let csv = 'Overdue Analysis Report\n\n';
    csv += 'Period: Current\n\n';

    csv += 'Summary:\n';
    csv += 'Total Overdue Amount: ₨<?= number_format($summary['total_remaining'], 0) ?>\n';
    csv += 'Total Overdue Installments: <?= $summary['total_overdue'] ?>\n';
    csv += 'Affected Customers: <?= $summary['affected_customers'] ?>\n\n';

    csv += 'Detailed Overdue Installments:\n';
    csv += 'Customer,CNIC,Product,Installment,Due Date,Days Overdue,Amount,Paid,Remaining,Status\n';
    <?php foreach($overdue_installments as $installment): ?>
    csv += '<?= addslashes($installment['customer_name']) ?>,<?= $installment['customer_cnic'] ?>,<?= addslashes($installment['product_name']) ?>,<?= $installment['installment_number'] ?>,<?= $installment['due_date'] ?>,<?= $installment['days_overdue'] ?>,<?= $installment['amount'] ?>,<?= $installment['paid_amount'] ?>,<?= $installment['remaining_amount'] ?>,<?= $installment['status'] ?>\n';
    <?php endforeach; ?>

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'overdue_analysis_report_<?= date('Y-m-d') ?>.csv';
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

.table-warning {
    background-color: #fff3cd !important;
}

.table-light {
    background-color: #fefefe !important;
}

.table-dark th {
    color: #ffffff !important;
    font-weight: 600 !important;
    background-color: #dc3545 !important;
}

.bg-primary { background: linear-gradient(45deg, #007bff, #0056b3) !important; }
.bg-success { background: linear-gradient(45deg, #28a745, #1e7e34) !important; }
.bg-info { background: linear-gradient(45deg, #17a2b8, #117a8b) !important; }
.bg-warning { background: linear-gradient(45deg, #ffc107, #d39e00) !important; }
.bg-danger { background: linear-gradient(45deg, #dc3545, #bd2130) !important; }
.bg-secondary { background: linear-gradient(45deg, #6c757d, #545b62) !important; }
</style>

<?php include '../includes/footer.php'; ?>