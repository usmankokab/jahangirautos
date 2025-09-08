<?php
include '../config/db.php';
include '../includes/header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && $_GET['limit'] === 'all' ? 0 : 20;
$offset = ($page - 1) * ($limit > 0 ? $limit : 0);

// Build SQL query with filters
$sql = "SELECT r.*, c.name AS customer,
         COALESCE(SUM(rp.paid_amount), 0) as total_paid,
         COUNT(*) OVER() as total_records
  FROM rents r
  JOIN customers c ON c.id=r.customer_id
  LEFT JOIN rent_payments rp ON r.id=rp.rent_id
  WHERE 1=1";

$params = [];
$types = "";

// Add filters
if (!empty($_GET['id'])) {
  $sql .= " AND r.id = ?";
  $params[] = $_GET['id'];
  $types .= "i";
}

if (!empty($_GET['customer_id'])) {
  $sql .= " AND r.customer_id = ?";
  $params[] = $_GET['customer_id'];
  $types .= "i";
}

if (!empty($_GET['product_name'])) {
  $sql .= " AND r.product_name LIKE ?";
  $params[] = '%' . $_GET['product_name'] . '%';
  $types .= "s";
}

if (!empty($_GET['rent_type'])) {
  $sql .= " AND r.rent_type = ?";
  $params[] = $_GET['rent_type'];
  $types .= "s";
}

$sql .= " GROUP BY r.id, r.customer_id, r.product_name, r.rent_type, r.daily_rent, r.total_rent, r.start_date, r.end_date, r.comments, r.created_at, c.name";

