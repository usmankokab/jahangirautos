<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('rental_profitability', 'view');

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$sort_by = $_GET['sort_by'] ?? 'total_revenue';

// Profitability analysis
$profitability_query = "
    SELECT
        r.product_name as product,
        COUNT(r.id) as total_rentals,
        SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ) as total_revenue,
        AVG(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ) as avg_revenue_per_rental,
        COUNT(CASE WHEN r.rent_type = 'daily' THEN 1 END) as daily_rentals,
        COUNT(CASE WHEN r.rent_type = 'once' THEN 1 END) as fixed_rentals,
        AVG(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1) as avg_rental_duration,
        SUM(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1) as total_rental_days,
        ROUND(
            SUM(
                CASE
                    WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                    ELSE r.total_rent
                END
            ) / SUM(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1), 2
        ) as revenue_per_day
    FROM rents r
    WHERE r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
    GROUP BY r.product_name
    HAVING total_rentals > 0
    ORDER BY
        CASE
            WHEN ? = 'total_revenue' THEN SUM(
                CASE
                    WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                    ELSE r.total_rent
                END
            )
            WHEN ? = 'total_rentals' THEN COUNT(r.id)
            WHEN ? = 'avg_revenue' THEN AVG(
                CASE
                    WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                    ELSE r.total_rent
                END
            )
            ELSE COUNT(r.id)
        END DESC
";

$stmt = $conn->prepare($profitability_query);
$stmt->bind_param("sssss", $to_date, $from_date, $sort_by, $sort_by, $sort_by);
$stmt->execute();
$profitability_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Overall profitability summary
$summary_query = "
    SELECT
        COUNT(DISTINCT r.product_name) as total_products,
        COUNT(r.id) as total_rentals,
        SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ) as total_revenue,
        AVG(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ) as avg_revenue_per_rental,
        SUM(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1) as total_rental_days,
        COUNT(CASE WHEN r.rent_type = 'daily' THEN 1 END) as daily_rentals,
        COUNT(CASE WHEN r.rent_type = 'once' THEN 1 END) as fixed_rentals,
        ROUND(
            SUM(
                CASE
                    WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                    ELSE r.total_rent
                END
            ) / SUM(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1), 2
        ) as overall_revenue_per_day
    FROM rents r
    WHERE r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("ss", $to_date, $from_date);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_all(MYSQLI_ASSOC)[0] ?? [];

// Monthly revenue trend
$monthly_trend_query = "
    SELECT
        DATE_FORMAT(r.start_date, '%Y-%m') as month,
        COUNT(r.id) as rentals_count,
        SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ) as monthly_revenue,
        SUM(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1) as rental_days,
        ROUND(
            SUM(
                CASE
                    WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                    ELSE r.total_rent
                END
            ) / SUM(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1), 2
        ) as revenue_per_day
    FROM rents r
    WHERE r.start_date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(r.start_date, '%Y-%m')
    ORDER BY month
";

$trend_stmt = $conn->prepare($monthly_trend_query);
$trend_stmt->bind_param("ss", $from_date, $to_date);
$trend_stmt->execute();
$monthly_trend = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Revenue by rental type
$type_revenue_query = "
    SELECT
        CASE
            WHEN r.rent_type = 'daily' THEN 'Daily Rentals'
            ELSE 'Fixed Rentals'
        END as rental_type,
        COUNT(r.id) as rentals_count,
        SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ) as total_revenue,
        AVG(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * (DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1)
                ELSE r.total_rent
            END
        ) as avg_revenue,
        SUM(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1) as total_days
    FROM rents r
    WHERE r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
    GROUP BY r.rent_type
";

