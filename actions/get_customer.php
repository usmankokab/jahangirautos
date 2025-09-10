<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

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
        echo json_encode($customer);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Customer not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID required']);
}
?>