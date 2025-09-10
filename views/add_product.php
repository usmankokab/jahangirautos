<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';
?>

<div class="container">
  <div class="card mx-auto" style="max-width:600px">
    <div class="card-header text-center">
      <h2>Add New Product</h2>
    </div>
    <div class="card-body">
      <form method="POST" action="../actions/insert_product.php">
        <div class="mb-3">
          <label class="form-label">Product Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Model</label>
          <input type="text" name="model" class="form-control" required>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Price (PKR)</label>
            <input type="number" step="0.01" name="price" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Stock Status</label>
            <select name="stock_status" class="form-select" required>
              <option value="in_stock" selected>In Stock</option>
              <option value="out_of_stock">Out of Stock</option>
            </select>
          </div>
        </div>
        <div class="row g-3 mt-3">
          <div class="col-md-6">
            <label class="form-label">Installment Months</label>
            <input type="number" name="installment_months" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Interest Rate (%)</label>
            <input type="number" step="0.01" name="interest_rate" class="form-control" value="0.00">
          </div>
        </div>
        <button type="submit" class="btn btn-success w-100 mt-4">Add Product</button>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>