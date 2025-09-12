<?php
// test_permission_controls.php - Test the permission controls for paid amount and save
require_once 'config/db.php';
require_once 'includes/permissions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>🔍 Permission Controls Test</h2>";

// Test 1: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<div style='color: red;'>❌ No user logged in. Please login first.</div>";
    echo "<a href='views/login.php'>Go to Login</a>";
    exit;
}

echo "<h3>1. Current User Info</h3>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "Username: " . ($_SESSION['username'] ?? 'Unknown') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'Unknown') . "<br><br>";

// Test 2: Check permissions for view_installments module
echo "<h3>2. View Installments Permissions</h3>";

$paid_amount_perm = check_permission('view_installments', 'paid_amount');
$save_perm = check_permission('view_installments', 'save');

echo "Paid Amount Permission: " . ($paid_amount_perm ? "<span style='color: green;'>✅ GRANTED</span>" : "<span style='color: red;'>❌ DENIED</span>") . "<br>";
echo "Save Permission: " . ($save_perm ? "<span style='color: green;'>✅ GRANTED</span>" : "<span style='color: red;'>❌ DENIED</span>") . "<br><br>";

// Test 3: Check permissions for view_rent module
echo "<h3>3. View Rent Permissions</h3>";

$rent_paid_amount_perm = check_permission('view_rent', 'paid_amount');
$rent_save_perm = check_permission('view_rent', 'save');

echo "Paid Amount Permission: " . ($rent_paid_amount_perm ? "<span style='color: green;'>✅ GRANTED</span>" : "<span style='color: red;'>❌ DENIED</span>") . "<br>";
echo "Save Permission: " . ($rent_save_perm ? "<span style='color: green;'>✅ GRANTED</span>" : "<span style='color: red;'>❌ DENIED</span>") . "<br><br>";

// Test 4: Show what the HTML attributes would be
echo "<h3>4. HTML Attribute Simulation</h3>";
echo "<strong>For Paid Amount Input Field:</strong><br>";
echo "HTML Attribute: <code>" . ($paid_amount_perm ? '' : 'disabled readonly') . "</code><br>";
echo "Result: " . ($paid_amount_perm ? "<span style='color: green;'>ENABLED</span>" : "<span style='color: red;'>DISABLED + READONLY</span>") . "<br><br>";

echo "<strong>For Save Button:</strong><br>";
echo "HTML Attribute: <code>" . ($save_perm ? '' : 'disabled') . "</code><br>";
echo "Result: " . ($save_perm ? "<span style='color: green;'>ENABLED</span>" : "<span style='color: red;'>DISABLED</span>") . "<br><br>";

// Test 5: Show current session permissions cache
echo "<h3>5. Session Permissions Cache</h3>";
if (isset($_SESSION['permissions'])) {
    echo "<pre>";
    print_r($_SESSION['permissions']);
    echo "</pre>";
} else {
    echo "<span style='color: orange;'>⚠️ No permissions cache found in session</span><br>";
}

// Test 6: Instructions
echo "<h3>6. Testing Instructions</h3>";
echo "<ol>";
echo "<li>Go to <strong>Manage Permissions</strong> for this user</li>";
echo "<li>Uncheck <strong>'Paid Amount'</strong> for <strong>'View Installments'</strong> module</li>";
echo "<li>Uncheck <strong>'Save'</strong> for <strong>'View Installments'</strong> module</li>";
echo "<li>Save the permissions</li>";
echo "<li>Go to any installment record (Sales → View Installments)</li>";
echo "<li>Verify that:</li>";
echo "<ul>";
echo "<li>✅ Paid amount textbox is <strong>grayed out and uneditable</strong></li>";
echo "<li>✅ Save button is <strong>disabled</strong></li>";
echo "</ul>";
echo "<li>Repeat for <strong>View Rent</strong> module</li>";
echo "</ol>";

echo "<br><strong>Current Test URL:</strong> <code>" . $_SERVER['REQUEST_URI'] . "</code>";
?>