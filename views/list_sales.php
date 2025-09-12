<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && $_GET['limit'] === 'all' ? 0 : 20;
$offset = ($page - 1) * ($limit > 0 ? $limit : 0);

// Build SQL query with filters
$sql = "SELECT s.id, s.sale_date, c.name cust, p.name prod, p.model,
         s.total_amount, s.down_payment, s.monthly_installment, s.months, s.interest_rate,
         (s.total_amount + ((s.total_amount * s.interest_rate) / 100)) as due_amount,
         COALESCE(SUM(i.paid_amount), 0) as total_paid,
         ((s.total_amount + ((s.total_amount * s.interest_rate) / 100)) - COALESCE(SUM(i.paid_amount), 0)) as remaining_amount,
         COUNT(*) OVER() as total_records
  FROM sales s
  JOIN customers c ON c.id=s.customer_id
  JOIN products p  ON p.id=s.product_id
  LEFT JOIN installments i ON s.id=i.sale_id
  WHERE 1=1";

$params = [];
$types = "";

// Add filters
if (!empty($_GET['id'])) {
  $sql .= " AND s.id = ?";
  $params[] = $_GET['id'];
  $types .= "i";
}

if (!empty($_GET['from_date'])) {
  $sql .= " AND s.sale_date >= ?";
  $params[] = $_GET['from_date'];
  $types .= "s";
}

if (!empty($_GET['to_date'])) {
  $sql .= " AND s.sale_date <= ?";
  $params[] = $_GET['to_date'];
  $types .= "s";
}

if (!empty($_GET['customer_id'])) {
  $sql .= " AND s.customer_id = ?";
  $params[] = $_GET['customer_id'];
  $types .= "i";
}

if (!empty($_GET['product_id'])) {
  $sql .= " AND s.product_id = ?";
  $params[] = $_GET['product_id'];
  $types .= "i";
}

if (!empty($_GET['model'])) {
  $sql .= " AND p.model LIKE ?";
  $params[] = '%' . $_GET['model'] . '%';
  $types .= "s";
}

$sql .= " GROUP BY s.id, s.sale_date, c.name, p.name, p.model, s.total_amount, s.down_payment, s.monthly_installment, s.months, s.interest_rate";

