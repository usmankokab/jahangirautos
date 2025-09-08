<?php 
include '../config/db.php'; 
include '../includes/header.php'; 

// Get sale data
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

if ($sale_id > 0) {
  // Fetch sale data
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
  $sale = $result->fetch_assoc();
  $stmt->close();
  
  if (!$sale) {
    header('Location: ' . BASE_URL . '/views/list_sales.php');
    exit;
  }
} else {
  header('Location: ' . BASE_URL . '/views/list_sales.php');
  exit;
}
?>

<div class="container" style="max-width:700px;">
  <div class="card shadow-sm my-4">
    <div class="card-header text-center">
      <h2>Edit Sale #<?= $sale['id'] ?></h2>
    </div>
    <div class="card-body">
      <form method="POST" action="<?= BASE_URL ?>/actions/update_sale.php">
        <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
        
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label"><strong>Customer</strong></label>
            <select name="customer_id" class="form-select" required>
              <option value="">Select Customer</option>
              <?php
                $custs = $conn->query("SELECT id, name FROM customers ORDER BY name");
                while($c = $custs->fetch_assoc()):
              ?>
                <option value="<?= $c['id'] ?>" <?= $c['id'] == $sale['customer_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label"><strong>Product</strong></label>
            <select name="product_id" id="productSelect" class="form-select" required>
              <option value="">Select Product</option>
              <?php
                $prods = $conn->query("SELECT id, name, price, installment_months, interest_rate FROM products");
                while($p = $prods->fetch_assoc()):
              ?>
                <option 
                  value="<?= $p['id'] ?>"
                  data-price="<?= $p['price'] ?>"
                  data-months="<?= $p['installment_months'] ?>"
                  data-rate="<?= $p['interest_rate'] ?>"
                  <?= $p['id'] == $sale['product_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-4">
            <label class="form-label"><strong>Price (PKR)</strong></label>
            <input type="number" step="0.01" id="price" name="price" class="form-control" value="<?= $sale['total_amount'] ?>">
            <input type="hidden" id="originalPrice" name="original_price" value="<?= $sale['total_amount'] ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label"><strong>Term (months)</strong></label>
            <input type="number" id="months" name="months" class="form-control" value="<?= $sale['months'] ?>">
            <input type="hidden" id="originalMonths" name="original_months" value="<?= $sale['months'] ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label"><strong>Interest Rate (%)</strong></label>
            <input type="number" step="0.01" id="rate" name="rate" class="form-control" value="<?= $sale['interest_rate'] ?>">
            <input type="hidden" id="originalRate" name="original_rate" value="<?= $sale['interest_rate'] ?>">
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-6">
            <label class="form-label"><strong>Down Payment (PKR)</strong></label>
            <input type="number" step="0.01" name="down_payment" id="downPayment" class="form-control" value="<?= $sale['down_payment'] ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label"><strong>Monthly Installment (PKR)</strong></label>
            <input type="text" id="monthlyInstallment" class="form-control" readonly value="<?= $sale['monthly_installment'] ?>">
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-12">
            <label class="form-label"><strong>Total Amount to be Paid in Installments (PKR)</strong></label>
            <input type="text" id="totalInstallmentAmount" class="form-control" readonly>
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-12 d-flex justify-content-between">
            <a href="<?= BASE_URL ?>/views/list_sales.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-success">Update Sale</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Auto-populate price, months & rate when a product is selected
document.getElementById('productSelect').addEventListener('change', function(){
  let opt = this.selectedOptions[0];
  document.getElementById('price').value  = opt.dataset.price || '';
  document.getElementById('originalPrice').value  = opt.dataset.price || '';
  document.getElementById('months').value = opt.dataset.months || '';
  document.getElementById('originalMonths').value = opt.dataset.months || '';
  document.getElementById('rate').value   = opt.dataset.rate || '';
  document.getElementById('originalRate').value   = opt.dataset.rate || '';
  
  // Calculate monthly installment
  calculateMonthlyInstallment();
});

// Calculate monthly installment when values change
document.getElementById('price').addEventListener('input', calculateMonthlyInstallment);
document.getElementById('months').addEventListener('input', calculateMonthlyInstallment);
document.getElementById('rate').addEventListener('input', calculateMonthlyInstallment);
document.getElementById('downPayment').addEventListener('input', calculateMonthlyInstallment);

// Calculate on page load
document.addEventListener('DOMContentLoaded', function() {
  calculateMonthlyInstallment();
  calculateTotalInstallmentAmount();
});

function calculateMonthlyInstallment() {
  let price = parseFloat(document.getElementById('price').value) || 0;
  let months = parseFloat(document.getElementById('months').value) || 0;
  let rate = parseFloat(document.getElementById('rate').value) || 0;
  let downPayment = parseFloat(document.getElementById('downPayment').value) || 0;
  
  if (price > 0 && months > 0) {
    let markup = (price * rate) / 100;
    let balance = price + markup - downPayment;
    let monthlyInstallment = balance / months;
    document.getElementById('monthlyInstallment').value = monthlyInstallment.toFixed(2);
    
    // Also calculate total installment amount
    calculateTotalInstallmentAmount();
  } else {
    document.getElementById('monthlyInstallment').value = '';
  }
}

function calculateTotalInstallmentAmount() {
  let monthlyInstallment = parseFloat(document.getElementById('monthlyInstallment').value) || 0;
  let months = parseFloat(document.getElementById('months').value) || 0;
  
  if (monthlyInstallment > 0 && months > 0) {
    let totalInstallmentAmount = monthlyInstallment * months;
    document.getElementById('totalInstallmentAmount').value = totalInstallmentAmount.toFixed(2);
  } else {
    document.getElementById('totalInstallmentAmount').value = '';
  }
}
</script>