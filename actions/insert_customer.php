<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

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
    $stmt = $conn->prepare("INSERT INTO customers (name, cnic, phone, address, guarantor_1, guarantor_2, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("sssssss", $name, $cnic, $phone, $addr, $ref, $ref2, $image_path);
        $success = $stmt->execute();
        $stmt->close();
    }

    $conn->close();

    // Redirect based on result
    if ($success) {
        header('Location: ' . BASE_URL . '/views/list_customers.php?success=' . urlencode('Customer added successfully'));
    } else {
        header('Location: ' . BASE_URL . '/views/add_customer.php?error=' . urlencode('Failed to add customer'));
    }
    exit();
} else {
    // If not POST request, redirect to add customer page
    header('Location: ' . BASE_URL . '/views/add_customer.php');
    exit();
}
?>