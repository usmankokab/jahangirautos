<?php 
include '../config/db.php'; 
include '../includes/header.php'; 

// Get rent data
$rent_id = isset($_GET['rent_id']) ? (int)$_GET['rent_id'] : 0;

if ($rent_id > 0) {
  // Fetch rent data
  $stmt = $conn->prepare("
    SELECT r.*, c.name as customer_name 
    FROM rents r 
    JOIN customers c ON r.customer_id = c.id 
    WHERE r.id = ?
  ");
  $stmt->bind_param("i", $rent_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $rent = $result->fetch_assoc();
  $stmt->close();
  
  if (!$rent) {
    header('Location: ' . BASE_URL . '/views/list_rents.php');
    exit;
  }
} else {
  header('Location: ' . BASE_URL . '/views/list_rents.php');
  exit;
}
?>

<div class="container" style="max-width:700px;">
  <div class="card shadow-sm my-4">
    <div class="card-header text-center">
      <h2>Edit Rent #<?= $rent['id'] ?></h2>
    </div>
    <div class="card-body">
      <form method="POST" action="../actions/update_rent.php">
        <input type="hidden" name="rent_id" value="<?= $rent['id'] ?>">
        
        <div class="mb-3">
          <label class="form-label">Customer</label>
          <select name="customer_id" class="form-select" required>
            <option value="">Select Customer</option>
            <?php
              $custs = $conn->query("SELECT id, name FROM customers ORDER BY name");
              while($c = $custs->fetch_assoc()):
            ?>
              <option value="<?= $c['id'] ?>" <?= $c['id'] == $rent['customer_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Product Name</label>
          <input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($rent['product_name']) ?>" required>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Rent Type</label>
          <select name="rent_type" id="rentType" class="form-select" required onchange="toggleRentType()">
            <option value="daily" <?= $rent['rent_type'] == 'daily' ? 'selected' : '' ?>>Daily Rent</option>
            <option value="once" <?= $rent['rent_type'] == 'once' ? 'selected' : '' ?>>Rent Once</option>
          </select>
        </div>
        
        <div class="mb-3" id="dailyRentDiv" style="<?= $rent['rent_type'] == 'daily' ? 'display:block;' : 'display:none;' ?>">
          <label class="form-label">Daily Rent (PKR)</label>
          <input type="number" step="0.01" name="daily_rent" class="form-control" value="<?= $rent['daily_rent'] ?>">
        </div>
        
        <div class="mb-3" id="totalRentDiv" style="<?= $rent['rent_type'] == 'once' ? 'display:block;' : 'display:none;' ?>">
          <label class="form-label">Total Rent (PKR)</label>
          <input type="number" step="0.01" name="total_rent" class="form-control" value="<?= $rent['total_rent'] ?>">
        </div>
        
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= $rent['start_date'] ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= $rent['end_date'] ?>" required>
          </div>
        </div>
        
        <div class="mb-3 mt-3">
          <label class="form-label">Comments</label>
          <textarea name="comments" class="form-control" rows="2"><?= htmlspecialchars($rent['comments']) ?></textarea>
        </div>
        
        <div class="d-flex justify-content-between mt-3">
          <a href="../views/list_rents.php" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-success">Update Rent</button>
        </div>
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