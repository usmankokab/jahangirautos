<?php
include __DIR__ . '/../config/db.php';
include __DIR__ . '/../config/auth.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $sale_id = (int)$_POST['sale_id'];
  $cust  = $_POST['customer_id'];
  $prod  = $_POST['product_id'];
  $dp    = (float)$_POST['down_payment'];
  $sale_date = $_POST['sale_date'] ?: date('Y-m-d');
  
  // Check if values were edited, otherwise fetch from product
  $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
  $months = isset($_POST['months']) ? (int)$_POST['months'] : 0;
  $rate = isset($_POST['rate']) ? (float)$_POST['rate'] : 0;
  
  // If values weren't provided in POST, fetch from product
  if ($price == 0 || $months == 0) {
    $pstmt = $conn->prepare("SELECT price, installment_months, interest_rate FROM products WHERE id=?");
    $pstmt->bind_param("i",$prod);
    $pstmt->execute();
    $pstmt->bind_result($price,$months,$rate);
    $pstmt->fetch();
    $pstmt->close();
  }
  
  // calculate installments
  $remaining = $price - $dp;
  $interest = ($remaining * $rate) / 100;
  $balance = $remaining + $interest;
  $mi = round($balance / $months);
  
  // Begin transaction
  $conn->begin_transaction();
  
  try {
    // Update sale
    $stmt = $conn->prepare(
      "UPDATE sales SET
       customer_id=?, product_id=?, total_amount=?, down_payment=?, months=?, interest_rate=?, monthly_installment=?, sale_date=?
       WHERE id=?"
    );
    $stmt->bind_param("iiddiddsi", $cust,$prod,$price,$dp,$months,$rate,$mi,$sale_date,$sale_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete existing installments
    $stmt = $conn->prepare("DELETE FROM installments WHERE sale_id=?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $stmt->close();
    
    // Generate new installment rows
    $due = new DateTime($sale_date);
    for($i=1;$i<=$months;$i++){
      $due->modify('+1 month');
      $ip = $conn->prepare(
        "INSERT INTO installments (sale_id,due_date,amount) VALUES (?,?,?)"
      );
      $amt = $mi;
      $date = $due->format('Y-m-d');
      $ip->bind_param("isd",$sale_id,$date,$amt);
      $ip->execute();
      $ip->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    session_start();
    $_SESSION['message'] = 'Sale updated successfully.';
    $_SESSION['message_type'] = 'success';
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Set error message
    session_start();
    $_SESSION['message'] = 'Error updating sale: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
  }
}

header('Location: ' . BASE_URL . '/views/list_sales.php');
exit;