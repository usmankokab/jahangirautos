<?php
file_put_contents('../debug_log.txt', "Script started\n", FILE_APPEND);
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

$user_id = $_SESSION['user_id'];

// Initialize variables
$success = false;
$image_path = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $name = $_POST['name'];
    $cnic = $_POST['cnic'];
    $phone = $_POST['phone'];
    $addr = $_POST['address'];
    $ref = $_POST['guarantor_1'];
    $ref2 = $_POST['guarantor_2'];

    // Handle image upload
    if (!empty($_FILES["customer_image"]["name"])) {
        $image_name = basename($_FILES["customer_image"]["name"]);
        $relative_path = "uploads/customers/" . time() . "_" . $image_name;
        $target_file = "../" . $relative_path;

        if (move_uploaded_file($_FILES["customer_image"]["tmp_name"], $target_file)) {
            $image_path = $relative_path;
        }
    }

    // Handle camera image if provided
    if (!empty($_POST['camera_image'])) {
        $base64 = $_POST['camera_image'];
        $data = explode(',', $base64);
        if (count($data) > 1) {
            $image_data = base64_decode($data[1]);
            $filename = "uploads/customers/" . time() . "_camera.jpg";
            $filepath = "../" . $filename;
            file_put_contents($filepath, $image_data);
            $image_path = $filename;
        }
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO customers (name, cnic, phone, address, guarantor_1, guarantor_2, image_path, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $error_message = 'Failed to add customer';

    if ($stmt) {
        $stmt->bind_param("sssssssi", $name, $cnic, $phone, $addr, $ref, $ref2, $image_path, $user_id);
        $success = $stmt->execute();
        if (!$success) {
            $log_message = "Insert customer failed: errno=" . $stmt->errno . ", error=" . $stmt->error . "\n";
            file_put_contents('../debug_log.txt', $log_message, FILE_APPEND);
            if ($stmt->errno == 1062) {
                $error_message = 'Customer with this CNIC already exists';
            }
        }
        $stmt->close();
    } else {
        $log_message = "Prepare statement failed: " . $conn->error . "\n";
        file_put_contents('../debug_log.txt', $log_message, FILE_APPEND);
        $success = false;
    }

    $conn->close();

    // Handle response based on request type
    if ($success) {
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // Return JSON response for AJAX
            file_put_contents('../debug_log.txt', "AJAX success\n", FILE_APPEND);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Customer added successfully'
            ]);
        } else {
            // Regular form submission - redirect
            header('Location: ' . BASE_URL . '/views/list_customers.php?success=' . urlencode('Customer added successfully'));
        }
    } else {
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // Return JSON response for AJAX
            file_put_contents('../debug_log.txt', "AJAX error: $error_message\n", FILE_APPEND);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message
            ]);
        } else {
            // Regular form submission - redirect
            header('Location: ' . BASE_URL . '/views/add_customer.php?error=' . urlencode($error_message));
        }
    }
    exit();
} else {
    // If not POST request, redirect to add customer page
    header('Location: ' . BASE_URL . '/views/add_customer.php');
    exit();
}
?>