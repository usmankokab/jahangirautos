<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('product_performance_report', 'view');

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$sort_by = $_GET['sort_by'] ?? 'total_revenue';

// Build WHERE clause for sales
$where_conditions = ["s.sale_date BETWEEN ? AND ?"];
$params = [$from_date, $to_date];
$types = "ss";

$where_clause = implode(" AND ", $where_conditions);

// Product performance analysis
$product_performance_query = "
    SELECT
        p.id,
        p.name,
        p.model,
        p.price,
        COUNT(DISTINCT s.id) as total_sales,
        COUNT(DISTINCT s.id) as total_quantity_sold,
        SUM(s.total_amount) as total_revenue,
        AVG(s.total_amount) as avg_sale_amount,
        COUNT(DISTINCT c.id) as unique_customers,
        SUM(s.down_payment) as total_down_payments,
        COUNT(DISTINCT i.id) as total_installments,
        SUM(i.amount) as total_installment_due,
        SUM(i.paid_amount) as total_installment_paid,
        COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_installments,
        COUNT(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1 END) as overdue_installments,
        ROUND((SUM(i.paid_amount) / NULLIF(SUM(i.amount), 0)) * 100, 2) as payment_rate,
        MIN(s.sale_date) as first_sale_date,
        MAX(s.sale_date) as last_sale_date,
        DATEDIFF(CURDATE(), MAX(s.sale_date)) as days_since_last_sale
    FROM products p
    LEFT JOIN sales s ON p.id = s.product_id AND $where_clause
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN installments i ON s.id = i.sale_id
    GROUP BY p.id, p.name, p.model, p.price
    HAVING total_sales > 0
    ORDER BY
        CASE
            WHEN ? = 'total_revenue' THEN SUM(s.total_amount)
            WHEN ? = 'total_sales' THEN COUNT(DISTINCT s.id)
            WHEN ? = 'total_quantity' THEN COUNT(DISTINCT s.id)
            WHEN ? = 'payment_rate' THEN ROUND((SUM(i.paid_amount) / NULLIF(SUM(i.amount), 0)) * 100, 2)
            ELSE SUM(s.total_amount)
        END DESC
";

$stmt = $conn->prepare($product_performance_query);
$stmt->bind_param($types . "ssss", ...array_merge($params, [$sort_by, $sort_by, $sort_by, $sort_by]));
$stmt->execute();
$product_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Product category analysis
$category_query = "
    SELECT
        CASE
            WHEN total_revenue >= 1000000 THEN 'High Value (₨1M+)'
            WHEN total_revenue >= 500000 THEN 'Premium (₨500K-1M)'
            WHEN total_revenue >= 200000 THEN 'Standard (₨200K-500K)'
            WHEN total_revenue >= 50000 THEN 'Budget (₨50K-200K)'
            ELSE 'Low Value (Under ₨50K)'
        END as category,
        COUNT(*) as product_count,
        SUM(total_sales) as category_sales,
        SUM(total_revenue) as category_revenue,
        AVG(payment_rate) as avg_payment_rate
    FROM (
        SELECT
            p.id,
            COUNT(DISTINCT s.id) as total_sales,
            SUM(s.total_amount) as total_revenue,
            ROUND((SUM(i.paid_amount) / NULLIF(SUM(i.amount), 0)) * 100, 2) as payment_rate
        FROM products p
        LEFT JOIN sales s ON p.id = s.product_id AND $where_clause
        LEFT JOIN installments i ON s.id = i.sale_id
        GROUP BY p.id
        HAVING total_sales > 0
    ) product_totals
    GROUP BY category
    ORDER BY
        CASE category
            WHEN 'High Value (₨1M+)' THEN 1
            WHEN 'Premium (₨500K-1M)' THEN 2
            WHEN 'Standard (₨200K-500K)' THEN 3
            WHEN 'Budget (₨50K-200K)' THEN 4
            ELSE 5
        END
";

