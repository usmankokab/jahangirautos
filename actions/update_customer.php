<?php
include '../config/db.php';

$id = $_POST['id'];
$name = $_POST['name'];
$cnic = $_POST['cnic'];
$phone = $_POST['phone'];
$address = $_POST['address'];
$guarantor_1 = $_POST['guarantor_1'];
$guarantor_2 = $_POST['guarantor_2'];

// Get existing image path
$query = "SELECT image_path FROM customers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$existing_image = $existing['image_path'] ?? null;

// Handle new image upload
$target_dir = "../uploads/customers/";
$image_uploaded = false;
$new_image_path = $existing_image;

if (!empty($_FILES["customer_image"]["name"])) {
    $image_name = basename($_FILES["customer_image"]["name"]);
    $relative_path = "uploads/customers/" . time() . "_" . $image_name;
    $target_file = "../" . $relative_path;

    if (move_uploaded_file($_FILES["customer_image"]["tmp_name"], $target_file)) {
        $image_uploaded = true;
        $new_image_path = $relative_path;

        if ($existing_image && file_exists("../" . $existing_image)) {
            unlink("../" . $existing_image);
        }
    }
}

if (!empty($_POST['camera_image'])) {
    $base64 = $_POST['camera_image'];
    $data = explode(',', $base64);
    $image_data = base64_decode($data[1]);

    $filename = "uploads/customers/" . time() . "_camera.jpg";
    $filepath = "../" . $filename;
    file_put_contents($filepath, $image_data);
    $new_image_path = $filename;

    // Optional: delete old image
    if ($existing_image && file_exists("../" . $existing_image)) {
        unlink("../" . $existing_image);
    }
}

// Update record
$update = "UPDATE customers SET name = ?, cnic = ?, phone = ?, address = ?, guarantor_1 = ?, guarantor_2 = ?, image_path = ? WHERE id = ?";
$stmt = $conn->prepare($update);
$stmt->bind_param("sssssssi", $name, $cnic, $phone, $address, $guarantor_1, $guarantor_2, $new_image_path, $id);

if ($stmt->execute()) {
    header('Location: ' . BASE_URL . '/views/list_customers.php?success=' . urlencode('Customer updated successfully'));
} else {
    header('Location: ' . BASE_URL . '/views/edit_customer.php?id=' . $id . '&error=' . urlencode('Failed to update customer'));
}
exit();
?>