<?php
// Database configuration template
// Copy this file to db.php and update with your database credentials

$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "installment_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Base URL configuration - Set this according to your environment
// define('BASE_URL', 'http://your-domain.com/your-app-path');
?>