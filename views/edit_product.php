<?php 
session_start();
require_once '../config/app.php';
require_once '../config/db.php';

// Get product data
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    $_SESSION['error'] = "Invalid product ID";
    header('Location: ' . BASE_URL . '/views/list_products.php');
    exit;
}

// Fetch product data
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    $_SESSION['error'] = "Product not found";
    header('Location: ' . BASE_URL . '/views/list_products.php');
    exit;
}

include '../includes/header.php';
?>

<div class="container">
  <div class="card mx-auto" style="max-width:600px">
    <div class="card-header text-center">
      <h2>Edit Product #<?= $product['id'] ?></h2>
    </div>
    <div class="card-body">
      <form method="POST" action="../actions/update_product.php">
        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Product Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Model</label>
            <input type="text" name="model" class="form-control" value="<?= htmlspecialchars($product['model'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Price (PKR)</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= $product['price'] ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Stock Status</label>
            <select name="stock_status" class="form-select" required>
              <option value="in_stock" <?= ($product['stock_status'] ?? 'in_stock') === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
              <option value="out_of_stock" <?= ($product['stock_status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Term (months)</label>
            <input type="number" name="installment_months" class="form-control" value="<?= $product['installment_months'] ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Interest Rate (%)</label>
            <input type="number" step="0.01" name="interest_rate" class="form-control" value="<?= $product['interest_rate'] ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
          <a href="../views/list_products.php" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-success">Update Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>