<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

// Get user info from users table
$user_query = "SELECT u.*, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Profile is read-only, no updates allowed
$message = '';
$message_type = '';

// Get user statistics based on role
$stats = [];
if ($user['role_name'] === 'customer') {
    // Customer statistics - simplified for now
    $stats = [
        'total_purchases' => 0,
        'total_amount' => 0,
        'total_paid' => 0,
        'overdue_count' => 0
    ];
} else {
    // Admin/Employee statistics
    try {
        $stats_query = "
            SELECT
                (SELECT COUNT(*) FROM customers) as total_customers,
                (SELECT COUNT(*) FROM sales) as total_sales,
                (SELECT COUNT(*) FROM products) as total_products,
                (SELECT COUNT(*) FROM installments WHERE status = 'unpaid' AND due_date < CURDATE()) as overdue_installments
        ";
        $stats_result = $conn->query($stats_query);
        $stats = $stats_result->fetch_assoc() ?: [
            'total_customers' => 0,
            'total_sales' => 0,
            'total_products' => 0,
            'overdue_installments' => 0
        ];
    } catch (Exception $e) {
        $stats = [
            'total_customers' => 0,
            'total_sales' => 0,
            'total_products' => 0,
            'overdue_installments' => 0
        ];
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0"><i class="bi bi-person me-2"></i>My Profile</h2>
                    <small class="text-muted">Manage your account information and preferences</small>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/views/change_password.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-key me-2"></i>Change Password
                    </a>
                    <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Information -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                    <div class="form-text">Username cannot be changed</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <input type="text" class="form-control" id="role" value="<?= htmlspecialchars($user['role_name']) ?>" readonly>
                                    <div class="form-text">Role is assigned by administrator</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="user_id" class="form-label">User ID</label>
                                    <input type="text" class="form-control" id="user_id" value="<?= htmlspecialchars($user['id']) ?>" readonly>
                                    <div class="form-text">Unique identifier</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Account Status</label>
                                    <input type="text" class="form-control" id="status" value="<?= $user['is_active'] ? 'Active' : 'Inactive' ?>" readonly>
                                    <div class="form-text">Current account status</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="created_at" class="form-label">Account Created</label>
                                <input type="text" class="form-control" id="created_at" value="<?= date('F j, Y \a\t g:i A', strtotime($user['created_at'])) ?>" readonly>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="<?= BASE_URL ?>/views/change_password.php" class="btn btn-primary">
                                    <i class="bi bi-key me-2"></i>Change Password
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Statistics -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Account Statistics</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($user['role_name'] === 'customer'): ?>
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <div class="h4 text-primary mb-1"><?= number_format($stats['total_purchases'] ?? 0) ?></div>
                                            <small class="text-muted">Total Purchases</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <div class="h4 text-success mb-1">₨<?= number_format($stats['total_amount'] ?? 0) ?></div>
                                            <small class="text-muted">Total Amount</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <div class="h4 text-info mb-1">₨<?= number_format($stats['total_paid'] ?? 0) ?></div>
                                            <small class="text-muted">Total Paid</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <div class="h4 text-warning mb-1"><?= number_format($stats['overdue_count'] ?? 0) ?></div>
                                            <small class="text-muted">Overdue</small>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <div class="h4 text-primary mb-1"><?= number_format($stats['total_customers'] ?? 0) ?></div>
                                            <small class="text-muted">Customers</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <div class="h4 text-success mb-1"><?= number_format($stats['total_sales'] ?? 0) ?></div>
                                            <small class="text-muted">Sales</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <div class="h4 text-info mb-1"><?= number_format($stats['total_products'] ?? 0) ?></div>
                                            <small class="text-muted">Products</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <div class="h4 text-danger mb-1"><?= number_format($stats['overdue_installments'] ?? 0) ?></div>
                                            <small class="text-muted">Overdue</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Account Status -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Account Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-circle-fill text-success me-2"></i>
                                <span>Account Active</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-circle-fill text-success me-2"></i>
                                <span>Email Verified</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-circle-fill text-success me-2"></i>
                                <span>Password Set</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?= BASE_URL ?>/views/change_password.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-key me-2"></i>Change Password
                                </a>
                                <a href="<?= BASE_URL ?>/views/user_settings.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-gear me-2"></i>Settings
                                </a>
                                <a href="mailto:support@example.com" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-envelope me-2"></i>Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity (for admin/employee) -->
            <?php if ($user['role_name'] !== 'customer'): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <div class="h4 text-primary mb-1">
                                            <?php
                                            $today_sales = $conn->query("SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
                                            echo $today_sales;
                                            ?>
                                        </div>
                                        <small class="text-muted">Today's Sales</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <div class="h4 text-success mb-1">
                                            <?php
                                            $today_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
                                            echo '₨' . number_format($today_revenue);
                                            ?>
                                        </div>
                                        <small class="text-muted">Today's Revenue</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <div class="h4 text-warning mb-1">
                                            <?php
                                            $pending_payments = $conn->query("SELECT COUNT(*) as count FROM installments WHERE status = 'unpaid' AND due_date < CURDATE()")->fetch_assoc()['count'];
                                            echo $pending_payments;
                                            ?>
                                        </div>
                                        <small class="text-muted">Pending Payments</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <div class="h4 text-info mb-1">
                                            <?php
                                            $new_customers = $conn->query("SELECT COUNT(*) as count FROM customers WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
                                            echo $new_customers;
                                            ?>
                                        </div>
                                        <small class="text-muted">New Customers</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>