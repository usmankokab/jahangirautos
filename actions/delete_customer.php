<?php
include '../config/db.php';
include '../config/auth.php';
include '../config/app.php';

$auth->requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/views/list_customers.php?error=' . urlencode('Invalid customer ID'));
    exit();
}

$id = (int)$_GET['id'];

// First check if customer exists and get image path
$check = $conn->prepare("SELECT image_path FROM customers WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/views/list_customers.php?error=' . urlencode('Customer not found'));
    exit();
}

$customer = $result->fetch_assoc();
$image = $customer['image_path'];

// Check if customer is used in any sales
$sales_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE customer_id = ?");
$sales_stmt->bind_param("i", $id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
$sales_count = $sales_result->fetch_assoc()['count'];
$sales_stmt->close();

// Check if customer is used in any rents
$rents_stmt = $conn->prepare("SELECT COUNT(*) as count FROM rents WHERE customer_id = ?");
$rents_stmt->bind_param("i", $id);
$rents_stmt->execute();
$rents_result = $rents_stmt->get_result();
$rents_count = $rents_result->fetch_assoc()['count'];
$rents_stmt->close();

if ($sales_count > 0 || $rents_count > 0) {
    $message = "This customer cannot be deleted because they are associated with ";
    if ($sales_count > 0) $message .= "$sales_count sale(s)";
    if ($sales_count > 0 && $rents_count > 0) $message .= " and ";
    if ($rents_count > 0) $message .= "$rents_count rental(s)";
    $message .= ". Please remove or modify the related records first.";
    header('Location: ' . BASE_URL . '/views/list_customers.php?error=' . urlencode($message));
    exit();
}

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
