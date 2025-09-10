<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

$sale_id = (int)$_GET['sale_id'];
$saleQ   = $conn->prepare("
  SELECT s.sale_date,c.name,p.name,s.monthly_installment
  FROM sales s
  JOIN customers c ON c.id=s.customer_id
  JOIN products p  ON p.id=s.product_id
  WHERE s.id=?
");
$saleQ->bind_param("i",$sale_id);
$saleQ->execute();
$saleQ->bind_result($sd,$cn,$pn,$mi);
$saleQ->fetch();
$saleQ->close();

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && $_GET['limit'] === 'all' ? 0 : 10;
$offset = ($page - 1) * ($limit > 0 ? $limit : 0);

// Build filter query with pagination
$filter_sql = "SELECT id,due_date,amount,status,paid_at,paid_amount,comment, COUNT(*) OVER() as total_records FROM installments WHERE sale_id=?";
$params = [$sale_id];
$types = "i";
if (!empty($_GET['status'])) {
  $filter_sql .= " AND status=?";
  $params[] = $_GET['status'];
  $types .= "s";
}
if (!empty($_GET['from_date'])) {
  $filter_sql .= " AND due_date >= ?";
  $params[] = $_GET['from_date'];
  $types .= "s";
}
if (!empty($_GET['to_date'])) {
  $filter_sql .= " AND due_date <= ?";
  $params[] = $_GET['to_date'];
  $types .= "s";
}

if ($limit > 0) {
  $filter_sql .= " ORDER BY due_date LIMIT ? OFFSET ?";
  $params[] = $limit;
  $params[] = $offset;
  $types .= "ii";
} else {
  $filter_sql .= " ORDER BY due_date";
}

$instQ = $conn->prepare($filter_sql);
$instQ->bind_param($types, ...$params);
$instQ->execute();
$res = $instQ->get_result();

$total_records = 0;
$installments = [];
while($row = $res->fetch_assoc()) {
  $total_records = $row['total_records'];
  unset($row['total_records']);
  $installments[] = $row;
}

$total_pages = $limit > 0 ? ceil($total_records / $limit) : 1;

// Calculate totals for all installments (not just filtered)
$sumQ = $conn->prepare("SELECT SUM(amount) as total_due, SUM(paid_amount) as total_paid FROM installments WHERE sale_id=?");
$sumQ->bind_param("i", $sale_id);
$sumQ->execute();
$sumQ->bind_result($total_due_all, $total_paid_all);
$sumQ->fetch();
$sumQ->close();
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between mb-2">
    <h2 class="mb-0" style="color: #0d6efd; font-weight: bold;">Installments for <?= htmlspecialchars($pn) ?> (<?= htmlspecialchars($cn) ?>)</h2>
    <div class="d-flex gap-2 no-print">
      <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
  </div>
  
  <div class="alert alert-info mb-3">
    <strong>Sale Date:</strong> <?= $sd ?> | <strong>Monthly Installment:</strong> ₨<?= number_format($mi,2) ?>
  </div>
  
  <!-- Filter Form -->
  <div class="card mb-4 no-print">
    <div class="card-header">
      <h5 class="mb-0">Filter Installments</h5>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <input type="hidden" name="sale_id" value="<?= htmlspecialchars($sale_id) ?>">
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="unpaid" <?= (isset($_GET['status']) && $_GET['status']==='unpaid')?'selected':'' ?>>Unpaid</option>
            <option value="partial" <?= (isset($_GET['status']) && $_GET['status']==='partial')?'selected':'' ?>>Partial</option>
            <option value="paid" <?= (isset($_GET['status']) && $_GET['status']==='paid')?'selected':'' ?>>Paid</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">From Date</label>
          <input type="date" name="from_date" class="form-control" value="<?= isset($_GET['from_date']) ? htmlspecialchars($_GET['from_date']) : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">To Date</label>
          <input type="date" name="to_date" class="form-control" value="<?= isset($_GET['to_date']) ? htmlspecialchars($_GET['to_date']) : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">&nbsp;</label>
          <div class="d-flex">
            <a href="?sale_id=<?= htmlspecialchars($sale_id) ?>" class="btn btn-secondary me-2">Clear</a>
            <button type="submit" class="btn btn-primary">Filter</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr style="background-color: #0d6efd !important;">
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">#</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Due Date</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Amount</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Status</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Paid At</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Comment</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;" class="no-print">Action</th>
        </tr>
      </thead>
      <tbody>
<?php $i = ($page - 1) * ($limit > 0 ? $limit : 0) + 1; foreach($installments as $row):
    $due = $row['amount'];
    $paid = $row['paid_amount'];
    $remaining = $due - $paid;
?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= $row['due_date'] ?></td>
          <td>
            <strong>Due:</strong> ₨<?= number_format($due,2) ?><br>
            <strong>Paid:</strong> ₨<?= number_format($paid,2) ?><br>
            <strong>Remain:</strong> ₨<?= number_format($remaining,2) ?>
          </td>
          <td><span class="badge <?= $row['status'] == 'paid' ? 'bg-success' : ($row['status'] == 'partial' ? 'bg-warning' : 'bg-danger') ?>"><?= ucfirst($row['status']) ?></span></td>
          <td><?= $row['paid_at'] ?: '—' ?></td>
          <td><?= htmlspecialchars($row['comment']) ?></td>
          <td class="no-print">
            <form method="POST" action="../actions/mark_installment_paid.php" class="d-inline">
              <input type="hidden" name="installment_id" value="<?= $row['id'] ?>">
              <div class="input-group input-group-sm mb-2">
                <span class="input-group-text">₨</span>
                <input type="number" step="0.01" min="0.01" max="<?= $due ?>" name="paid_amount" class="form-control paid-amount-input" placeholder="<?= number_format($due,2) ?>" value="<?= ($paid > 0 ? $paid : '') ?>" oninput="if(this.value <= 0) this.value=''">
              </div>
              <input type="text" name="comment" class="form-control form-control-sm mb-2" placeholder="Add comment" value="<?= htmlspecialchars($row['comment']) ?>">
              <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check-circle"></i> Save Payment</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
        <tr class="table-primary">
          <td colspan="2"><strong>Totals (All Installments)</strong></td>
          <td colspan="2">
            <strong>Due:</strong> ₨<?= number_format($total_due_all,2) ?> | 
            <strong>Paid:</strong> ₨<?= number_format($total_paid_all,2) ?> | 
            <strong>Remaining:</strong> ₨<?= number_format($total_due_all-$total_paid_all,2) ?>
          </td>
          <td colspan="3"></td>
        </tr>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination -->
  <?php if ($total_pages > 1 && $limit > 0): ?>
  <nav aria-label="Installments pagination" class="no-print">
    <ul class="pagination justify-content-center">
      <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?sale_id=<?= $sale_id ?>&page=1<?= isset($_GET['status']) ? '&status='.$_GET['status'] : '' ?><?= isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '' ?><?= isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '' ?>" aria-label="First">
          <span aria-hidden="true">&laquo;&laquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?sale_id=<?= $sale_id ?>&page=<?= $page-1 ?><?= isset($_GET['status']) ? '&status='.$_GET['status'] : '' ?><?= isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '' ?><?= isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '' ?>" aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>
      <?php endif; ?>
      
      <?php
      // Show page numbers
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);
      
      if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="?sale_id='.$sale_id.'&page=1'.(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '').(isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '').'">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
      }
      
      for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<li class="page-item '.$active.'"><a class="page-link" href="?sale_id='.$sale_id.'&page='.$i.(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '').(isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '').'">'.$i.'</a></li>';
      }
      
      if ($end < $total_pages) {
        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        echo '<li class="page-item"><a class="page-link" href="?sale_id='.$sale_id.'&page='.$total_pages.(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '').(isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '').'">'.$total_pages.'</a></li>';
      }
      ?>
      
      <?php if ($page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="?sale_id=<?= $sale_id ?>&page=<?= $page+1 ?><?= isset($_GET['status']) ? '&status='.$_GET['status'] : '' ?><?= isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '' ?><?= isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '' ?>" aria-label="Next">
          <span aria-hidden="true">&raquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?sale_id=<?= $sale_id ?>&page=<?= $total_pages ?><?= isset($_GET['status']) ? '&status='.$_GET['status'] : '' ?><?= isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '' ?><?= isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '' ?>" aria-label="Last">
          <span aria-hidden="true">&raquo;&raquo;</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<script>
// Prevent paid amount > due amount
document.querySelectorAll('.paid-amount-input').forEach(function(input) {
  input.addEventListener('change', function() {
    var due = parseFloat(this.getAttribute('max'));
    var val = parseFloat(this.value);
    if (val > due) {
      alert('Paid amount should not be greater than due amount!');
      this.value = '';
      this.focus();
    }
  });
});
</script>

<style>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>

<?php include '../includes/footer.php'; ?>