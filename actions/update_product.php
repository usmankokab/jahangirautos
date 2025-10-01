<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $product_id = (int)$_POST['product_id'];
  $name = $_POST['name'];
  $model = $_POST['model'];
  $price = $_POST['price'];
  $stock_status = $_POST['stock_status'];
  $months = $_POST['installment_months'];
  $rate = $_POST['interest_rate'];
  $description = $_POST['description'] ?? '';

  $stmt = $conn->prepare(
    "UPDATE products 
     SET name=?, model=?, price=?, stock_status=?, installment_months=?, interest_rate=?, description=?
     WHERE id=?"
  );
  $stmt->bind_param("ssdsiddi", $name, $model, $price, $stock_status, $months, $rate, $description, $product_id);
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
      'message' => 'Product updated successfully'
    ]);
  } else {
    // Regular form submission - redirect
    header('Location: ' . BASE_URL . '/views/list_products.php?success=' . urlencode('Product updated successfully'));
  }
} else {
  // Check if this is an AJAX request
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode([
      'success' => false,
      'message' => 'Failed to update product'
    ]);
  } else {
    // Regular form submission - redirect
    header('Location: ' . BASE_URL . '/views/list_products.php?error=' . urlencode('Failed to update product'));
  }
}
exit();
?>

<div class="container">
  <div class="alert alert-<?php echo $message['type']; ?> text-center">
    <?php echo $message['text']; ?>
  </div>
  <div class="text-center my-3">
    <a href="../views/list_products.php" class="btn btn-primary">
      Back to Product List
    </a>
  </div>
</div>

<?php include '../includes/footer.php'; ?>