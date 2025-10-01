<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

$rent_id = isset($_GET['rent_id']) && is_numeric($_GET['rent_id']) ? (int)$_GET['rent_id'] : 0;

if ($rent_id == 0) {
    echo "<div class='alert alert-danger'>Invalid access. Rent ID is required.</div>";
    include '../includes/footer.php';
    exit;
}

// Get current user info to check if they're a customer
$user_query = "SELECT u.*, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

// If user is a customer, ensure they can only view their own rents
if ($current_user['role_name'] === 'customer') {
    $customer_id = $_SESSION['customer_id'];

    // Verify that this rent belongs to the customer
    $rent_check_query = "SELECT r.id FROM rents r WHERE r.id = ? AND r.customer_id = ?";
    $check_stmt = $conn->prepare($rent_check_query);
    $check_stmt->bind_param("ii", $rent_id, $customer_id);
    $check_stmt->execute();
    $rent_check = $check_stmt->get_result()->fetch_assoc();

    if (!$rent_check) {
        echo "<div class='alert alert-danger'>Access denied. You can only view your own rent records.</div>";
        include '../includes/footer.php';
        exit;
    }
}

$rentQ = $conn->prepare("SELECT r.*, c.name AS customer,
         c.guarantor_1, c.guarantor_1_phone, c.guarantor_1_address,
         c.guarantor_2, c.guarantor_2_phone, c.guarantor_2_address
         FROM rents r JOIN customers c ON c.id=r.customer_id WHERE r.id=?");
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

  <!-- Guarantor Information -->
  <?php if ($rent['guarantor_1'] || $rent['guarantor_2']): ?>
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Guarantor Information</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <?php if ($rent['guarantor_1']): ?>
        <div class="col-md-6 mb-3">
          <div class="border rounded p-3">
            <h6 class="text-primary mb-2"><i class="bi bi-person-check me-2"></i>Guarantor 1</h6>
            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($rent['guarantor_1']) ?></p>
            <?php if ($rent['guarantor_1_phone']): ?>
            <p class="mb-1">
              <strong>Phone:</strong>
              <span id="g1-phone-display"><?= htmlspecialchars($rent['guarantor_1_phone']) ?></span>
              <button class="btn btn-sm btn-outline-success ms-2" onclick="callGuarantor('<?= htmlspecialchars($rent['guarantor_1_phone']) ?>')" title="Call Guarantor">
                <i class="bi bi-telephone-fill"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary ms-1" onclick="copyToClipboard('g1-phone-display')" title="Copy Phone">
                <i class="bi bi-clipboard"></i>
              </button>
            </p>
            <?php endif; ?>
            <?php if ($rent['guarantor_1_address']): ?>
            <p class="mb-0"><strong>Address:</strong> <?= htmlspecialchars($rent['guarantor_1_address']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($rent['guarantor_2']): ?>
        <div class="col-md-6 mb-3">
          <div class="border rounded p-3">
            <h6 class="text-primary mb-2"><i class="bi bi-person-check me-2"></i>Guarantor 2</h6>
            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($rent['guarantor_2']) ?></p>
            <?php if ($rent['guarantor_2_phone']): ?>
            <p class="mb-1">
              <strong>Phone:</strong>
              <span id="g2-phone-display"><?= htmlspecialchars($rent['guarantor_2_phone']) ?></span>
              <button class="btn btn-sm btn-outline-success ms-2" onclick="callGuarantor('<?= htmlspecialchars($rent['guarantor_2_phone']) ?>')" title="Call Guarantor">
                <i class="bi bi-telephone-fill"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary ms-1" onclick="copyToClipboard('g2-phone-display')" title="Copy Phone">
                <i class="bi bi-clipboard"></i>
              </button>
            </p>
            <?php endif; ?>
            <?php if ($rent['guarantor_2_address']): ?>
            <p class="mb-0"><strong>Address:</strong> <?= htmlspecialchars($rent['guarantor_2_address']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
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

  // Auto-apply overdue filter if coming from notifications
  if (isset($_GET['filter_overdue']) && $_GET['filter_overdue'] == '1') {
    $filter_sql .= " AND status IN ('unpaid', 'partial') AND rent_date < CURDATE()";
  }

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
            <?php if (in_array($row['status'], ['paid', 'partial'])): ?>
            <button class="btn btn-sm btn-info mt-1" onclick="printReceiptDirectRent('<?= $row['id'] ?>', '<?= $row['rent_date'] ?>', '<?= $due ?>', '<?= $paid ?>', '<?= $row['status'] ?>', '<?= $row['paid_at'] ?>', '<?= htmlspecialchars(addslashes($row['comment'])) ?>')"><i class="bi bi-receipt"></i> Receipt</button>
            <?php endif; ?>
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
  // For monthly rents, apply overdue filter if coming from notifications
  $monthly_sql = "SELECT * FROM rent_payments WHERE rent_id=?";
  $monthly_params = [$rent_id];
  $monthly_types = "i";

  if (isset($_GET['filter_overdue']) && $_GET['filter_overdue'] == '1') {
    $monthly_sql .= " AND status IN ('unpaid', 'partial') AND rent_date < CURDATE()";
  }

  $monthly_sql .= " LIMIT 1";

  $payQ = $conn->prepare($monthly_sql);
  $payQ->bind_param($monthly_types, ...$monthly_params);
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
            <?php if (in_array($row['status'], ['paid', 'partial'])): ?>
            <button class="btn btn-sm btn-info mt-1" onclick="printReceiptDirectRent('<?= $row['id'] ?>', '<?= $rent['start_date'] ?>', '<?= $due ?>', '<?= $paid ?>', '<?= $row['status'] ?>', '<?= $row['paid_at'] ?>', '<?= htmlspecialchars(addslashes($row['comment'])) ?>')"><i class="bi bi-receipt"></i> Receipt</button>
            <?php endif; ?>
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
const BASE_URL = '<?= BASE_URL ?>';

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

// Call guarantor function
function callGuarantor(phone) {
    if (phone) {
        window.location.href = 'tel:' + phone;
    }
}

// Receipt Print Handler for Rent
function printReceiptDirectRent(id, rentDate, amount, paid, status, paidAt, comment) {
  const content = `
    <div style="text-align: center; margin-bottom: 20px;">
      <div style="display: flex; align-items: center; justify-content: center;">
        <img src="${BASE_URL}/assets/images/0929-yellow.png" alt="Jahangir Autos" height="40" style="margin-right: 10px;">
        <div>
          <div style="font-weight: bold; font-size: 18px;">Jahangir Autos & Electronics</div>
          <div style="font-size: 14px;">Printed on ${new Date().toLocaleString()}</div>
        </div>
      </div>
    </div>
    <h4 style="text-align: center; margin-bottom: 20px;">Rent Payment Receipt</h4>
    <table style="width: 100%; margin-bottom: 20px;">
      <tr>
        <td style="width: 20%;"><strong>Customer:</strong></td>
        <td style="width: 30%;"><?= htmlspecialchars($rent['customer']) ?></td>
        <td style="width: 20%;"><strong>Payment ID:</strong></td>
        <td style="width: 30%;">${id}</td>
      </tr>
      <tr>
        <td><strong>Product:</strong></td>
        <td><?= htmlspecialchars($rent['product_name']) ?></td>
        <td><strong>Rent Date:</strong></td>
        <td>${rentDate}</td>
      </tr>
      <tr>
        <td><strong>Rent Type:</strong></td>
        <td><?= ucfirst($rent['rent_type']) ?></td>
        <td><strong>Status:</strong></td>
        <td>${status.charAt(0).toUpperCase() + status.slice(1)}</td>
      </tr>
      <tr>
        <td><strong>Start Date:</strong></td>
        <td><?= $rent['start_date'] ?></td>
        <td><strong>End Date:</strong></td>
        <td><?= $rent['end_date'] ?></td>
      </tr>
    </table>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
      <tr>
        <th style="border: 1px solid #000; padding: 8px; text-align: left;">Due Amount</th>
        <td style="border: 1px solid #000; padding: 8px;">₨${parseFloat(amount).toLocaleString()}</td>
      </tr>
      <tr>
        <th style="border: 1px solid #000; padding: 8px; text-align: left;">Paid Amount</th>
        <td style="border: 1px solid #000; padding: 8px;">₨${parseFloat(paid).toLocaleString()}</td>
      </tr>
      <tr>
        <th style="border: 1px solid #000; padding: 8px; text-align: left;">Remaining</th>
        <td style="border: 1px solid #000; padding: 8px;">₨${(parseFloat(amount) - parseFloat(paid)).toLocaleString()}</td>
      </tr>
      <tr>
        <th style="border: 1px solid #000; padding: 8px; text-align: left;">Paid At</th>
        <td style="border: 1px solid #000; padding: 8px;">${paidAt || 'N/A'}</td>
      </tr>
      <tr>
        <th style="border: 1px solid #000; padding: 8px; text-align: left;">Comment</th>
        <td style="border: 1px solid #000; padding: 8px;">${comment || 'N/A'}</td>
      </tr>
    </table>
    <div style="margin-top: 40px; display: flex; justify-content: space-between;">
      <div>
        <p>Received by: _______________________________</p>
      </div>
      <div>
        <p>Signature: _______________________________</p>
      </div>
    </div>
  `;
  const printWindow = window.open('', '_blank');
  printWindow.document.write('<html><head><title>Rent Payment Receipt</title>');
  printWindow.document.write('<style>body { font-family: Arial, sans-serif; margin: 0.5in; } @page { margin: 0; @bottom-center { content: none; } @bottom-left { content: none; } @bottom-right { content: none; } }</style>');
  printWindow.document.write('</head><body>');
  printWindow.document.write(content);
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  setTimeout(() => printWindow.print(), 100);
}

// Copy to clipboard function
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const text = element.textContent || element.innerText;
        navigator.clipboard.writeText(text).then(function() {
            // Show success feedback
            const originalText = element.innerHTML;
            element.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>' + text;
            setTimeout(() => {
                element.innerHTML = originalText;
            }, 2000);
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            alert('Failed to copy to clipboard');
        });
    }
}
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