if ($limit > 0) {
  $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
  $params[] = $limit;
  $params[] = $offset;
  $types .= "ii";
} else {
  $sql .= " ORDER BY r.created_at DESC";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$total_records = 0;
$rents = [];
while($row = $result->fetch_assoc()) {
  $total_records = $row['total_records'];
  unset($row['total_records']);
  $rents[] = $row;
}

$total_pages = $limit > 0 ? ceil($total_records / $limit) : 1;

// Calculate total pages with default limit for "Print All" button
$total_pages_default = ceil($total_records / 20);
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between mb-2">
    <h2 class="mb-0" style="color: #0d6efd; font-weight: bold;">Rent Records</h2>
    <div class="d-flex gap-2 no-print">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRentModal">
        <i class="bi bi-plus-circle"></i> Add Rent
      </button>
      <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      <?php if ($total_pages_default > 1): ?>
      <button class="btn btn-outline-secondary" onclick="printAllRecords()"><i class="bi bi-printer"></i> Print All</button>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Filter Form -->
  <div class="card mb-4 no-print">
    <div class="card-header">
      <h5 class="mb-0">Filter Rents</h5>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-2">
          <label class="form-label">ID</label>
          <input type="number" name="id" class="form-control" value="<?= isset($_GET['id']) ? htmlspecialchars($_GET['id']) : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Customer</label>
          <select name="customer_id" class="form-select">
            <option value="">All Customers</option>
            <?php
              $custs = $conn->query("SELECT id, name FROM customers ORDER BY name");
              while($c = $custs->fetch_assoc()):
            ?>
              <option value="<?= $c['id'] ?>" <?= isset($_GET['customer_id']) && $_GET['customer_id'] == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Product</label>
          <input type="text" name="product_name" class="form-control" value="<?= isset($_GET['product_name']) ? htmlspecialchars($_GET['product_name']) : '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Type</label>
          <select name="rent_type" class="form-select">
            <option value="">All Types</option>
            <option value="daily" <?= isset($_GET['rent_type']) && $_GET['rent_type'] == 'daily' ? 'selected' : '' ?>>Daily</option>
            <option value="once" <?= isset($_GET['rent_type']) && $_GET['rent_type'] == 'once' ? 'selected' : '' ?>>Once</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">&nbsp;</label>
          <div class="d-flex">
            <button type="submit" class="btn btn-primary me-2">Filter</button>
            <a href="<?= BASE_URL ?>/views/list_rents.php" class="btn btn-secondary">Clear</a>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr style="background-color: #0d6efd !important;">
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">#</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Customer</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Product</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Type</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Start</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">End</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Rent</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Due</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Paid</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Remaining</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;" class="text-center no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
<?php
  foreach($rents as $r):
?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['customer']) ?></td>
        <td><?= htmlspecialchars($r['product_name']) ?></td>
        <td><?= ucfirst($r['rent_type']) ?></td>
        <td><?= $r['start_date'] ?></td>
        <td><?= $r['end_date'] ?></td>
        <td>
          <?php if($r['rent_type']==='daily'): ?>
            <?= number_format($r['daily_rent'],0) ?> / day
          <?php else: ?>
            <?= number_format($r['total_rent'],0) ?>
          <?php endif; ?>
        </td>
        <td>
          <?php 
            if($r['rent_type']==='daily') {
              $start = new DateTime($r['start_date']);
              $end = new DateTime($r['end_date']);
              $days = $start->diff($end)->days + 1;
              $due = $r['daily_rent'] * $days;
            } else {
              $due = $r['total_rent'];
            }
            echo number_format($due, 0);
          ?>
        </td>
        <td><?= number_format($r['total_paid'], 0) ?></td>
        <td><?= number_format($due - $r['total_paid'], 0) ?></td>
        <td class="text-center no-print">
          <button type="button" class="btn btn-sm btn-outline-primary" 
                  onclick="editRent(<?= $r['id'] ?>)" 
                  title="Edit Rent">
            <i class="bi bi-pencil"></i>
          </button>
          <a href="view_rent.php?rent_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info" title="View Rent">
            <i class="bi bi-eye"></i>
          </a>
          <a href="../actions/delete_rent.php?rent_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this rent record?')" title="Delete Rent">
            <i class="bi bi-trash"></i>
          </a>
        </td>
      </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination -->
  <?php if ($total_pages > 1 && $limit > 0): ?>
  <nav aria-label="Rents pagination" class="no-print">
    <ul class="pagination justify-content-center">
      <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?page=1<?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '' ?><?= isset($_GET['product_name']) ? '&product_name='.$_GET['product_name'] : '' ?><?= isset($_GET['rent_type']) ? '&rent_type='.$_GET['rent_type'] : '' ?>" aria-label="First">
          <span aria-hidden="true">&laquo;&laquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page-1 ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '' ?><?= isset($_GET['product_name']) ? '&product_name='.$_GET['product_name'] : '' ?><?= isset($_GET['rent_type']) ? '&rent_type='.$_GET['rent_type'] : '' ?>" aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>
      <?php endif; ?>
      
      <?php
      // Show page numbers
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);
      
      if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="?page=1'.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '').(isset($_GET['product_name']) ? '&product_name='.$_GET['product_name'] : '').(isset($_GET['rent_type']) ? '&rent_type='.$_GET['rent_type'] : '').'">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
      }
      
      for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '').(isset($_GET['product_name']) ? '&product_name='.$_GET['product_name'] : '').(isset($_GET['rent_type']) ? '&rent_type='.$_GET['rent_type'] : '').'">'.$i.'</a></li>';
      }
      
      if ($end < $total_pages) {
        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '').(isset($_GET['product_name']) ? '&product_name='.$_GET['product_name'] : '').(isset($_GET['rent_type']) ? '&rent_type='.$_GET['rent_type'] : '').'">'.$total_pages.'</a></li>';
      }
      ?>
      
      <?php if ($page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page+1 ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '' ?><?= isset($_GET['product_name']) ? '&product_name='.$_GET['product_name'] : '' ?><?= isset($_GET['rent_type']) ? '&rent_type='.$_GET['rent_type'] : '' ?>" aria-label="Next">
          <span aria-hidden="true">&raquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $total_pages ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '' ?><?= isset($_GET['product_name']) ? '&product_name='.$_GET['product_name'] : '' ?><?= isset($_GET['rent_type']) ? '&rent_type='.$_GET['rent_type'] : '' ?>" aria-label="Last">
          <span aria-hidden="true">&raquo;&raquo;</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<!-- Add Rent Modal -->
<div class="modal fade" id="addRentModal" tabindex="-1" aria-labelledby="addRentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addRentModalLabel">Add New Rent</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?= BASE_URL ?>/actions/insert_rent.php" method="POST">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
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
            <div class="col-md-6">
              <label class="form-label">Product Name</label>
              <input type="text" name="product_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Rent Type</label>
              <select name="rent_type" id="addRentType" class="form-select" required onchange="toggleAddRentType()">
                <option value="daily">Daily Rent</option>
                <option value="once">Rent Once</option>
              </select>
            </div>
            <div class="col-md-6" id="addDailyRentDiv">
              <label class="form-label">Daily Rent (PKR)</label>
              <input type="number" step="0.01" name="daily_rent" class="form-control">
            </div>
            <div class="col-md-6" id="addTotalRentDiv" style="display:none;">
              <label class="form-label">Total Rent (PKR)</label>
              <input type="number" step="0.01" name="total_rent" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Comments</label>
              <textarea name="comments" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Add Rent</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Rent Modal -->
<div class="modal fade" id="editRentModal" tabindex="-1" aria-labelledby="editRentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editRentModalLabel">Edit Rent</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?= BASE_URL ?>/actions/update_rent.php" method="POST" id="editRentForm">
        <input type="hidden" name="rent_id" id="editRentId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Customer</label>
              <select name="customer_id" id="editCustomerId" class="form-select" required>
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
              <label class="form-label">Product Name</label>
              <input type="text" name="product_name" id="editProductName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Rent Type</label>
              <select name="rent_type" id="editRentType" class="form-select" required onchange="toggleEditRentType()">
                <option value="daily">Daily Rent</option>
                <option value="once">Rent Once</option>
              </select>
            </div>
            <div class="col-md-6" id="editDailyRentDiv">
              <label class="form-label">Daily Rent (PKR)</label>
              <input type="number" step="0.01" name="daily_rent" id="editDailyRent" class="form-control">
            </div>
            <div class="col-md-6" id="editTotalRentDiv" style="display:none;">
              <label class="form-label">Total Rent (PKR)</label>
              <input type="number" step="0.01" name="total_rent" id="editTotalRent" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" id="editStartDate" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" id="editEndDate" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Comments</label>
              <textarea name="comments" id="editComments" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Update Rent</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function printAllRecords() {
  const url = new URL(window.location.href);
  url.searchParams.set('limit', 'all');
  url.searchParams.delete('page');
  window.open(url.toString(), '_blank');
}

// Add Rent Modal Functions
function toggleAddRentType() {
  var type = document.getElementById('addRentType').value;
  document.getElementById('addDailyRentDiv').style.display = (type === 'daily') ? 'block' : 'none';
  document.getElementById('addTotalRentDiv').style.display = (type === 'once') ? 'block' : 'none';
}

// Edit Rent Modal Functions
function toggleEditRentType() {
  var type = document.getElementById('editRentType').value;
  document.getElementById('editDailyRentDiv').style.display = (type === 'daily') ? 'block' : 'none';
  document.getElementById('editTotalRentDiv').style.display = (type === 'once') ? 'block' : 'none';
}

function editRent(id) {
  fetch(`<?= BASE_URL ?>/actions/get_rent.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
      document.getElementById('editRentId').value = data.id;
      document.getElementById('editCustomerId').value = data.customer_id;
      document.getElementById('editProductName').value = data.product_name;
      document.getElementById('editRentType').value = data.rent_type;
      document.getElementById('editDailyRent').value = data.daily_rent || '';
      document.getElementById('editTotalRent').value = data.total_rent || '';
      document.getElementById('editStartDate').value = data.start_date;
      document.getElementById('editEndDate').value = data.end_date;
      document.getElementById('editComments').value = data.comments || '';
      
      toggleEditRentType();
      
      new bootstrap.Modal(document.getElementById('editRentModal')).show();
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error loading rent data');
    });
}
</script>

<style>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>

<?php include '../includes/footer.php'; ?>
