<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';

// Build WHERE clause
$where_conditions = ["i.due_date BETWEEN ? AND ?"];
$params = [$from_date, $to_date];
$types = "ss";

if ($status) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($customer_id) {
    $where_conditions[] = "s.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Main installment summary
$summary_query = "
    SELECT 
        COUNT(*) as total_installments,
        SUM(i.amount) as total_due,
        SUM(i.paid_amount) as total_paid,
        SUM(i.amount - i.paid_amount) as total_pending,
        AVG(i.amount) as avg_installment_amount,
        COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN i.status = 'partial' THEN 1 END) as partial_count,
        COUNT(CASE WHEN i.status = 'unpaid' THEN 1 END) as unpaid_count,
        COUNT(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1 END) as overdue_count
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    WHERE $where_clause
";

$stmt = $conn->prepare($summary_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Monthly collection trend
$collection_trend_query = "
    SELECT 
        DATE_FORMAT(i.due_date, '%Y-%m') as month,
        COUNT(*) as installments_due,
        SUM(i.amount) as amount_due,
        SUM(i.paid_amount) as amount_collected,
        COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1 END) as overdue_count
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    WHERE $where_clause
    GROUP BY DATE_FORMAT(i.due_date, '%Y-%m')
    ORDER BY month
";

$stmt = $conn->prepare($collection_trend_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$collection_trend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Customer payment behavior
$customer_behavior_query = "
    SELECT 
        c.name,
        COUNT(*) as total_installments,
        SUM(i.amount) as total_due,
        SUM(i.paid_amount) as total_paid,
        COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1 END) as overdue_count,
        ROUND((SUM(i.paid_amount) / SUM(i.amount)) * 100, 2) as payment_rate
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    WHERE $where_clause
    GROUP BY c.id, c.name
    HAVING total_installments > 0
    ORDER BY payment_rate DESC, total_due DESC
    LIMIT 15
";

