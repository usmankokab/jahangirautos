<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('overdue_report', 'view');

include '../includes/header.php';

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-3 months'));
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'days_overdue';

// Build WHERE clause
$where_conditions = ["i.due_date <= CURDATE() AND DAY(CURDATE()) >= 10"];
$params = [];
$types = "";

if ($status_filter === 'all') {
    $where_conditions[] = "i.status IN ('unpaid', 'partial')";
} elseif ($status_filter !== 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Overdue installments analysis
$overdue_query = "
    SELECT
        i.id as installment_id,
        (SELECT COUNT(*) FROM installments i2 WHERE i2.sale_id = i.sale_id AND i2.due_date <= i.due_date) as installment_number,
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
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Database error. Please check logs.");
}
$stmt->bind_param($types . "ssss", ...array_merge($params, [$sort_by, $sort_by, $sort_by, $sort_by]));
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("Database error. Please check logs.");
}
$overdue_installments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug logging for main overdue query
error_log("Overdue Report - Main Query Count: " . count($overdue_installments));

// Summary statistics (filtered)
$summary_query = "
    SELECT
        COUNT(*) as total_overdue,
        COALESCE(SUM(i.amount), 0) as total_amount_due,
        COALESCE(SUM(i.paid_amount), 0) as total_amount_paid,
        COALESCE(SUM(i.amount - COALESCE(i.paid_amount, 0)), 0) as total_remaining,
        COALESCE(AVG(DATEDIFF(CURDATE(), i.due_date)), 0) as avg_days_overdue,
        COUNT(CASE WHEN i.status = 'unpaid' THEN 1 END) as fully_unpaid,
        COUNT(CASE WHEN i.status = 'partial' THEN 1 END) as partially_paid,
        COUNT(DISTINCT c.id) as affected_customers,
        COUNT(DISTINCT s.id) as affected_sales
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    WHERE i.due_date <= CURDATE() AND DAY(CURDATE()) >= 10 AND i.status != 'paid' AND s.sale_date BETWEEN ? AND ?
";

$summary_stmt = $conn->prepare($summary_query);
if (!$summary_stmt) {
    error_log("Summary prepare failed: " . $conn->error);
    die("Database error. Please check logs.");
}
$summary_stmt->bind_param("ss", $from_date, $to_date);
if (!$summary_stmt->execute()) {
    error_log("Summary execute failed: " . $summary_stmt->error);
    die("Database error. Please check logs.");
}
$summary = $summary_stmt->get_result()->fetch_assoc() ?: [
    'total_overdue' => 0,
    'total_amount_due' => 0,
    'total_amount_paid' => 0,
    'total_remaining' => 0,
    'avg_days_overdue' => 0,
    'fully_unpaid' => 0,
    'partially_paid' => 0,
    'affected_customers' => 0,
    'affected_sales' => 0
];
$summary_stmt->close();

// Total overdue amount (filtered by sale date)
$total_overdue_amount = $summary ? $summary['total_remaining'] : 0;

// Debug logging for overdue report summary
error_log("Overdue Report - Summary Total Overdue: " . $summary['total_overdue']);
error_log("Overdue Report - Summary Total Remaining: " . $summary['total_remaining']);
error_log("Overdue Report - Summary Affected Customers: " . $summary['affected_customers']);
error_log("Overdue Report - Summary Fully Unpaid: " . $summary['fully_unpaid']);
error_log("Overdue Report - Summary Partially Paid: " . $summary['partially_paid']);

// Overdue by customer
$customer_overdue_query = "
    SELECT
        c.id,
        c.name,
        c.phone,
        c.cnic,
        COUNT(i.id) as overdue_installments,
        COALESCE(SUM(i.amount - COALESCE(i.paid_amount, 0)), 0) as total_overdue_amount,
        COALESCE(AVG(DATEDIFF(CURDATE(), i.due_date)), 0) as avg_days_overdue,
        COALESCE(MAX(DATEDIFF(CURDATE(), i.due_date)), 0) as max_days_overdue,
        GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as products
    FROM customers c
    JOIN sales s ON c.id = s.customer_id
    JOIN installments i ON s.id = i.sale_id
    JOIN products p ON s.product_id = p.id
    WHERE i.due_date <= CURDATE() AND DAY(CURDATE()) >= 10 AND i.status != 'paid' AND s.sale_date BETWEEN ? AND ?
    GROUP BY c.id, c.name, c.phone, c.cnic
    ORDER BY total_overdue_amount DESC
