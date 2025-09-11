<?php
require_once 'db.php';
require_once 'app.php';

// First, check if super admin already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = 'superadmin'");
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    die("Super admin already exists!");
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Make sure we have the super_admin role
    $stmt = $conn->prepare("SELECT id FROM user_roles WHERE role_name = 'super_admin' LIMIT 1");
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    
    if (!$role) {
        // Create roles if they don't exist
        $conn->query("
            INSERT INTO user_roles (role_name, description) VALUES
            ('super_admin', 'Super Administrator with full system access'),
            ('admin', 'Administrator with limited system access'),
            ('employee', 'Regular employee user'),
            ('customer', 'Customer account')
        ");
        
        $role_id = $conn->insert_id;
    } else {
        $role_id = $role['id'];
    }

    // 2. Create super admin user
    $username = 'superadmin';
    $password = password_hash('superadmin', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, role_id, is_active) 
        VALUES (?, ?, ?, 1)
    ");
    $stmt->bind_param("ssi", $username, $password, $role_id);
    $stmt->execute();
    $user_id = $conn->insert_id;

    // 3. Make sure we have all modules
    $conn->query("
        INSERT IGNORE INTO modules (module_name, description) VALUES
        ('dashboard', 'Dashboard Access'),
        ('users', 'User Management'),
        ('customers', 'Customer Management'),
        ('products', 'Product Management'),
        ('sales', 'Sales Management'),
        ('rents', 'Rent Management'),
        ('reports', 'Reports Access')
    ");

    // 4. Add child modules for reports if they don't exist
    $conn->query("
        INSERT IGNORE INTO modules (module_name, description, parent_id)
        SELECT
            m.module_name,
            m.description,
            (SELECT id FROM modules WHERE module_name = 'reports') as parent_id
        FROM (
            SELECT 'customer_performance' as module_name, 'Customer Performance Report' as description UNION ALL
            SELECT 'sales_summary', 'Sales Summary Report' UNION ALL
            SELECT 'rent_summary', 'Rent Summary Report' UNION ALL
            SELECT 'installment_analysis', 'Installment Analysis Report' UNION ALL
            SELECT 'overdue_report', 'Overdue Payments Report' UNION ALL
            SELECT 'rental_utilization_report', 'Rental Utilization Report' UNION ALL
            SELECT 'rental_profitability_report', 'Rental Profitability Report' UNION ALL
            SELECT 'rent_payment_report', 'Rent Payment Report' UNION ALL
            SELECT 'rent_customer_report', 'Rent Customer Report' UNION ALL
            SELECT 'product_performance_report', 'Product Performance Report'
        ) as m
    ");

    // 5. Grant all permissions to super admin
    $modules_result = $conn->query("SELECT id FROM modules");
    while ($module = $modules_result->fetch_assoc()) {
        $stmt = $conn->prepare("
            INSERT INTO user_permissions 
            (user_id, module_id, can_view, can_add, can_edit, can_delete, created_by) 
            VALUES (?, ?, 1, 1, 1, 1, ?)
        ");
        $stmt->bind_param("iii", $user_id, $module['id'], $user_id);
        $stmt->execute();
    }

    // Commit transaction
    $conn->commit();
    echo "Super admin created successfully!<br>";
    echo "Username: superadmin<br>";
    echo "Password: superadmin<br>";
    echo "<a href='" . BASE_URL . "/views/login.php'>Go to Login</a>";

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    die("Error: " . $e->getMessage());
}
