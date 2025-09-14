<?php
require_once '../config/db.php';
require_once '../config/auth.php';

// Ensure only customers can access this
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$sale_id = (int)($_GET['sale_id'] ?? 0);

if ($sale_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid sale ID']);
    exit();
}

// Get customer ID from session
$customer_id = $_SESSION['customer_id'];

// Verify that this sale belongs to the customer
$sale_check_query = "
    SELECT s.id FROM sales s
    WHERE s.id = ? AND s.customer_id = ?
";
$stmt = $conn->prepare($sale_check_query);
$stmt->bind_param("ii", $sale_id, $customer_id);
$stmt->execute();
$sale_check = $stmt->get_result()->fetch_assoc();

if (!$sale_check) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// Get sale information
$sale_query = "
    SELECT s.sale_date, c.name as customer_name, p.name as product_name, p.model,
           s.total_amount, s.monthly_installment
    FROM sales s
    JOIN customers c ON c.id = s.customer_id
    JOIN products p ON p.id = s.product_id
    WHERE s.id = ?
";
$stmt = $conn->prepare($sale_query);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

// Get installments
$installments_query = "
    SELECT id, due_date, amount, paid_amount, status, paid_at, comment
    FROM installments
    WHERE sale_id = ?
    ORDER BY due_date ASC
";
$stmt = $conn->prepare($installments_query);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$installments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totals_query = "
    SELECT
        SUM(amount) as total_due,
        SUM(COALESCE(paid_amount, 0)) as total_paid
    FROM installments
    WHERE sale_id = ?
";
$stmt = $conn->prepare($totals_query);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'sale' => $sale,
    'installments' => $installments,
    'totals' => $totals
]);
?>