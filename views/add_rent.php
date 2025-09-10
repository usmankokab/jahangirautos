<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';
?>

<div class="container" style="max-width:700px;">
  <div class="card shadow-sm my-4">
    <div class="card-header text-center">
      <h2>Add New Rent</h2>
    </div>
    <div class="card-body">
      <form method="POST" action="../actions/insert_rent.php">
        <div class="mb-3">
          <label class="form-label">Customer</label>
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
        <div class="mb-3">
          <label class="form-label">Product Name</label>
          <input type="text" name="product_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Rent Type</label>
          <select name="rent_type" id="rentType" class="form-select" required onchange="toggleRentType()">
            <option value="daily">Daily Rent</option>
            <option value="once">Rent Once</option>
          </select>
        </div>
        <div class="mb-3" id="dailyRentDiv">
          <label class="form-label">Daily Rent (PKR)</label>
          <input type="number" step="0.01" name="daily_rent" class="form-control">
        </div>
        <div class="mb-3" id="totalRentDiv" style="display:none;">
          <label class="form-label">Total Rent (PKR)</label>
          <input type="number" step="0.01" name="total_rent" class="form-control">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" required>
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label">Comments</label>
          <textarea name="comments" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-success w-100 mt-3">Add Rent</button>
      </form>
    </div>
  </div>
</div>

<script>
function toggleRentType() {
  var type = document.getElementById('rentType').value;
  document.getElementById('dailyRentDiv').style.display = (type === 'daily') ? 'block' : 'none';
  document.getElementById('totalRentDiv').style.display = (type === 'once') ? 'block' : 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
