<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'];
  $model = $_POST['model'];
  $price = $_POST['price'];
  $stock_status = $_POST['stock_status'];
  $months = $_POST['installment_months'];
  $rate = $_POST['interest_rate'];
  $description = $_POST['description'] ?? '';

  $stmt = $conn->prepare(
    "INSERT INTO products 
     (name, model, price, stock_status, installment_months, interest_rate, description) 
     VALUES (?, ?, ?, ?, ?, ?, ?)"
  );
  $stmt->bind_param("ssdsidd", $name, $model, $price, $stock_status, $months, $rate, $description);
  $success = $stmt->execute();
  $stmt->close();
  // Don't close $conn here as it's needed for permissions in header.php
} else {
  $success = false;
}

// Handle response based on request type
if ($success) {
  // Check if this is an AJAX request
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode([
      'success' => true,
      'message' => 'Product added successfully'
    ]);
  } else {
    // Regular form submission - redirect
    header('Location: ' . BASE_URL . '/views/list_products.php?success=' . urlencode('Product added successfully'));
  }
} else {
  // Check if this is an AJAX request
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode([
      'success' => false,
      'message' => 'Failed to add product'
    ]);
  } else {
    // Regular form submission - redirect
    header('Location: ' . BASE_URL . '/views/list_products.php?error=' . urlencode('Failed to add product'));
  }
}
exit();