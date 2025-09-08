<?php
include '../config/db.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $sale_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("
        SELECT s.*, c.name as customer_name, p.name as product_name 
        FROM sales s 
        JOIN customers c ON s.customer_id = c.id 
        JOIN products p ON s.product_id = p.id 
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($sale = $result->fetch_assoc()) {
        echo json_encode($sale);
    } else {
        echo json_encode(['error' => 'Sale not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Sale ID not provided']);
}

$conn->close();
?>