if ($limit > 0) {
  $sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
  $params[] = $limit;
  $params[] = $offset;
  $types .= "ii";
} else {
  $sql .= " ORDER BY s.created_at DESC";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$total_records = 0;
$sales = [];
while($row = $result->fetch_assoc()) {
  $total_records = $row['total_records'];
  unset($row['total_records']);
  $sales[] = $row;
}

$total_pages = $limit > 0 ? ceil($total_records / $limit) : 1;

// Calculate total pages with default limit for "Print All" button
$total_pages_default = ceil($total_records / 20);
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between mb-2">
    <h2 class="mb-0" style="color: #0d6efd; font-weight: bold;">Sales Records</h2>
    <div class="d-flex gap-2 no-print">
      <?php if (check_permission('sales', 'add')): ?>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal">
        <i class="bi bi-plus-circle"></i> Add Sale
      </button>
      <?php endif; ?>
      <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      <?php if ($total_pages_default > 1): ?>
      <button class="btn btn-outline-secondary" onclick="printAllRecords()"><i class="bi bi-printer"></i> Print All</button>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Filter Form -->
  <div class="card mb-4 no-print">
    <div class="card-header">
      <h5 class="mb-0">Filter Sales</h5>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-2">
          <label class="form-label">ID</label>
          <input type="number" name="id" class="form-control" value="<?= isset($_GET['id']) ? htmlspecialchars($_GET['id']) : '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">From Date</label>
          <input type="date" name="from_date" class="form-control" value="<?= isset($_GET['from_date']) ? htmlspecialchars($_GET['from_date']) : '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">To Date</label>
          <input type="date" name="to_date" class="form-control" value="<?= isset($_GET['to_date']) ? htmlspecialchars($_GET['to_date']) : '' ?>">
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
        <div class="col-md-2">
          <label class="form-label">Product</label>
          <select name="product_id" class="form-select">
            <option value="">All Products</option>
            <?php
              $prods = $conn->query("SELECT id, name FROM products ORDER BY name");
              while($p = $prods->fetch_assoc()):
            ?>
              <option value="<?= $p['id'] ?>" <?= isset($_GET['product_id']) && $_GET['product_id'] == $p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Model</label>
          <input type="text" name="model" class="form-control" value="<?= isset($_GET['model']) ? htmlspecialchars($_GET['model']) : '' ?>">
        </div>
        <div class="col-md-12">
          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary me-2">Filter</button>
            <a href="<?= BASE_URL ?>/views/list_sales.php" class="btn btn-secondary">Clear</a>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr style="background-color: #007bff !important;">
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">#</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Date</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Customer</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Product</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Model</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Price</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Due</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Paid</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Remaining</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">DP</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Monthly</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Term</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;" class="text-center no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
<?php
  foreach($sales as $r):
?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= $r['sale_date'] ?></td>
        <td><?= htmlspecialchars($r['cust']) ?></td>
        <td><?= htmlspecialchars($r['prod']) ?></td>
        <td><?= isset($r['model']) ? htmlspecialchars($r['model']) : 'N/A' ?></td>
        <td><?= number_format($r['total_amount'],0) ?></td>
        <td><?= number_format($r['due_amount'],0) ?></td>
        <td><?= number_format($r['total_paid'],0) ?></td>
        <td><?= number_format($r['remaining_amount'],0) ?></td>
        <td><?= number_format($r['down_payment'],0) ?></td>
        <td><?= number_format($r['monthly_installment'],0) ?></td>
        <td><?= $r['months'] ?></td>
        <td class="text-center no-print">
          <?php if (check_permission('sales', 'edit')): ?>
          <button type="button" class="btn btn-sm btn-outline-primary"
                  onclick="editSale(<?= $r['id'] ?>)"
                  title="Edit Sale">
            <i class="bi bi-pencil"></i>
          </button>
          <?php endif; ?>
          <?php if (check_permission('view_installments', 'view')): ?>
          <a href="<?= BASE_URL ?>/views/view_installments.php?sale_id=<?= $r['id'] ?>"
             class="btn btn-sm btn-outline-info"
             title="View Installments">
            <i class="bi bi-eye"></i>
          </a>
          <?php endif; ?>
          <?php if (check_permission('sales', 'delete')): ?>
          <a href="<?= BASE_URL ?>/actions/delete_sale.php?sale_id=<?= $r['id'] ?>"
             class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Are you sure you want to delete this sale? This will also delete all associated installments.')"
             title="Delete Sale">
            <i class="bi bi-trash"></i>
          </a>
          <?php endif; ?>
        </td>
      </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination -->
  <?php if ($total_pages > 1 && $limit > 0): ?>
  <nav aria-label="Sales pagination" class="no-print">
    <ul class="pagination justify-content-center">
      <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?page=1<?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '' ?><?= isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '' ?><?= isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '' ?><?= isset($_GET['product_id']) ? '&product_id='.$_GET['product_id'] : '' ?>" aria-label="First">
          <span aria-hidden="true">&laquo;&laquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page-1 ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '' ?><?= isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '' ?><?= isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '' ?><?= isset($_GET['product_id']) ? '&product_id='.$_GET['product_id'] : '' ?>" aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>
      <?php endif; ?>
      
      <?php
      // Show page numbers
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);
      
      if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="?page=1'.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '').(isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '').(isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '').(isset($_GET['product_id']) ? '&product_id='.$_GET['product_id'] : '').'">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
      }
      
      for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '').(isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '').(isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '').(isset($_GET['product_id']) ? '&product_id='.$_GET['product_id'] : '').'">'.$i.'</a></li>';
      }
      
      if ($end < $total_pages) {
        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '').(isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '').(isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '').(isset($_GET['product_id']) ? '&product_id='.$_GET['product_id'] : '').'">'.$total_pages.'</a></li>';
      }
      ?>
      
      <?php if ($page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page+1 ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '' ?><?= isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '' ?><?= isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '' ?><?= isset($_GET['product_id']) ? '&product_id='.$_GET['product_id'] : '' ?>" aria-label="Next">
          <span aria-hidden="true">&raquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $total_pages ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['from_date']) ? '&from_date='.$_GET['from_date'] : '' ?><?= isset($_GET['to_date']) ? '&to_date='.$_GET['to_date'] : '' ?><?= isset($_GET['customer_id']) ? '&customer_id='.$_GET['customer_id'] : '' ?><?= isset($_GET['product_id']) ? '&product_id='.$_GET['product_id'] : '' ?>" aria-label="Last">
          <span aria-hidden="true">&raquo;&raquo;</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<!-- Add Sale Modal -->