";

$customer_stmt = $conn->prepare($customer_overdue_query);
if (!$customer_stmt) {
    error_log("Customer prepare failed: " . $conn->error);
    die("Database error. Please check logs.");
}
$customer_stmt->bind_param("ss", $from_date, $to_date);
if (!$customer_stmt->execute()) {
    error_log("Customer execute failed: " . $customer_stmt->error);
    die("Database error. Please check logs.");
}
$customer_overdue = $customer_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$customer_stmt->close();

// Debug logging for customer overdue
error_log("Overdue Report - Customer Overdue Count: " . count($customer_overdue));

// Recovery rate analysis (filtered by sale date)
$recovery_query = "
    SELECT
        CASE
            WHEN days_overdue <= 7 THEN '1-7 days'
            WHEN days_overdue <= 15 THEN '8-15 days'
            WHEN days_overdue <= 30 THEN '16-30 days'
            WHEN days_overdue <= 60 THEN '31-60 days'
            WHEN days_overdue <= 90 THEN '61-90 days'
            WHEN days_overdue <= 180 THEN '91-180 days'
            ELSE '180+ days'
        END as overdue_range,
        COUNT(*) as installment_count,
        COALESCE(SUM(amount), 0) as total_amount,
        COALESCE(SUM(paid_amount), 0) as total_paid,
        COALESCE(SUM(amount - COALESCE(paid_amount, 0)), 0) as total_remaining,
        ROUND(CASE WHEN COALESCE(SUM(amount), 0) > 0 THEN (COALESCE(SUM(paid_amount), 0) / SUM(amount)) * 100 ELSE 0 END, 2) as recovery_rate
    FROM (
        SELECT
            i.amount,
            i.paid_amount,
            DATEDIFF(CURDATE(), i.due_date) as days_overdue
        FROM installments i
        JOIN sales s ON i.sale_id = s.id
        WHERE i.due_date <= CURDATE() AND DAY(CURDATE()) >= 10 AND i.status IN ('unpaid', 'partial') AND s.sale_date BETWEEN ? AND ?
    ) overdue_data
    GROUP BY overdue_range
    ORDER BY
        CASE overdue_range
            WHEN '1-7 days' THEN 1
            WHEN '8-15 days' THEN 2
            WHEN '16-30 days' THEN 3
            WHEN '31-60 days' THEN 4
            WHEN '61-90 days' THEN 5
            WHEN '91-180 days' THEN 6
            ELSE 7
        END
";