$stmt = $conn->prepare($customer_behavior_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customer_behavior = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Overdue analysis
$overdue_analysis_query = "
    SELECT 
        c.name as customer_name,
        p.name as product_name,
        i.due_date,
        i.amount,
        i.paid_amount,
        (i.amount - i.paid_amount) as pending_amount,
        DATEDIFF(CURDATE(), i.due_date) as days_overdue,
        c.phone,
        s.sale_date
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    JOIN products p ON s.product_id = p.id
    WHERE i.status IN ('unpaid', 'partial') 
    AND i.due_date < CURDATE()
    AND i.due_date BETWEEN ? AND ?
    ORDER BY days_overdue DESC, pending_amount DESC
    LIMIT 20
";

$stmt = $conn->prepare($overdue_analysis_query);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$overdue_analysis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate collection efficiency
$collection_efficiency = $summary['total_due'] > 0 ? ($summary['total_paid'] / $summary['total_due']) * 100 : 0;
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Installment Analysis Report</h2>
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
                        <option value="">All Status</option>
                        <option value="paid" <?= $status == 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="partial" <?= $status == 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="unpaid" <?= $status == 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        <?php
                        $customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
                        while($customer = $customers->fetch_assoc()):
                        ?>
                            <option value="<?= $customer['id'] ?>" <?= $customer_id == $customer['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($customer['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="installment_analysis_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Installments</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_installments'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-check fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Due</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['total_due'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Collected</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['total_paid'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Pending</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['total_pending'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Overdue</div>
                            <div class="h4 mb-0"><?= number_format($summary['overdue_count'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Collection Rate</div>
                            <div class="h4 mb-0"><?= number_format($collection_efficiency, 1) ?>%</div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Collection Trend Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Collection Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="collectionTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Payment Status Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Payment Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentStatusChart"></canvas>
                    <div class="mt-3">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-success">
                                    <strong><?= number_format($summary['paid_count'] ?? 0) ?></strong>
                                    <small class="d-block">Paid</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-warning">
                                    <strong><?= number_format($summary['partial_count'] ?? 0) ?></strong>
                                    <small class="d-block">Partial</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-danger">
                                    <strong><?= number_format($summary['unpaid_count'] ?? 0) ?></strong>
                                    <small class="d-block">Unpaid</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables Row -->
    <div class="row">
        <!-- Customer Payment Behavior -->
        <div class="col-lg-7 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Customer Payment Behavior</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Total Due</th>
                                    <th>Paid</th>
                                    <th>Rate</th>
                                    <th>Overdue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customer_behavior as $customer): ?>
                                <tr>
                                    <td><?= htmlspecialchars($customer['name']) ?></td>
                                    <td>₨<?= number_format($customer['total_due'], 0) ?></td>
                                    <td>₨<?= number_format($customer['total_paid'], 0) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $customer['payment_rate'] >= 80 ? 'bg-success' : ($customer['payment_rate'] >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                                 style="width: <?= $customer['payment_rate'] ?>%">
                                                <?= number_format($customer['payment_rate'], 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($customer['overdue_count'] > 0): ?>
                                            <span class="badge bg-danger"><?= $customer['overdue_count'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($customer['payment_rate'] >= 90): ?>
                                            <span class="badge bg-success">Excellent</span>
                                        <?php elseif($customer['payment_rate'] >= 70): ?>
                                            <span class="badge bg-info">Good</span>
                                        <?php elseif($customer['payment_rate'] >= 50): ?>
                                            <span class="badge bg-warning">Average</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Poor</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overdue Analysis -->
        <div class="col-lg-5 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Critical Overdue</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Days</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach(array_slice($overdue_analysis, 0, 10) as $overdue): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($overdue['customer_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($overdue['product_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $overdue['days_overdue'] > 30 ? 'bg-danger' : ($overdue['days_overdue'] > 15 ? 'bg-warning' : 'bg-info') ?>">
                                            <?= $overdue['days_overdue'] ?>
                                        </span>
                                    </td>
                                    <td>₨<?= number_format($overdue['pending_amount'], 0) ?></td>
                                    <td>
                                        <?php if($overdue['phone']): ?>
                                            <a href="tel:<?= $overdue['phone'] ?>" class="btn btn-sm btn-outline-primary" title="Call Customer">
                                                <i class="bi bi-telephone"></i>
                                            </a>
                                        <?php endif; ?>
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
// Collection Trend Chart
const trendData = <?= json_encode($collection_trend) ?>;
const trendLabels = trendData.map(item => item.month);
const amountDue = trendData.map(item => parseFloat(item.amount_due || 0));
const amountCollected = trendData.map(item => parseFloat(item.amount_collected || 0));

const collectionTrendCtx = document.getElementById('collectionTrendChart').getContext('2d');
new Chart(collectionTrendCtx, {
    type: 'bar',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Amount Due (₨)',
            data: amountDue,
            backgroundColor: 'rgba(255, 99, 132, 0.5)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1
        }, {
            label: 'Amount Collected (₨)',
            data: amountCollected,
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgba(75, 192, 192, 1)',
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
                    text: 'Amount (₨)'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Month'
                }
            }
        }
    }
});

// Payment Status Chart
const statusData = [
    <?= $summary['paid_count'] ?? 0 ?>,
    <?= $summary['partial_count'] ?? 0 ?>,
    <?= $summary['unpaid_count'] ?? 0 ?>
];

const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
new Chart(paymentStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Paid', 'Partial', 'Unpaid'],
        datasets: [{
            data: statusData,
            backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

function exportToExcel() {
    let csv = 'Installment Analysis Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';
    csv += 'Summary:\n';
    csv += 'Total Installments,<?= $summary['total_installments'] ?? 0 ?>\n';
    csv += 'Total Due,<?= $summary['total_due'] ?? 0 ?>\n';
    csv += 'Total Collected,<?= $summary['total_paid'] ?? 0 ?>\n';
    csv += 'Collection Rate,<?= number_format($collection_efficiency, 2) ?>%\n\n';
    
    csv += 'Customer Payment Behavior:\n';
    csv += 'Customer,Total Due,Total Paid,Payment Rate,Overdue Count\n';
    <?php foreach($customer_behavior as $customer): ?>
    csv += '<?= addslashes($customer['name']) ?>,<?= $customer['total_due'] ?>,<?= $customer['total_paid'] ?>,<?= $customer['payment_rate'] ?>,<?= $customer['overdue_count'] ?>\n';
    <?php endforeach; ?>
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'installment_analysis_report_<?= date('Y-m-d') ?>.csv';
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

.progress {
    font-size: 0.75rem;
}

.bg-primary { background: linear-gradient(45deg, #007bff, #0056b3) !important; }
.bg-success { background: linear-gradient(45deg, #28a745, #1e7e34) !important; }
.bg-info { background: linear-gradient(45deg, #17a2b8, #117a8b) !important; }
.bg-warning { background: linear-gradient(45deg, #ffc107, #d39e00) !important; }
.bg-danger { background: linear-gradient(45deg, #dc3545, #bd2130) !important; }
.bg-secondary { background: linear-gradient(45deg, #6c757d, #545b62) !important; }
</style>

<?php include '../includes/footer.php'; ?>