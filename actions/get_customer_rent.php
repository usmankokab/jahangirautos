<?php
require_once '../config/db.php';
require_once '../config/auth.php';

// Ensure only customers can access this
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$rent_id = (int)($_GET['rent_id'] ?? 0);

if ($rent_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid rent ID']);
    exit();
}

// Get customer ID from session
$customer_id = $_SESSION['customer_id'];

// Verify that this rent belongs to the customer
$rent_check_query = "
    SELECT r.id FROM rents r
    WHERE r.id = ? AND r.customer_id = ?
";
$stmt = $conn->prepare($rent_check_query);
$stmt->bind_param("ii", $rent_id, $customer_id);
$stmt->execute();
$rent_check = $stmt->get_result()->fetch_assoc();

if (!$rent_check) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// Get rent information
$rent_query = "
    SELECT r.*, c.name as customer_name,
           CASE
               WHEN r.rent_type = 'daily' THEN (r.daily_rent * DATEDIFF(r.end_date, r.start_date) + 1)
               ELSE r.total_rent
           END as total_due,
           CASE
               WHEN r.rent_type = 'daily' THEN DATEDIFF(r.end_date, r.start_date) + 1
               ELSE 1
           END as days
    FROM rents r
    JOIN customers c ON c.id = r.customer_id
    WHERE r.id = ?
";
$stmt = $conn->prepare($rent_query);
$stmt->bind_param("i", $rent_id);
$stmt->execute();
$rent = $stmt->get_result()->fetch_assoc();

// Get rent payments
$payments_query = "
    SELECT id, rent_date, amount, paid_amount, status, paid_at, comment
    FROM rent_payments
    WHERE rent_id = ?
    ORDER BY rent_date ASC
";
$stmt = $conn->prepare($payments_query);
$stmt->bind_param("i", $rent_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totals_query = "
    SELECT
        SUM(amount) as total_due,
        SUM(COALESCE(paid_amount, 0)) as total_paid
    FROM rent_payments
    WHERE rent_id = ?
";
$stmt = $conn->prepare($totals_query);
$stmt->bind_param("i", $rent_id);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();


// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'rent' => $rent,
    'payments' => $payments,
    'totals' => $totals
]);
?>