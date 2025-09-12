<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

$rent_id = (int)$_GET['rent_id'];
$rentQ = $conn->prepare("SELECT r.*, c.name AS customer FROM rents r JOIN customers c ON c.id=r.customer_id WHERE r.id=?");
$rentQ->bind_param("i", $rent_id);
$rentQ->execute();
$rent = $rentQ->get_result()->fetch_assoc();
$rentQ->close();

if (!$rent) {
  echo "<div class='alert alert-danger'>Rent record not found.</div>";
  include '../includes/footer.php';
  exit;
}

$days = 1;
if ($rent['rent_type'] === 'daily') {
  $start = new DateTime($rent['start_date']);
  $end = new DateTime($rent['end_date']);
  $days = $start->diff($end)->days + 1;
}
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between mb-2">
    <h2 class="mb-0" style="color: #0d6efd; font-weight: bold;">Rent Details</h2>
    <div class="d-flex gap-2 no-print">
      <button class="btn btn-outline-secondary" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
  </div>
  
  <!-- Rent Info Card -->
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><?= htmlspecialchars($rent['product_name']) ?> - <?= htmlspecialchars($rent['customer']) ?></h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3"><strong>Type:</strong> <?= ucfirst($rent['rent_type']) ?></div>
        <div class="col-md-3"><strong>Start:</strong> <?= $rent['start_date'] ?></div>
        <div class="col-md-3"><strong>End:</strong> <?= $rent['end_date'] ?></div>
        <div class="col-md-3"><strong>Days:</strong> <?= $days ?></div>
      </div>
    </div>
  </div>
  <!-- Filter Form -->
  <div class="card mb-4 no-print">
    <div class="card-header">
      <h5 class="mb-0">Filter Payments</h5>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <input type="hidden" name="rent_id" value="<?= htmlspecialchars($rent_id) ?>">
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select" <?= check_permission('view_rent', 'view') ? '' : 'disabled' ?>>
            <option value="">All Status</option>
            <option value="unpaid" <?= (isset($_GET['status']) && $_GET['status']==='unpaid')?'selected':'' ?>>Unpaid</option>
            <option value="partial" <?= (isset($_GET['status']) && $_GET['status']==='partial')?'selected':'' ?>>Partial</option>
            <option value="paid" <?= (isset($_GET['status']) && $_GET['status']==='paid')?'selected':'' ?>>Paid</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Date</label>
          <input type="date" name="filter_date" class="form-control" value="<?= isset($_GET['filter_date']) ? htmlspecialchars($_GET['filter_date']) : '' ?>" <?= check_permission('view_rent', 'view') ? '' : 'disabled' ?>>
        </div>
        <div class="col-md-4">
          <label class="form-label">&nbsp;</label>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" <?= check_permission('view_rent', 'view') ? '' : 'disabled' ?>>Filter</button>
            <a href="?rent_id=<?= htmlspecialchars($rent_id) ?>" class="btn btn-secondary" <?= check_permission('view_rent', 'view') ? '' : 'style="pointer-events: none; opacity: 0.5;" onclick="return false;"' ?>>Clear</a>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered">
      <thead>
        <tr style="background-color: #0d6efd !important;">
<?php if($rent['rent_type']==='daily'): ?>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Day</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Date</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Rent</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Status</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Paid At</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;" class="no-print">Action</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Comment</th>
<?php else: ?>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Date</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Rent</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Status</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Paid At</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;" class="no-print">Action</th>
          <th style="color: white; font-weight: bold; background-color: #0d6efd !important;">Comment</th>
<?php endif; ?>
        </tr>
      </thead>
      <tbody>
