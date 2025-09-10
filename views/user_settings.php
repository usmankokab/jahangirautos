<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

// Get current user settings (we'll add a user_settings table later, for now use defaults)
$user_settings = [
    'theme' => 'light',
    'language' => 'en',
    'notifications' => 'enabled',
    'dashboard_layout' => 'default',
    'items_per_page' => 20,
    'timezone' => 'Asia/Karachi'
];

// Handle settings update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $theme = $_POST['theme'] ?? 'light';
    $language = $_POST['language'] ?? 'en';
    $notifications = $_POST['notifications'] ?? 'enabled';
    $items_per_page = (int)($_POST['items_per_page'] ?? 20);

    // For now, we'll store in session (in production, save to database)
    $_SESSION['user_settings'] = [
        'theme' => $theme,
        'language' => $language,
        'notifications' => $notifications,
        'items_per_page' => $items_per_page
    ];

    $message = 'Settings updated successfully!';
    $message_type = 'success';

    // Update current settings
    $user_settings = array_merge($user_settings, $_SESSION['user_settings']);
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0"><i class="bi bi-gear me-2"></i>User Settings</h2>
                    <small class="text-muted">Customize your account preferences and interface</small>
                </div>
                <a href="<?= BASE_URL ?>/views/profile.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Profile
                </a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- General Settings -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="update_settings" value="1">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="theme" class="form-label">
                                            <i class="bi bi-palette me-2"></i>Theme
                                        </label>
                                        <select class="form-select" id="theme" name="theme">
                                            <option value="light" <?= ($user_settings['theme'] === 'light') ? 'selected' : '' ?>>Light Theme</option>
                                            <option value="dark" <?= ($user_settings['theme'] === 'dark') ? 'selected' : '' ?>>Dark Theme</option>
                                            <option value="auto" <?= ($user_settings['theme'] === 'auto') ? 'selected' : '' ?>>Auto (System)</option>
                                        </select>
                                        <div class="form-text">Choose your preferred color theme</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="language" class="form-label">
                                            <i class="bi bi-translate me-2"></i>Language
                                        </label>
                                        <select class="form-select" id="language" name="language">
                                            <option value="en" <?= ($user_settings['language'] === 'en') ? 'selected' : '' ?>>English</option>
                                            <option value="ur" <?= ($user_settings['language'] === 'ur') ? 'selected' : '' ?>>Urdu</option>
                                        </select>
                                        <div class="form-text">Select your preferred language</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="notifications" class="form-label">
                                            <i class="bi bi-bell me-2"></i>Notifications
                                        </label>
                                        <select class="form-select" id="notifications" name="notifications">
                                            <option value="enabled" <?= ($user_settings['notifications'] === 'enabled') ? 'selected' : '' ?>>Enabled</option>
                                            <option value="disabled" <?= ($user_settings['notifications'] === 'disabled') ? 'selected' : '' ?>>Disabled</option>
                                            <option value="important_only" <?= ($user_settings['notifications'] === 'important_only') ? 'selected' : '' ?>>Important Only</option>
                                        </select>
                                        <div class="form-text">Control notification preferences</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="items_per_page" class="form-label">
                                            <i class="bi bi-list me-2"></i>Items Per Page
                                        </label>
                                        <select class="form-select" id="items_per_page" name="items_per_page">
                                            <option value="10" <?= ($user_settings['items_per_page'] == 10) ? 'selected' : '' ?>>10 items</option>
                                            <option value="20" <?= ($user_settings['items_per_page'] == 20) ? 'selected' : '' ?>>20 items</option>
                                            <option value="50" <?= ($user_settings['items_per_page'] == 50) ? 'selected' : '' ?>>50 items</option>
                                            <option value="100" <?= ($user_settings['items_per_page'] == 100) ? 'selected' : '' ?>>100 items</option>
                                        </select>
                                        <div class="form-text">Number of items to display per page</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i>Save Settings
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetSettings()">
                                        <i class="bi bi-arrow-counterclockwise me-2"></i>Reset to Default
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Privacy Settings -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Privacy & Security</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6>Account Security</h6>
                                    <div class="d-grid gap-2">
                                        <a href="<?= BASE_URL ?>/views/change_password.php" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-key me-2"></i>Change Password
                                        </a>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="enable2FA()">
                                            <i class="bi bi-shield-check me-2"></i>Enable 2FA
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6>Data & Privacy</h6>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-info btn-sm" onclick="exportData()">
                                            <i class="bi bi-download me-2"></i>Export My Data
                                        </button>
                                        <button class="btn btn-outline-warning btn-sm" onclick="privacySettings()">
                                            <i class="bi bi-eye-slash me-2"></i>Privacy Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Summary & Info -->
                <div class="col-lg-4 mb-4">
                    <!-- Current Settings Summary -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Current Settings</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Theme:</strong>
                                <span class="badge bg-secondary ms-2"><?= ucfirst($user_settings['theme']) ?></span>
                            </div>
                            <div class="mb-2">
                                <strong>Language:</strong>
                                <span class="badge bg-info ms-2">
                                    <?= $user_settings['language'] === 'en' ? 'English' : 'Urdu' ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <strong>Notifications:</strong>
                                <span class="badge bg-<?= $user_settings['notifications'] === 'enabled' ? 'success' : 'secondary' ?> ms-2">
                                    <?= ucfirst(str_replace('_', ' ', $user_settings['notifications'])) ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <strong>Items per page:</strong>
                                <span class="badge bg-primary ms-2"><?= $user_settings['items_per_page'] ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Help & Tips -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Tips</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled small mb-0">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Dark theme reduces eye strain
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Fewer items per page loads faster
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Enable notifications for important updates
                                </li>
                                <li class="mb-0">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Settings are saved automatically
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-star me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?= BASE_URL ?>/views/profile.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-person me-2"></i>View Profile
                                </a>
                                <a href="<?= BASE_URL ?>/views/help.php" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-question-circle me-2"></i>Get Help
                                </a>
                                <a href="mailto:support@example.com" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-envelope me-2"></i>Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function resetSettings() {
    if (confirm('Are you sure you want to reset all settings to default?')) {
        document.getElementById('theme').value = 'light';
        document.getElementById('language').value = 'en';
        document.getElementById('notifications').value = 'enabled';
        document.getElementById('items_per_page').value = '20';
    }
}

function enable2FA() {
    alert('Two-Factor Authentication setup will be available in a future update.');
}

function exportData() {
    if (confirm('Export all your personal data? This may take a few moments.')) {
        window.location.href = '<?= BASE_URL ?>/actions/export_user_data.php';
    }
}

function privacySettings() {
    alert('Privacy settings panel will be available in a future update.');
}

// Apply theme immediately when changed
document.getElementById('theme').addEventListener('change', function() {
    const theme = this.value;
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
    } else {
        document.body.classList.remove('dark-theme');
    }
});
</script>

<style>
.dark-theme {
    background-color: #1a1a1a !important;
    color: #ffffff !important;
}

.dark-theme .card {
    background-color: #2d2d2d !important;
    border-color: #404040 !important;
    color: #ffffff !important;
}

.dark-theme .form-control,
.dark-theme .form-select {
    background-color: #3d3d3d !important;
    border-color: #404040 !important;
    color: #ffffff !important;
}

.dark-theme .form-control:focus,
.dark-theme .form-select:focus {
    background-color: #3d3d3d !important;
    border-color: #007bff !important;
    color: #ffffff !important;
}
</style>

<?php include '../includes/footer.php'; ?>