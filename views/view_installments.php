<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

$sale_id = isset($_GET['sale_id']) && is_numeric($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

if ($sale_id == 0) {
    echo "<div class='alert alert-danger'>Invalid access. Sale ID is required.</div>";
    include '../includes/footer.php';
    exit;
}

// Get current user info to check if they're a customer
$user_query = "SELECT u.*, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

// If user is a customer, ensure they can only view their own sales
if ($current_user['role_name'] === 'customer') {
    $customer_id = $_SESSION['customer_id'];

    // Verify that this sale belongs to the customer
    $sale_check_query = "SELECT s.id FROM sales s WHERE s.id = ? AND s.customer_id = ?";
    $check_stmt = $conn->prepare($sale_check_query);
    $check_stmt->bind_param("ii", $sale_id, $customer_id);
    $check_stmt->execute();
    $sale_check = $check_stmt->get_result()->fetch_assoc();

    if (!$sale_check) {
        echo "<div class='alert alert-danger'>Access denied. You can only view your own sale records.</div>";
        include '../includes/footer.php';
        exit;
    }
}

$saleQ   = $conn->prepare("
  SELECT s.sale_date,c.name,p.name,s.monthly_installment,
         c.guarantor_1, c.guarantor_1_phone, c.guarantor_1_address,
         c.guarantor_2, c.guarantor_2_phone, c.guarantor_2_address
  FROM sales s
  JOIN customers c ON c.id=s.customer_id
  JOIN products p  ON p.id=s.product_id
  WHERE s.id=?
 ");
$saleQ->bind_param("i",$sale_id);
$saleQ->execute();
$saleQ->bind_result($sd,$cn,$pn,$mi,$g1,$g1_phone,$g1_addr,$g2,$g2_phone,$g2_addr);
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

// Auto-apply overdue filter if coming from notifications
if (isset($_GET['filter_overdue']) && $_GET['filter_overdue'] == '1') {
  $filter_sql .= " AND status IN ('unpaid', 'partial') AND due_date < CURDATE()";
}

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

  <!-- Guarantor Information -->
  <?php if ($g1 || $g2): ?>
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Guarantor Information</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <?php if ($g1): ?>
        <div class="col-md-6 mb-3">
          <div class="border rounded p-3">
            <h6 class="text-primary mb-2"><i class="bi bi-person-check me-2"></i>Guarantor 1</h6>
            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($g1) ?></p>
            <?php if ($g1_phone): ?>
            <p class="mb-1">
              <strong>Phone:</strong>
              <span id="g1-phone-display"><?= htmlspecialchars($g1_phone) ?></span>
              <button class="btn btn-sm btn-outline-success ms-2" onclick="callGuarantor('<?= htmlspecialchars($g1_phone) ?>')" title="Call Guarantor">
                <i class="bi bi-telephone-fill"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary ms-1" onclick="copyToClipboard('g1-phone-display')" title="Copy Phone">
                <i class="bi bi-clipboard"></i>
              </button>
            </p>
            <?php endif; ?>
            <?php if ($g1_addr): ?>
            <p class="mb-0"><strong>Address:</strong> <?= htmlspecialchars($g1_addr) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($g2): ?>
        <div class="col-md-6 mb-3">
          <div class="border rounded p-3">
            <h6 class="text-primary mb-2"><i class="bi bi-person-check me-2"></i>Guarantor 2</h6>
            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($g2) ?></p>
            <?php if ($g2_phone): ?>
            <p class="mb-1">
              <strong>Phone:</strong>
              <span id="g2-phone-display"><?= htmlspecialchars($g2_phone) ?></span>
              <button class="btn btn-sm btn-outline-success ms-2" onclick="callGuarantor('<?= htmlspecialchars($g2_phone) ?>')" title="Call Guarantor">
                <i class="bi bi-telephone-fill"></i>
              </button>
              <button class="btn btn-sm btn-outline-secondary ms-1" onclick="copyToClipboard('g2-phone-display')" title="Copy Phone">
                <i class="bi bi-clipboard"></i>
              </button>
            </p>
            <?php endif; ?>
            <?php if ($g2_addr): ?>
            <p class="mb-0"><strong>Address:</strong> <?= htmlspecialchars($g2_addr) ?></p>
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
      <h5 class="mb-0">Filter Installments</h5>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <input type="hidden" name="sale_id" value="<?= htmlspecialchars($sale_id) ?>">
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select" <?= check_permission('view_installments', 'view') ? '' : 'disabled' ?>>
            <option value="">All Status</option>
            <option value="unpaid" <?= (isset($_GET['status']) && $_GET['status']==='unpaid')?'selected':'' ?>>Unpaid</option>
            <option value="partial" <?= (isset($_GET['status']) && $_GET['status']==='partial')?'selected':'' ?>>Partial</option>
            <option value="paid" <?= (isset($_GET['status']) && $_GET['status']==='paid')?'selected':'' ?>>Paid</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">From Date</label>
          <input type="date" name="from_date" class="form-control" value="<?= isset($_GET['from_date']) ? htmlspecialchars($_GET['from_date']) : '' ?>" <?= check_permission('view_installments', 'view') ? '' : 'disabled' ?>>
        </div>
        <div class="col-md-3">
          <label class="form-label">To Date</label>
          <input type="date" name="to_date" class="form-control" value="<?= isset($_GET['to_date']) ? htmlspecialchars($_GET['to_date']) : '' ?>" <?= check_permission('view_installments', 'view') ? '' : 'disabled' ?>>
        </div>
        <div class="col-md-3">
          <label class="form-label">&nbsp;</label>
          <div class="d-flex">
            <a href="?sale_id=<?= htmlspecialchars($sale_id) ?>" class="btn btn-secondary me-2" <?= check_permission('view_installments', 'view') ? '' : 'style="pointer-events: none; opacity: 0.5;" onclick="return false;"' ?>>Clear</a>
            <button type="submit" class="btn btn-primary" <?= check_permission('view_installments', 'view') ? '' : 'disabled' ?>>Filter</button>
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
                <input type="number" step="0.01" min="0.01" max="<?= $due ?>" name="paid_amount" class="form-control paid-amount-input" placeholder="<?= number_format($due,2) ?>" value="<?= ($paid > 0 ? $paid : '') ?>" oninput="if(this.value <= 0) this.value=''" <?= check_permission('view_installments', 'paid_amount') ? '' : 'disabled readonly' ?> title="<?= check_permission('view_installments', 'paid_amount') ? '' : 'You do not have permission to edit paid amounts' ?>">
              </div>
              <input type="text" name="comment" class="form-control form-control-sm mb-2" placeholder="Add comment" value="<?= htmlspecialchars($row['comment']) ?>">
              <button type="submit" class="btn btn-sm btn-success w-100" <?= check_permission('view_installments', 'save') ? '' : 'disabled' ?> title="<?= check_permission('view_installments', 'save') ? '' : 'You do not have permission to save payments' ?>"><i class="bi bi-check-circle"></i> Save Payment</button>
            </form>
            <?php if (in_array($row['status'], ['paid', 'partial'])): ?>
            <button class="btn btn-sm btn-info mt-2 w-100" onclick="printReceiptDirect('<?= $row['id'] ?>', '<?= $row['due_date'] ?>', '<?= $due ?>', '<?= $paid ?>', '<?= $row['status'] ?>', '<?= $row['paid_at'] ?>', '<?= htmlspecialchars(addslashes($row['comment'])) ?>')"><i class="bi bi-receipt"></i> Receipt</button>
            <?php endif; ?>
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
const BASE_URL = '<?= BASE_URL ?>';

// Receipt Print Handler
function printReceiptDirect(id, dueDate, amount, paid, status, paidAt, comment) {
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
    <h4 style="text-align: center; margin-bottom: 20px;">Installment Payment Receipt</h4>
    <table style="width: 100%; margin-bottom: 20px;">
      <tr>
        <td style="width: 20%;"><strong>Customer:</strong></td>
        <td style="width: 30%;"><?= htmlspecialchars($cn) ?></td>
        <td style="width: 20%;"><strong>Installment ID:</strong></td>
        <td style="width: 30%;">${id}</td>
      </tr>
      <tr>
        <td><strong>Product:</strong></td>
        <td><?= htmlspecialchars($pn) ?></td>
        <td><strong>Due Date:</strong></td>
        <td>${dueDate}</td>
      </tr>
      <tr>
        <td><strong>Sale Date:</strong></td>
        <td><?= $sd ?></td>
        <td><strong>Status:</strong></td>
        <td>${status.charAt(0).toUpperCase() + status.slice(1)}</td>
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
  printWindow.document.write('<html><head><title>Payment Receipt</title>');
  printWindow.document.write('<style>body { font-family: Arial, sans-serif; margin: 0.5in; } @page { margin: 0; @bottom-center { content: none; } @bottom-left { content: none; } @bottom-right { content: none; } }</style>');
  printWindow.document.write('</head><body>');
  printWindow.document.write(content);
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  setTimeout(() => printWindow.print(), 100);
}

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

<style>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>

<?php include '../includes/footer.php'; ?>