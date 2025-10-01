<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

if (isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if ($product) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'product' => $product
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Product not found'
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Product ID required'
    ]);
}
?>