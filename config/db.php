<?php
// config/db.php

// Load BASE_URL and other constants
require_once __DIR__ . '/app.php';

// Environment-aware DB credentials
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') {
    $host     = 'localhost';
    $dbname   = 'installment_db';
    $username = 'root';
    $password = '';
} else {
    // Default to localhost for command line or other environments
    $host     = 'localhost';
    $dbname   = 'u473559570_installment_db';
    $username = 'u473559570_admin';
    $password = 'TalhaJahangir@980';
}

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Set charset and collation to avoid collation mismatches
$conn->set_charset("utf8mb4");
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') {
    $conn->query("SET collation_connection = 'utf8mb4_general_ci'");
} else {
    $conn->query("SET collation_connection = 'utf8mb4_uca1400_ai_ci'");
}
?>