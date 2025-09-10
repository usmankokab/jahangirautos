<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $payment_id = (int)$_POST['payment_id'];
  $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : 0;
  $comment = $_POST['comment'] ?? '';

  // Get current payment row
  $get = $conn->prepare("SELECT amount, paid_amount FROM rent_payments WHERE id=?");
  $get->bind_param("i", $payment_id);
  $get->execute();
  $get->bind_result($amount, $already_paid);
  $get->fetch();
  $get->close();

  $total_paid = $paid_amount;
  $due = $amount;
  // Add any carry from previous unpaid/partial
  // (handled in view, so here just update this row)

  $status = 'unpaid';
  if ($total_paid >= $due) {
    $status = 'paid';
  } elseif ($total_paid > 0 && $total_paid < $due) {
    $status = 'partial';
  }

  $stmt = $conn->prepare("UPDATE rent_payments SET paid_amount=?, status=?, paid_at=IF(? > 0, NOW(), NULL), comment=? WHERE id=?");
  $stmt->bind_param("dsssi", $total_paid, $status, $total_paid, $comment, $payment_id);
  $stmt->execute();
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
