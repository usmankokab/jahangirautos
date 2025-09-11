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

// Get target user info
$user_id = (int)($_GET['user_id'] ?? 0);
if ($user_id <= 0) {
    header('Location: ' . BASE_URL . '/views/user_settings.php');
    exit();
}

$target_user_query = "SELECT u.*, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
$target_stmt = $conn->prepare($target_user_query);
$target_stmt->bind_param("i", $user_id);
$target_stmt->execute();
$target_user = $target_stmt->get_result()->fetch_assoc();

if (!$target_user) {
    header('Location: ' . BASE_URL . '/views/user_settings.php');
    exit();
}

// Check permission hierarchy
$can_manage = false;
if ($current_user['role_name'] === 'super_admin') {
    $can_manage = true; // Super admin can manage all
} elseif ($current_user['role_name'] === 'admin') {
    $can_manage = in_array($target_user['role_name'], ['manager', 'employee']); // Admin can manage managers and employees
} elseif ($current_user['role_name'] === 'manager') {
    $can_manage = $target_user['role_name'] === 'employee'; // Manager can only manage employees
}

if (!$can_manage) {
    header('Location: ' . BASE_URL . '/views/user_settings.php');
    exit();
}

// Get all available modules, excluding duplicates
$modules_query = "SELECT * FROM modules WHERE id NOT IN (14, 19, 12, 22, 15, 17, 21, 18, 16, 20, 13) ORDER BY parent_id, module_name";
$modules_result = $conn->query($modules_query);
$modules = [];
while ($module = $modules_result->fetch_assoc()) {
    $modules[] = $module;
}

