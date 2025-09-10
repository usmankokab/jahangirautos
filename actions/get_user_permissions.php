<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

// Check if user is admin
$user_query = "SELECT r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!in_array($user['role_name'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = (int)($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

// Check if current user can manage the target user's permissions
$target_user_query = "SELECT r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
$target_stmt = $conn->prepare($target_user_query);
$target_stmt->bind_param("i", $user_id);
$target_stmt->execute();
$target_user = $target_stmt->get_result()->fetch_assoc();

if (!$target_user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

// Check permission hierarchy
$can_manage = false;
if ($user['role_name'] === 'super_admin') {
    $can_manage = true; // Super admin can manage all
} elseif ($user['role_name'] === 'admin') {
    $can_manage = !in_array($target_user['role_name'], ['super_admin', 'admin']); // Admin cannot manage super_admin or other admins
} elseif ($user['role_name'] === 'employee') {
    $can_manage = $target_user['role_name'] === 'customer'; // Employee can only manage customers
}

if (!$can_manage) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to manage this user']);
    exit();
}

try {
    // Get user permissions
    $permissions_query = "
        SELECT up.*, m.module_name, m.parent_id
        FROM user_permissions up
        LEFT JOIN modules m ON up.module_id = m.id
        WHERE up.user_id = ?
        ORDER BY m.parent_id, m.module_name
    ";
    $stmt = $conn->prepare($permissions_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($permissions);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>