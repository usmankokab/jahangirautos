<?php
// config/db.php

// Load BASE_URL and other constants
require_once __DIR__ . '/app.php';

// Environment-aware DB credentials
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    $host     = 'localhost';
    $dbname   = 'installment_db';
    $username = 'root';
    $password = '';
} else {
    $host     = 'localhost'; // or your Hostinger DB host (e.g., 'mysql.hostinger.com')
    $dbname   = 'your_live_db_name';
    $username = 'your_live_db_user';
    $password = 'your_live_db_password';
}

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
?>