$type_stmt = $conn->prepare($type_revenue_query);
$type_stmt->bind_param("ss", $to_date, $from_date);
$type_stmt->execute();
$revenue_by_type = $type_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top performing products
$top_products = array_slice($profitability_data, 0, 5);
$least_products = array_slice(array_reverse($profitability_data), 0, 5);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-graph-up me-2"></i>Rental Profitability Report</h2>
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
                            <div class="small">Total Revenue</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['total_revenue'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash-coin fa-2x"></i>
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
                            <div class="small">Avg Revenue/Rental</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['avg_revenue_per_rental'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calculator fa-2x"></i>
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
                            <div class="small">Revenue/Day</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['overall_revenue_per_day'] ?? 0, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-day fa-2x"></i>
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
                            <div class="small">Total Rentals</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_rentals'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-receipt fa-2x"></i>
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
                            <div class="small">Total Days</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_rental_days'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock fa-2x"></i>
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
                            <div class="small">Products</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_products'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-box-seam fa-2x"></i>
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
                        <option value="total_rentals" <?= $sort_by == 'total_rentals' ? 'selected' : '' ?>>Total Rentals</option>
                        <option value="avg_revenue" <?= $sort_by == 'avg_revenue' ? 'selected' : '' ?>>Avg Revenue</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="rental_profitability_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Revenue Trend Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Monthly Revenue Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Revenue by Type Chart -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Revenue by Rental Type</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueTypeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers -->
    <div class="row mb-4">
        <!-- Top Revenue Products -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Revenue Products</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($top_products as $product): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($product['product']) ?></div>
                                <small class="text-muted"><?= $product['total_rentals'] ?> rentals</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">₨<?= number_format($product['total_revenue'], 0) ?></div>
                                <small class="text-muted">₨<?= number_format($product['revenue_per_day'], 0) ?>/day</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Least Revenue Products -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h6 class="mb-0"><i class="bi bi-arrow-down me-2"></i>Lowest Revenue Products</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($least_products as $product): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($product['product']) ?></div>
                                <small class="text-muted"><?= $product['total_rentals'] ?> rentals</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-warning">₨<?= number_format($product['total_revenue'], 0) ?></div>
                                <small class="text-muted">₨<?= number_format($product['revenue_per_day'], 0) ?>/day</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Profitability Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Product Profitability</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product</th>
                                    <th>Total Rentals</th>
                                    <th>Daily/Fixed</th>
                                    <th>Total Revenue</th>
                                    <th>Avg Revenue/Rental</th>
                                    <th>Revenue/Day</th>
                                    <th>Avg Duration</th>
                                    <th>Total Days</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($profitability_data as $product): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($product['product']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $product['total_rentals'] ?></span>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <small class="d-block">
                                                <span class="badge bg-info">Daily: <?= $product['daily_rentals'] ?></span>
                                            </small>
                                            <small class="d-block">
                                                <span class="badge bg-secondary">Fixed: <?= $product['fixed_rentals'] ?></span>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-success">₨<?= number_format($product['total_revenue'], 0) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">₨<?= number_format($product['avg_revenue_per_rental'], 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">₨<?= number_format($product['revenue_per_day'], 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= number_format($product['avg_rental_duration'], 1) ?> days
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?= number_format($product['total_rental_days']) ?> days
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $performance = 'Low';
                                        $badge_class = 'bg-secondary';

                                        if($product['revenue_per_day'] >= 1000) {
                                            $performance = 'High';
                                            $badge_class = 'bg-success';
                                        } elseif($product['revenue_per_day'] >= 500) {
                                            $performance = 'Medium';
                                            $badge_class = 'bg-warning';
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= $performance ?> Performance</span>
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
// Revenue Trend Chart
const trendData = <?= json_encode($monthly_trend) ?>;
const trendLabels = trendData.map(item => {
    const date = new Date(item.month + '-01');
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
});
const trendRevenue = trendData.map(item => parseFloat(item.monthly_revenue));

const trendCtx = document.getElementById('revenueTrendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Monthly Revenue (₨)',
            data: trendRevenue,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₨' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Revenue by Type Chart
const typeData = <?= json_encode($revenue_by_type) ?>;
const typeLabels = typeData.map(item => item.rental_type);
const typeRevenue = typeData.map(item => parseFloat(item.total_revenue));

const typeCtx = document.getElementById('revenueTypeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{
            data: typeRevenue,
            backgroundColor: ['#28a745', '#6c757d'],
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
    let csv = 'Rental Profitability Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';

    csv += 'Summary:\n';
    csv += 'Total Revenue: ₨<?= number_format($summary['total_revenue'] ?? 0, 0) ?>\n';
    csv += 'Total Rentals: <?= $summary['total_rentals'] ?? 0 ?>\n';
    csv += 'Avg Revenue per Rental: ₨<?= number_format($summary['avg_revenue_per_rental'] ?? 0, 0) ?>\n';
    csv += 'Revenue per Day: ₨<?= number_format($summary['overall_revenue_per_day'] ?? 0, 0) ?>\n\n';

    csv += 'Product Profitability:\n';
    csv += 'Product,Total Rentals,Daily Rentals,Fixed Rentals,Total Revenue,Avg Revenue,Rental Days,Revenue per Day\n';
    <?php foreach($profitability_data as $product): ?>
    csv += '<?= addslashes($product['product']) ?>,<?= $product['total_rentals'] ?>,<?= $product['daily_rentals'] ?>,<?= $product['fixed_rentals'] ?>,<?= $product['total_revenue'] ?>,<?= $product['avg_revenue_per_rental'] ?>,<?= $product['total_rental_days'] ?>,<?= $product['revenue_per_day'] ?>\n';
    <?php endforeach; ?>

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'rental_profitability_report_<?= date('Y-m-d') ?>.csv';
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

.list-group-item:hover {
    background-color: #f8f9fa;
}

.bg-primary { background: linear-gradient(45deg, #007bff, #0056b3) !important; }
.bg-success { background: linear-gradient(45deg, #28a745, #1e7e34) !important; }
.bg-info { background: linear-gradient(45deg, #17a2b8, #117a8b) !important; }
.bg-warning { background: linear-gradient(45deg, #ffc107, #d39e00) !important; }
.bg-danger { background: linear-gradient(45deg, #dc3545, #bd2130) !important; }
.bg-secondary { background: linear-gradient(45deg, #6c757d, #545b62) !important; }
</style>

<?php include '../includes/footer.php'; ?>