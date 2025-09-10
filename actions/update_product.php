<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $product_id = (int)$_POST['product_id'];
  $name = $_POST['name'];
  $model = $_POST['model'];
  $price = $_POST['price'];
  $stock_status = $_POST['stock_status'];
  $months = $_POST['installment_months'];
  $rate = $_POST['interest_rate'];
  $description = $_POST['description'] ?? '';

  $stmt = $conn->prepare(
    "UPDATE products 
     SET name=?, model=?, price=?, stock_status=?, installment_months=?, interest_rate=?, description=?
     WHERE id=?"
  );
  $stmt->bind_param("ssdsiddi", $name, $model, $price, $stock_status, $months, $rate, $description, $product_id);
  $success = $stmt->execute();
  $stmt->close();
  $conn->close();
} else {
  $success = false;
}

$message = $success
  ? ['type'=>'success','text'=>'Product updated successfully.']
  : ['type'=>'danger','text'=>'Failed to update product.'];

include '../includes/header.php';
?>

<div class="container">
  <div class="alert alert-<?php echo $message['type']; ?> text-center">
    <?php echo $message['text']; ?>
  </div>
  <div class="text-center my-3">
    <a href="../views/list_products.php" class="btn btn-primary">
      Back to Product List
    </a>
  </div>
</div>

<?php include '../includes/footer.php'; ?>