<?php
include __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $cust  = $_POST['customer_id'];
  $prod  = $_POST['product_id'];
  $dp    = (float)$_POST['down_payment'];

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
  $markup = ($price * $rate)/100;
  $balance = $price + $markup - $dp;
  $mi = round($balance/$months,2);

  // insert sale
  $stmt = $conn->prepare(
    "INSERT INTO sales 
     (customer_id,product_id,total_amount,down_payment,months,interest_rate,monthly_installment)
     VALUES (?,?,?,?,?,?,?)"
  );
  $stmt->bind_param("iiiddid", $cust,$prod,$price,$dp,$months,$rate,$mi);
  $stmt->execute();
  $sale_id = $stmt->insert_id;
  $stmt->close();

  // generate installment rows
  $due = new DateTime();
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
}

header('Location: ' . BASE_URL . '/views/list_sales.php');
exit;