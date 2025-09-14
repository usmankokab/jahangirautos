<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

$auth->requireNonCustomer();

if (isset($_GET['id'])) {
    $customer_id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    if ($customer) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'customer' => $customer
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Customer not found'
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Customer ID required'
    ]);
}
?>