$stmt = $conn->prepare($category_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$product_categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Sales trend analysis
$sales_trend_query = "
    SELECT
        CASE
            WHEN payment_rate >= 95 THEN 'Excellent (95%+)'
            WHEN payment_rate >= 80 THEN 'Good (80-94%)'
            WHEN payment_rate >= 60 THEN 'Average (60-79%)'
            WHEN payment_rate >= 40 THEN 'Poor (40-59%)'
            ELSE 'Critical (Under 40%)'
        END as performance_category,
        COUNT(*) as product_count,
        AVG(total_revenue) as avg_revenue,
        AVG(overdue_installments) as avg_overdue
    FROM (
        SELECT
            p.id,
            SUM(s.total_amount) as total_revenue,
            COUNT(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1 END) as overdue_installments,
            ROUND((SUM(i.paid_amount) / NULLIF(SUM(i.amount), 0)) * 100, 2) as payment_rate
        FROM products p
        LEFT JOIN sales s ON p.id = s.product_id AND $where_clause
        LEFT JOIN installments i ON s.id = i.sale_id
        GROUP BY p.id
        HAVING COUNT(DISTINCT s.id) > 0
    ) product_performance_sub
    GROUP BY performance_category
    ORDER BY
        CASE performance_category
            WHEN 'Excellent (95%+)' THEN 1
            WHEN 'Good (80-94%)' THEN 2
            WHEN 'Average (60-79%)' THEN 3
            WHEN 'Poor (40-59%)' THEN 4
            ELSE 5
        END
";

$stmt = $conn->prepare($sales_trend_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$performance_categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top products by different metrics
$top_revenue = array_slice($product_performance, 0, 5);
$best_payment = array_filter($product_performance, function($p) { return $p['payment_rate'] >= 90; });
usort($best_payment, function($a, $b) { return $b['payment_rate'] <=> $a['payment_rate']; });
$best_payment = array_slice($best_payment, 0, 5);

$most_sold = $product_performance;
usort($most_sold, function($a, $b) { return $b['total_quantity_sold'] <=> $a['total_quantity_sold']; });
$most_sold = array_slice($most_sold, 0, 5);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-box-seam me-2"></i>Product Performance Report</h2>
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
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sort By</label>
                    <select name="sort_by" class="form-select">
                        <option value="total_revenue" <?= $sort_by == 'total_revenue' ? 'selected' : '' ?>>Total Revenue</option>
                        <option value="total_sales" <?= $sort_by == 'total_sales' ? 'selected' : '' ?>>Total Sales</option>
                        <option value="total_quantity" <?= $sort_by == 'total_quantity' ? 'selected' : '' ?>>Total Sales</option>
                        <option value="payment_rate" <?= $sort_by == 'payment_rate' ? 'selected' : '' ?>>Payment Rate</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="product_performance_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Product Category Cards -->
    <div class="row mb-4">
        <?php foreach($product_categories as $category): ?>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card <?=
                strpos($category['category'], 'High Value') !== false ? 'bg-primary' :
                (strpos($category['category'], 'Premium') !== false ? 'bg-success' :
                (strpos($category['category'], 'Standard') !== false ? 'bg-info' :
                (strpos($category['category'], 'Budget') !== false ? 'bg-warning' : 'bg-secondary')))
            ?> text-white">
                <div class="card-body text-center">
                    <h6 class="card-title"><?= htmlspecialchars($category['category']) ?></h6>
                    <h4><?= number_format($category['product_count']) ?></h4>
                    <small>₨<?= number_format($category['category_revenue'], 0) ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Product Category Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Product Categories</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Payment Performance</h5>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers Row -->
    <div class="row mb-4">
        <!-- Top Revenue -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Revenue</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($top_revenue as $product): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($product['name']) ?></div>
                                <small class="text-muted"><?= $product['total_sales'] ?> sales</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">₨<?= number_format($product['total_revenue'], 0) ?></div>
                                <small class="text-muted"><?= number_format($product['payment_rate'], 1) ?>% paid</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Best Payment Rate -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Best Payment Rate</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($best_payment as $product): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($product['name']) ?></div>
                                <small class="text-muted">₨<?= number_format($product['total_revenue'], 0) ?> revenue</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?= number_format($product['payment_rate'], 1) ?>%</div>
                                <small class="text-muted"><?= $product['overdue_installments'] ?> overdue</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Most Sold -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Most Sales</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($most_sold as $product): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($product['name']) ?></div>
                                <small class="text-muted">₨<?= number_format($product['avg_sale_amount'], 0) ?> avg</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-info"><?= $product['total_quantity_sold'] ?></div>
                                <small class="text-muted">sales</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Product Performance Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Product Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product</th>
                                    <th>Sales</th>
                                    <th>Sales Count</th>
                                    <th>Total Revenue</th>
                                    <th>Avg Sale</th>
                                    <th>Payment Rate</th>
                                    <th>Overdue</th>
                                    <th>Last Sale</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($product_performance as $product): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($product['name']) ?></div>
                                        <small class="text-muted">Model: <?= htmlspecialchars($product['model']) ?></small>
                                    </td>
                                    <td><?= number_format($product['total_sales']) ?></td>
                                    <td><?= number_format($product['total_quantity_sold']) ?></td>
                                    <td>₨<?= number_format($product['total_revenue'], 0) ?></td>
                                    <td>₨<?= number_format($product['avg_sale_amount'], 0) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $product['payment_rate'] >= 80 ? 'bg-success' : ($product['payment_rate'] >= 50 ? 'bg-warning' : 'bg-danger') ?>"
                                                 style="width: <?= $product['payment_rate'] ?>%">
                                                <?= number_format($product['payment_rate'], 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($product['overdue_installments'] > 0): ?>
                                            <span class="badge bg-danger"><?= $product['overdue_installments'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $product['last_sale_date'] ?>
                                        <small class="d-block text-muted"><?= $product['days_since_last_sale'] ?> days ago</small>
                                    </td>
                                    <td>
                                        <?php
                                        $status = 'Slow';
                                        $badge_class = 'bg-secondary';

                                        if($product['total_revenue'] >= 1000000) {
                                            $status = 'Bestseller';
                                            $badge_class = 'bg-primary';
                                        } elseif($product['payment_rate'] >= 90) {
                                            $status = 'Reliable';
                                            $badge_class = 'bg-success';
                                        } elseif($product['overdue_installments'] > 5) {
                                            $status = 'Risk';
                                            $badge_class = 'bg-danger';
                                        } elseif($product['total_sales'] >= 10) {
                                            $status = 'Popular';
                                            $badge_class = 'bg-info';
                                        } elseif($product['payment_rate'] < 50) {
                                            $status = 'Poor';
                                            $badge_class = 'bg-warning';
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= $status ?></span>
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
// Product Category Chart
const categoryData = <?= json_encode($product_categories) ?>;
const categoryLabels = categoryData.map(item => item.category);
const categoryCounts = categoryData.map(item => parseInt(item.product_count));

const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: categoryLabels,
        datasets: [{
            data: categoryCounts,
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

// Performance Chart
const performanceData = <?= json_encode($performance_categories) ?>;
const performanceLabels = performanceData.map(item => item.performance_category);
const performanceCounts = performanceData.map(item => parseInt(item.product_count));

const performanceCtx = document.getElementById('performanceChart').getContext('2d');
new Chart(performanceCtx, {
    type: 'bar',
    data: {
        labels: performanceLabels,
        datasets: [{
            label: 'Product Count',
            data: performanceCounts,
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
                    text: 'Number of Products'
                }
            }
        }
    }
});

function exportToExcel() {
    let csv = 'Product Performance Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';

    csv += 'Product Performance:\n';
    csv += 'Name,Model,Sales,Sales Count,Revenue,Avg Sale,Payment Rate,Overdue,Last Sale\n';
    <?php foreach($product_performance as $product): ?>
    csv += '<?= addslashes($product['name']) ?>,<?= addslashes($product['model']) ?>,<?= $product['total_sales'] ?>,<?= $product['total_quantity_sold'] ?>,<?= $product['total_revenue'] ?>,<?= $product['avg_sale_amount'] ?>,<?= $product['payment_rate'] ?>,<?= $product['overdue_installments'] ?>,<?= $product['last_sale_date'] ?>\n';
    <?php endforeach; ?>

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'product_performance_report_<?= date('Y-m-d') ?>.csv';
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

.table-dark th {
    color: #000000 !important;
    font-weight: 600 !important;
}
</style>

<?php include '../includes/footer.php'; ?>