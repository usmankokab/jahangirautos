<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('rent_summary', 'view');

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$customer_id = $_GET['customer_id'] ?? '';
$rent_type = $_GET['rent_type'] ?? '';

// Build WHERE clause
$where_conditions = ["r.start_date <= ? AND r.end_date >= ?"];
$params = [$to_date, $from_date];
$types = "ss";

if ($customer_id) {
    $where_conditions[] = "r.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

if ($rent_type) {
    $where_conditions[] = "r.rent_type = ?";
    $params[] = $rent_type;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Main rent summary query
$summary_query = "
    SELECT 
        COUNT(*) as total_rents,
        COUNT(CASE WHEN r.rent_type = 'daily' THEN 1 END) as daily_rents,
        COUNT(CASE WHEN r.rent_type = 'once' THEN 1 END) as once_rents,
        SUM(CASE 
            WHEN r.rent_type = 'daily' THEN 
                r.daily_rent * GREATEST(1, DATEDIFF(
                    LEAST(r.end_date, ?), 
                    GREATEST(r.start_date, ?)
                ) + 1)
            ELSE r.total_rent 
        END) as total_revenue,
        AVG(CASE 
            WHEN r.rent_type = 'daily' THEN r.daily_rent 
            ELSE r.total_rent 
        END) as avg_rent_amount,
        AVG(DATEDIFF(r.end_date, r.start_date) + 1) as avg_rental_period
    FROM rents r
    JOIN customers c ON r.customer_id = c.id
    WHERE $where_clause
";

$stmt = $conn->prepare($summary_query);
$stmt->bind_param($types . "ss", ...array_merge($params, [$to_date, $from_date]));
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Monthly trend data
$trend_query = "
    SELECT 
        DATE_FORMAT(r.start_date, '%Y-%m') as month,
        COUNT(*) as rent_count,
        SUM(CASE 
            WHEN r.rent_type = 'daily' THEN 
                r.daily_rent * DATEDIFF(r.end_date, r.start_date) + r.daily_rent
            ELSE r.total_rent 
        END) as revenue,
        COUNT(CASE WHEN r.rent_type = 'daily' THEN 1 END) as daily_count,
        COUNT(CASE WHEN r.rent_type = 'once' THEN 1 END) as once_count
    FROM rents r
    JOIN customers c ON r.customer_id = c.id
    WHERE $where_clause
    GROUP BY DATE_FORMAT(r.start_date, '%Y-%m')
    ORDER BY month
";

$stmt = $conn->prepare($trend_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$trend_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top customers by rental value
$top_customers_query = "
    SELECT 
        c.name,
        COUNT(*) as rental_count,
        SUM(CASE 
            WHEN r.rent_type = 'daily' THEN 
                r.daily_rent * DATEDIFF(r.end_date, r.start_date) + r.daily_rent
            ELSE r.total_rent 
        END) as total_spent,
        AVG(CASE 
            WHEN r.rent_type = 'daily' THEN r.daily_rent 
            ELSE r.total_rent 
        END) as avg_rental_value,
        AVG(DATEDIFF(r.end_date, r.start_date) + 1) as avg_rental_days
    FROM rents r
    JOIN customers c ON r.customer_id = c.id
    WHERE $where_clause
    GROUP BY c.id, c.name
    ORDER BY total_spent DESC
    LIMIT 10
";

$stmt = $conn->prepare($top_customers_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$top_customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Most rented products
$top_products_query = "
    SELECT 
        r.product_name,
        COUNT(*) as rental_count,
        SUM(CASE 
            WHEN r.rent_type = 'daily' THEN 
                r.daily_rent * DATEDIFF(r.end_date, r.start_date) + r.daily_rent
            ELSE r.total_rent 
        END) as total_revenue,
        AVG(CASE 
            WHEN r.rent_type = 'daily' THEN r.daily_rent 
            ELSE r.total_rent 
        END) as avg_rent_rate,
        AVG(DATEDIFF(r.end_date, r.start_date) + 1) as avg_rental_period
    FROM rents r
    JOIN customers c ON r.customer_id = c.id
    WHERE $where_clause
    GROUP BY r.product_name
    ORDER BY rental_count DESC
    LIMIT 10
";

$stmt = $conn->prepare($top_products_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Rental duration analysis
$duration_analysis_query = "
    SELECT 
        CASE 
            WHEN DATEDIFF(r.end_date, r.start_date) + 1 <= 7 THEN '1-7 days'
            WHEN DATEDIFF(r.end_date, r.start_date) + 1 <= 30 THEN '8-30 days'
            WHEN DATEDIFF(r.end_date, r.start_date) + 1 <= 90 THEN '31-90 days'
            ELSE '90+ days'
        END as duration_range,
        COUNT(*) as count,
        SUM(CASE 
            WHEN r.rent_type = 'daily' THEN 
                r.daily_rent * DATEDIFF(r.end_date, r.start_date) + r.daily_rent
            ELSE r.total_rent 
        END) as revenue
    FROM rents r
    JOIN customers c ON r.customer_id = c.id
    WHERE $where_clause
    GROUP BY duration_range
    ORDER BY 
        CASE duration_range
            WHEN '1-7 days' THEN 1
            WHEN '8-30 days' THEN 2
            WHEN '31-90 days' THEN 3
            ELSE 4
        END
";

$stmt = $conn->prepare($duration_analysis_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$duration_analysis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Current active rentals
$active_rentals_query = "
    SELECT 
        c.name as customer_name,
        r.product_name,
        r.start_date,
        r.end_date,
        r.rent_type,
        CASE 
            WHEN r.rent_type = 'daily' THEN r.daily_rent
            ELSE r.total_rent
        END as rent_amount,
        DATEDIFF(r.end_date, CURDATE()) as days_remaining,
        c.phone
    FROM rents r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.end_date >= CURDATE() AND r.start_date <= CURDATE()
    ORDER BY r.end_date ASC
    LIMIT 15
";

$active_rentals = $conn->query($active_rentals_query)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="color: #007bff;"><i class="bi bi-graph-up me-2"></i>Rent Summary Report</h2>
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
                    <label class="form-label">Rent Type</label>
                    <select name="rent_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="daily" <?= $rent_type == 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="once" <?= $rent_type == 'once' ? 'selected' : '' ?>>Once</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="rent_summary_report.php" class="btn btn-secondary ms-2">
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
                            <div class="small">Total Rentals</div>
                            <div class="h3 mb-0"><?= number_format($summary['total_rents'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash-coin fa-2x opacity-75"></i>
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
                            <div class="small">Daily Rentals</div>
                            <div class="h3 mb-0"><?= number_format($summary['daily_rents'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-day fa-2x opacity-75"></i>
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
                            <div class="small">Fixed Rentals</div>
                            <div class="h3 mb-0"><?= number_format($summary['once_rents'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-check fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Rental Trend Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Rental Trend</h5>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 300px;">
                        <canvas id="rentalTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rental Duration Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Duration Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="durationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables Row -->
    <div class="row mb-4">
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
                                    <th>Rentals</th>
                                    <th>Total Spent</th>
                                    <th>Avg Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_customers as $customer): ?>
                                <tr>
                                    <td><?= htmlspecialchars($customer['name']) ?></td>
                                    <td><?= number_format($customer['rental_count']) ?></td>
                                    <td>₨<?= number_format($customer['total_spent'], 0) ?></td>
                                    <td><?= number_format($customer['avg_rental_days'], 1) ?></td>
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
                    <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Most Rented Products</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Rentals</th>
                                    <th>Revenue</th>
                                    <th>Avg Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= number_format($product['rental_count']) ?></td>
                                    <td>₨<?= number_format($product['total_revenue'], 0) ?></td>
                                    <td>₨<?= number_format($product['avg_rent_rate'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Rentals -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Currently Active Rentals</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Days Left</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($active_rentals as $rental): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rental['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($rental['product_name']) ?></td>
                                    <td><?= $rental['start_date'] ?></td>
                                    <td><?= $rental['end_date'] ?></td>
                                    <td>
                                        <span class="badge <?= $rental['rent_type'] == 'daily' ? 'bg-info' : 'bg-success' ?>">
                                            <?= ucfirst($rental['rent_type']) ?>
                                        </span>
                                    </td>
                                    <td>₨<?= number_format($rental['rent_amount'], 0) ?></td>
                                    <td>
                                        <?php if($rental['days_remaining'] < 0): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php elseif($rental['days_remaining'] <= 3): ?>
                                            <span class="badge bg-warning"><?= $rental['days_remaining'] ?> days</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $rental['days_remaining'] ?> days</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($rental['phone']): ?>
                                            <a href="tel:<?= $rental['phone'] ?>" class="btn btn-sm btn-outline-primary" title="Call Customer">
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

    <!-- Additional Metrics -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Additional Metrics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6>Avg Rental Period</h6>
                                <h4 class="text-primary"><?= number_format($summary['avg_rental_period'] ?? 0, 1) ?> days</h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6>Avg Rent Amount</h6>
                                <h4 class="text-success">₨<?= number_format($summary['avg_rent_amount'] ?? 0, 0) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6>Active Rentals</h6>
                                <h4 class="text-info"><?= count($active_rentals) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6>Daily vs Fixed Ratio</h6>
                                <h4 class="text-warning">
                                    <?= $summary['total_rents'] > 0 ? number_format(($summary['daily_rents'] / $summary['total_rents']) * 100, 1) : 0 ?>% Daily
                                </h4>
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
// Rental Trend Chart
const trendData = <?= json_encode($trend_data) ?>;
const trendLabels = trendData.map(item => item.month);
const trendRevenue = trendData.map(item => parseFloat(item.revenue || 0));
const trendCount = trendData.map(item => parseInt(item.rent_count || 0));

const rentalTrendCtx = document.getElementById('rentalTrendChart').getContext('2d');
new Chart(rentalTrendCtx, {
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
            label: 'Rental Count',
            data: trendCount,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
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
                    text: 'Rental Count'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});

// Duration Distribution Chart
const durationData = <?= json_encode($duration_analysis) ?>;
const durationLabels = durationData.map(item => item.duration_range);
const durationCounts = durationData.map(item => parseInt(item.count));

const durationCtx = document.getElementById('durationChart').getContext('2d');
new Chart(durationCtx, {
    type: 'doughnut',
    data: {
        labels: durationLabels,
        datasets: [{
            data: durationCounts,
            backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545'],
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
    let csv = 'Rent Summary Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';
    csv += 'Summary Metrics:\n';
    csv += 'Total Rentals,<?= $summary['total_rents'] ?? 0 ?>\n';
    csv += 'Total Revenue,<?= $summary['total_revenue'] ?? 0 ?>\n';
    csv += 'Daily Rentals,<?= $summary['daily_rents'] ?? 0 ?>\n';
    csv += 'Fixed Rentals,<?= $summary['once_rents'] ?? 0 ?>\n\n';
    
    csv += 'Top Customers:\n';
    csv += 'Name,Rentals,Total Spent,Avg Days\n';
    <?php foreach($top_customers as $customer): ?>
    csv += '<?= addslashes($customer['name']) ?>,<?= $customer['rental_count'] ?>,<?= $customer['total_spent'] ?>,<?= $customer['avg_rental_days'] ?>\n';
    <?php endforeach; ?>
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'rent_summary_report_<?= date('Y-m-d') ?>.csv';
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