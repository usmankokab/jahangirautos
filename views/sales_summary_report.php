<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('sales_summary_report', 'view');

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$customer_id = $_GET['customer_id'] ?? '';
$product_id = $_GET['product_id'] ?? '';

// Build WHERE clause
$where_conditions = ["s.sale_date BETWEEN ? AND ?"];
$params = [$from_date, $to_date];
$types = "ss";

if ($customer_id) {
    $where_conditions[] = "s.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

if ($product_id) {
    $where_conditions[] = "s.product_id = ?";
    $params[] = $product_id;
    $types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Main sales summary query
$summary_query = "
    SELECT 
        COUNT(*) as total_sales,
        SUM(s.total_amount) as total_revenue,
        SUM(s.down_payment) as total_down_payments,
        SUM(s.monthly_installment * s.months) as total_installment_value,
        AVG(s.total_amount) as avg_sale_amount,
        AVG(s.monthly_installment) as avg_monthly_installment,
        AVG(s.months) as avg_installment_period,
        AVG(s.interest_rate) as avg_interest_rate
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN products p ON s.product_id = p.id
    WHERE $where_clause
";

$stmt = $conn->prepare($summary_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Monthly trend data
$trend_query = "
    SELECT 
        DATE_FORMAT(s.sale_date, '%Y-%m') as month,
        COUNT(*) as sales_count,
        SUM(s.total_amount) as revenue,
        SUM(s.down_payment) as down_payments
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN products p ON s.product_id = p.id
    WHERE $where_clause
    GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
    ORDER BY month
";

$stmt = $conn->prepare($trend_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$trend_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top customers
$top_customers_query = "
    SELECT 
        c.name,
        COUNT(*) as purchase_count,
        SUM(s.total_amount) as total_spent,
        AVG(s.total_amount) as avg_purchase
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN products p ON s.product_id = p.id
    WHERE $where_clause
    GROUP BY c.id, c.name
    ORDER BY total_spent DESC
    LIMIT 10
";

$stmt = $conn->prepare($top_customers_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$top_customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top products
$top_products_query = "
    SELECT 
        p.name,
        COUNT(*) as sales_count,
        SUM(s.total_amount) as total_revenue,
        AVG(s.total_amount) as avg_price
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN products p ON s.product_id = p.id
    WHERE $where_clause
    GROUP BY p.id, p.name
    ORDER BY sales_count DESC
    LIMIT 10
";

$stmt = $conn->prepare($top_products_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Payment status analysis
$payment_status_query = "
    SELECT 
        i.status,
        COUNT(*) as count,
        SUM(i.amount) as total_amount,
        SUM(i.paid_amount) as paid_amount
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    JOIN products p ON s.product_id = p.id
    WHERE $where_clause
    GROUP BY i.status
";

$stmt = $conn->prepare($payment_status_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payment_status = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-graph-up me-2"></i>Sales Summary Report</h2>
        <div class="d-flex gap-2 no-print">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button class="btn btn-outline-success" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel"></i> Export
            </button>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-primary">
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
                    <label class="form-label">Product</label>
                    <select name="product_id" class="form-select">
                        <option value="">All Products</option>
                        <?php
                        $products = $conn->query("SELECT id, name FROM products ORDER BY name");
                        while($product = $products->fetch_assoc()):
                        ?>
                            <option value="<?= $product['id'] ?>" <?= $product_id == $product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="sales_summary_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Sales</div>
                            <div class="h3 mb-0"><?= number_format($summary['total_sales'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cart-fill fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Revenue</div>
                            <div class="h3 mb-0">₨<?= number_format($summary['total_revenue'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Avg Sale Amount</div>
                            <div class="h3 mb-0">₨<?= number_format($summary['avg_sale_amount'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Down Payments</div>
                            <div class="h3 mb-0">₨<?= number_format($summary['total_down_payments'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Sales Trend Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Sales Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Payment Status Pie Chart -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Payment Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables Row -->
    <div class="row">
        <!-- Top Customers -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Top Customers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Purchases</th>
                                    <th>Total Spent</th>
                                    <th>Avg Purchase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_customers as $customer): ?>
                                <tr>
                                    <td><?= htmlspecialchars($customer['name']) ?></td>
                                    <td><?= number_format($customer['purchase_count']) ?></td>
                                    <td>₨<?= number_format($customer['total_spent'], 0) ?></td>
                                    <td>₨<?= number_format($customer['avg_purchase'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Products -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Top Products</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Sales</th>
                                    <th>Revenue</th>
                                    <th>Avg Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= number_format($product['sales_count']) ?></td>
                                    <td>₨<?= number_format($product['total_revenue'], 0) ?></td>
                                    <td>₨<?= number_format($product['avg_price'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Metrics -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Additional Metrics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6>Avg Installment Period</h6>
                                <h4 class="text-primary"><?= number_format($summary['avg_installment_period'] ?? 0, 1) ?> months</h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6>Avg Interest Rate</h6>
                                <h4 class="text-success"><?= number_format($summary['avg_interest_rate'] ?? 0, 2) ?>%</h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6>Avg Monthly Installment</h6>
                                <h4 class="text-info">₨<?= number_format($summary['avg_monthly_installment'] ?? 0, 0) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6>Total Installment Value</h6>
                                <h4 class="text-warning">₨<?= number_format($summary['total_installment_value'] ?? 0, 0) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Sales Trend Chart
const trendData = <?= json_encode($trend_data) ?>;
const trendLabels = trendData.map(item => item.month);
const trendRevenue = trendData.map(item => parseFloat(item.revenue || 0));
const trendSales = trendData.map(item => parseInt(item.sales_count || 0));

const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
new Chart(salesTrendCtx, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Revenue (₨)',
            data: trendRevenue,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            yAxisID: 'y'
        }, {
            label: 'Sales Count',
            data: trendSales,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Month'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Revenue (₨)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Sales Count'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});

// Payment Status Chart
const paymentData = <?= json_encode($payment_status) ?>;
const statusLabels = paymentData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1));
const statusCounts = paymentData.map(item => parseInt(item.count));
const statusColors = {
    'Paid': '#28a745',
    'Partial': '#ffc107', 
    'Unpaid': '#dc3545'
};

const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
new Chart(paymentStatusCtx, {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusCounts,
            backgroundColor: statusLabels.map(label => statusColors[label] || '#6c757d'),
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
    // Create a simple CSV export
    let csv = 'Sales Summary Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';
    csv += 'Summary Metrics:\n';
    csv += 'Total Sales,<?= $summary['total_sales'] ?? 0 ?>\n';
    csv += 'Total Revenue,<?= $summary['total_revenue'] ?? 0 ?>\n';
    csv += 'Average Sale Amount,<?= $summary['avg_sale_amount'] ?? 0 ?>\n';
    csv += 'Total Down Payments,<?= $summary['total_down_payments'] ?? 0 ?>\n\n';
    
    csv += 'Top Customers:\n';
    csv += 'Name,Purchases,Total Spent,Average Purchase\n';
    <?php foreach($top_customers as $customer): ?>
    csv += '<?= addslashes($customer['name']) ?>,<?= $customer['purchase_count'] ?>,<?= $customer['total_spent'] ?>,<?= $customer['avg_purchase'] ?>\n';
    <?php endforeach; ?>
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sales_summary_report_<?= date('Y-m-d') ?>.csv';
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
    .bg-primary, .bg-success, .bg-info, .bg-warning {
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
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

.bg-primary { background: linear-gradient(45deg, #007bff, #0056b3) !important; }
.bg-success { background: linear-gradient(45deg, #28a745, #1e7e34) !important; }
.bg-info { background: linear-gradient(45deg, #17a2b8, #117a8b) !important; }
.bg-warning { background: linear-gradient(45deg, #ffc107, #d39e00) !important; }
</style>

<?php include '../includes/footer.php'; ?>