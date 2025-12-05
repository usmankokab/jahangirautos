<?php
require_once 'config/auth.php';

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

include 'config/db.php';
include 'includes/permissions.php';

// Check if user has permission to view dashboard
if (!check_permission('dashboard', 'view')) {
    header("Location: " . BASE_URL . "/views/overdue_installments_notifications.php");
    exit();
}

include 'includes/header.php';

// Get current date for default filters
$current_date = date('Y-m-d');
$current_month_start = date('Y-m-01');
$current_year_start = date('Y-01-01');

// Quick stats for dashboard cards
$stats_query = "
    SELECT
        (SELECT COUNT(DISTINCT s.id) FROM sales s LEFT JOIN installments i ON s.id=i.sale_id) as total_sales,
        (SELECT COUNT(*) FROM rents) as total_rents,
        (SELECT COALESCE(SUM(total_amount + ((total_amount * interest_rate) / 100)), 0) FROM sales) as total_sales_amount,
        (SELECT COALESCE(SUM(CASE WHEN rent_type = 'daily' THEN daily_rent * DATEDIFF(end_date, start_date) ELSE total_rent END), 0) FROM rents) as total_rent_amount,
        (SELECT COUNT(*) FROM installments WHERE status IN ('unpaid', 'partial') AND due_date <= CURDATE() AND DAY(CURDATE()) >= 10) as overdue_count,
        (SELECT COALESCE(SUM(amount - COALESCE(paid_amount, 0)), 0) FROM installments WHERE status IN ('unpaid', 'partial')) as pending_amount
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-bar-chart-line-fill me-2"></i>Dashboard</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Sales</div>
                            <div class="h4 mb-0"><?= number_format($stats['total_sales']) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cart-fill fa-2x"></i>
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
                            <div class="small">Total Rents</div>
                            <div class="h4 mb-0"><?= number_format($stats['total_rents']) ?></div>
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
                            <div class="small">Total Sales Value</div>
                            <div class="h4 mb-0">₨<?= number_format($stats['total_sales_amount'], 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fa-2x"></i>
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
                            <div class="small">Total Rents Value</div>
                            <div class="h4 mb-0">₨<?= number_format($stats['total_rent_amount'], 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-month fa-2x"></i>
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
                            <div class="small">Overdue</div>
                            <div class="h4 mb-0"><?= number_format($stats['overdue_count']) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-exclamation-triangle fa-2x"></i>
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
                            <div class="small">Pending Amount</div>
                            <div class="h4 mb-0">₨<?= number_format($stats['pending_amount'], 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Categories -->
    <div class="row">
        <!-- Sales Reports -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-cart-fill me-2"></i>Sales Reports</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="views/sales_summary_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-graph-up text-primary me-2"></i>
                                <strong>Sales Summary</strong>
                                <small class="d-block text-muted">Overview of sales performance with trends</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        
                        <a href="views/installment_analysis_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-calendar-check text-success me-2"></i>
                                <strong>Installment Analysis</strong>
                                <small class="d-block text-muted">Payment status and collection analysis</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        
                        <a href="views/customer_performance_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-people text-info me-2"></i>
                                <strong>Customer Performance</strong>
                                <small class="d-block text-muted">Customer-wise sales and payment behavior</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        
                        <a href="views/product_performance_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-box-seam text-warning me-2"></i>
                                <strong>Product Performance</strong>
                                <small class="d-block text-muted">Best selling products and inventory insights</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        
                        <a href="views/overdue_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                                <strong>Overdue Analysis</strong>
                                <small class="d-block text-muted">Late payments and recovery tracking</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rent Reports -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Rent Reports</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="views/rent_summary_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-graph-up text-primary me-2"></i>
                                <strong>Rent Summary</strong>
                                <small class="d-block text-muted">Overview of rental business performance</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        
                        <a href="views/rental_utilization_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-calendar-range text-success me-2"></i>
                                <strong>Utilization Analysis</strong>
                                <small class="d-block text-muted">Equipment usage and availability tracking</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        
                        <a href="views/rent_customer_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-people text-info me-2"></i>
                                <strong>Customer Analysis</strong>
                                <small class="d-block text-muted">Rental customer behavior and preferences</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        
                        <a href="views/rent_payment_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-credit-card text-warning me-2"></i>
                                <strong>Payment Tracking</strong>
                                <small class="d-block text-muted">Rent payment status and collections</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        
                        <a href="views/rental_profitability_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-currency-dollar text-danger me-2"></i>
                                <strong>Profitability Analysis</strong>
                                <small class="d-block text-muted">Revenue analysis and profit margins</small>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning-fill me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-outline-primary w-100" onclick="exportAllData()">
                                <i class="bi bi-download me-2"></i>Export All Data
                            </button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-outline-success w-100" onclick="generateMonthlyReport()">
                                <i class="bi bi-file-earmark-text me-2"></i>Monthly Report
                            </button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-outline-warning w-100" onclick="sendReminders()">
                                <i class="bi bi-bell me-2"></i>Send Reminders
                            </button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-outline-info w-100" onclick="scheduleReport()">
                                <i class="bi bi-calendar-event me-2"></i>Schedule Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshDashboard() {
    location.reload();
}

function exportAllData() {
    if(confirm('This will export all sales and rent data. Continue?')) {
        window.open('views/export_data.php?type=all', '_blank');
    }
}

function generateMonthlyReport() {
    const month = prompt('Enter month (YYYY-MM):', '<?= date('Y-m') ?>');
    if(month) {
        window.open('views/monthly_report.php?month=' + month, '_blank');
    }
}

function sendReminders() {
    if(confirm('Send payment reminders to customers with overdue installments?')) {
        // Implementation for sending reminders
        alert('Reminder functionality will be implemented based on your notification preferences.');
    }
}

function scheduleReport() {
    alert('Report scheduling functionality will be implemented based on your requirements.');
}
</script>

<style>
.card {
    transition: transform 0.2s ease-in-out;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.list-group-item-action:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
    transition: all 0.2s ease;
}

.bg-primary { background: linear-gradient(45deg, #007bff, #0056b3) !important; }
.bg-success { background: linear-gradient(45deg, #28a745, #1e7e34) !important; }
.bg-info { background: linear-gradient(45deg, #17a2b8, #117a8b) !important; }
.bg-warning { background: linear-gradient(45deg, #ffc107, #d39e00) !important; }
.bg-danger { background: linear-gradient(45deg, #dc3545, #bd2130) !important; }
.bg-secondary { background: linear-gradient(45deg, #6c757d, #545b62) !important; }

@media (max-width: 768px) {
    .h4 {
        font-size: 1.1rem;
    }
    .small {
        font-size: 0.8rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>