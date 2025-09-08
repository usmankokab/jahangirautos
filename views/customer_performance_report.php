<?php
include '../config/db.php';
include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$customer_id = $_GET['customer_id'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'total_spent';

// Build WHERE clause for sales
$where_conditions = ["s.sale_date BETWEEN ? AND ?"];
$params = [$from_date, $to_date];
$types = "ss";

if ($customer_id) {
    $where_conditions[] = "s.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Customer performance analysis
$customer_performance_query = "
    SELECT 
        c.id,
        c.name,
        c.phone,
        c.cnic,
        COUNT(DISTINCT s.id) as total_purchases,
        SUM(s.total_amount) as total_spent,
        AVG(s.total_amount) as avg_purchase_amount,
        SUM(s.down_payment) as total_down_payments,
        COUNT(DISTINCT i.id) as total_installments,
        SUM(i.amount) as total_installment_due,
        SUM(i.paid_amount) as total_installment_paid,
        COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_installments,
        COUNT(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1 END) as overdue_installments,
        ROUND((SUM(i.paid_amount) / NULLIF(SUM(i.amount), 0)) * 100, 2) as payment_rate,
        MIN(s.sale_date) as first_purchase_date,
        MAX(s.sale_date) as last_purchase_date,
        DATEDIFF(CURDATE(), MAX(s.sale_date)) as days_since_last_purchase
    FROM customers c
    LEFT JOIN sales s ON c.id = s.customer_id AND $where_clause
    LEFT JOIN installments i ON s.id = i.sale_id
    GROUP BY c.id, c.name, c.phone, c.cnic
    HAVING total_purchases > 0
    ORDER BY 
        CASE 
            WHEN ? = 'total_spent' THEN total_spent
            WHEN ? = 'payment_rate' THEN payment_rate
            WHEN ? = 'total_purchases' THEN total_purchases
            WHEN ? = 'overdue_installments' THEN overdue_installments
            ELSE total_spent
        END DESC
";

$stmt = $conn->prepare($customer_performance_query);
$stmt->bind_param($types . "ssss", ...array_merge($params, [$sort_by, $sort_by, $sort_by, $sort_by]));
$stmt->execute();
$customer_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Customer segmentation
$segmentation_query = "
    SELECT 
        CASE 
            WHEN total_spent >= 500000 THEN 'Premium (₨500K+)'
            WHEN total_spent >= 200000 THEN 'High Value (₨200K-500K)'
            WHEN total_spent >= 100000 THEN 'Medium Value (₨100K-200K)'
            WHEN total_spent >= 50000 THEN 'Regular (₨50K-100K)'
            ELSE 'New (Under ₨50K)'
        END as segment,
        COUNT(*) as customer_count,
        SUM(total_spent) as segment_revenue,
        AVG(payment_rate) as avg_payment_rate
    FROM (
        SELECT 
            c.id,
            SUM(s.total_amount) as total_spent,
            ROUND((SUM(i.paid_amount) / NULLIF(SUM(i.amount), 0)) * 100, 2) as payment_rate
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id AND $where_clause
        LEFT JOIN installments i ON s.id = i.sale_id
        GROUP BY c.id
        HAVING total_spent > 0
    ) customer_totals
    GROUP BY segment
    ORDER BY 
        CASE segment
            WHEN 'Premium (₨500K+)' THEN 1
            WHEN 'High Value (₨200K-500K)' THEN 2
            WHEN 'Medium Value (₨100K-200K)' THEN 3
            WHEN 'Regular (₨50K-100K)' THEN 4
            ELSE 5
        END
";

$stmt = $conn->prepare($segmentation_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customer_segments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Payment behavior analysis
$payment_behavior_query = "
    SELECT 
        CASE 
            WHEN payment_rate >= 95 THEN 'Excellent (95%+)'
            WHEN payment_rate >= 80 THEN 'Good (80-94%)'
            WHEN payment_rate >= 60 THEN 'Average (60-79%)'
            WHEN payment_rate >= 40 THEN 'Poor (40-59%)'
            ELSE 'Critical (Under 40%)'
        END as behavior_category,
        COUNT(*) as customer_count,
        AVG(total_spent) as avg_spending,
        AVG(overdue_installments) as avg_overdue
    FROM (
        SELECT 
            c.id,
            SUM(s.total_amount) as total_spent,
            COUNT(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1 END) as overdue_installments,
            ROUND((SUM(i.paid_amount) / NULLIF(SUM(i.amount), 0)) * 100, 2) as payment_rate
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id AND $where_clause
        LEFT JOIN installments i ON s.id = i.sale_id
        GROUP BY c.id
        HAVING total_spent > 0
    ) customer_behavior
    GROUP BY behavior_category
    ORDER BY 
        CASE behavior_category
            WHEN 'Excellent (95%+)' THEN 1
            WHEN 'Good (80-94%)' THEN 2
            WHEN 'Average (60-79%)' THEN 3
            WHEN 'Poor (40-59%)' THEN 4
            ELSE 5
        END
";

$stmt = $conn->prepare($payment_behavior_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payment_behavior = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top customers by different metrics
$top_spenders = array_slice($customer_performance, 0, 5);
$best_payers = array_filter($customer_performance, function($c) { return $c['payment_rate'] >= 90; });
usort($best_payers, function($a, $b) { return $b['payment_rate'] <=> $a['payment_rate']; });
$best_payers = array_slice($best_payers, 0, 5);

$most_purchases = $customer_performance;
usort($most_purchases, function($a, $b) { return $b['total_purchases'] <=> $a['total_purchases']; });
$most_purchases = array_slice($most_purchases, 0, 5);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-people me-2"></i>Customer Performance Report</h2>
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
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort_by" class="form-select">
                        <option value="total_spent" <?= $sort_by == 'total_spent' ? 'selected' : '' ?>>Total Spent</option>
                        <option value="payment_rate" <?= $sort_by == 'payment_rate' ? 'selected' : '' ?>>Payment Rate</option>
                        <option value="total_purchases" <?= $sort_by == 'total_purchases' ? 'selected' : '' ?>>Total Purchases</option>
                        <option value="overdue_installments" <?= $sort_by == 'overdue_installments' ? 'selected' : '' ?>>Overdue Count</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="customer_performance_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer Segmentation Cards -->
    <div class="row mb-4">
        <?php foreach($customer_segments as $segment): ?>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card <?= 
                strpos($segment['segment'], 'Premium') !== false ? 'bg-primary' : 
                (strpos($segment['segment'], 'High Value') !== false ? 'bg-success' : 
                (strpos($segment['segment'], 'Medium') !== false ? 'bg-info' : 
                (strpos($segment['segment'], 'Regular') !== false ? 'bg-warning' : 'bg-secondary'))) 
            ?> text-white">
                <div class="card-body text-center">
                    <h6 class="card-title"><?= htmlspecialchars($segment['segment']) ?></h6>
                    <h4><?= number_format($segment['customer_count']) ?></h4>
                    <small>₨<?= number_format($segment['segment_revenue'], 0) ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Customer Segmentation Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Customer Segmentation</h5>
                </div>
                <div class="card-body">
                    <canvas id="segmentationChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Payment Behavior Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Payment Behavior</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentBehaviorChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers Row -->
    <div class="row mb-4">
        <!-- Top Spenders -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Spenders</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($top_spenders as $index => $customer): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                <small class="text-muted"><?= $customer['total_purchases'] ?> purchases</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">₨<?= number_format($customer['total_spent'], 0) ?></div>
                                <small class="text-muted"><?= number_format($customer['payment_rate'], 1) ?>% paid</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Best Payers -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Best Payers</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($best_payers as $customer): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                <small class="text-muted">₨<?= number_format($customer['total_spent'], 0) ?> spent</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?= number_format($customer['payment_rate'], 1) ?>%</div>
                                <small class="text-muted"><?= $customer['overdue_installments'] ?> overdue</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Most Active -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Most Active</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($most_purchases as $customer): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                <small class="text-muted">₨<?= number_format($customer['avg_purchase_amount'], 0) ?> avg</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-info"><?= $customer['total_purchases'] ?></div>
                                <small class="text-muted">purchases</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Customer Performance Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Customer Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Customer</th>
                                    <th>Purchases</th>
                                    <th>Total Spent</th>
                                    <th>Avg Purchase</th>
                                    <th>Payment Rate</th>
                                    <th>Overdue</th>
                                    <th>Last Purchase</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customer_performance as $customer): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($customer['cnic']) ?></small>
                                    </td>
                                    <td><?= number_format($customer['total_purchases']) ?></td>
                                    <td>₨<?= number_format($customer['total_spent'], 0) ?></td>
                                    <td>₨<?= number_format($customer['avg_purchase_amount'], 0) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $customer['payment_rate'] >= 80 ? 'bg-success' : ($customer['payment_rate'] >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                                 style="width: <?= $customer['payment_rate'] ?>%">
                                                <?= number_format($customer['payment_rate'], 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($customer['overdue_installments'] > 0): ?>
                                            <span class="badge bg-danger"><?= $customer['overdue_installments'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $customer['last_purchase_date'] ?>
                                        <small class="d-block text-muted"><?= $customer['days_since_last_purchase'] ?> days ago</small>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = 'New';
                                        $badge_class = 'bg-secondary';
                                        
                                        if($customer['total_spent'] >= 500000) {
                                            $status = 'Premium';
                                            $badge_class = 'bg-primary';
                                        } elseif($customer['payment_rate'] >= 90) {
                                            $status = 'Excellent';
                                            $badge_class = 'bg-success';
                                        } elseif($customer['overdue_installments'] > 3) {
                                            $status = 'Risk';
                                            $badge_class = 'bg-danger';
                                        } elseif($customer['payment_rate'] >= 70) {
                                            $status = 'Good';
                                            $badge_class = 'bg-info';
                                        } elseif($customer['payment_rate'] < 50) {
                                            $status = 'Poor';
                                            $badge_class = 'bg-warning';
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= $status ?></span>
                                    </td>
                                    <td>
                                        <?php if($customer['phone']): ?>
                                            <a href="tel:<?= $customer['phone'] ?>" class="btn btn-sm btn-outline-primary" title="Call Customer">
                                                <i class="bi bi-telephone"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>/views/view_installments.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-info" title="View Details">
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
// Customer Segmentation Chart
const segmentData = <?= json_encode($customer_segments) ?>;
const segmentLabels = segmentData.map(item => item.segment);
const segmentCounts = segmentData.map(item => parseInt(item.customer_count));

const segmentationCtx = document.getElementById('segmentationChart').getContext('2d');
new Chart(segmentationCtx, {
    type: 'doughnut',
    data: {
        labels: segmentLabels,
        datasets: [{
            data: segmentCounts,
            backgroundColor: ['#007bff', '#28a745', '#17a2b8', '#ffc107', '#6c757d'],
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

// Payment Behavior Chart
const behaviorData = <?= json_encode($payment_behavior) ?>;
const behaviorLabels = behaviorData.map(item => item.behavior_category);
const behaviorCounts = behaviorData.map(item => parseInt(item.customer_count));

const paymentBehaviorCtx = document.getElementById('paymentBehaviorChart').getContext('2d');
new Chart(paymentBehaviorCtx, {
    type: 'bar',
    data: {
        labels: behaviorLabels,
        datasets: [{
            label: 'Customer Count',
            data: behaviorCounts,
            backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545'],
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
                    text: 'Number of Customers'
                }
            }
        }
    }
});

function exportToExcel() {
    let csv = 'Customer Performance Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';
    
    csv += 'Customer Performance:\n';
    csv += 'Name,CNIC,Purchases,Total Spent,Avg Purchase,Payment Rate,Overdue,Last Purchase\n';
    <?php foreach($customer_performance as $customer): ?>
    csv += '<?= addslashes($customer['name']) ?>,<?= $customer['cnic'] ?>,<?= $customer['total_purchases'] ?>,<?= $customer['total_spent'] ?>,<?= $customer['avg_purchase_amount'] ?>,<?= $customer['payment_rate'] ?>,<?= $customer['overdue_installments'] ?>,<?= $customer['last_purchase_date'] ?>\n';
    <?php endforeach; ?>
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'customer_performance_report_<?= date('Y-m-d') ?>.csv';
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

.list-group-item:hover {
    background-color: #f8f9fa;
}

.bg-primary { background: linear-gradient(45deg, #007bff, #0056b3) !important; }
.bg-success { background: linear-gradient(45deg, #28a745, #1e7e34) !important; }
.bg-info { background: linear-gradient(45deg, #17a2b8, #117a8b) !important; }
.bg-warning { background: linear-gradient(45deg, #ffc107, #d39e00) !important; }
.bg-secondary { background: linear-gradient(45deg, #6c757d, #545b62) !important; }
</style>

<?php include '../includes/footer.php'; ?>