<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';
?>

<div class="container" style="max-width:700px;">
  <div class="card shadow-sm my-4">
    <div class="card-header text-center">
      <h2>Record New Sale</h2>
    </div>
    <div class="card-body">
      <form method="POST" action="<?= BASE_URL ?>/actions/insert_sale.php">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label"><strong>Customer</strong></label>
            <select name="customer_id" class="form-select" required>
              <option value="">Select Customer</option>
<?php
  $custs = $conn->query("SELECT id, name FROM customers ORDER BY name");
  while($c = $custs->fetch_assoc()):
?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
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
                data-rate="<?= $p['interest_rate'] ?>">
                <?= htmlspecialchars($p['name']) ?>
              </option>
<?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-4">
            <label class="form-label"><strong>Price (PKR)</strong></label>
            <input type="number" step="0.01" id="price" name="price" class="form-control">
            <input type="hidden" id="originalPrice" name="original_price">
          </div>
          <div class="col-md-4">
            <label class="form-label"><strong>Term (months)</strong></label>
            <input type="number" id="months" name="months" class="form-control">
            <input type="hidden" id="originalMonths" name="original_months">
          </div>
          <div class="col-md-4">
            <label class="form-label"><strong>Interest Rate (%)</strong></label>
            <input type="number" step="0.01" id="rate" name="rate" class="form-control">
            <input type="hidden" id="originalRate" name="original_rate">
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-6">
            <label class="form-label"><strong>Down Payment (PKR)</strong></label>
            <input type="number" step="0.01" name="down_payment" id="downPayment" class="form-control" value="0.00">
          </div>
          <div class="col-md-6">
            <label class="form-label"><strong>Monthly Installment (PKR)</strong></label>
            <input type="text" id="monthlyInstallment" class="form-control" readonly>
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-12">
            <label class="form-label"><strong>Total Amount to be Paid in Installments (PKR)</strong></label>
            <input type="text" id="totalInstallmentAmount" class="form-control" readonly>
          </div>
        </div>

        <div class="row g-3 mt-3">
           <div class="col-md-12 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100">Save Sale</button>
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
});

function calculateMonthlyInstallment() {
  let price = parseFloat(document.getElementById('price').value) || 0;
  let months = parseFloat(document.getElementById('months').value) || 0;
  let rate = parseFloat(document.getElementById('rate').value) || 0;
  let downPayment = parseFloat(document.getElementById('downPayment').value) || 0;
  
  if (price > 0 && months > 0) {
    let remainingAmount = price - downPayment;
    let interestAmount = (remainingAmount * rate) / 100;
    let totalAmount = remainingAmount + interestAmount;
    let monthlyInstallment = totalAmount / months;
    document.getElementById('monthlyInstallment').value = Math.round(monthlyInstallment);
  } else {
    document.getElementById('monthlyInstallment').value = '';
  }
  
  // Calculate total installment amount
  calculateTotalInstallmentAmount();
}

function calculateTotalInstallmentAmount() {
  let monthlyInstallment = parseFloat(document.getElementById('monthlyInstallment').value) || 0;
  let months = parseFloat(document.getElementById('months').value) || 0;
  
  if (monthlyInstallment > 0 && months > 0) {
    let totalInstallmentAmount = monthlyInstallment * months;
    document.getElementById('totalInstallmentAmount').value = Math.round(totalInstallmentAmount);
  } else {
    document.getElementById('totalInstallmentAmount').value = '';
  }
}
</script>