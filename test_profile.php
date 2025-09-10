<?php
include 'config/db.php';
include 'config/auth.php';

$auth->requireLogin();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0"><i class="bi bi-person me-2"></i>Test Profile Functionality</h2>
                    <small class="text-muted">Verify that profile options are working correctly</small>
                </div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Profile Menu Test</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Current Session Data:</strong></p>
                            <ul class="list-unstyled">
                                <li><strong>User ID:</strong> <?= $_SESSION['user_id'] ?? 'N/A' ?></li>
                                <li><strong>Username:</strong> <?= $_SESSION['username'] ?? 'N/A' ?></li>
                                <li><strong>Role:</strong> <?= $_SESSION['role'] ?? 'N/A' ?></li>
                            </ul>

                            <hr>

                            <p><strong>Test Profile Options:</strong></p>
                            <div class="d-grid gap-2">
                                <a href="views/profile.php" class="btn btn-outline-primary">
                                    <i class="bi bi-person me-2"></i>Test Profile Page
                                </a>
                                <a href="views/change_password.php" class="btn btn-outline-warning">
                                    <i class="bi bi-key me-2"></i>Test Change Password
                                </a>
                                <a href="views/user_settings.php" class="btn btn-outline-info">
                                    <i class="bi bi-gear me-2"></i>Test Settings
                                </a>
                                <a href="views/help.php" class="btn btn-outline-success">
                                    <i class="bi bi-question-circle me-2"></i>Test Help
                                </a>
                                <a href="views/about.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-info-circle me-2"></i>Test About
                                </a>
                                <a href="actions/logout.php" class="btn btn-outline-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Test Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-database me-2"></i>Database Test</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Database Connection:</strong></p>
                            <?php
                            if ($conn) {
                                echo '<span class="badge bg-success">Connected</span>';
                            } else {
                                echo '<span class="badge bg-danger">Failed</span>';
                            }
                            ?>

                            <hr>

                            <p><strong>Users Table Test:</strong></p>
                            <?php
                            try {
                                $result = $conn->query("SELECT COUNT(*) as count FROM users");
                                if ($result) {
                                    $count = $result->fetch_assoc()['count'];
                                    echo "<span class=\"badge bg-success\">Found $count users</span>";
                                } else {
                                    echo '<span class="badge bg-danger">Query failed</span>';
                                }
                            } catch (Exception $e) {
                                echo '<span class="badge bg-danger">Error: ' . $e->getMessage() . '</span>';
                            }
                            ?>

                            <hr>

                            <p><strong>Current User Data:</strong></p>
                            <?php
                            try {
                                $stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?");
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $user = $stmt->get_result()->fetch_assoc();

                                if ($user) {
                                    echo '<ul class="list-unstyled small">';
                                    echo '<li><strong>ID:</strong> ' . $user['id'] . '</li>';
                                    echo '<li><strong>Username:</strong> ' . htmlspecialchars($user['username']) . '</li>';
                                    echo '<li><strong>Role:</strong> ' . htmlspecialchars($user['role_name']) . '</li>';
                                    echo '<li><strong>Active:</strong> ' . ($user['is_active'] ? 'Yes' : 'No') . '</li>';
                                    echo '<li><strong>Created:</strong> ' . date('M d, Y', strtotime($user['created_at'])) . '</li>';
                                    echo '</ul>';
                                } else {
                                    echo '<span class="badge bg-warning">User not found</span>';
                                }
                                $stmt->close();
                            } catch (Exception $e) {
                                echo '<span class="badge bg-danger">Error: ' . $e->getMessage() . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Troubleshooting Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Common Issues:</h6>
                                    <ul>
                                        <li><strong>"Column doesn't exist":</strong> Database schema mismatch</li>
                                        <li><strong>"Access denied":</strong> Permission or authentication issue</li>
                                        <li><strong>"Page not found":</strong> Missing files or wrong paths</li>
                                        <li><strong>"Session expired":</strong> User logged out automatically</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Solutions:</h6>
                                    <ul>
                                        <li><strong>Check database:</strong> Run user_tables.sql</li>
                                        <li><strong>Verify login:</strong> Ensure user is authenticated</li>
                                        <li><strong>Clear cache:</strong> Browser and server cache</li>
                                        <li><strong>Check logs:</strong> PHP error logs for details</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>