<div class="modal fade" id="addSaleModal" tabindex="-1" aria-labelledby="addSaleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addSaleModalLabel">Record New Sale</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?= BASE_URL ?>/actions/insert_sale.php" method="POST">
        <div class="modal-body">
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
            <div class="col-md-4">
              <label class="form-label"><strong>Product</strong></label>
              <select name="product_id" id="addProductSelect" class="form-select" required>
                <option value="">Select Product</option>
                <?php
                  $prods = $conn->query("SELECT id, name, model, price, installment_months, interest_rate FROM products");
                  while($p = $prods->fetch_assoc()):
                ?>
                  <option 
                    value="<?= $p['id'] ?>"
                    data-price="<?= $p['price'] ?>"
                    data-months="<?= $p['installment_months'] ?>"
                    data-rate="<?= $p['interest_rate'] ?>"
                    data-model="<?= htmlspecialchars($p['model']) ?>">
                    <?= htmlspecialchars($p['name']) ?> - <?= htmlspecialchars($p['model']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label"><strong>Model</strong></label>
              <input type="text" id="addModel" name="model" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label"><strong>Price (PKR)</strong></label>
              <input type="number" step="0.01" id="addPrice" name="price" class="form-control">
              <input type="hidden" id="addOriginalPrice" name="original_price">
            </div>
            <div class="col-md-4">
              <label class="form-label"><strong>Term (months)</strong></label>
              <input type="number" id="addMonths" name="months" class="form-control">
              <input type="hidden" id="addOriginalMonths" name="original_months">
            </div>
            <div class="col-md-4">
              <label class="form-label"><strong>Interest Rate (%)</strong></label>
              <input type="number" step="0.01" id="addRate" name="rate" class="form-control">
              <input type="hidden" id="addOriginalRate" name="original_rate">
            </div>
            <div class="col-md-6">
              <label class="form-label"><strong>Down Payment (PKR)</strong></label>
              <input type="number" step="0.01" name="down_payment" id="addDownPayment" class="form-control" value="0.00">
            </div>
            <div class="col-md-6">
              <label class="form-label"><strong>Monthly Installment (PKR)</strong></label>
              <input type="text" id="addMonthlyInstallment" class="form-control" readonly>
            </div>
            <div class="col-12">
              <label class="form-label"><strong>Total Amount to be Paid in Installments (PKR)</strong></label>
              <input type="text" id="addTotalInstallmentAmount" class="form-control" readonly>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Save Sale</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Sale Modal -->
<div class="modal fade" id="editSaleModal" tabindex="-1" aria-labelledby="editSaleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editSaleModalLabel">Edit Sale</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?= BASE_URL ?>/actions/update_sale.php" method="POST" id="editSaleForm">
        <input type="hidden" name="sale_id" id="editSaleId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><strong>Customer</strong></label>
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
            <div class="col-md-4">
              <label class="form-label"><strong>Product</strong></label>
              <select name="product_id" id="editProductSelect" class="form-select" required>
                <option value="">Select Product</option>
                <?php
                  $prods = $conn->query("SELECT id, name, model, price, installment_months, interest_rate FROM products");
                  while($p = $prods->fetch_assoc()):
                ?>
                  <option 
                    value="<?= $p['id'] ?>"
                    data-price="<?= $p['price'] ?>"
                    data-months="<?= $p['installment_months'] ?>"
                    data-rate="<?= $p['interest_rate'] ?>"
                    data-model="<?= htmlspecialchars($p['model']) ?>">
                    <?= htmlspecialchars($p['name']) ?> - <?= htmlspecialchars($p['model']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label"><strong>Model</strong></label>
              <input type="text" id="editModel" name="model" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label"><strong>Price (PKR)</strong></label>
              <input type="number" step="0.01" id="editPrice" name="price" class="form-control">
              <input type="hidden" id="editOriginalPrice" name="original_price">
            </div>
            <div class="col-md-4">
              <label class="form-label"><strong>Term (months)</strong></label>
              <input type="number" id="editMonths" name="months" class="form-control">
              <input type="hidden" id="editOriginalMonths" name="original_months">
            </div>
            <div class="col-md-4">
              <label class="form-label"><strong>Interest Rate (%)</strong></label>
              <input type="number" step="0.01" id="editRate" name="rate" class="form-control">
              <input type="hidden" id="editOriginalRate" name="original_rate">
            </div>
            <div class="col-md-6">
              <label class="form-label"><strong>Down Payment (PKR)</strong></label>
              <input type="number" step="0.01" name="down_payment" id="editDownPayment" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label"><strong>Monthly Installment (PKR)</strong></label>
              <input type="text" id="editMonthlyInstallment" class="form-control" readonly>
            </div>
            <div class="col-12">
              <label class="form-label"><strong>Total Amount to be Paid in Installments (PKR)</strong></label>
              <input type="text" id="editTotalInstallmentAmount" class="form-control" readonly>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Update Sale</button>
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

// Add Sale Modal Functions
document.getElementById('addProductSelect').addEventListener('change', function(){
  let opt = this.selectedOptions[0];
  document.getElementById('addPrice').value = opt.dataset.price || '';
  document.getElementById('addOriginalPrice').value = opt.dataset.price || '';
  document.getElementById('addMonths').value = opt.dataset.months || '';
  document.getElementById('addOriginalMonths').value = opt.dataset.months || '';
  document.getElementById('addRate').value = opt.dataset.rate || '';
  document.getElementById('addOriginalRate').value = opt.dataset.rate || '';
  document.getElementById('addModel').value = opt.dataset.model || '';
  calculateAddMonthlyInstallment();
});

document.getElementById('addPrice').addEventListener('input', calculateAddMonthlyInstallment);
document.getElementById('addMonths').addEventListener('input', calculateAddMonthlyInstallment);
document.getElementById('addRate').addEventListener('input', calculateAddMonthlyInstallment);
document.getElementById('addDownPayment').addEventListener('input', calculateAddMonthlyInstallment);

function calculateAddMonthlyInstallment() {
  let price = parseFloat(document.getElementById('addPrice').value) || 0;
  let months = parseFloat(document.getElementById('addMonths').value) || 0;
  let rate = parseFloat(document.getElementById('addRate').value) || 0;
  let downPayment = parseFloat(document.getElementById('addDownPayment').value) || 0;
  
  if (price > 0 && months > 0) {
    let remainingAmount = price - downPayment;
    let interestAmount = (remainingAmount * rate) / 100;
    let totalAmount = remainingAmount + interestAmount;
    let monthlyInstallment = totalAmount / months;
    document.getElementById('addMonthlyInstallment').value = Math.round(monthlyInstallment);
    
    let totalInstallmentAmount = monthlyInstallment * months;
    document.getElementById('addTotalInstallmentAmount').value = Math.round(totalInstallmentAmount);
  } else {
    document.getElementById('addMonthlyInstallment').value = '';
    document.getElementById('addTotalInstallmentAmount').value = '';
  }
}

// Edit Sale Modal Functions
document.getElementById('editProductSelect').addEventListener('change', function(){
  let opt = this.selectedOptions[0];
  document.getElementById('editPrice').value = opt.dataset.price || '';
  document.getElementById('editOriginalPrice').value = opt.dataset.price || '';
  document.getElementById('editMonths').value = opt.dataset.months || '';
  document.getElementById('editOriginalMonths').value = opt.dataset.months || '';
  document.getElementById('editRate').value = opt.dataset.rate || '';
  document.getElementById('editOriginalRate').value = opt.dataset.rate || '';
  document.getElementById('editModel').value = opt.dataset.model || '';
  calculateEditMonthlyInstallment();
});

document.getElementById('editPrice').addEventListener('input', calculateEditMonthlyInstallment);
document.getElementById('editMonths').addEventListener('input', calculateEditMonthlyInstallment);
document.getElementById('editRate').addEventListener('input', calculateEditMonthlyInstallment);
document.getElementById('editDownPayment').addEventListener('input', calculateEditMonthlyInstallment);

function calculateEditMonthlyInstallment() {
  let price = parseFloat(document.getElementById('editPrice').value) || 0;
  let months = parseFloat(document.getElementById('editMonths').value) || 0;
  let rate = parseFloat(document.getElementById('editRate').value) || 0;
  let downPayment = parseFloat(document.getElementById('editDownPayment').value) || 0;
  
  if (price > 0 && months > 0) {
    let remainingAmount = price - downPayment;
    let interestAmount = (remainingAmount * rate) / 100;
    let totalAmount = remainingAmount + interestAmount;
    let monthlyInstallment = totalAmount / months;
    document.getElementById('editMonthlyInstallment').value = Math.round(monthlyInstallment);
    
    let totalInstallmentAmount = monthlyInstallment * months;
    document.getElementById('editTotalInstallmentAmount').value = Math.round(totalInstallmentAmount);
  } else {
    document.getElementById('editMonthlyInstallment').value = '';
    document.getElementById('editTotalInstallmentAmount').value = '';
  }
}

function editSale(id) {
  fetch(`<?= BASE_URL ?>/actions/get_sale.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const sale = data.sale;
        document.getElementById('editSaleId').value = sale.id;
        document.getElementById('editCustomerId').value = sale.customer_id;
        document.getElementById('editProductSelect').value = sale.product_id;
        document.getElementById('editPrice').value = sale.total_amount;
        document.getElementById('editOriginalPrice').value = sale.total_amount;
        document.getElementById('editMonths').value = sale.months;
        document.getElementById('editOriginalMonths').value = sale.months;
        document.getElementById('editRate').value = sale.interest_rate;
        document.getElementById('editOriginalRate').value = sale.interest_rate;
        document.getElementById('editDownPayment').value = sale.down_payment;
        document.getElementById('editMonthlyInstallment').value = sale.monthly_installment;

        // Set model from selected product
        let selectedOption = document.querySelector(`#editProductSelect option[value="${sale.product_id}"]`);
        if (selectedOption) {
          document.getElementById('editModel').value = selectedOption.dataset.model || '';
        }

        calculateEditMonthlyInstallment();

        new bootstrap.Modal(document.getElementById('editSaleModal')).show();
      } else {
        alert('Error loading sale data: ' + (data.error || 'Unknown error'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error loading sale data');
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