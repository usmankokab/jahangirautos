<?php
include __DIR__ . '/../config/db.php';
include __DIR__ . '/../config/auth.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['sale_id'])) {
  $sale_id = (int)$_GET['sale_id'];
  
  // Begin transaction
  $conn->begin_transaction();
  
  try {
    // Delete installments first (foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM installments WHERE sale_id = ?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete the sale
    $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    session_start();
    $_SESSION['message'] = 'Sale deleted successfully.';
    $_SESSION['message_type'] = 'success';
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Set error message
    session_start();
    $_SESSION['message'] = 'Error deleting sale: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
  }
}

header('Location: ' . BASE_URL . '/views/list_sales.php');
exit;