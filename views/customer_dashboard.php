<?php
require_once '../config/auth.php';

// Ensure only customers can access this page
if (!$auth->isLoggedIn()) {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

require_once '../config/db.php';

$customer_id = $_SESSION['customer_id'];

// Get customer info
$customer_query = "SELECT * FROM customers WHERE id = ?";
$stmt = $conn->prepare($customer_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

// Get customer's sales with detailed information
$sales_query = "
    SELECT s.*, p.name as product_name, p.model,
           COUNT(i.id) as total_installments,
           SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END) as paid_installments,
           SUM(CASE WHEN i.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_installments,
           SUM(CASE WHEN i.status = 'partial' THEN 1 ELSE 0 END) as partial_installments,
           SUM(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() AND DAY(CURDATE()) >= 10 THEN 1 ELSE 0 END) as overdue_installments,
           COALESCE(SUM(i.paid_amount), 0) as total_paid,
           COALESCE(SUM(CASE WHEN i.status IN ('unpaid', 'partial') THEN i.amount - COALESCE(i.paid_amount, 0) ELSE 0 END), 0) as remaining_amount,
           COALESCE(SUM(CASE WHEN i.status IN ('unpaid', 'partial') THEN i.amount - COALESCE(i.paid_amount, 0) ELSE 0 END), 0) as total_remaining
    FROM sales s
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN installments i ON s.id = i.sale_id
    WHERE s.customer_id = ?
    GROUP BY s.id
    ORDER BY s.sale_date DESC
";
$stmt = $conn->prepare($sales_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get customer's rents with detailed information
$rents_query = "
    SELECT r.*, c.name as customer_name,
            COUNT(rp.id) as total_payments,
            SUM(CASE WHEN rp.status = 'paid' THEN 1 ELSE 0 END) as paid_payments,
            COALESCE(SUM(rp.paid_amount), 0) as total_paid,
            COALESCE(SUM(rp.amount), 0) as total_due,
            COALESCE(SUM(rp.amount), 0) - COALESCE(SUM(rp.paid_amount), 0) as remaining_amount
    FROM rents r
    LEFT JOIN customers c ON r.customer_id = c.id
    LEFT JOIN rent_payments rp ON r.id = rp.rent_id
    WHERE r.customer_id = ?
    GROUP BY r.id
    ORDER BY r.start_date DESC
";
$stmt = $conn->prepare($rents_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$rents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all installments for the customer
$all_installments_query = "
    SELECT i.*, s.sale_date, s.id as sale_id, p.name as product_name, p.model,
           DATEDIFF(i.due_date, CURDATE()) as days_until_due
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN products p ON s.product_id = p.id
    WHERE s.customer_id = ?
    ORDER BY i.due_date ASC
";
$stmt = $conn->prepare($all_installments_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$all_installments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all rent payments for the customer
$all_rent_payments_query = "
    SELECT rp.*, r.start_date, r.end_date, r.rent_type, r.daily_rent, r.total_rent,
           DATEDIFF(rp.rent_date, CURDATE()) as days_until_due
    FROM rent_payments rp
    JOIN rents r ON rp.rent_id = r.id
    WHERE r.customer_id = ?
    ORDER BY rp.rent_date ASC
";
$stmt = $conn->prepare($all_rent_payments_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$all_rent_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate comprehensive summary stats
$total_sales = count($sales);
$total_sales_paid = array_sum(array_column($sales, 'total_paid'));
$total_sales_remaining = array_sum(array_column($sales, 'total_remaining'));

$total_rents = count($rents);
$total_rents_paid = array_sum(array_column($rents, 'total_paid'));
$total_rents_remaining = array_sum(array_column($rents, 'remaining_amount'));

// Total Due = Total Paid + Remaining Balance (represents original total amounts)
$grand_total_paid = $total_sales_paid + $total_rents_paid;
$grand_total_remaining = $total_sales_remaining + $total_rents_remaining;
$grand_total_due = $grand_total_paid + $grand_total_remaining;

// Count overdue items
$overdue_installments = array_filter($all_installments, function($i) {
    return $i['status'] != 'paid' && strtotime($i['due_date']) < time();
});
$overdue_rent_payments = array_filter($all_rent_payments, function($rp) {
    return $rp['status'] != 'paid' && strtotime($rp['rent_date']) < time();
});
$total_overdue = count($overdue_installments) + count($overdue_rent_payments);

// Debug logging for dashboard calculations
error_log("Customer Dashboard - Total Sales: " . $total_sales);
error_log("Customer Dashboard - Total Sales Paid: " . $total_sales_paid);
error_log("Customer Dashboard - Total Sales Remaining: " . $total_sales_remaining);
error_log("Customer Dashboard - Total Rents: " . $total_rents);
error_log("Customer Dashboard - Total Rents Paid: " . $total_rents_paid);
error_log("Customer Dashboard - Total Rents Remaining: " . $total_rents_remaining);
error_log("Customer Dashboard - Grand Total Paid: " . $grand_total_paid);
error_log("Customer Dashboard - Grand Total Remaining: " . $grand_total_remaining);
error_log("Customer Dashboard - Grand Total Due: " . $grand_total_due);
error_log("Customer Dashboard - Total Overdue: " . $total_overdue);

// Upcoming payments (next 30 days)
$upcoming_payments = array_filter($all_installments, function($i) {
    return $i['status'] != 'paid' && $i['days_until_due'] >= 0 && $i['days_until_due'] <= 30;
});
$upcoming_rent_payments_filtered = array_filter($all_rent_payments, function($rp) {
    return $rp['status'] != 'paid' && $rp['days_until_due'] >= 0 && $rp['days_until_due'] <= 30;
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Financial Dashboard - <?= htmlspecialchars($customer['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: var(--primary-gradient) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .dashboard-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .stat-card {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .stat-card.success { background: var(--success-gradient); }
        .stat-card.warning { background: var(--warning-gradient); }
        .stat-card.danger { background: var(--danger-gradient); }
        .stat-card.info { background: var(--info-gradient); }

        .section-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            margin: 0;
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .table thead th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
            transform: scale(1.01);
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-paid { background: linear-gradient(45deg, #00d2d3, #54a0ff); color: white; }
        .status-unpaid { background: linear-gradient(45deg, #ff9a9e, #fecfef); color: #721c24; }
        .status-partial { background: linear-gradient(45deg, #f093fb, #f5576c); color: white; }
        .status-overdue { background: linear-gradient(45deg, #ff6b6b, #ee5a24); color: white; }

        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: #e9ecef;
        }

        .progress-bar {
            border-radius: 10px;
        }

        .customer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .tab-content {
            padding: 2rem 0;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 2rem;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }

        .summary-card h3 {
            color: #667eea;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .summary-card p {
            color: #6c757d;
            margin: 0;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-chart-line me-2"></i>
                <span class="fw-bold">My Financial Dashboard</span>
            </a>
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <div class="d-flex align-items-center me-3">
                    <div class="bg-white rounded-circle p-2 me-2">
                        <i class="fas fa-user text-primary"></i>
                    </div>
                    <span class="text-white fw-medium"><?= htmlspecialchars($customer['name']) ?></span>
                </div>
                <a href="../actions/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Customer Profile Section -->
        <div class="dashboard-card">
            <div class="row align-items-center p-4">
                <div class="col-md-3 text-center">
                    <?php if ($customer['image_path']): ?>
                        <img src="../uploads/customers/<?= htmlspecialchars($customer['image_path']) ?>"
                             class="customer-avatar" alt="Profile Picture">
                    <?php else: ?>
                        <div class="bg-primary customer-avatar d-flex align-items-center justify-content-center">
                            <i class="fas fa-user fa-2x text-white"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h2 class="mb-1 text-primary"><?= htmlspecialchars($customer['name']) ?></h2>
                    <p class="text-muted mb-2"><i class="fas fa-id-card me-2"></i>CNIC: <?= htmlspecialchars($customer['cnic']) ?></p>
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-1"><i class="fas fa-phone me-2 text-success"></i><?= htmlspecialchars($customer['phone']) ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-1"><i class="fas fa-map-marker-alt me-2 text-info"></i><?= htmlspecialchars($customer['address']) ?></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h5 class="text-primary">Guarantors</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Guarantor 1:</strong> <?= htmlspecialchars($customer['guarantor_1']) ?></p>
                                <?php if ($customer['guarantor_1_phone']): ?>
                                    <p class="mb-1"><i class="fas fa-phone me-2 text-success"></i><?= htmlspecialchars($customer['guarantor_1_phone']) ?></p>
                                <?php endif; ?>
                                <?php if ($customer['guarantor_1_address']): ?>
                                    <p class="mb-1"><i class="fas fa-map-marker-alt me-2 text-info"></i><?= htmlspecialchars($customer['guarantor_1_address']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Guarantor 2:</strong> <?= htmlspecialchars($customer['guarantor_2']) ?></p>
                                <?php if ($customer['guarantor_2_phone']): ?>
                                    <p class="mb-1"><i class="fas fa-phone me-2 text-success"></i><?= htmlspecialchars($customer['guarantor_2_phone']) ?></p>
                                <?php endif; ?>
                                <?php if ($customer['guarantor_2_address']): ?>
                                    <p class="mb-1"><i class="fas fa-map-marker-alt me-2 text-info"></i><?= htmlspecialchars($customer['guarantor_2_address']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="summary-card">
                        <h3 class="text-primary">₨<?= number_format($grand_total_remaining) ?></h3>
                        <p>Total Outstanding</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="summary-grid">
            <div class="summary-card">
                <h3 class="text-success">₨<?= number_format($grand_total_paid) ?></h3>
                <p>Total Paid</p>
            </div>
            <div class="summary-card">
                <h3 class="text-warning">₨<?= number_format($grand_total_remaining) ?></h3>
                <p>Remaining Balance</p>
            </div>
            <div class="summary-card">
                <h3 class="text-info">₨<?= number_format($grand_total_due) ?></h3>
                <p>Total Due</p>
            </div>
            <div class="summary-card">
                <h3 class="text-danger"><?= $total_overdue ?></h3>
                <p>Overdue Items</p>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="dashboard-card">
            <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab">
                        <i class="fas fa-shopping-cart me-2"></i>Sales & Installments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="rents-tab" data-bs-toggle="tab" data-bs-target="#rents" type="button" role="tab">
                        <i class="fas fa-home me-2"></i>Rentals & Payments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                        <i class="fas fa-calendar-check me-2"></i>Payment Schedule
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="dashboardTabsContent">
                <!-- Sales & Installments Tab -->
                <div class="tab-pane fade show active" id="sales" role="tabpanel">
                    <div class="p-4">
                        <h4 class="mb-4 text-primary"><i class="fas fa-shopping-bag me-2"></i>My Sales & Installments</h4>

                        <?php if (empty($sales)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No Sales Found</h4>
                                <p class="text-muted">You haven't made any purchases yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-box me-1"></i>Product</th>
                                            <th><i class="fas fa-calendar me-1"></i>Date</th>
                                            <th><i class="fas fa-money-bill me-1"></i>Total Amount</th>
                                            <th><i class="fas fa-check-circle me-1"></i>Paid</th>
                                            <th><i class="fas fa-clock me-1"></i>Remaining</th>
                                            <th><i class="fas fa-tasks me-1"></i>Progress</th>
                                            <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                            <th><i class="fas fa-eye me-1"></i>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales as $sale):
                                            $progress = $sale['total_amount'] > 0 ? ($sale['total_paid'] / $sale['total_amount']) * 100 : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-light rounded p-2 me-3">
                                                        <i class="fas fa-box text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <strong class="text-primary"><?= htmlspecialchars($sale['product_name']) ?></strong>
                                                        <?php if ($sale['model']): ?>
                                                            <small class="d-block text-muted">Model: <?= htmlspecialchars($sale['model']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= date('M d, Y', strtotime($sale['sale_date'])) ?></strong>
                                            </td>
                                            <td>
                                                <strong class="text-primary">₨<?= number_format($sale['total_amount']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="text-success">₨<?= number_format($sale['total_paid']) ?></span>
                                            </td>
                                            <td>
                                                <span class="text-warning">₨<?= number_format($sale['total_remaining']) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-<?= $progress >= 100 ? 'success' : ($progress >= 50 ? 'warning' : 'danger') ?>"
                                                             style="width: <?= $progress ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?= number_format($progress, 1) ?>%</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $sale['total_remaining'] <= 0 ? 'paid' : 'partial' ?>">
                                                    <?= $sale['total_remaining'] <= 0 ? 'Completed' : 'In Progress' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button onclick="viewInstallments(<?= $sale['id'] ?>)"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rentals & Payments Tab -->
                <div class="tab-pane fade" id="rents" role="tabpanel">
                    <div class="p-4">
                        <h4 class="mb-4 text-primary"><i class="fas fa-home me-2"></i>My Rentals & Payments</h4>

                        <?php if (empty($rents)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-home fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No Rentals Found</h4>
                                <p class="text-muted">You haven't rented any items yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-box me-1"></i>Product</th>
                                            <th><i class="fas fa-calendar me-1"></i>Period</th>
                                            <th><i class="fas fa-money-bill me-1"></i>Total Due</th>
                                            <th><i class="fas fa-check-circle me-1"></i>Paid</th>
                                            <th><i class="fas fa-clock me-1"></i>Remaining</th>
                                            <th><i class="fas fa-tasks me-1"></i>Progress</th>
                                            <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                            <th><i class="fas fa-eye me-1"></i>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rents as $rent):
                                            $progress = $rent['total_due'] > 0 ? ($rent['total_paid'] / $rent['total_due']) * 100 : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-light rounded p-2 me-3">
                                                        <i class="fas fa-home text-success"></i>
                                                    </div>
                                                    <div>
                                                        <strong class="text-success">Rental Service</strong>
                                                        <small class="d-block text-muted">
                                                            <?= ucfirst($rent['rent_type']) ?> Rent
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= date('M d, Y', strtotime($rent['start_date'])) ?></strong>
                                                <small class="d-block text-muted">to</small>
                                                <strong><?= date('M d, Y', strtotime($rent['end_date'])) ?></strong>
                                            </td>
                                            <td>
                                                <strong class="text-primary">₨<?= number_format($rent['total_due']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="text-success">₨<?= number_format($rent['total_paid']) ?></span>
                                            </td>
                                            <td>
                                                <span class="text-warning">₨<?= number_format($rent['remaining_amount']) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-<?= $progress >= 100 ? 'success' : ($progress >= 50 ? 'warning' : 'danger') ?>"
                                                             style="width: <?= $progress ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?= number_format($progress, 1) ?>%</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $rent['remaining_amount'] <= 0 ? 'paid' : 'partial' ?>">
                                                    <?= $rent['remaining_amount'] <= 0 ? 'Completed' : 'In Progress' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button onclick="viewRent(<?= $rent['id'] ?>)"
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Schedule Tab -->
                <div class="tab-pane fade" id="payments" role="tabpanel">
                    <div class="p-4">
                        <h4 class="mb-4 text-primary"><i class="fas fa-calendar-alt me-2"></i>Payment Schedule</h4>

                        <!-- Upcoming Payments Summary -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="stat-card success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small opacity-75">Next 7 Days</div>
                                            <div class="h4 mb-0">
                                                <?= count(array_filter($all_installments, function($i) {
                                                    return $i['status'] != 'paid' && $i['days_until_due'] >= 0 && $i['days_until_due'] <= 7;
                                                })) + count(array_filter($all_rent_payments, function($rp) {
                                                    return $rp['status'] != 'paid' && $rp['days_until_due'] >= 0 && $rp['days_until_due'] <= 7;
                                                })) ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-calendar-week fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card warning">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small opacity-75">Next 30 Days</div>
                                            <div class="h4 mb-0">
                                                <?= count($upcoming_payments) + count($upcoming_rent_payments_filtered) ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-calendar-month fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card danger">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small opacity-75">Overdue</div>
                                            <div class="h4 mb-0"><?= $total_overdue ?></div>
                                        </div>
                                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- All Installments -->
                        <h5 class="mb-3 text-primary"><i class="fas fa-list me-2"></i>All Installment Payments</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Installment #</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Remaining</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_installments as $installment):
                                        $remaining = $installment['amount'] - $installment['paid_amount'];
                                        $status_class = $installment['status'];
                                        if ($installment['days_until_due'] < 0 && $installment['status'] != 'paid') {
                                            $status_class = 'overdue';
                                        }
                                    ?>
                                    <tr class="<?= $installment['days_until_due'] < 0 && $installment['status'] != 'paid' ? 'table-danger' : '' ?>">
                                        <td>
                                            <strong class="text-primary"><?= htmlspecialchars($installment['product_name']) ?></strong>
                                            <small class="d-block text-muted">Sale #<?= $installment['sale_id'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">Installment <?= $installment['installment_number'] ?></span>
                                        </td>
                                        <td>
                                            <strong><?= date('M d, Y', strtotime($installment['due_date'])) ?></strong>
                                            <?php if ($installment['days_until_due'] < 0 && $installment['status'] != 'paid'): ?>
                                                <small class="d-block text-danger">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <?= abs($installment['days_until_due']) ?> days overdue
                                                </small>
                                            <?php elseif ($installment['days_until_due'] >= 0 && $installment['days_until_due'] <= 7 && $installment['status'] != 'paid'): ?>
                                                <small class="d-block text-warning">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Due in <?= $installment['days_until_due'] ?> days
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>₨<?= number_format($installment['amount']) ?></strong></td>
                                        <td><span class="text-success">₨<?= number_format($installment['paid_amount']) ?></span></td>
                                        <td><span class="text-warning">₨<?= number_format($remaining) ?></span></td>
                                        <td>
                                            <span class="status-badge status-<?= $status_class ?>">
                                                <?= $status_class == 'overdue' ? 'Overdue' : ucfirst($installment['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- All Rent Payments -->
                        <h5 class="mb-3 text-success"><i class="fas fa-home me-2"></i>All Rent Payments</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rental Period</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Remaining</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_rent_payments as $payment):
                                        $remaining = $payment['amount'] - $payment['paid_amount'];
                                        $status_class = $payment['status'];
                                        if ($payment['days_until_due'] < 0 && $payment['status'] != 'paid') {
                                            $status_class = 'overdue';
                                        }
                                    ?>
                                    <tr class="<?= $payment['days_until_due'] < 0 && $payment['status'] != 'paid' ? 'table-danger' : '' ?>">
                                        <td>
                                            <strong class="text-success">
                                                <?= date('M d', strtotime($payment['start_date'])) ?> - <?= date('M d, Y', strtotime($payment['end_date'])) ?>
                                            </strong>
                                            <small class="d-block text-muted">
                                                <?= ucfirst($payment['rent_type']) ?> Rent
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?= date('M d, Y', strtotime($payment['rent_date'])) ?></strong>
                                            <?php if ($payment['days_until_due'] < 0 && $payment['status'] != 'paid'): ?>
                                                <small class="d-block text-danger">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <?= abs($payment['days_until_due']) ?> days overdue
                                                </small>
                                            <?php elseif ($payment['days_until_due'] >= 0 && $payment['days_until_due'] <= 7 && $payment['status'] != 'paid'): ?>
                                                <small class="d-block text-warning">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Due in <?= $payment['days_until_due'] ?> days
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>₨<?= number_format($payment['amount']) ?></strong></td>
                                        <td><span class="text-success">₨<?= number_format($payment['paid_amount']) ?></span></td>
                                        <td><span class="text-warning">₨<?= number_format($remaining) ?></span></td>
                                        <td>
                                            <span class="status-badge status-<?= $status_class ?>">
                                                <?= $status_class == 'overdue' ? 'Overdue' : ucfirst($payment['status']) ?>
                                            </span>
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

    <!-- Installments Modal -->
    <div class="modal fade" id="installmentsModal" tabindex="-1" aria-labelledby="installmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title" id="installmentsModalLabel">
                        <i class="fas fa-list me-2"></i>Installment Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="installmentsModalBody">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading installment details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rent Modal -->
    <div class="modal fade" id="rentModal" tabindex="-1" aria-labelledby="rentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--success-gradient); color: white;">
                    <h5 class="modal-title" id="rentModalLabel">
                        <i class="fas fa-home me-2"></i>Rent Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="rentModalBody">
                    <div class="text-center">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading rent details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to view installments
        function viewInstallments(saleId) {
            const modal = new bootstrap.Modal(document.getElementById('installmentsModal'));
            const modalBody = document.getElementById('installmentsModalBody');

            // Show loading state
            modalBody.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading installment details...</p>
                </div>
            `;

            modal.show();

            // Fetch installment data
            fetch(`../actions/get_customer_installments.php?sale_id=${saleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const sale = data.sale;
                        const installments = data.installments;
                        const totals = data.totals;

                        let html = `
                            <!-- Sale Info -->
                            <div class="alert alert-info mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-user me-2"></i>${sale.customer_name}</h6>
                                        <p class="mb-1"><strong>Product:</strong> ${sale.product_name}</p>
                                        ${sale.model ? `<p class="mb-1"><strong>Model:</strong> ${sale.model}</p>` : ''}
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Sale Date:</strong> ${sale.sale_date}</p>
                                        <p class="mb-1"><strong>Monthly Installment:</strong> ₨${parseFloat(sale.monthly_installment).toLocaleString()}</p>
                                        <p class="mb-0"><strong>Total Amount:</strong> ₨${parseFloat(sale.total_amount).toLocaleString()}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Installments Table -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead style="background: var(--primary-gradient); color: white;">
                                        <tr>
                                            <th>#</th>
                                            <th>Due Date</th>
                                            <th>Amount</th>
                                            <th>Paid</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Paid Date</th>
                                            <th>Comment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        installments.forEach((installment, index) => {
                            const remaining = parseFloat(installment.amount) - parseFloat(installment.paid_amount || 0);
                            const statusClass = installment.status === 'paid' ? 'success' :
                                              installment.status === 'partial' ? 'warning' : 'danger';

                            html += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${installment.due_date}</td>
                                    <td>₨${parseFloat(installment.amount).toLocaleString()}</td>
                                    <td>₨${parseFloat(installment.paid_amount || 0).toLocaleString()}</td>
                                    <td>₨${remaining.toLocaleString()}</td>
                                    <td>
                                        <span class="badge bg-${statusClass}">
                                            ${installment.status.charAt(0).toUpperCase() + installment.status.slice(1)}
                                        </span>
                                    </td>
                                    <td>${installment.paid_at || '—'}</td>
                                    <td>${installment.comment || '—'}</td>
                                </tr>
                            `;
                        });

                        // Add totals row
                        html += `
                                <tr class="table-primary">
                                    <td colspan="2"><strong>Totals (All Installments)</strong></td>
                                    <td><strong>₨${parseFloat(totals.total_due).toLocaleString()}</strong></td>
                                    <td><strong>₨${parseFloat(totals.total_paid).toLocaleString()}</strong></td>
                                    <td><strong>₨${(parseFloat(totals.total_due) - parseFloat(totals.total_paid)).toLocaleString()}</strong></td>
                                    <td colspan="3"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                        `;

                        modalBody.innerHTML = html;
                    } else {
                        modalBody.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error loading installment details: ${data.error || 'Unknown error'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading installment details. Please try again.
                        </div>
                    `;
                    console.error('Error:', error);
                });
        }

        // Function to view rent details
        function viewRent(rentId) {
            const modal = new bootstrap.Modal(document.getElementById('rentModal'));
            const modalBody = document.getElementById('rentModalBody');

            // Show loading state
            modalBody.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading rent details...</p>
                </div>
            `;

            modal.show();

            // Fetch rent data
            fetch(`../actions/get_customer_rent.php?rent_id=${rentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const rent = data.rent;
                        const payments = data.payments;
                        const totals = data.totals;

                        let html = `
                            <!-- Rent Info -->
                            <div class="alert alert-success mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-user me-2"></i>${rent.customer_name}</h6>
                                        <p class="mb-1"><strong>Product:</strong> ${rent.product_name}</p>
                                        <p class="mb-1"><strong>Type:</strong> ${rent.rent_type.charAt(0).toUpperCase() + rent.rent_type.slice(1)} Rent</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Start Date:</strong> ${rent.start_date}</p>
                                        <p class="mb-1"><strong>End Date:</strong> ${rent.end_date}</p>
                                        <p class="mb-0"><strong>Duration:</strong> ${rent.days} days</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Payments Table -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead style="background: var(--success-gradient); color: white;">
                                        <tr>
                        `;

                        if (rent.rent_type === 'daily') {
                            html += `
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Daily Rent</th>
                                            <th>Paid</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Paid Date</th>
                                            <th>Comment</th>
                            `;
                        } else {
                            html += `
                                            <th>Date</th>
                                            <th>Total Rent</th>
                                            <th>Paid</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Paid Date</th>
                                            <th>Comment</th>
                            `;
                        }

                        html += `
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        payments.forEach((payment, index) => {
                            const remaining = parseFloat(payment.amount) - parseFloat(payment.paid_amount || 0);
                            const statusClass = payment.status === 'paid' ? 'success' :
                                              payment.status === 'partial' ? 'warning' : 'danger';

                            if (rent.rent_type === 'daily') {
                                html += `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td>${payment.rent_date}</td>
                                        <td>₨${parseFloat(payment.amount).toLocaleString()}</td>
                                        <td>₨${parseFloat(payment.paid_amount || 0).toLocaleString()}</td>
                                        <td>₨${remaining.toLocaleString()}</td>
                                        <td>
                                            <span class="badge bg-${statusClass}">
                                                ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                                            </span>
                                        </td>
                                        <td>${payment.paid_at || '—'}</td>
                                        <td>${payment.comment || '—'}</td>
                                    </tr>
                                `;
                            } else {
                                html += `
                                    <tr>
                                        <td>${payment.rent_date}</td>
                                        <td>₨${parseFloat(payment.amount).toLocaleString()}</td>
                                        <td>₨${parseFloat(payment.paid_amount || 0).toLocaleString()}</td>
                                        <td>₨${remaining.toLocaleString()}</td>
                                        <td>
                                            <span class="badge bg-${statusClass}">
                                                ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                                            </span>
                                        </td>
                                        <td>${payment.paid_at || '—'}</td>
                                        <td>${payment.comment || '—'}</td>
                                    </tr>
                                `;
                            }
                        });

                        // Add totals row
                        if (rent.rent_type === 'daily') {
                            html += `
                                    <tr class="table-success">
                                        <td colspan="2"><strong>Totals (All Days)</strong></td>
                                        <td><strong>₨${parseFloat(totals.total_due).toLocaleString()}</strong></td>
                                        <td><strong>₨${parseFloat(totals.total_paid).toLocaleString()}</strong></td>
                                        <td><strong>₨${(parseFloat(totals.total_due) - parseFloat(totals.total_paid)).toLocaleString()}</strong></td>
                                        <td colspan="3"></td>
                                    </tr>
                            `;
                        } else {
                            html += `
                                    <tr class="table-success">
                                        <td><strong>Totals</strong></td>
                                        <td><strong>₨${parseFloat(totals.total_due).toLocaleString()}</strong></td>
                                        <td><strong>₨${parseFloat(totals.total_paid).toLocaleString()}</strong></td>
                                        <td><strong>₨${(parseFloat(totals.total_due) - parseFloat(totals.total_paid)).toLocaleString()}</strong></td>
                                        <td colspan="3"></td>
                                    </tr>
                            `;
                        }

                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;

                        modalBody.innerHTML = html;
                    } else {
                        modalBody.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error loading rent details: ${data.error || 'Unknown error'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading rent details. Please try again.
                        </div>
                    `;
                    console.error('Error:', error);
                });
        }

        // Auto-refresh data every 30 seconds
        setInterval(function() {
            // You can add auto-refresh logic here if needed
            console.log('Dashboard data refresh check...');
        }, 30000);
    </script>
</body>
</html>