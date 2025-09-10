<?php
session_start();
require_once '../config/app.php';
require_once '../config/db.php';
require_once '../config/auth.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
  
  // Begin transaction
  $conn->begin_transaction();
  
  try {
    // Check if product is used in any sales
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $sales_count = $row['count'];
    $stmt->close();
    
    if ($sales_count > 0) {
      throw new Exception("Cannot delete product. It is used in $sales_count sale(s).");
    }
    
    // Delete the product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Redirect with success message
    header('Location: ../views/list_products.php?success=' . urlencode('Product deleted successfully.'));
    exit;
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Redirect with error message
    header('Location: ../views/list_products.php?error=' . urlencode('Error deleting product: ' . $e->getMessage()));
    exit;
  }
}

