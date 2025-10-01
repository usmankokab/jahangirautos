<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('rent_customer_report', 'view');

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$sort_by = $_GET['sort_by'] ?? 'total_rentals';

// Customer rental analysis
$customer_rental_query = "
    SELECT
        c.id,
        c.name,
        c.phone,
        c.cnic,
        COUNT(r.id) as total_rentals,
        SUM(CASE WHEN r.rent_type = 'daily' THEN 1 ELSE 0 END) as daily_rentals,
        SUM(CASE WHEN r.rent_type = 'once' THEN 1 ELSE 0 END) as fixed_rentals,
        COALESCE(SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1
                ELSE r.total_rent
            END
        ), 0) as total_rental_amount,
        AVG(
            CASE
                WHEN r.rent_type = 'daily' THEN DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1
                ELSE 1
            END
        ) as avg_rental_duration,
        COUNT(DISTINCT r.product_name) as unique_products,
        MIN(r.start_date) as first_rental_date,
        MAX(COALESCE(r.end_date, CURDATE())) as last_rental_date,
        GROUP_CONCAT(DISTINCT r.product_name SEPARATOR ', ') as rented_products
    FROM customers c
    LEFT JOIN rents r ON c.id = r.customer_id AND r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
    GROUP BY c.id, c.name, c.phone, c.cnic
    HAVING total_rentals > 0
    ORDER BY total_rentals DESC
";

$stmt = $conn->prepare($customer_rental_query);
$stmt->bind_param("ss", $to_date, $from_date);
$stmt->execute();
$customer_rentals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Apply sorting in PHP if needed
if ($sort_by == 'total_amount') {
    usort($customer_rentals, function($a, $b) {
        return $b['total_rental_amount'] <=> $a['total_rental_amount'];
    });
} elseif ($sort_by == 'avg_duration') {
    usort($customer_rentals, function($a, $b) {
        return $b['avg_rental_duration'] <=> $a['avg_rental_duration'];
    });
}

// Summary statistics
$summary_query = "
    SELECT
        COUNT(DISTINCT c.id) as total_customers_renting,
        COUNT(r.id) as total_rentals,
        COALESCE(SUM(
            CASE
                WHEN r.rent_type = 'daily' THEN r.daily_rent * DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1
                ELSE r.total_rent
            END
        ), 0) as total_rental_revenue,
        AVG(
            CASE
                WHEN r.rent_type = 'daily' THEN DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1
                ELSE 1
            END
        ) as avg_rental_duration,
        COUNT(CASE WHEN r.rent_type = 'daily' THEN 1 END) as daily_rentals,
        COUNT(CASE WHEN r.rent_type = 'once' THEN 1 END) as fixed_rentals
    FROM customers c
    LEFT JOIN rents r ON c.id = r.customer_id AND r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
    WHERE r.id IS NOT NULL
";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("ss", $to_date, $from_date);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Customer rental categories
$category_query = "
    SELECT
        CASE
            WHEN total_rental_amount >= 50000 THEN 'High Value (₨50K+)'
            WHEN total_rental_amount >= 20000 THEN 'Medium Value (₨20K-50K)'
            WHEN total_rental_amount >= 5000 THEN 'Regular (₨5K-20K)'
            ELSE 'Low Value (Under ₨5K)'
        END as category,
        COUNT(*) as customer_count,
        SUM(total_rentals) as category_rentals,
        SUM(total_rental_amount) as category_revenue,
        AVG(avg_rental_duration) as avg_duration
    FROM (
        SELECT
            c.id,
            COUNT(r.id) as total_rentals,
            COALESCE(SUM(
                CASE
                    WHEN r.rent_type = 'daily' THEN r.daily_rent * DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1
                    ELSE r.total_rent
                END
            ), 0) as total_rental_amount,
            AVG(
                CASE
                    WHEN r.rent_type = 'daily' THEN DATEDIFF(COALESCE(r.end_date, CURDATE()), r.start_date) + 1
                    ELSE 1
                END
            ) as avg_rental_duration
        FROM customers c
        LEFT JOIN rents r ON c.id = r.customer_id AND r.start_date <= ? AND (r.end_date >= ? OR r.end_date IS NULL)
        GROUP BY c.id
        HAVING total_rentals > 0
    ) customer_totals
    GROUP BY category
    ORDER BY
        CASE category
            WHEN 'High Value (₨50K+)' THEN 1
            WHEN 'Medium Value (₨20K-50K)' THEN 2
            WHEN 'Regular (₨5K-20K)' THEN 3
            ELSE 4
        END