// Handle permission updates
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $permissions = $_POST['permissions'] ?? [];


    try {
        // Start transaction for data integrity
        $conn->begin_transaction();

        // Delete existing permissions
        $delete_stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();

        // Insert new permissions
        $inserted_count = 0;
        foreach ($permissions as $module_id => $perms) {
            $can_view = isset($perms['view']) ? 1 : 0;
            $can_add = isset($perms['add']) ? 1 : 0;
            $can_edit = isset($perms['edit']) ? 1 : 0;
            $can_delete = isset($perms['delete']) ? 1 : 0;

            // Only insert if at least view permission is granted
            if ($can_view || $can_add || $can_edit || $can_delete) {
                $perm_stmt = $conn->prepare("INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $perm_stmt->bind_param("iiiiiii", $user_id, $module_id, $can_view, $can_add, $can_edit, $can_delete, $_SESSION['user_id']);
                $perm_stmt->execute();
                $inserted_count++;
            }
        }

        // Commit transaction
        $conn->commit();

        $message = "Permissions updated successfully! ($inserted_count permissions saved) Changes take effect immediately.";
        $message_type = 'success';

        // Refresh permissions in session for immediate effect
        require_once '../includes/permissions.php';
        refresh_user_permissions($user_id);

        // Refresh current permissions after update
        $current_permissions = [];
        $perm_query = "SELECT * FROM user_permissions WHERE user_id = ?";
        $perm_stmt = $conn->prepare($perm_query);
        $perm_stmt->bind_param("i", $user_id);
        $perm_stmt->execute();
        $perm_result = $perm_stmt->get_result();
        while ($perm = $perm_result->fetch_assoc()) {
            $current_permissions[$perm['module_id']] = $perm;
        }

        // Add JavaScript to reset button state on page load (for success case)
        echo "<script>document.addEventListener('DOMContentLoaded', function() { document.getElementById('submitBtn').disabled = false; document.getElementById('submitBtn').innerHTML = '<i class=\"bi bi-check-circle me-1\"></i>Update Permissions'; });</script>";

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $message = 'Error updating permissions: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get current permissions for the user
$current_permissions = [];
$perm_query = "SELECT * FROM user_permissions WHERE user_id = ?";
$perm_stmt = $conn->prepare($perm_query);
$perm_stmt->bind_param("i", $user_id);
$perm_stmt->execute();
$perm_result = $perm_stmt->get_result();
while ($perm = $perm_result->fetch_assoc()) {
    $current_permissions[$perm['module_id']] = $perm;
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Manage Permissions</h2>
                    <small class="text-muted">Configure permissions for <?= htmlspecialchars($target_user['username']) ?> (<?= htmlspecialchars($target_user['role_name']) ?>)</small>
                </div>
                <a href="<?= BASE_URL ?>/views/user_settings.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to User Settings
                </a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="permissionsForm">
                <input type="hidden" name="update_permissions" value="1">

                <div class="row">
                    <!-- Quick Actions -->
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-warning" onclick="setRolePermissions('viewer')">
                                        <i class="bi bi-eye me-1"></i>Viewer
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="clearAllPermissions()">
                                        <i class="bi bi-x-circle me-1"></i>Clear All
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Core System Modules -->
                <div class="col-md-6">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-gear me-2"></i>Core System Modules
                            </h6>
                            <small>Essential system functionality</small>
                        </div>
                        <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                            <?php
                            // Get only core modules (no parent_id and not reports)
                            $core_modules = array_filter($modules, function($module) {
                                return is_null($module['parent_id']) &&
                                       $module['module_name'] !== 'reports';
                            });

                            foreach ($core_modules as $module):
                                $module_icon = '';
                                $module_color = 'primary';
                                switch($module['module_name']) {
                                     case 'dashboard': $module_icon = 'bi-speedometer2'; $module_color = 'primary'; break;
                                     case 'users': $module_icon = 'bi-people'; $module_color = 'success'; $module['module_name'] = 'User Management'; break;
                                     case 'customers': $module_icon = 'bi-person-check'; $module_color = 'info'; break;
                                     case 'products': $module_icon = 'bi-box-seam'; $module_color = 'warning'; break;
                                     case 'sales': $module_icon = 'bi-receipt'; $module_color = 'danger'; break;
                                     case 'rents': $module_icon = 'bi-calendar-event'; $module_color = 'secondary'; break;
                                     default: $module_icon = 'bi-circle'; $module_color = 'primary';
                                 }

                                $current_perm = $current_permissions[$module['id']] ?? null;
                            ?>
                            <div class="card mb-3 border-<?= $module_color ?> shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-<?= $module_color ?> text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi <?= $module_icon ?> fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 text-capitalize fw-bold"><?= htmlspecialchars($module['module_name']) ?></h6>
                                                <small class="text-muted">System module</small>
                                            </div>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="view_<?= $module['id'] ?>" name="permissions[<?= $module['id'] ?>][view]"
                                                   onchange="toggleModulePermissions(<?= $module['id'] ?>, this.checked)"
                                                   <?= ($current_perm && $current_perm['can_view']) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="view_<?= $module['id'] ?>">
                                                <i class="bi bi-eye me-1"></i>View
                                            </label>
                                        </div>
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input module-perm-<?= $module['id'] ?>" type="checkbox" id="add_<?= $module['id'] ?>" name="permissions[<?= $module['id'] ?>][add]"
                                                       <?= ($current_perm && $current_perm['can_add']) ? 'checked' : '' ?>
                                                       <?= ($current_perm && $current_perm['can_view']) ? '' : 'disabled' ?>>
                                                <label class="form-check-label small" for="add_<?= $module['id'] ?>">
                                                    <i class="bi bi-plus-circle text-success me-1"></i>Add
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input module-perm-<?= $module['id'] ?>" type="checkbox" id="edit_<?= $module['id'] ?>" name="permissions[<?= $module['id'] ?>][edit]"
                                                       <?= ($current_perm && $current_perm['can_edit']) ? 'checked' : '' ?>
                                                       <?= ($current_perm && $current_perm['can_view']) ? '' : 'disabled' ?>>
                                                <label class="form-check-label small" for="edit_<?= $module['id'] ?>">
                                                    <i class="bi bi-pencil text-warning me-1"></i>Edit
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input module-perm-<?= $module['id'] ?>" type="checkbox" id="delete_<?= $module['id'] ?>" name="permissions[<?= $module['id'] ?>][delete]"
                                                       <?= ($current_perm && $current_perm['can_delete']) ? 'checked' : '' ?>
                                                       <?= ($current_perm && $current_perm['can_view']) ? '' : 'disabled' ?>>
                                                <label class="form-check-label small" for="delete_<?= $module['id'] ?>">
                                                    <i class="bi bi-trash text-danger me-1"></i>Delete
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-sm me-1" onclick="selectAllPermissions(<?= $module['id'] ?>)">
                                            <i class="bi bi-check-all me-1"></i>All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-sm me-1" onclick="selectViewOnlyPermissions(<?= $module['id'] ?>)">
                                            <i class="bi bi-eye me-1"></i>View Only
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-sm" onclick="clearModulePermissions(<?= $module['id'] ?>)">
                                            <i class="bi bi-x me-1"></i>Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Reports & Analytics -->
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-bar-chart-line me-2"></i>Reports & Analytics
                            </h6>
                            <small>Access to reporting and analytical tools</small>
                        </div>
                        <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                            <?php
                            // Get reports module and its submodules
                            $reports_module = array_filter($modules, function($module) {
                                return $module['module_name'] === 'reports';
                            });
                            $reports_module = reset($reports_module);

                            if ($reports_module):
                                $report_submodules = array_filter($modules, function($module) use ($reports_module) {
                                    return $module['parent_id'] == $reports_module['id'];
                                });
                            ?>
                            <!-- Reports Dashboard Access -->
                            <div class="card mb-3 border-success shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-graph-up fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">Reports Dashboard</h6>
                                                <small class="text-muted">Main reports hub</small>
                                            </div>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="view_<?= $reports_module['id'] ?>" name="permissions[<?= $reports_module['id'] ?>][view]"
                                                   <?= (isset($current_permissions[$reports_module['id']]) && $current_permissions[$reports_module['id']]['can_view']) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="view_<?= $reports_module['id'] ?>">
                                                <i class="bi bi-eye me-1"></i>Access
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sales Reports -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-receipt me-2"></i>Sales Reports
                                </h6>
                                <?php
                                $sales_reports = array_filter($report_submodules, function($report) {
                                    return in_array($report['module_name'], [
                                        'sales_summary',
                                        'customer_performance',
                                        'product_performance',
                                        'installment_analysis',
                                        'overdue_report'
                                    ]);
                                });

                                foreach ($sales_reports as $report):
                                    $report_icon = '';
                                    $report_color = 'primary';
                                    switch($report['module_name']) {
                                        case 'sales_summary': $report_icon = 'bi-receipt'; $report_color = 'primary'; break;
                                        case 'customer_performance': $report_icon = 'bi-people'; $report_color = 'info'; break;
                                        case 'product_performance': $report_icon = 'bi-box-seam'; $report_color = 'secondary'; break;
                                        case 'installment_analysis': $report_icon = 'bi-calendar-check'; $report_color = 'warning'; break;
                                        case 'overdue_report': $report_icon = 'bi-exclamation-triangle'; $report_color = 'danger'; break;
                                        default: $report_icon = 'bi-file-earmark-text'; $report_color = 'primary';
                                    }

                                    $current_report_perm = $current_permissions[$report['id']] ?? null;
                                ?>
                                <div class="card mb-2 border-<?= $report_color ?> shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-<?= $report_color ?> text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px;">
                                                    <i class="bi <?= $report_icon ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 small fw-bold">
                                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['module_name']))) ?>
                                                    </h6>
                                                    <small class="text-muted">Sales report</small>
                                                </div>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="view_<?= $report['id'] ?>" name="permissions[<?= $report['id'] ?>][view]"
                                                       <?= ($current_report_perm && $current_report_perm['can_view']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="view_<?= $report['id'] ?>">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Rent Reports -->
                            <div class="mb-3">
                                <h6 class="text-secondary mb-3">
                                    <i class="bi bi-calendar-event me-2"></i>Rent Reports
                                </h6>
                                <?php
                                $rent_reports = array_filter($report_submodules, function($report) {
                                    return in_array($report['module_name'], [
                                        'rent_summary',
                                        'rental_utilization_report',
                                        'rental_profitability',
                                        'rent_payment',
                                        'rent_customer'
                                    ]);
                                });

                                foreach ($rent_reports as $report):
                                    $report_icon = '';
                                    $report_color = 'secondary';
                                    switch($report['module_name']) {
                                        case 'rent_summary': $report_icon = 'bi-calendar-event'; $report_color = 'secondary'; break;
                                        case 'rental_utilization_report': $report_icon = 'bi-bar-chart-line'; $report_color = 'info'; break;
                                        case 'rental_profitability': $report_icon = 'bi-graph-up'; $report_color = 'success'; break;
                                        case 'rent_payment': $report_icon = 'bi-cash-stack'; $report_color = 'warning'; break;
                                        case 'rent_customer': $report_icon = 'bi-person-lines-fill'; $report_color = 'primary'; break;
                                        default: $report_icon = 'bi-file-earmark-text'; $report_color = 'secondary';
                                    }

                                    $current_report_perm = $current_permissions[$report['id']] ?? null;
                                ?>
                                <div class="card mb-2 border-<?= $report_color ?> shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-<?= $report_color ?> text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px;">
                                                    <i class="bi <?= $report_icon ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 small fw-bold">
                                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['module_name']))) ?>
                                                    </h6>
                                                    <small class="text-muted">Rent report</small>
                                                </div>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="view_<?= $report['id'] ?>" name="permissions[<?= $report['id'] ?>][view]"
                                                       <?= ($current_report_perm && $current_report_perm['can_view']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="view_<?= $report['id'] ?>">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Summary Section -->
                            <div class="alert alert-info mt-3">
                                <h6 class="alert-heading mb-2">
                                    <i class="bi bi-info-circle me-2"></i>Permission Summary
                                </h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="fw-bold text-primary" id="totalModules">0</div>
                                        <small>Modules</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-success" id="totalReports">0</div>
                                        <small>Reports</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-warning" id="totalPermissions">0</div>
                                        <small>Permissions</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Changes take effect immediately - no logout required
                                </div>
                                <div>
                                    <a href="<?= BASE_URL ?>/views/user_settings.php" class="btn btn-secondary me-2">
                                        <i class="bi bi-x me-1"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="bi bi-check-circle me-1"></i>Update Permissions
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>
</div>

<script>
// Permission management functions
function toggleModulePermissions(moduleId, isChecked) {
    const modulePermissions = document.querySelectorAll(`.module-perm-${moduleId}`);
    modulePermissions.forEach(cb => {
        cb.checked = isChecked;
        cb.disabled = !isChecked;
    });
    updatePermissionSummary();
}

function selectAllPermissions(moduleId) {
    document.getElementById(`view_${moduleId}`).checked = true;
    const modulePermissions = document.querySelectorAll(`.module-perm-${moduleId}`);
    modulePermissions.forEach(cb => {
        cb.checked = true;
        cb.disabled = false;
    });
    updatePermissionSummary();
}

function selectViewOnlyPermissions(moduleId) {
    document.getElementById(`view_${moduleId}`).checked = true;
    const modulePermissions = document.querySelectorAll(`.module-perm-${moduleId}`);
    modulePermissions.forEach(cb => {
        cb.checked = false;
        cb.disabled = false;
    });
    updatePermissionSummary();
}

function clearModulePermissions(moduleId) {
    document.getElementById(`view_${moduleId}`).checked = false;
    const modulePermissions = document.querySelectorAll(`.module-perm-${moduleId}`);
    modulePermissions.forEach(cb => {
        cb.checked = false;
        cb.disabled = true;
    });
    updatePermissionSummary();
}

function clearAllPermissions() {
    if (confirm('Are you sure you want to clear all permissions?')) {
        document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
        document.querySelectorAll('[class*="module-perm-"]').forEach(cb => {
            cb.disabled = true;
        });
        updatePermissionSummary();
    }
}

function setRolePermissions(role) {
    // Reset all checkboxes and disable module permissions
    document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    document.querySelectorAll('[class*="module-perm-"]').forEach(cb => {
        cb.disabled = true;
    });

    // Set permissions based on role
    if (role === 'viewer') {
        // View-only permissions for all modules
        document.querySelectorAll('#permissionsForm input[id^="view_"]').forEach(cb => {
            cb.checked = true;
            // Enable the module permissions but don't check them
            const moduleId = cb.id.split('_')[1];
            const modulePermissions = document.querySelectorAll(`.module-perm-${moduleId}`);
            modulePermissions.forEach(permCb => {
                permCb.disabled = false;
                permCb.checked = false;
            });
        });
    }

    updatePermissionSummary();
}

function updatePermissionSummary() {
    const totalModules = document.querySelectorAll('.col-md-6:first-child .card').length - 1; // Subtract 1 for the quick actions card
    const totalReports = document.querySelectorAll('.col-md-6:last-child .card').length - 1; // Subtract 1 for the summary alert
    const totalPermissions = document.querySelectorAll('#permissionsForm input[type="checkbox"]:checked').length;

    document.getElementById('totalModules').textContent = totalModules;
    document.getElementById('totalReports').textContent = totalReports;
    document.getElementById('totalPermissions').textContent = totalPermissions;
}

// Initialize tooltips and permission summary on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePermissionSummary();

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle form submission
    const form = document.getElementById('permissionsForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Updating...';

            // Optional: Add visual feedback
            console.log('Form submitted with permissions data');
        });
    }
});
</script>

