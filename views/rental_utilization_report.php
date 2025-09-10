<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

// Check if rents table exists
$table_check = $conn->query("SHOW TABLES LIKE 'rents'");
$rents_table_exists = $table_check->num_rows > 0;

if (!$rents_table_exists) {
    // Show placeholder message
    $utilization_data = [];
    $summary = [
        'overall_utilization' => 0,
        'total_rental_revenue' => 0,
        'total_rentals' => 0,
        'active_rentals' => 0,
        'completed_rentals' => 0,
        'avg_rental_duration' => 0,
        'total_products_rented' => 0
    ];
    $utilization_trend = [];
} else {
    // Get filter parameters
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-t');
    $status_filter = $_GET['status'] ?? 'all';
    $sort_by = $_GET['sort_by'] ?? 'utilization_rate';

    // Build WHERE clause
    $where_conditions = ["r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)"];
    $params = [$to_date, $from_date];
    $types = "ss";

    if ($status_filter !== 'all') {
        $where_conditions[] = "r.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Simplified rental utilization analysis
    $utilization_query = "
        SELECT
            r.product_name as product_name,
            '' as model,
            COUNT(r.id) as total_rentals,
            AVG(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1) as avg_rental_duration,
            COALESCE(SUM(r.daily_rent * DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1), 0) as total_revenue,
            0 as active_rentals,
            0 as completed_rentals,
            0 as utilization_rate,
            0 as total_rental_days
        FROM rents r
        WHERE r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
        GROUP BY r.product_name
        HAVING total_rentals > 0
        ORDER BY total_rentals DESC
    ";

    $stmt = $conn->prepare($utilization_query);
    $stmt->bind_param("ss", $to_date, $from_date);
    $stmt->execute();
    $utilization_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Summary statistics
    $summary_query = "
        SELECT
            COUNT(DISTINCT r.product_name) as total_products_rented,
            COUNT(r.id) as total_rentals,
            COALESCE(SUM(r.daily_rent * DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1), 0) as total_rental_revenue,
            AVG(DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1) as avg_rental_duration,
            0 as active_rentals,
            0 as completed_rentals,
            0 as overall_utilization
        FROM rents r
        WHERE r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
    ";

    $summary_stmt = $conn->prepare($summary_query);
    $summary_stmt->bind_param("ss", $to_date, $from_date);
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_assoc();

    // Monthly utilization trend (simplified)
    $trend_query = "
        SELECT
            DATE_FORMAT(r.start_date, '%Y-%m') as month,
            COUNT(r.id) as rentals_count,
            0 as utilization_rate,
            0 as rental_days,
            0 as days_in_month
        FROM rents r
        WHERE r.start_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(r.start_date, '%Y-%m')
        ORDER BY month
    ";

    $trend_stmt = $conn->prepare($trend_query);
    $trend_stmt->bind_param("ss", $from_date, $to_date);
    $trend_stmt->execute();
    $utilization_trend = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Rental Utilization Report</h2>
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

    <?php if (!$rents_table_exists): ?>
    <div class="alert alert-info mb-4">
        <h5><i class="bi bi-info-circle me-2"></i>Rental Functionality Not Yet Implemented</h5>
        <p>The rental management system has not been fully implemented yet. This report shows the framework and will display actual data once the rental tables are created and populated.</p>
        <p>To enable rental functionality, the database needs to include a <code>rents</code> table with appropriate schema.</p>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Overall Utilization</div>
                            <div class="h4 mb-0"><?= number_format($summary['overall_utilization'], 1) ?>%</div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fa-2x"></i>
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
                            <div class="small">Total Revenue</div>
                            <div class="h4 mb-0">₨<?= number_format($summary['total_rental_revenue'], 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash-coin fa-2x"></i>
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
                            <div class="small">Total Rentals</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_rentals']) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-check fa-2x"></i>
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
                            <div class="small">Active Rentals</div>
                            <div class="h4 mb-0"><?= number_format($summary['active_rentals']) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-play-circle fa-2x"></i>
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
                            <div class="small">Avg Duration</div>
                            <div class="h4 mb-0"><?= number_format($summary['avg_rental_duration'], 1) ?> days</div>
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
                            <div class="small">Products Rented</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_products_rented']) ?></div>
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
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort_by" class="form-select">
                        <option value="utilization_rate" <?= $sort_by == 'utilization_rate' ? 'selected' : '' ?>>Utilization Rate</option>
                        <option value="total_rentals" <?= $sort_by == 'total_rentals' ? 'selected' : '' ?>>Total Rentals</option>
                        <option value="total_revenue" <?= $sort_by == 'total_revenue' ? 'selected' : '' ?>>Total Revenue</option>
                        <option value="total_rentals" <?= $sort_by == 'total_rentals' ? 'selected' : '' ?>>Total Rentals</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="rental_utilization_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Utilization Trend Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Utilization Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="utilizationTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Utilization Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Utilization Levels</h5>
                </div>
                <div class="card-body">
                    <canvas id="utilizationPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Utilization Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Product Utilization</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product</th>
                                    <th>Total Rentals</th>
                                    <th>Avg Duration</th>
                                    <th>Total Revenue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($utilization_data as $product): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($product['product_name']) ?></div>
                                    </td>
                                    <td><?= number_format($product['total_rentals']) ?></td>
                                    <td><?= number_format($product['avg_rental_duration'], 1) ?> days</td>
                                    <td>₨<?= number_format($product['total_revenue'], 0) ?></td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
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
// Utilization Trend Chart
const trendData = <?= json_encode($utilization_trend) ?>;
const trendLabels = trendData.map(item => {
    const date = new Date(item.month + '-01');
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
});
const trendRates = trendData.map(item => parseFloat(item.utilization_rate));

const trendCtx = document.getElementById('utilizationTrendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Utilization Rate (%)',
            data: trendRates,
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
                max: 100,
                title: {
                    display: true,
                    text: 'Utilization Rate (%)'
                }
            }
        }
    }
});

