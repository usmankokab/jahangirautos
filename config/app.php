<?php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// OPTIONAL: If your app is in a subfolder like /installment_app, define it here
$subfolder = '/installment_app'; // Leave blank '' if hosted at root

define('BASE_URL', $protocol . $host . $subfolder);
?>