$recovery_stmt = $conn->prepare($recovery_query);
if (!$recovery_stmt) {
    error_log("Recovery prepare failed: " . $conn->error);
    die("Database error. Please check logs.");
}
$recovery_stmt->bind_param("ss", $from_date, $to_date);
if (!$recovery_stmt->execute()) {
    error_log("Recovery execute failed: " . $recovery_stmt->error);
    die("Database error. Please check logs.");
}
$recovery_data = $recovery_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recovery_stmt->close();
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
                            <div class="h4 mb-0">₨<?= number_format($total_overdue_amount, 0) ?></div>
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
                            <div class="h4 mb-0"><?= number_format($summary['total_overdue'] ?? 0) ?></div>
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
                            <div class="h4 mb-0"><?= number_format($summary['affected_customers'] ?? 0) ?></div>
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
                            <div class="h4 mb-0"><?= number_format($summary['avg_days_overdue'] ?? 0, 1) ?></div>
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
                            <div class="h4 mb-0"><?= number_format($summary['fully_unpaid'] ?? 0) ?></div>
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
                            <div class="h4 mb-0"><?= number_format($summary['partially_paid'] ?? 0) ?></div>
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

    <!-- Enhanced Charts Section -->
    <div class="row mb-4">
        <!-- Overdue Amount Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Overdue Amount by Period</h5>
                </div>
                <div class="card-body">
                    <canvas id="overdueAmountChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Recovery Rate Analysis -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Recovery Rate by Period</h5>
                </div>
                <div class="card-body">
                    <canvas id="recoveryRateChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Status Breakdown -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart-fill me-2"></i>Payment Status Overview</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentStatusChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Metrics Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Overdue Metrics by Period</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Period</th>
                                    <th>Installments</th>
                                    <th>Total Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Remaining</th>
                                    <th>Recovery Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_installments = array_sum(array_column($recovery_data, 'installment_count'));
                                $total_amount_all = array_sum(array_column($recovery_data, 'total_amount'));
                                $total_paid_all = array_sum(array_column($recovery_data, 'total_paid'));
                                $total_remaining_all = array_sum(array_column($recovery_data, 'total_remaining'));

                                foreach($recovery_data as $period):
                                ?>
                                <tr>
                                    <td><strong><?= $period['overdue_range'] ?></strong></td>
                                    <td class="text-center"><?= $period['installment_count'] ?></td>
                                    <td>₨<?= number_format($period['total_amount'], 0) ?></td>
                                    <td>₨<?= number_format($period['total_paid'], 0) ?></td>
                                    <td><strong class="text-danger">₨<?= number_format($period['total_remaining'], 0) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $period['recovery_rate'] >= 50 ? 'success' : ($period['recovery_rate'] >= 25 ? 'warning' : 'danger') ?>">
                                            <?= number_format($period['recovery_rate'], 1) ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary fw-bold">
                                    <td><strong>TOTAL</strong></td>
                                    <td class="text-center"><?= $total_installments ?></td>
                                    <td>₨<?= number_format($total_amount_all, 0) ?></td>
                                    <td>₨<?= number_format($total_paid_all, 0) ?></td>
                                    <td>₨<?= number_format($total_remaining_all, 0) ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?= $total_amount_all > 0 ? number_format(($total_paid_all / $total_amount_all) * 100, 1) : 0 ?>%
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
                                        <strong class="text-danger">₨<?= number_format($customer['total_overdue_amount'] ?? 0, 0) ?></strong>
                                    </td>
                                    <td><?= number_format($customer['avg_days_overdue'] ?? 0, 1) ?> days</td>
                                    <td>
                                        <span class="badge bg-<?= ($customer['max_days_overdue'] ?? 0) > 90 ? 'danger' : (($customer['max_days_overdue'] ?? 0) > 30 ? 'warning' : 'secondary') ?>">
                                            <?= $customer['max_days_overdue'] ?? 0 ?> days
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
// Recovery data for charts
const recoveryData = <?= json_encode($recovery_data) ?>;
const chartLabels = recoveryData.map(item => item.overdue_range);

// 1. Overdue Amount by Period Chart
const overdueAmountCtx = document.getElementById('overdueAmountChart').getContext('2d');
new Chart(overdueAmountCtx, {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Overdue Amount (₨)',
            data: recoveryData.map(item => parseFloat(item.total_remaining)),
            backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#17a2b8', '#6f42c1', '#e83e8c'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Amount (₨)'
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// 2. Recovery Rate by Period Chart
const recoveryRateCtx = document.getElementById('recoveryRateChart').getContext('2d');
new Chart(recoveryRateCtx, {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Recovery Rate (%)',
            data: recoveryData.map(item => parseFloat(item.recovery_rate)),
            backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545', '#17a2b8', '#6f42c1', '#e83e8c'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Recovery Rate (%)'
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// 3. Payment Status Overview Chart
const totalAmount = recoveryData.reduce((sum, item) => sum + parseFloat(item.total_amount), 0);
const totalPaid = recoveryData.reduce((sum, item) => sum + parseFloat(item.total_paid), 0);
const totalRemaining = recoveryData.reduce((sum, item) => sum + parseFloat(item.total_remaining), 0);

const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
new Chart(paymentStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Paid Amount', 'Remaining Amount'],
        datasets: [{
            data: [totalPaid, totalRemaining],
            backgroundColor: ['#28a745', '#dc3545'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = context.parsed;
                        const percentage = ((value / (totalPaid + totalRemaining)) * 100).toFixed(1);
                        return context.label + ': ₨' + value.toLocaleString() + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

function exportToExcel() {
    let csv = 'Overdue Analysis Report\n\n';
    csv += 'Period: Current\n\n';

    csv += 'Summary:\n';
    csv += 'Total Overdue Amount: ₨<?= number_format($total_overdue_amount, 0) ?>\n';
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