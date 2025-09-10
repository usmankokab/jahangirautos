<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

// Handle password change
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'All fields are required.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New password and confirmation do not match.';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'New password must be at least 6 characters long.';
        $message_type = 'danger';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);

            if ($update_stmt->execute()) {
                $message = 'Password changed successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to update password. Please try again.';
                $message_type = 'danger';
            }
            $update_stmt->close();
        } else {
            $message = 'Current password is incorrect.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0"><i class="bi bi-key me-2"></i>Change Password</h2>
                    <small class="text-muted">Update your account password</small>
                </div>
                <a href="<?= BASE_URL ?>/views/profile.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Profile
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Password Security</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($message): ?>
                                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                                    <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                                    <?= htmlspecialchars($message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">
                                        <i class="bi bi-lock me-2"></i>Current Password
                                    </label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <div class="form-text">Enter your current password to verify your identity</div>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">
                                        <i class="bi bi-key me-2"></i>New Password
                                    </label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                    <div class="form-text">Minimum 6 characters required</div>
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">
                                        <i class="bi bi-check-circle me-2"></i>Confirm New Password
                                    </label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                    <div class="form-text">Re-enter your new password</div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i>Change Password
                                    </button>
                                    <a href="<?= BASE_URL ?>/views/profile.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Password Requirements</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small">
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Minimum 6 characters</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Use a mix of letters and numbers</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Avoid common passwords</li>
                                <li class="mb-0"><i class="bi bi-check-circle text-success me-2"></i>Change regularly for security</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;

    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value && this.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity('Passwords do not match');
    } else {
        confirmPassword.setCustomValidity('');
    }
});
</script>

<?php include '../includes/footer.php'; ?>