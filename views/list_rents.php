<?php
include '../config/db.php';
include '../includes/header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && $_GET['limit'] === 'all' ? 0 : 20;
$offset = ($page - 1) * ($limit > 0 ? $limit : 0);

// Build SQL query with filters
$sql = "SELECT r.*, c.name AS customer,
         COUNT(*) OVER() as total_records
  FROM rents r
  JOIN customers c ON c.id=r.customer_id
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
    <h2 class="mb-0">Rent List</h2>
    <div class="d-flex gap-2 no-print">
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
            <a href="<?= BASE_URL ?>/views/list_rents.php" class="btn btn-secondary me-2">Clear</a>
            <button type="submit" class="btn btn-primary">Filter</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th>#</th><th>Customer</th><th>Product</th><th>Type</th><th>Start</th><th>End</th><th>Rent</th><th>Actions</th>
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
            <?= number_format($r['daily_rent'],2) ?> / day
          <?php else: ?>
            <?= number_format($r['total_rent'],2) ?>
          <?php endif; ?>
        </td>
        <td>
          <a href="edit_rent.php?rent_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Rent">
            <i class="bi bi-pencil"></i>
          </a>
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

<script>
function printAllRecords() {
  // Open a new window with all records
  const url = new URL(window.location.href);
  url.searchParams.set('limit', 'all');
  url.searchParams.delete('page');
  window.open(url.toString(), '_blank');
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
