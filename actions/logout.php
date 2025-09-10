<?php
session_start();
include '../config/auth.php';

// Destroy the session
session_destroy();

// Clear all session variables
$_SESSION = array();

// Redirect to login page
header('Location: ../views/login.php');
exit;