<style>
/* Interactive Permissions Page Styles */
.permissions-page .card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.permissions-page .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.permissions-page .module-icon {
    transition: transform 0.2s ease;
}

.permissions-page .card:hover .module-icon {
    transform: scale(1.1);
}

.permissions-page .form-check-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.permissions-page .btn-group .btn {
    border-radius: 0.375rem !important;
    margin: 0 2px;
}

.permissions-page .btn-group .btn:first-child {
    margin-left: 0;
}

.permissions-page .btn-group .btn:last-child {
    margin-right: 0;
}

.permissions-page .permission-summary {
    background: linear-gradient(45deg, #f8f9fa, #e9ecef);
    border-radius: 8px;
    padding: 15px;
}

/* Quick action buttons styling */
.permissions-page .quick-actions .btn {
    transition: all 0.2s ease;
}

.permissions-page .quick-actions .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Module permission cards */
.permissions-page .module-card {
    position: relative;
    overflow: hidden;
}

.permissions-page .module-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(to bottom, var(--bs-primary), var(--bs-primary-rgb));
    opacity: 0.7;
}

.permissions-page .module-card.primary::before {
    background: linear-gradient(to bottom, var(--bs-primary), #0056b3);
}

.permissions-page .module-card.success::before {
    background: linear-gradient(to bottom, var(--bs-success), #1e7e34);
}

.permissions-page .module-card.info::before {
    background: linear-gradient(to bottom, var(--bs-info), #117a8b);
}

.permissions-page .module-card.warning::before {
    background: linear-gradient(to bottom, var(--bs-warning), #d39e00);
}

.permissions-page .module-card.danger::before {
    background: linear-gradient(to bottom, var(--bs-danger), #bd2130);
}

/* Dark theme support */
.dark-theme .permissions-page .permission-summary {
    background: linear-gradient(45deg, #2d2d2d, #404040);
    color: #ffffff;
}

.dark-theme .permissions-page .card {
    background-color: #2d2d2d !important;
    border-color: #404040 !important;
}

.dark-theme .permissions-page .card-header {
    background-color: #404040 !important;
    border-color: #505050 !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .permissions-page .btn-group {
        flex-direction: column;
        gap: 5px;
    }

    .permissions-page .btn-group .btn {
        margin: 0 !important;
    }
}

/* Animation for permission changes */
@keyframes permissionChange {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.permissions-page .form-check-input:checked {
    animation: permissionChange 0.3s ease;
}
</style>

<?php include '../includes/footer.php'; ?>