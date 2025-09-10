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

// Get customer's sales and installments
$sales_query = "
    SELECT s.*, p.name as product_name, p.image_path,
           COUNT(i.id) as total_installments,
           SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END) as paid_installments,
           SUM(CASE WHEN i.status = 'unpaid' AND i.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_installments,
           COALESCE(SUM(i.paid_amount), 0) as total_paid,
           (s.total_amount - COALESCE(SUM(i.paid_amount), 0)) as remaining_amount
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

// Get upcoming installments
$upcoming_query = "
    SELECT i.*, s.sale_date, p.name as product_name
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN products p ON s.product_id = p.id
    WHERE s.customer_id = ? AND i.status IN ('unpaid', 'partial')
    ORDER BY i.due_date ASC
    LIMIT 5
";
$stmt = $conn->prepare($upcoming_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$upcoming_installments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate summary stats
$total_purchases = count($sales);
$total_amount = array_sum(array_column($sales, 'total_amount'));
$total_paid = array_sum(array_column($sales, 'total_paid'));
$remaining_amount = $total_amount - $total_paid;
$overdue_count = array_sum(array_column($sales, 'overdue_installments'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - <?= htmlspecialchars($customer['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background: linear-gradient(45deg, #667eea, #764ba2); }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1rem; }
        .stat-card { background: linear-gradient(45deg, #667eea, #764ba2); color: white; }
        .installment-row.overdue { background-color: #fff5f5; border-left: 4px solid #dc3545; }
        .installment-row.due-soon { background-color: #fff8e1; border-left: 4px solid #ffc107; }
        .product-image { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-circle me-2"></i>My Account
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?= htmlspecialchars($customer['name']) ?>
                </span>
                <a href="../actions/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Customer Info & Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <?php if ($customer['image_path']): ?>
                            <img src="../uploads/customers/<?= htmlspecialchars($customer['image_path']) ?>" 
                                 class="rounded-circle mb-3" width="100" height="100" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                 style="width: 100px; height: 100px;">
                                <i class="fas fa-user fa-3x text-white"></i>
                            </div>
                        <?php endif; ?>
                        <h5><?= htmlspecialchars($customer['name']) ?></h5>
                        <p class="text-muted mb-1">CNIC: <?= htmlspecialchars($customer['cnic']) ?></p>
                        <p class="text-muted mb-1">Phone: <?= htmlspecialchars($customer['phone']) ?></p>
                        <p class="text-muted">Address: <?= htmlspecialchars($customer['address']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="small">Total Purchases</div>
                                        <div class="h4 mb-0"><?= $total_purchases ?></div>
                                    </div>
                                    <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="small">Total Paid</div>
                                        <div class="h4 mb-0">₨<?= number_format($total_paid) ?></div>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="small">Remaining Amount</div>
                                        <div class="h4 mb-0">₨<?= number_format($remaining_amount) ?></div>
                                    </div>
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card <?= $overdue_count > 0 ? 'bg-danger' : 'bg-info' ?> text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="small">Overdue Payments</div>
                                        <div class="h4 mb-0"><?= $overdue_count ?></div>
                                    </div>
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Installments -->
        <?php if (!empty($upcoming_installments)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Payments</h5>
            </div>
            <div class="card-body">
                <?php foreach ($upcoming_installments as $installment): 
                    $days_until_due = (strtotime($installment['due_date']) - time()) / (60 * 60 * 24);
                    $row_class = '';
                    if ($days_until_due < 0) $row_class = 'overdue';
                    elseif ($days_until_due <= 3) $row_class = 'due-soon';
                ?>
                <div class="installment-row <?= $row_class ?> p-3 mb-2 rounded">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <strong><?= htmlspecialchars($installment['product_name']) ?></strong>
                            <small class="d-block text-muted">Sale Date: <?= date('M d, Y', strtotime($installment['sale_date'])) ?></small>
                        </div>
                        <div class="col-md-2">
                            <strong>₨<?= number_format($installment['amount']) ?></strong>
                        </div>
                        <div class="col-md-3">
                            Due: <?= date('M d, Y', strtotime($installment['due_date'])) ?>
                            <?php if ($days_until_due < 0): ?>
                                <span class="badge bg-danger ms-2"><?= abs(floor($days_until_due)) ?> days overdue</span>
                            <?php elseif ($days_until_due <= 3): ?>
                                <span class="badge bg-warning ms-2">Due in <?= floor($days_until_due) ?> days</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <span class="badge bg-<?= $installment['status'] === 'paid' ? 'success' : ($installment['status'] === 'partial' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($installment['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Purchase History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>My Purchases</h5>
            </div>
            <div class="card-body">
                <?php if (empty($sales)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No purchases yet</h5>
                        <p class="text-muted">Your purchase history will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Date</th>
                                    <th>Total Amount</th>
                                    <th>Paid</th>
                                    <th>Remaining</th>
                                    <th>Progress</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): 
                                    $progress = $sale['total_amount'] > 0 ? ($sale['total_paid'] / $sale['total_amount']) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($sale['image_path']): ?>
                                                <img src="../uploads/products/<?= htmlspecialchars($sale['image_path']) ?>" 
                                                     class="product-image me-3">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center me-3 product-image">
                                                    <i class="fas fa-box text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($sale['product_name']) ?></strong>
                                                <small class="d-block text-muted">Qty: <?= $sale['quantity'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($sale['sale_date'])) ?></td>
                                    <td>₨<?= number_format($sale['total_amount']) ?></td>
                                    <td>₨<?= number_format($sale['total_paid']) ?></td>
                                    <td>₨<?= number_format($sale['remaining_amount']) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?= $progress >= 100 ? 'success' : ($progress >= 50 ? 'warning' : 'danger') ?>" 
                                                 style="width: <?= $progress ?>%">
                                                <?= number_format($progress, 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="view_installments.php?sale_id=<?= $sale['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>