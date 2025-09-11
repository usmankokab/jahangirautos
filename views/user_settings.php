<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

// Get current user info and check if admin
$user_query = "SELECT u.*, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$is_admin = in_array($current_user['role_name'], ['super_admin', 'admin', 'manager']);

// Get current user settings (we'll add a user_settings table later, for now use defaults)
$user_settings = [
    'theme' => 'light',
    'language' => 'en',
    'notifications' => 'enabled',
    'dashboard_layout' => 'default',
    'items_per_page' => 20,
    'timezone' => 'Asia/Karachi'
];

// Get all available modules, excluding duplicates
$modules_query = "SELECT * FROM modules WHERE id NOT IN (14, 19, 12, 22, 15, 17, 21, 18, 16, 20, 13) ORDER BY parent_id, module_name";
$modules_result = $conn->query($modules_query);
$modules = [];
while ($module = $modules_result->fetch_assoc()) {
    $modules[] = $module;
}

// Get all user roles
$roles_query = "SELECT * FROM user_roles ORDER BY role_name";
$roles_result = $conn->query($roles_query);
$roles = [];
while ($role = $roles_result->fetch_assoc()) {
    $roles[] = $role;
}

// Get existing users for management (filtered by current user's role)
$users_query = "SELECT u.*, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE 1=1";

// Filter users based on current user's role
if ($current_user['role_name'] === 'super_admin') {
    // Super admin can see all users except itself
    $users_query .= " AND u.id != " . $_SESSION['user_id'];
} elseif ($current_user['role_name'] === 'admin') {
    // Admin can see managers, employees, but not super_admin
    $users_query .= " AND r.role_name IN ('admin', 'manager', 'employee')";
} elseif ($current_user['role_name'] === 'manager') {
    // Manager can see employees
    $users_query .= " AND r.role_name = 'employee'";
} elseif ($current_user['role_name'] === 'employee') {
    // Employee cannot see any users
    $users_query .= " AND 1=0"; // This will return no results
}

$users_query .= " ORDER BY u.created_at DESC";
$users_result = $conn->query($users_query);
$all_users = [];
while ($user = $users_result->fetch_assoc()) {
    $all_users[] = $user;
}