<?php
if($rent['rent_type']==='daily') {
  // Pagination variables
  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $limit = 20;
  $offset = ($page - 1) * $limit;
  
  // Build filter query with pagination
  $filter_sql = "SELECT *, COUNT(*) OVER() as total_records FROM rent_payments WHERE rent_id=?";
  $params = [$rent_id];
  $types = "i";
  if (!empty($_GET['status'])) {
    $filter_sql .= " AND status=?";
    $params[] = $_GET['status'];
    $types .= "s";
  }
  if (!empty($_GET['filter_date'])) {
    $filter_sql .= " AND rent_date=?";
    $params[] = $_GET['filter_date'];
    $types .= "s";
  }
  $filter_sql .= " ORDER BY rent_date LIMIT ? OFFSET ?";
  $params[] = $limit;
  $params[] = $offset;
  $types .= "ii";
  
  $payQ = $conn->prepare($filter_sql);
  $payQ->bind_param($types, ...$params);
  $payQ->execute();
  $res = $payQ->get_result();
  
  $total_records = 0;
  $payments = [];
  while($row = $res->fetch_assoc()) {
    $total_records = $row['total_records'];
    unset($row['total_records']);
    $payments[] = $row;
  }
  $payQ->close();
  
  $total_pages = ceil($total_records / $limit);
  $total_due = 0;
  $i = ($page - 1) * $limit + 1;
  // Calculate totals for all rent days (not just filtered)
  $sumQ = $conn->prepare("SELECT SUM(amount) as total_due, SUM(paid_amount) as total_paid FROM rent_payments WHERE rent_id=?");
  $sumQ->bind_param("i", $rent_id);
  $sumQ->execute();
  $sumQ->bind_result($total_due_all, $total_paid_all);
  $sumQ->fetch();
  $sumQ->close();
  foreach($payments as $row):
    if (is_null($row['paid_amount'])) {
      $paid = '';
      $due = $row['amount'];
      $remaining = $due; // nothing paid yet
    } else {
      $paid = $row['paid_amount'];
      $due = $row['amount'];
      $remaining = $due - $paid;
    }
    $total_due += $due;
?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= $row['rent_date'] ?></td>
          <td>
            Due: <?= number_format($due,0) ?><br>
            Paid: <?= ($paid === '' ? '' : number_format($paid,0)) ?><br>
            Remain: <?= ($remaining === '' ? '' : number_format($remaining,0)) ?>
          </td>
          <td><?= ucfirst($row['status']) ?></td>
          <td><?= $row['paid_at'] ?: '—' ?></td>
          <td>
            <form method="POST" action="../actions/mark_rent_paid.php" class="d-inline">
              <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
                <input type="number" step="0.01" min="0.01" max="<?= $due ?>" name="paid_amount" class="form-control form-control-sm mb-1 paid-amount-input" placeholder="Amount (<?= number_format($due,2) ?>)" value="<?= $paid ?>" oninput="if(this.value <= 0) this.value=''" <?= check_permission('view_rent', 'paid_amount') ? '' : 'disabled readonly' ?> title="<?= check_permission('view_rent', 'paid_amount') ? '' : 'You do not have permission to edit paid amounts' ?>">
                <input type="text" name="comment" class="form-control form-control-sm mb-1" placeholder="Comment" value="<?= htmlspecialchars($row['comment']) ?>">
              <button type="submit" class="btn btn-sm btn-success" <?= check_permission('view_rent', 'save') ? '' : 'disabled' ?> title="<?= check_permission('view_rent', 'save') ? '' : 'You do not have permission to save payments' ?>">Save</button>
            </form>
          </td>
          <td><?= htmlspecialchars($row['comment']) ?></td>
        </tr>
<?php endforeach; ?>
        <tr class="table-info">
          <td colspan="2"><b>Totals (All Days)</b></td>
          <td colspan="2">Due: <?= number_format($total_due_all,0) ?> | Paid: <?= number_format($total_paid_all,0) ?> | Remain: <?= number_format($total_due_all-$total_paid_all,0) ?></td>
          <td colspan="3"></td>
        </tr>
<?php 
} else {
  $payQ = $conn->prepare("SELECT * FROM rent_payments WHERE rent_id=? LIMIT 1");
  $payQ->bind_param("i", $rent_id);
  $payQ->execute();
  $row = $payQ->get_result()->fetch_assoc();
  $due = $rent['total_rent'];
  $paid = $row['paid_amount'];
  $remaining = $due - $paid;
?>
        <tr>
          <td><?= $rent['start_date'] ?></td>
          <td>
            Due: <?= number_format($due,0) ?><br>
            Paid: <?= number_format($paid,0) ?><br>
            Remain: <?= number_format($remaining,0) ?>
          </td>
          <td><?= ucfirst($row['status']) ?></td>
          <td><?= $row['paid_at'] ?: '—' ?></td>
          <td>
            <form method="POST" action="../actions/mark_rent_paid.php" class="d-inline">
              <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
                <input type="number" step="0.01" name="paid_amount" class="form-control form-control-sm mb-1" placeholder="Amount (<?= number_format($due,2) ?>)" value="<?= ($paid > 0 ? $paid : '') ?>" <?= check_permission('view_rent', 'paid_amount') ? '' : 'disabled readonly' ?> title="<?= check_permission('view_rent', 'paid_amount') ? '' : 'You do not have permission to edit paid amounts' ?>">
              <input type="text" name="comment" class="form-control form-control-sm mb-1" placeholder="Comment" value="<?= htmlspecialchars($row['comment']) ?>">
              <button type="submit" class="btn btn-sm btn-success" <?= check_permission('view_rent', 'save') ? '' : 'disabled' ?> title="<?= check_permission('view_rent', 'save') ? '' : 'You do not have permission to save payments' ?>">Save</button>
             </form>
           </td>
           <td><?= htmlspecialchars($row['comment']) ?></td>
        </tr>
        <tr class="table-info">
          <td><b>Totals</b></td>
          <td colspan="2">Due: <?= number_format($due,0) ?> | Paid: <?= number_format($paid,0) ?> | Remain: <?= number_format($remaining,0) ?></td>
          <td colspan="3"></td>
        </tr>
<?php $payQ->close(); } ?>
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
      </tbody>
    </table>
  </div>
  
  <!-- Pagination -->
  <?php if ($rent['rent_type'] === 'daily' && $total_pages > 1): ?>
  <nav aria-label="Rent payments pagination" class="no-print">
    <ul class="pagination justify-content-center">
      <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?rent_id=<?= $rent_id ?>&page=1<?= isset($_GET['status']) ? '&status='.$_GET['status'] : '' ?><?= isset($_GET['filter_date']) ? '&filter_date='.$_GET['filter_date'] : '' ?>" aria-label="First">
          <span aria-hidden="true">&laquo;&laquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?rent_id=<?= $rent_id ?>&page=<?= $page-1 ?><?= isset($_GET['status']) ? '&status='.$_GET['status'] : '' ?><?= isset($_GET['filter_date']) ? '&filter_date='.$_GET['filter_date'] : '' ?>" aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>
      <?php endif; ?>
      
      <?php
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);
      
      for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<li class="page-item '.$active.'"><a class="page-link" href="?rent_id='.$rent_id.'&page='.$i.(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['filter_date']) ? '&filter_date='.$_GET['filter_date'] : '').'">'.$i.'</a></li>';
      }
      ?>
      
      <?php if ($page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="?rent_id=<?= $rent_id ?>&page=<?= $page+1 ?><?= isset($_GET['status']) ? '&status='.$_GET['status'] : '' ?><?= isset($_GET['filter_date']) ? '&filter_date='.$_GET['filter_date'] : '' ?>" aria-label="Next">
          <span aria-hidden="true">&raquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?rent_id=<?= $rent_id ?>&page=<?= $total_pages ?><?= isset($_GET['status']) ? '&status='.$_GET['status'] : '' ?><?= isset($_GET['filter_date']) ? '&filter_date='.$_GET['filter_date'] : '' ?>" aria-label="Last">
          <span aria-hidden="true">&raquo;&raquo;</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<style>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>

<?php include '../includes/footer.php'; ?>
