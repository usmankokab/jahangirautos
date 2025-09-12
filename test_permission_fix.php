<?php
// test_permission_fix.php - Comprehensive test and fix for permission system
require_once 'config/db.php';

// Test 1: Check database connection
echo "<h2>🔍 Permission System Diagnostic</h2>";
echo "<h3>1. Database Connection Test</h3>";
if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
} else {
    echo "✅ Database connected successfully<br>";
    echo "Database: installment_db<br><br>";
}

// Test 2: Check table structure
echo "<h3>2. Table Structure Test</h3>";
$result = $conn->query('DESCRIBE user_permissions');
$columns = [];
$column_details = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    $column_details[] = $row['Field'] . ' (' . $row['Type'] . ')';
}

echo "Current columns: " . implode(', ', $column_details) . "<br>";

// Check required columns
$required_columns = ['can_paid_amount', 'can_save'];
$missing_columns = [];
foreach ($required_columns as $col) {
    if (!in_array($col, $columns)) {
        $missing_columns[] = $col;
    }
}

if (empty($missing_columns)) {
    echo "✅ All required columns exist<br><br>";
} else {
    echo "❌ Missing columns: " . implode(', ', $missing_columns) . "<br>";
    echo "🔧 Adding missing columns...<br>";

    foreach ($missing_columns as $col) {
        $alter_sql = "ALTER TABLE user_permissions ADD COLUMN $col TINYINT(1) DEFAULT 0";
        if ($conn->query($alter_sql)) {
            echo "✅ Added column: $col<br>";
        } else {
            echo "❌ Failed to add column $col: " . $conn->error . "<br>";
        }
    }
    echo "<br>";
}

// Test 3: Test INSERT statement
echo "<h3>3. INSERT Statement Test</h3>";
$user_id = 999; // Test user ID
$module_id = 1;
$can_view = 1;
$can_add = 1;
$can_edit = 1;
$can_delete = 1;
$can_paid_amount = 1;
$can_save = 1;
$created_by = 1;

$insert_sql = "INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete, can_paid_amount, can_save, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

try {
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iiiiiiiii", $user_id, $module_id, $can_view, $can_add, $can_edit, $can_delete, $can_paid_amount, $can_save, $created_by);

    if ($stmt->execute()) {
        echo "✅ INSERT statement works correctly<br>";
        echo "Test record inserted with ID: " . $conn->insert_id . "<br>";

        // Clean up test record
        $conn->query("DELETE FROM user_permissions WHERE user_id = 999 AND module_id = 1 AND created_by = 1");
        echo "✅ Test record cleaned up<br>";
    } else {
        echo "❌ INSERT failed: " . $stmt->error . "<br>";
    }

    $stmt->close();
} catch (Exception $e) {
    echo "❌ Exception during INSERT: " . $e->getMessage() . "<br>";
}

echo "<br>";

// Test 4: Check manage_permissions.php file
echo "<h3>4. File Integrity Check</h3>";
$manage_permissions_path = 'views/manage_permissions.php';
if (file_exists($manage_permissions_path)) {
    $content = file_get_contents($manage_permissions_path);

    // Check for required elements
    $checks = [
        'can_paid_amount' => strpos($content, 'can_paid_amount') !== false,
        'can_save' => strpos($content, 'can_save') !== false,
        'INSERT INTO user_permissions' => strpos($content, 'INSERT INTO user_permissions') !== false,
        'bind_param' => strpos($content, 'bind_param') !== false
    ];

    foreach ($checks as $check => $result) {
        echo ($result ? "✅" : "❌") . " $check found in manage_permissions.php<br>";
    }
} else {
    echo "❌ manage_permissions.php file not found<br>";
}

echo "<br>";

// Test 5: Summary and recommendations
echo "<h3>5. Summary & Recommendations</h3>";
echo "<strong>Status:</strong> ";
if (empty($missing_columns) && !isset($e)) {
    echo "✅ <span style='color: green;'>All systems operational</span><br>";
    echo "<strong>Recommendation:</strong> Clear browser cache and try again<br>";
    echo "<strong>Steps:</strong><br>";
    echo "1. Press Ctrl+Shift+R (or Cmd+Shift+R on Mac) to hard refresh<br>";
    echo "2. Clear browser cache completely<br>";
    echo "3. Restart your web server (Apache/Nginx)<br>";
    echo "4. Try updating permissions again<br>";
} else {
    echo "❌ <span style='color: red;'>Issues detected - see above</span><br>";
    echo "<strong>Recommendation:</strong> Address the issues listed above<br>";
}

echo "<br><strong>Alternative Fix:</strong><br>";
echo "If the issue persists, try this SQL command directly in your MySQL database:<br>";
echo "<code style='background: #f4f4f4; padding: 10px; display: block;'>";
echo "ALTER TABLE user_permissions<br>";
echo "ADD COLUMN IF NOT EXISTS can_paid_amount TINYINT(1) DEFAULT 0,<br>";
echo "ADD COLUMN IF NOT EXISTS can_save TINYINT(1) DEFAULT 0;";
echo "</code>";

echo "<br><strong>Quick Test:</strong><br>";
echo "Run this URL to test: <code>http://" . $_SERVER['HTTP_HOST'] . "/test_permission_fix.php</code>";
?>