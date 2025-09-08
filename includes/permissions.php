<?php
function check_permission($module, $action = 'view') {
    // Super admin has all permissions
    if ($_SESSION['role'] === 'super_admin') {
        return true;
    }
    
    // Check if user has permission for this module and action
    if (isset($_SESSION['permissions'][$module][$action])) {
        return $_SESSION['permissions'][$module][$action];
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

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/views/login.php');
        exit;
    }
}

// Customer-specific permission check
function is_own_record($table, $id) {
    if ($_SESSION['role'] !== 'customer') {
        return true;
    }
    
    global $conn;
    $customer_id = $_SESSION['customer_id'];
    
    $query = match($table) {
        'sales' => "SELECT 1 FROM sales WHERE id = ? AND customer_id = ?",
        'rents' => "SELECT 1 FROM rents WHERE id = ? AND customer_id = ?",
        default => null
    };
    
    if (!$query) {
        return false;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $customer_id);
    $stmt->execute();
    
    return $stmt->get_result()->num_rows > 0;
}

// Function to get all accessible modules for a user
function get_accessible_modules($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT DISTINCT m.* 
        FROM modules m
        LEFT JOIN user_permissions up ON m.id = up.module_id
        WHERE up.user_id = ? AND up.can_view = 1
        ORDER BY m.parent_id NULLS FIRST, m.module_name
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
