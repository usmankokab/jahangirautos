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
?>