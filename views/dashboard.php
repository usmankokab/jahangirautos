<?php
session_start();
require_once '../config/app.php';
require_once '../config/db.php';
require_once '../includes/permissions.php';

// Check if user is logged in
require_login();

// Get user's role and permissions
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Installment Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h2>Welcome, <?= htmlspecialchars($username) ?></h2>
                <p class="text-muted">Role: <?= ucfirst(str_replace('_', ' ', $role)) ?></p>
            </div>
        </div>

        <?php if ($role !== 'customer'): ?>
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <?php if (check_permission('customers', 'view')): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Customers</h5>
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as count FROM customers");
                        $count = $result->fetch_assoc()['count'];
                        ?>
                        <h2 class="mb-0"><?= number_format($count) ?></h2>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (check_permission('products', 'view')): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Products in Stock</h5>
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock > 0");
                        $count = $result->fetch_assoc()['count'];
                        ?>
                        <h2 class="mb-0"><?= number_format($count) ?></h2>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (check_permission('sales', 'view')): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Active Sales</h5>
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as count FROM sales WHERE status != 'completed'");
                        $count = $result->fetch_assoc()['count'];
                        ?>
                        <h2 class="mb-0"><?= number_format($count) ?></h2>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (check_permission('rents', 'view')): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Active Rentals</h5>
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as count FROM rents WHERE status != 'completed'");
                        $count = $result->fetch_assoc()['count'];
                        ?>
                        <h2 class="mb-0"><?= number_format($count) ?></h2>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($role === 'customer'): ?>
        <!-- Customer Dashboard -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Active Sales</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT s.*, p.name as product_name 
                                        FROM sales s 
                                        JOIN products p ON s.product_id = p.id 
                                        WHERE s.customer_id = ? AND s.status != 'completed' 
                                        ORDER BY s.created_at DESC
                                    ");
                                    $stmt->bind_param("i", $_SESSION['customer_id']);
                                    $stmt->execute();
                                    $sales = $stmt->get_result();
                                    while ($sale = $sales->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sale['product_name']) ?></td>
                                        <td><?= number_format($sale['total_amount'], 2) ?></td>
                                        <td><?= ucfirst($sale['status']) ?></td>
                                        <td>
                                            <a href="view_sale.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Active Rentals</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT r.*, p.name as product_name 
                                        FROM rents r 
                                        JOIN products p ON r.product_id = p.id 
                                        WHERE r.customer_id = ? AND r.status != 'completed' 
                                        ORDER BY r.created_at DESC
                                    ");
                                    $stmt->bind_param("i", $_SESSION['customer_id']);
                                    $stmt->execute();
                                    $rents = $stmt->get_result();
                                    while ($rent = $rents->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($rent['product_name']) ?></td>
                                        <td><?= number_format($rent['total_amount'], 2) ?></td>
                                        <td><?= ucfirst($rent['status']) ?></td>
                                        <td>
                                            <a href="view_rent.php?id=<?= $rent['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
