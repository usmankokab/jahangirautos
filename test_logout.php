<?php
session_start();
include 'config/db.php';
include 'config/auth.php';

$auth->requireLogin();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3>Logout Test</h3>
                </div>
                <div class="card-body">
                    <p><strong>Current Session Status:</strong></p>
                    <ul>
                        <li>Logged In: <?= $auth->isLoggedIn() ? 'Yes' : 'No' ?></li>
                        <li>User ID: <?= $_SESSION['user_id'] ?? 'N/A' ?></li>
                        <li>Username: <?= $_SESSION['username'] ?? 'N/A' ?></li>
                        <li>Role: <?= $_SESSION['role'] ?? 'N/A' ?></li>
                    </ul>

                    <p><strong>Test Logout Links:</strong></p>
                    <div class="mb-3">
                        <a href="actions/logout.php" class="btn btn-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Test Logout
                        </a>
                    </div>

                    <p><strong>Instructions:</strong></p>
                    <ol>
                        <li>Click the "Test Logout" button above</li>
                        <li>You should be redirected to the login page</li>
                        <li>Your session should be completely cleared</li>
                    </ol>

                    <div class="alert alert-info">
                        <strong>Note:</strong> The logout functionality has been implemented and should work from both the header dropdown and this test button.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>