<?php
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['rent_id'])) {
  $rent_id = (int)$_GET['rent_id'];
  
  // Begin transaction
  $conn->begin_transaction();
  
  try {
    // Delete rent payments first (foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM rent_payments WHERE rent_id = ?");
    $stmt->bind_param("i", $rent_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete the rent
    $stmt = $conn->prepare("DELETE FROM rents WHERE id = ?");
    $stmt->bind_param("i", $rent_id);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    session_start();
    $_SESSION['message'] = 'Rent deleted successfully.';
    $_SESSION['message_type'] = 'success';
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Set error message
    session_start();
    $_SESSION['message'] = 'Error deleting rent: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
  }
}

header('Location: ../views/list_rents.php');
exit;