<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $rent_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("
        SELECT r.*, c.name as customer_name 
        FROM rents r 
        JOIN customers c ON r.customer_id = c.id 
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $rent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($rent = $result->fetch_assoc()) {
        echo json_encode($rent);
    } else {
        echo json_encode(['error' => 'Rent not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Rent ID not provided']);
}

$conn->close();
?>