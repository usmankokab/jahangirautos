<?php
session_start();
include '../config/auth.php';

$auth->requireLogin();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ../views/login.php');
exit;