// Handle settings update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
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

    // Handle user creation
    elseif (isset($_POST['create_user']) && $is_admin) {
        $username = trim($_POST['new_username'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $role_id = (int)($_POST['new_role_id'] ?? 0);

        if (empty($username) || empty($password) || $role_id == 0) {
            $message = 'All fields are required for user creation.';
            $message_type = 'danger';
        } else {
            // Check if username exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows > 0) {
                $message = 'Username already exists.';
                $message_type = 'danger';
            } else {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $create_stmt = $conn->prepare("INSERT INTO users (username, password, role_id, admin_id) VALUES (?, ?, ?, ?)");
                $create_stmt->bind_param("ssii", $username, $hashed_password, $role_id, $_SESSION['user_id']);

                if ($create_stmt->execute()) {
                    $new_user_id = $conn->insert_id;

                    // Set default permissions based on role
                    if ($role_id == 3) { // manager
                        $default_permissions = [
                            ['module_id' => 1, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0], // dashboard
                            ['module_id' => 2, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0], // users
                            ['module_id' => 3, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // customers
                            ['module_id' => 4, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // products
                            ['module_id' => 5, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // sales
                            ['module_id' => 6, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0], // rents
                            ['module_id' => 7, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0], // reports
                        ];
                    } elseif ($role_id == 4) { // employee
                        $default_permissions = [
                            ['module_id' => 1, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0], // dashboard
                            ['module_id' => 3, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0], // customers
                            ['module_id' => 4, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0], // products
                            ['module_id' => 5, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0], // sales
                        ];
                    } else { // admin/super_admin
                        $default_permissions = [
                            ['module_id' => 1, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // dashboard
                            ['module_id' => 2, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // users
                            ['module_id' => 3, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // customers
                            ['module_id' => 4, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // products
                            ['module_id' => 5, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // sales
                            ['module_id' => 6, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1], // rents
                            ['module_id' => 7, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0], // reports
                        ];
                    }

                    // Insert default permissions
                    foreach ($default_permissions as $perm) {
                        $perm_stmt = $conn->prepare("INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $perm_stmt->bind_param("iiiiiii", $new_user_id, $perm['module_id'], $perm['can_view'], $perm['can_add'], $perm['can_edit'], $perm['can_delete'], $_SESSION['user_id']);
                        $perm_stmt->execute();
                    }

                    $message = 'User created successfully!';
                    $message_type = 'success';

                    // Refresh users list
                    $users_result = $conn->query($users_query);
                    $all_users = [];
                    while ($user = $users_result->fetch_assoc()) {
                        $all_users[] = $user;
                    }
                } else {
                    $message = 'Failed to create user. Please try again.';
                    $message_type = 'danger';
                }
            }
        }
    }

    // Handle permission updates
    elseif (isset($_POST['update_permissions']) && $is_admin) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $permissions = $_POST['permissions'] ?? [];

        if ($user_id > 0) {
            // Check if current user can manage the target user's permissions
            $target_user_query = "SELECT r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
            $target_stmt = $conn->prepare($target_user_query);
            $target_stmt->bind_param("i", $user_id);
            $target_stmt->execute();
            $target_user = $target_stmt->get_result()->fetch_assoc();

            if ($target_user) {
                $can_manage = false;
                if ($current_user['role_name'] === 'super_admin') {
                    $can_manage = true; // Super admin can manage all
                } elseif ($current_user['role_name'] === 'admin') {
                    $can_manage = in_array($target_user['role_name'], ['manager', 'employee']); // Admin can manage managers and employees
                } elseif ($current_user['role_name'] === 'manager') {
                    $can_manage = $target_user['role_name'] === 'employee'; // Manager can only manage employees
                }

                if ($can_manage) {
                    // Delete existing permissions
                    $delete_stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                    $delete_stmt->bind_param("i", $user_id);
                    $delete_stmt->execute();

                    // Insert new permissions
                    foreach ($permissions as $module_id => $perms) {
                        $can_view = isset($perms['view']) ? 1 : 0;
                        $can_add = isset($perms['add']) ? 1 : 0;
                        $can_edit = isset($perms['edit']) ? 1 : 0;
                        $can_delete = isset($perms['delete']) ? 1 : 0;

                        $perm_stmt = $conn->prepare("INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $perm_stmt->bind_param("iiiiiii", $user_id, $module_id, $can_view, $can_add, $can_edit, $can_delete, $_SESSION['user_id']);
                        $perm_stmt->execute();
                    }

                    $message = 'Permissions updated successfully! Changes take effect immediately.';
                    $message_type = 'success';

                    // Refresh permissions in session for immediate effect
                    require_once '../includes/permissions.php';
                    refresh_user_permissions($user_id);
                } else {
                    $message = 'You do not have permission to manage this user.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'User not found.';
                $message_type = 'danger';
            }
        }
    }

    // Handle password change
    elseif (isset($_POST['change_password']) && $is_admin) {
        $user_id = (int)($_POST['password_user_id'] ?? 0);
        $new_password = $_POST['new_user_password'] ?? '';
        $confirm_password = $_POST['confirm_new_password'] ?? '';

        if ($user_id > 0) {
            // Check if current user can manage the target user's password
            $target_user_query = "SELECT r.role_name, u.username FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
            $target_stmt = $conn->prepare($target_user_query);
            $target_stmt->bind_param("i", $user_id);
            $target_stmt->execute();
            $target_user = $target_stmt->get_result()->fetch_assoc();

            if ($target_user) {
                $can_manage = false;
                if ($current_user['role_name'] === 'super_admin') {
                    $can_manage = true; // Super admin can manage all
                } elseif ($current_user['role_name'] === 'admin') {
                    $can_manage = in_array($target_user['role_name'], ['manager', 'employee']); // Admin can manage managers and employees
                } elseif ($current_user['role_name'] === 'manager') {
                    $can_manage = $target_user['role_name'] === 'employee'; // Manager can only manage employees
                }

                if ($can_manage) {
                    // Validate password
                    if (empty($new_password)) {
                        $message = 'Password cannot be empty.';
                        $message_type = 'danger';
                    } elseif (strlen($new_password) < 6) {
                        $message = 'Password must be at least 6 characters long.';
                        $message_type = 'danger';
                    } elseif ($new_password !== $confirm_password) {
                        $message = 'Passwords do not match.';
                        $message_type = 'danger';
                    } else {
                        // Hash the new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                        // Update password
                        $password_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $password_stmt->bind_param("si", $hashed_password, $user_id);

                        if ($password_stmt->execute()) {
                            $message = "Password changed successfully for user '{$target_user['username']}'!";
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to change password. Please try again.';
                            $message_type = 'danger';
                        }
                    }
                } else {
                    $message = 'You do not have permission to change this user\'s password.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'User not found.';
                $message_type = 'danger';
            }
        }
    }

    // Handle user status toggle
    elseif (isset($_POST['toggle_user_status']) && $is_admin) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_status = (int)($_POST['new_status'] ?? 0);

        if ($user_id > 0) {
            // Check if current user can manage the target user's status
            $target_user_query = "SELECT r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
            $target_stmt = $conn->prepare($target_user_query);
            $target_stmt->bind_param("i", $user_id);
            $target_stmt->execute();
            $target_user = $target_stmt->get_result()->fetch_assoc();

            if ($target_user) {
                $can_manage = false;
                if ($current_user['role_name'] === 'super_admin') {
                    $can_manage = true; // Super admin can manage all
                } elseif ($current_user['role_name'] === 'admin') {
                    $can_manage = in_array($target_user['role_name'], ['manager', 'employee']); // Admin can manage managers and employees
                } elseif ($current_user['role_name'] === 'manager') {
                    $can_manage = $target_user['role_name'] === 'employee'; // Manager can only manage employees
                }

                if ($can_manage) {
                    // Prevent deactivating the current user themselves
                    if ($user_id == $_SESSION['user_id']) {
                        $message = 'You cannot deactivate your own account.';
                        $message_type = 'warning';
                    } else {
                        // Update user status
                        $status_stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                        $status_stmt->bind_param("ii", $new_status, $user_id);

                        if ($status_stmt->execute()) {
                            $action = $new_status ? 'activated' : 'deactivated';
                            $message = "User has been {$action} successfully!";
                            $message_type = 'success';

                            // Refresh users list
                            $users_result = $conn->query($users_query);
                            $all_users = [];
                            while ($user = $users_result->fetch_assoc()) {
                                $all_users[] = $user;
                            }
                        } else {
                            $message = 'Failed to update user status. Please try again.';
                            $message_type = 'danger';
                        }
                    }
                } else {
                    $message = 'You do not have permission to manage this user.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'User not found.';
                $message_type = 'danger';
            }
        }
    }

    // Handle user deletion (Super Admin only)
    elseif (isset($_POST['delete_user']) && $current_user['role_name'] === 'super_admin') {
        $user_id = (int)($_POST['delete_user_id'] ?? 0);

        if ($user_id > 0) {
            // Prevent self-deletion
            if ($user_id == $_SESSION['user_id']) {
                $message = 'You cannot delete your own account.';
                $message_type = 'danger';
            } else {
                // Get user details for confirmation
                $target_user_query = "SELECT u.username, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
                $target_stmt = $conn->prepare($target_user_query);
                $target_stmt->bind_param("i", $user_id);
                $target_stmt->execute();
                $target_user = $target_stmt->get_result()->fetch_assoc();

                if ($target_user) {
                    try {
                        // Start transaction for safe deletion
                        $conn->begin_transaction();

                        // Delete user permissions first (foreign key constraint)
                        $delete_perms_stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                        $delete_perms_stmt->bind_param("i", $user_id);
                        $delete_perms_stmt->execute();

                        // Delete user (this will cascade to related tables if set up)
                        $delete_user_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $delete_user_stmt->bind_param("i", $user_id);
                        $delete_user_stmt->execute();

                        // Commit transaction
                        $conn->commit();

                        $message = "User '{$target_user['username']}' has been permanently deleted!";
                        $message_type = 'success';

                        // Refresh users list
                        $users_result = $conn->query($users_query);
                        $all_users = [];
                        while ($user = $users_result->fetch_assoc()) {
                            $all_users[] = $user;
                        }

                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $message = 'Failed to delete user. Please try again. Error: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'User not found.';
                    $message_type = 'danger';
                }
            }
        }
    }
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
                <!-- User Management (Admin Only) -->
                <?php if ($is_admin && check_permission('users', 'view')): ?>
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>User Management</h5>
                        </div>
                        <div class="card-body">
                            <!-- Create New User -->
                            <div class="row mb-4">
                                <div class="col-lg-6">
                                    <h6 class="mb-3"><i class="bi bi-person-plus me-2"></i>Create New User</h6>
                                    <form method="POST" action="">
                                        <input type="hidden" name="create_user" value="1">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="new_username" class="form-label">Username *</label>
                                                <input type="text" class="form-control" id="new_username" name="new_username" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="new_password" class="form-label">Password *</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="new_role_id" class="form-label">Role *</label>
                                                <select class="form-select" id="new_role_id" name="new_role_id" required>
                                                    <option value="">Select Role</option>
                                                    <?php
                                                    $allowed_roles = [];
                                                    if ($current_user['role_name'] === 'super_admin') {
                                                        $allowed_roles = ['admin'];
                                                    } elseif ($current_user['role_name'] === 'admin') {
                                                        $allowed_roles = ['manager', 'employee'];
                                                    } elseif ($current_user['role_name'] === 'manager') {
                                                        $allowed_roles = ['employee'];
                                                    }

                                                    foreach ($roles as $role):
                                                        if (in_array($role['role_name'], $allowed_roles)):
                                                    ?>
                                                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                                    <?php
                                                        endif;
                                                    endforeach;
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="bi bi-person-plus me-2"></i>Create User
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Existing Users -->
                                <div class="col-lg-6">
                                    <h6 class="mb-3"><i class="bi bi-list-ul me-2"></i>Existing Users</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Username</th>
                                                    <th>Role</th>
                                                    <th>Status</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_users as $user):
                                                    // Determine if current user can manage this user's permissions
                                                    $can_manage = false;
                                                    if ($current_user['role_name'] === 'super_admin') {
                                                        $can_manage = true; // Super admin can manage all
                                                    } elseif ($current_user['role_name'] === 'admin') {
                                                        $can_manage = in_array($user['role_name'], ['manager', 'employee']); // Admin can manage managers and employees
                                                    } elseif ($current_user['role_name'] === 'manager') {
                                                        $can_manage = $user['role_name'] === 'employee'; // Manager can only manage employees
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $user['role_name'] === 'super_admin' ? 'danger' : ($user['role_name'] === 'admin' ? 'warning' : ($user['role_name'] === 'manager' ? 'success' : ($user['role_name'] === 'employee' ? 'info' : 'secondary'))) ?>">
                                                            <?= htmlspecialchars($user['role_name']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?> status-badge" id="status-badge-<?= $user['id'] ?>">
                                                            <i class="bi bi-<?= $user['is_active'] ? 'check-circle' : 'x-circle' ?> me-1"></i>
                                                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                                    <td>
                                                        <?php if ($can_manage): ?>
                                                            <div class="d-flex gap-1" role="group">
                                                                <a href="<?= BASE_URL ?>/views/manage_permissions.php?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Permissions">
                                                                    <i class="bi bi-gear"></i>
                                                                </a>
                                                                <button class="btn btn-sm btn-outline-secondary" onclick="changePassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" title="Change Password">
                                                                    <i class="bi bi-key"></i>
                                                                </button>
                                                                <button class="btn btn-sm <?= $user['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                                                        onclick="toggleUserStatus(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', <?= $user['is_active'] ? 1 : 0 ?>)"
                                                                        title="<?= $user['is_active'] ? 'Deactivate User' : 'Activate User' ?>">
                                                                    <i class="bi bi-<?= $user['is_active'] ? 'person-dash' : 'person-check' ?>"></i>
                                                                </button>
                                                                <?php if ($current_user['role_name'] === 'super_admin' && $user['id'] != $_SESSION['user_id']): ?>
                                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" title="Delete User">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Cannot manage</span>
                                                        <?php endif; ?>
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
                <?php endif; ?>

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

<!-- Change Password Modal -->
<?php if ($is_admin && check_permission('users', 'view')): ?>
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Change Password - <span id="passwordUserName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="change_password" value="1">
                <input type="hidden" name="password_user_id" id="passwordUserId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_user_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_user_password" name="new_user_password" required>
                        <div class="form-text">Enter a strong password for the user</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                        <div class="form-text">Re-enter the password to confirm</div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This will change the user's password. They will need to use the new password to log in.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


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

// Password strength indicator
document.getElementById('new_password')?.addEventListener('input', function() {
    const password = this.value;
    const strength = calculatePasswordStrength(password);

    // Remove existing classes
    this.classList.remove('is-valid', 'is-invalid');

    if (password.length > 0) {
        if (strength >= 3) {
            this.classList.add('is-valid');
        } else {
            this.classList.add('is-invalid');
        }
    }
});

function calculatePasswordStrength(password) {
    let strength = 0;
    if (password.length >= 6) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    return strength;
}

// User Status Toggle Function
function toggleUserStatus(userId, username, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    const action = newStatus ? 'activate' : 'deactivate';
    const confirmMessage = `Are you sure you want to ${action} the user "${username}"?`;

    if (confirm(confirmMessage)) {
        // Create a form to submit the toggle request
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        // Add form fields
        const fields = {
            'toggle_user_status': '1',
            'user_id': userId.toString(),
            'new_status': newStatus.toString()
        };

        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        // Add form to body and submit
        document.body.appendChild(form);
        form.submit();
    }
}

// Change Password Function
function changePassword(userId, username) {
    document.getElementById('passwordUserId').value = userId;
    document.getElementById('passwordUserName').textContent = username;

    // Clear form fields
    document.getElementById('new_user_password').value = '';
    document.getElementById('confirm_new_password').value = '';

    // Show modal
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
}

// Password Confirmation Validation
document.addEventListener('DOMContentLoaded', function() {
    const confirmPasswordField = document.getElementById('confirm_new_password');
    if (confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function() {
            const password = document.getElementById('new_user_password').value;
            const confirmPassword = this.value;

            // Remove existing validation classes
            this.classList.remove('is-valid', 'is-invalid');

            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('is-invalid');
            } else if (confirmPassword && password === confirmPassword) {
                this.classList.add('is-valid');
            }
        });
    }
});

// Delete User Function
function deleteUser(userId, username) {
    const confirmMessage = `⚠️ WARNING: This action cannot be undone!\n\nAre you sure you want to permanently delete the user "${username}"?\n\nThis will:\n• Delete all user data\n• Remove all permissions\n• Delete associated records\n• Make the username available for new users`;

    if (confirm(confirmMessage)) {
        // Additional confirmation for safety
        const finalConfirm = prompt(`To confirm deletion of "${username}", please type "DELETE" below:`);

        if (finalConfirm === 'DELETE') {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            // Add form fields
            const fields = {
                'delete_user': '1',
                'delete_user_id': userId.toString()
            };

            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }

            // Add form to body and submit
            document.body.appendChild(form);
            form.submit();
        } else {
            alert('Deletion cancelled. Please type "DELETE" to confirm.');
        }
    }
}
</script>


<?php include '../includes/footer.php'; ?>