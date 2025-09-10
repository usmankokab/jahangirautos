<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $customer_id = $_POST['customer_id'];
  $product_name = $_POST['product_name'];
  $rent_type = $_POST['rent_type'];
  $daily_rent = $_POST['daily_rent'] ?? null;
  $total_rent = $_POST['total_rent'] ?? null;
  $start_date = $_POST['start_date'];
  $end_date = $_POST['end_date'];
  $comments = $_POST['comments'];

  // Insert rent record
  $stmt = $conn->prepare("INSERT INTO rents (customer_id, product_name, rent_type, daily_rent, total_rent, start_date, end_date, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("issddsss", $customer_id, $product_name, $rent_type, $daily_rent, $total_rent, $start_date, $end_date, $comments);
  $stmt->execute();
  $rent_id = $stmt->insert_id;
  $stmt->close();

  if ($rent_type === 'daily') {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = $start->diff($end)->days + 1;
    $date = clone $start;
    for ($i = 0; $i < $days; $i++) {
      $rent_date = $date->format('Y-m-d');
      $ip = $conn->prepare("INSERT INTO rent_payments (rent_id, rent_date, amount) VALUES (?, ?, ?)");
      $ip->bind_param("isd", $rent_id, $rent_date, $daily_rent);
      $ip->execute();
      $ip->close();
      $date->modify('+1 day');
    }
  } else {
    // Rent once
    $ip = $conn->prepare("INSERT INTO rent_payments (rent_id, rent_date, amount) VALUES (?, ?, ?)");
    $ip->bind_param("isd", $rent_id, $start_date, $total_rent);
    $ip->execute();
    $ip->close();
  }
}

header('Location: ../views/list_rents.php');
exit;