// Utilization Distribution Pie Chart
const utilizationLevels = <?= json_encode($utilization_data) ?>;
const highUtil = utilizationLevels.filter(p => p.utilization_rate >= 70).length;
const mediumUtil = utilizationLevels.filter(p => p.utilization_rate >= 40 && p.utilization_rate < 70).length;
const lowUtil = utilizationLevels.filter(p => p.utilization_rate < 40).length;

const pieCtx = document.getElementById('utilizationPieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: ['High (70%+)', 'Medium (40-69%)', 'Low (<40%)'],
        datasets: [{
            data: [highUtil, mediumUtil, lowUtil],
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
                position: 'bottom'
            }
        }
    }
});

function exportToExcel() {
    let csv = 'Rental Utilization Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';

    csv += 'Summary:\n';
    csv += 'Overall Utilization: <?= number_format($summary['overall_utilization'], 1) ?>%\n';
    csv += 'Total Revenue: ₨<?= number_format($summary['total_rental_revenue'], 0) ?>\n';
    csv += 'Total Rentals: <?= $summary['total_rentals'] ?>\n\n';

    csv += 'Product Utilization:\n';
    csv += 'Product,Rentals,Avg Duration,Revenue\n';
    <?php foreach($utilization_data as $product): ?>
    csv += '<?= addslashes($product['product_name']) ?>,<?= $product['total_rentals'] ?>,<?= $product['avg_rental_duration'] ?>,<?= $product['total_revenue'] ?>\n';
    <?php endforeach; ?>

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'rental_utilization_report_<?= date('Y-m-d') ?>.csv';
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
.bg-danger { background: linear-gradient(45deg, #dc3545, #bd2130) !important; }
.bg-secondary { background: linear-gradient(45deg, #6c757d, #545b62) !important; }
</style>

<?php include '../includes/footer.php'; ?>