";

$category_stmt = $conn->prepare($category_query);
$category_stmt->bind_param("ss", $to_date, $from_date);
$category_stmt->execute();
$customer_categories = $category_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top customers by different metrics
$top_revenue = array_slice($customer_rentals, 0, 5);
$most_active = $customer_rentals;
usort($most_active, function($a, $b) {
    return $b['total_rentals'] <=> $a['total_rentals'];
});
$most_active = array_slice($most_active, 0, 5);

$longest_renters = $customer_rentals;
usort($longest_renters, function($a, $b) {
    return $b['avg_rental_duration'] <=> $a['avg_rental_duration'];
});
$longest_renters = array_slice($longest_renters, 0, 5);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-people me-2"></i>Rent Customer Analysis Report</h2>
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
                            <div class="small">Customers Renting</div>
                            <div class="h4 mb-0"><?= number_format($summary['total_customers_renting'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fa-2x"></i>
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
                            <div class="h4 mb-0">₨<?= number_format($summary['total_rental_revenue'] ?? 0, 0) ?></div>
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
                            <div class="h4 mb-0"><?= number_format($summary['total_rentals'] ?? 0) ?></div>
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
                            <div class="small">Daily Rentals</div>
                            <div class="h4 mb-0"><?= number_format($summary['daily_rentals'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-sun fa-2x"></i>
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
                            <div class="small">Fixed Rentals</div>
                            <div class="h4 mb-0"><?= number_format($summary['fixed_rentals'] ?? 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-tag fa-2x"></i>
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
                            <div class="small">Avg Duration</div>
                            <div class="h4 mb-0"><?= number_format($summary['avg_rental_duration'] ?? 0, 1) ?> days</div>
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
                        <option value="total_rentals" <?= $sort_by == 'total_rentals' ? 'selected' : '' ?>>Total Rentals</option>
                        <option value="total_amount" <?= $sort_by == 'total_amount' ? 'selected' : '' ?>>Total Amount</option>
                        <option value="avg_duration" <?= $sort_by == 'avg_duration' ? 'selected' : '' ?>>Avg Duration</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Apply Filters
                    </button>
                    <a href="rent_customer_report.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer Categories -->
    <div class="row mb-4">
        <?php foreach($customer_categories as $category): ?>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card <?= strpos($category['category'], 'High Value') !== false ? 'border-primary' :
                               (strpos($category['category'], 'Medium') !== false ? 'border-success' :
                               (strpos($category['category'], 'Regular') !== false ? 'border-warning' : 'border-secondary')) ?> h-100">
                <div class="card-body text-center">
                    <h6 class="card-title"><?= htmlspecialchars($category['category']) ?></h6>
                    <h4 class="text-primary"><?= number_format($category['customer_count']) ?></h4>
                    <small class="text-muted">customers</small>
                    <div class="mt-2">
                        <small>₨<?= number_format($category['category_revenue'], 0) ?> revenue</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Top Performers -->
    <div class="row mb-4">
        <!-- Top Revenue Customers -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Revenue Customers</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($top_revenue as $customer): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                <small class="text-muted"><?= $customer['total_rentals'] ?> rentals</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">₨<?= number_format($customer['total_rental_amount'], 0) ?></div>
                                <small class="text-muted"><?= number_format($customer['avg_rental_duration'], 1) ?> days avg</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Most Active Customers -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Most Active Customers</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($most_active as $customer): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                <small class="text-muted">₨<?= number_format($customer['total_rental_amount'], 0) ?> spent</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?= $customer['total_rentals'] ?></div>
                                <small class="text-muted">rentals</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Longest Renters -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-clock me-2"></i>Longest Average Duration</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($longest_renters as $customer): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                <small class="text-muted"><?= $customer['total_rentals'] ?> rentals</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-info"><?= number_format($customer['avg_rental_duration'], 1) ?> days</div>
                                <small class="text-muted">average</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Customer Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Customer Rental Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Customer</th>
                                    <th>Total Rentals</th>
                                    <th>Daily/Fixed</th>
                                    <th>Total Amount</th>
                                    <th>Avg Duration</th>
                                    <th>Unique Products</th>
                                    <th>First Rental</th>
                                    <th>Products Rented</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customer_rentals as $customer): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($customer['name']) ?></div>
                                        <small class="text-muted">
                                            CNIC: <?= htmlspecialchars($customer['cnic']) ?><br>
                                            Phone: <?= htmlspecialchars($customer['phone']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $customer['total_rentals'] ?></span>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <small class="d-block">
                                                <span class="badge bg-warning">Daily: <?= $customer['daily_rentals'] ?></span>
                                            </small>
                                            <small class="d-block">
                                                <span class="badge bg-info">Fixed: <?= $customer['fixed_rentals'] ?></span>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-success">₨<?= number_format($customer['total_rental_amount'], 0) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= number_format($customer['avg_rental_duration'], 1) ?> days
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?= $customer['unique_products'] ?></span>
                                    </td>
                                    <td>
                                        <?= $customer['first_rental_date'] ?>
                                        <small class="d-block text-muted">
                                            Last: <?= $customer['last_rental_date'] ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($customer['rented_products']) ?></small>
                                    </td>
                                    <td class="no-print">
                                        <?php if($customer['phone']): ?>
                                            <a href="tel:<?= $customer['phone'] ?>" class="btn btn-sm btn-outline-primary" title="Call Customer">
                                                <i class="bi bi-telephone"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="list_rents.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-info" title="View Customer Rentals">
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
// Customer Category Chart
const categoryData = <?= json_encode($customer_categories) ?>;
const categoryLabels = categoryData.map(item => item.category);
const categoryCounts = categoryData.map(item => parseInt(item.customer_count));

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

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show a brief success message
        const notification = document.createElement('div');
        notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>Phone number copied to clipboard!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);

        // Auto remove after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }).catch(function(err) {
        console.error('Failed to copy: ', err);
        alert('Failed to copy phone number');
    });
}

function exportToExcel() {
    let csv = 'Rent Customer Analysis Report\n\n';
    csv += 'Period: <?= $from_date ?> to <?= $to_date ?>\n\n';

    csv += 'Summary:\n';
    csv += 'Total Customers Renting: <?= $summary['total_customers_renting'] ?? 0 ?>\n';
    csv += 'Total Rentals: <?= $summary['total_rentals'] ?? 0 ?>\n';
    csv += 'Total Revenue: ₨<?= number_format($summary['total_rental_revenue'] ?? 0, 0) ?>\n\n';

    csv += 'Customer Rental Analysis:\n';
    csv += 'Name,CNIC,Phone,Total Rentals,Daily Rentals,Fixed Rentals,Total Amount,Avg Duration,Unique Products,First Rental,Last Rental,Products Rented\n';
    <?php foreach($customer_rentals as $customer): ?>
    csv += '<?= addslashes($customer['name']) ?>,<?= $customer['cnic'] ?>,<?= $customer['phone'] ?>,<?= $customer['total_rentals'] ?>,<?= $customer['daily_rentals'] ?>,<?= $customer['fixed_rentals'] ?>,<?= $customer['total_rental_amount'] ?>,<?= $customer['avg_rental_duration'] ?>,<?= $customer['unique_products'] ?>,<?= $customer['first_rental_date'] ?>,<?= $customer['last_rental_date'] ?>,<?= addslashes($customer['rented_products']) ?>\n';
    <?php endforeach; ?>

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'rent_customer_analysis_report_<?= date('Y-m-d') ?>.csv';
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

.table-dark th {
    color: #000000 !important;
    font-weight: 600 !important;
}
</style>

<?php include '../includes/footer.php'; ?>