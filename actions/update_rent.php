<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rent_id = (int)$_POST['rent_id'];
  $customer_id = $_POST['customer_id'];
  $product_name = $_POST['product_name'];
  $rent_type = $_POST['rent_type'];
  $daily_rent = $_POST['daily_rent'] ?? null;
  $total_rent = $_POST['total_rent'] ?? null;
  $start_date = $_POST['start_date'];
  $end_date = $_POST['end_date'];
  $comments = $_POST['comments'];

  // Begin transaction
  $conn->begin_transaction();

  try {
    // Update rent record
    $stmt = $conn->prepare("UPDATE rents SET customer_id=?, product_name=?, rent_type=?, daily_rent=?, total_rent=?, start_date=?, end_date=?, comments=? WHERE id=?");
    $stmt->bind_param("issddsssi", $customer_id, $product_name, $rent_type, $daily_rent, $total_rent, $start_date, $end_date, $comments, $rent_id);
    $stmt->execute();
    $stmt->close();

    // Delete existing rent payments
    $stmt = $conn->prepare("DELETE FROM rent_payments WHERE rent_id=?");
    $stmt->bind_param("i", $rent_id);
    $stmt->execute();
    $stmt->close();

    // Insert new rent payments
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

    // Commit transaction
    $conn->commit();

    // Set success message
    session_start();
    $_SESSION['message'] = 'Rent updated successfully.';
    $_SESSION['message_type'] = 'success';
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    // Set error message
    session_start();
    $_SESSION['message'] = 'Error updating rent: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
  }
}

header('Location: ../views/list_rents.php');
exit;