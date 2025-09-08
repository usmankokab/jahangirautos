<?php
include '../config/db.php';
include '../config/app.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/views/list_customers.php?error=' . urlencode('Invalid customer ID'));
    exit();
}

$id = (int)$_GET['id'];

// First check if customer exists and get image path
$check = $conn->prepare("SELECT image FROM customers WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/views/list_customers.php?error=' . urlencode('Customer not found'));
    exit();
}

$customer = $result->fetch_assoc();
$image = $customer['image'];

// Delete the customer
$stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);
$success = $stmt->execute();
$stmt->close();

// If deletion was successful and there was an image, delete it
if ($success && $image && file_exists("../" . $image)) {
    unlink("../" . $image);
}

$conn->close();

// Redirect with appropriate message
if ($success) {
    header('Location: ' . BASE_URL . '/views/list_customers.php?success=' . urlencode('Customer deleted successfully'));
} else {
    header('Location: ' . BASE_URL . '/views/list_customers.php?error=' . urlencode('Failed to delete customer'));
}
exit();
