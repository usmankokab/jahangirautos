<?php
include 'config/db.php';

// Add missing columns to products table
$sql1 = "ALTER TABLE products ADD COLUMN modal VARCHAR(255) AFTER name";
$sql2 = "ALTER TABLE products ADD COLUMN stock_status ENUM('in_stock', 'out_of_stock') DEFAULT 'in_stock' AFTER modal";

try {
    $conn->query($sql1);
    echo "Added modal column successfully<br>";
} catch (Exception $e) {
    echo "Modal column might already exist: " . $e->getMessage() . "<br>";
}

try {
    $conn->query($sql2);
    echo "Added stock_status column successfully<br>";
} catch (Exception $e) {
    echo "Stock_status column might already exist: " . $e->getMessage() . "<br>";
}

$conn->close();
echo "Database update completed. You can delete this file now.";
?>