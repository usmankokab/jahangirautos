<?php
if (!function_exists('check_permission')) {

function check_permission($module, $action = 'view') {
    // Super admin has all permissions
    if ($_SESSION['role'] === 'super_admin') {
        return true;
    }

    // First check session cache for performance
    if (isset($_SESSION['permissions'][$module][$action])) {
        return $_SESSION['permissions'][$module][$action];
    }

    // If not in session, check database directly (fallback for real-time updates)
    global $conn;
    try {
        $stmt = $conn->prepare("
            SELECT up.can_view, up.can_add, up.can_edit, up.can_delete, up.can_paid_amount, up.can_save
            FROM user_permissions up
            INNER JOIN modules m ON up.module_id = m.id
            WHERE up.user_id = ? AND m.module_name = ?
        ");
        $stmt->bind_param("is", $_SESSION['user_id'], $module);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            $permission_value = false;
            switch ($action) {
                case 'view': $permission_value = $result['can_view']; break;
                case 'add': $permission_value = $result['can_add']; break;
                case 'edit': $permission_value = $result['can_edit']; break;
                case 'delete': $permission_value = $result['can_delete']; break;
                case 'paid_amount': $permission_value = $result['can_paid_amount']; break;
                case 'save': $permission_value = $result['can_save']; break;
            }

            // Update session cache for future requests
            if (!isset($_SESSION['permissions'][$module])) {
                $_SESSION['permissions'][$module] = [];
            }
            $_SESSION['permissions'][$module][$action] = $permission_value;

            return $permission_value;
        }
    } catch (Exception $e) {
        error_log("Error checking permission from database: " . $e->getMessage());
    }

    return false;
}

function require_permission($module, $action = 'view') {
    if (!check_permission($module, $action)) {
        $_SESSION['error'] = "You don't have permission to {$action} {$module}";
        header('Location: ' . BASE_URL . '/views/dashboard.php');
        exit;
    }
}

function require_permission_or_lock($module, $action = 'view') {
    if (!check_permission($module, $action)) {
        // Show locked message instead of redirecting
        show_locked_message($module, $action);
        exit;
    }
}

function refresh_user_permissions($user_id = null) {
    global $conn;

    // Use current user if no user_id provided
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'];
    }

    try {
        // Fetch current permissions from database
        $stmt = $conn->prepare("
            SELECT m.module_name, up.*
            FROM user_permissions up
            INNER JOIN modules m ON up.module_id = m.id
            WHERE up.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $permissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Update session permissions
        $_SESSION['permissions'] = [];
        foreach ($permissions as $perm) {
            $_SESSION['permissions'][$perm['module_name']] = [
                'view' => $perm['can_view'],
                'add' => $perm['can_add'],
                'edit' => $perm['can_edit'],
                'delete' => $perm['can_delete'],
                'paid_amount' => $perm['can_paid_amount'],
                'save' => $perm['can_save']
            ];
        }

        return true;
    } catch (Exception $e) {
        error_log("Error refreshing permissions: " . $e->getMessage());
        return false;
    }
}

function clear_permission_cache() {
    // Clear the permission cache to force fresh database checks
    $_SESSION['permissions'] = [];
}

function force_permission_refresh() {
    // Clear cache and refresh from database
    clear_permission_cache();
    return refresh_user_permissions();
}

function check_permission_with_fallback($module, $action = 'view') {
    // Always check database first for critical permission checks
    // This ensures real-time permission updates
    global $conn;

    try {
        $stmt = $conn->prepare("
            SELECT up.can_view, up.can_add, up.can_edit, up.can_delete, up.can_paid_amount, up.can_save
            FROM user_permissions up
            INNER JOIN modules m ON up.module_id = m.id
            WHERE up.user_id = ? AND m.module_name = ?
        ");
        $stmt->bind_param("is", $_SESSION['user_id'], $module);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            $permission_value = false;
            switch ($action) {
                case 'view': $permission_value = $result['can_view']; break;
                case 'add': $permission_value = $result['can_add']; break;
                case 'edit': $permission_value = $result['can_edit']; break;
                case 'delete': $permission_value = $result['can_delete']; break;
                case 'paid_amount': $permission_value = $result['can_paid_amount']; break;
                case 'save': $permission_value = $result['can_save']; break;
            }

            // Update session cache for future requests
            if (!isset($_SESSION['permissions'][$module])) {
                $_SESSION['permissions'][$module] = [];
            }
            $_SESSION['permissions'][$module][$action] = $permission_value;

            return $permission_value;
        }
    } catch (Exception $e) {
        error_log("Error checking permission with fallback: " . $e->getMessage());
    }

    return false;
}

function check_and_refresh_permissions_if_needed() {
    // Check if permissions need to be refreshed (called on page load)
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    try {
        // Check if there have been recent permission changes for this user
        $stmt = $conn->prepare("
            SELECT COUNT(*) as changes_count
            FROM user_permissions
            WHERE user_id = ? AND updated_at > ?
        ");

        // Check for changes in the last 5 minutes
        $five_minutes_ago = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $stmt->bind_param("is", $_SESSION['user_id'], $five_minutes_ago);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result && $result['changes_count'] > 0) {
            // Permissions have been recently changed, refresh the cache
            clear_permission_cache();
            refresh_user_permissions();
            return true;
        }
    } catch (Exception $e) {
        error_log("Error checking for permission changes: " . $e->getMessage());
    }

    return false;
}

function show_locked_message($module, $action = 'view') {
    $module_name = ucwords(str_replace('_', ' ', $module));
    $user_role = isset($_SESSION['role']) ? ucwords(str_replace('_', ' ', $_SESSION['role'])) : 'User';

    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Access Denied - {$module_name}</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link href='" . BASE_URL . "/assets/css/advanced-design.css' rel='stylesheet'>
        <style>
            .locked-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .locked-card {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border: none;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                max-width: 500px;
                text-align: center;
            }
            .lock-icon {
                font-size: 4rem;
                color: #dc3545;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <div class='locked-container'>
            <div class='container'>
                <div class='row justify-content-center'>
                    <div class='col-md-8 col-lg-6'>
                        <div class='locked-card card p-5'>
                            <div class='card-body'>
                                <div class='lock-icon'>
                                    <i class='bi bi-shield-lock-fill'></i>
                                </div>
                                <h2 class='card-title text-danger mb-3'>Access Denied</h2>
                                <h5 class='card-subtitle mb-4 text-muted'>{$module_name} is locked for your account</h5>

                                <div class='alert alert-warning' role='alert'>
                                    <i class='bi bi-exclamation-triangle me-2'></i>
                                    <strong>Permission Required:</strong> You need {$action} permission for {$module_name}
                                </div>

                                <div class='mb-4'>
                                    <p class='text-muted mb-2'>Your current role: <strong>{$user_role}</strong></p>
                                    <p class='text-muted'>Contact your administrator to request access to this module.</p>
                                </div>

                                <div class='d-grid gap-2'>
                                    <button onclick='history.back()' class='btn btn-outline-secondary'>
                                        <i class='bi bi-arrow-left me-2'></i>Go Back
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
    </body>
    </html>
    ";
}


} // End of include guard
