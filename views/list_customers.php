<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between mb-2">
    <h2 class="mb-0" style="color: #0d6efd; font-weight: bold;">Customers</h2>
    <div class="d-flex gap-2 no-print">
      <?php if (check_permission('customers', 'add')): ?>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
        <i class="bi bi-person-plus"></i> Add Customer
      </button>
      <?php endif; ?>
      <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
  </div>
  
  <!-- Filter Form -->
  <div class="card mb-4 no-print">
    <div class="card-header">
      <h5 class="mb-0">Filter Customers</h5>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-2">
          <label class="form-label">ID</label>
          <input type="number" name="id" class="form-control" value="<?= isset($_GET['id']) ? htmlspecialchars($_GET['id']) : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?= isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">CNIC</label>
          <input type="text" name="cnic" class="form-control" value="<?= isset($_GET['cnic']) ? htmlspecialchars($_GET['cnic']) : '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">&nbsp;</label>
          <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/views/list_customers.php" class="btn btn-secondary">Clear</a>
            <button type="submit" class="btn btn-primary">Filter</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr style="background-color: #007bff !important;">
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">#</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Name</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">CNIC</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Phone</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Address</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Guarantor_1</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;">Guarantor_2</th>
          <th style="color: white; font-weight: bold; background-color: #007bff !important;" class="text-center no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT *, COUNT(*) OVER() as total_records FROM customers WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($_GET['id'])) {
          $sql .= " AND id = ?";
          $params[] = $_GET['id'];
          $types .= "i";
        }
        
        if (!empty($_GET['name'])) {
          $sql .= " AND name LIKE ?";
          $params[] = '%' . $_GET['name'] . '%';
          $types .= "s";
        }

        if (!empty($_GET['cnic'])) {
          $sql .= " AND cnic LIKE ?";
          $params[] = '%' . $_GET['cnic'] . '%';
          $types .= "s";
        }

        if (!empty($_GET['phone'])) {
          $sql .= " AND phone LIKE ?";
          $params[] = '%' . $_GET['phone'] . '%';
          $types .= "s";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
          $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $total_records = 0;
        $customers = [];
        while($row = $result->fetch_assoc()) {
          $total_records = $row['total_records'];
          unset($row['total_records']);
          $customers[] = $row;
        }
        
        $total_pages = ceil($total_records / $limit);
        
        foreach($customers as $row):
      ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['cnic']) ?></td>
          <td><?= htmlspecialchars($row['phone']) ?></td>
          <td><?= htmlspecialchars($row['address']) ?></td>
          <td><?= htmlspecialchars($row['guarantor_1']) ?></td>
          <td><?= htmlspecialchars($row['guarantor_2']) ?></td>
          <td class="text-center no-print">
            <div class="btn-group">
              <?php if (check_permission('customers', 'edit')): ?>
              <button type="button" class="btn btn-sm btn-outline-primary"
                      onclick="editCustomer(<?= $row['id'] ?>)"
                      title="Edit Customer">
                <i class="bi bi-pencil"></i>
              </button>
              <?php endif; ?>
              <?php if (check_permission('customers', 'delete')): ?>
              <a href="<?= BASE_URL ?>/actions/delete_customer.php?id=<?= $row['id'] ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Are you sure you want to delete this customer?')"
                 title="Delete Customer">
                <i class="bi bi-trash"></i>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  
  <?php if ($total_pages > 1): ?>
  <nav aria-label="Customers pagination" class="no-print">
    <ul class="pagination justify-content-center">
      <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?page=1<?= http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) : '' ?>" aria-label="First">
          <span aria-hidden="true">&laquo;&laquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page-1 ?><?= http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) : '' ?>" aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>
      <?php endif; ?>
      
      <?php
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);
      
      for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.(http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) : '').'">'.$i.'</a></li>';
      }
      ?>
      
      <?php if ($page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page+1 ?><?= http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) : '' ?>" aria-label="Next">
          <span aria-hidden="true">&raquo;</span>
        </a>
      </li>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $total_pages ?><?= http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) : '' ?>" aria-label="Last">
          <span aria-hidden="true">&raquo;&raquo;</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="addCustomerForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name *</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">CNIC *</label>
              <input type="text" name="cnic" id="addCustomerCnic" class="form-control" required
                     pattern="^\d{5}-\d{7}-\d{1}$"
                     placeholder="XXXXX-XXXXXXX-X"
                     maxlength="15"
                     title="CNIC must be in format: XXXXX-XXXXXXX-X">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone *</label>
              <input type="text" name="phone" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 1</label>
              <input type="text" name="guarantor_1" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 2</label>
              <input type="text" name="guarantor_2" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 1 Phone</label>
              <input type="text" name="guarantor_1_phone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 2 Phone</label>
              <input type="text" name="guarantor_2_phone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 1 Address</label>
              <input type="text" name="guarantor_1_address" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 2 Address</label>
              <input type="text" name="guarantor_2_address" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editCustomerForm">
        <input type="hidden" name="id" id="editCustomerId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name *</label>
              <input type="text" name="name" id="editCustomerName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">CNIC *</label>
              <input type="text" name="cnic" id="editCustomerCnic" class="form-control" required
                     pattern="^\d{5}-\d{7}-\d{1}$"
                     placeholder="XXXXX-XXXXXXX-X"
                     maxlength="15"
                     title="CNIC must be in format: XXXXX-XXXXXXX-X">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone *</label>
              <input type="text" name="phone" id="editCustomerPhone" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" id="editCustomerAddress" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 1</label>
              <input type="text" name="guarantor_1" id="editCustomerGuarantor1" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 2</label>
              <input type="text" name="guarantor_2" id="editCustomerGuarantor2" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 1 Phone</label>
              <input type="text" name="guarantor_1_phone" id="editCustomerGuarantor1Phone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 2 Phone</label>
              <input type="text" name="guarantor_2_phone" id="editCustomerGuarantor2Phone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 1 Address</label>
              <input type="text" name="guarantor_1_address" id="editCustomerGuarantor1Address" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guarantor 2 Address</label>
              <input type="text" name="guarantor_2_address" id="editCustomerGuarantor2Address" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// CNIC validation and formatting functions
function formatCNIC(input) {
  // Remove all non-digits
  let value = input.value.replace(/\D/g, '');

  // Limit to 13 digits
  if (value.length > 13) {
    value = value.substring(0, 13);
  }

  // Format as XXXXX-XXXXXXX-X
  if (value.length >= 6) {
    value = value.substring(0, 5) + '-' + value.substring(5);
  }
  if (value.length >= 14) {
    value = value.substring(0, 13) + '-' + value.substring(13);
  }

  input.value = value;
}

function validateCNIC(cnic) {
  const cnicPattern = /^\d{5}-\d{7}-\d{1}$/;
  return cnicPattern.test(cnic);
}

// Add CNIC formatting to both modals
document.getElementById('addCustomerCnic').addEventListener('input', function() {
  formatCNIC(this);
});

document.getElementById('editCustomerCnic').addEventListener('input', function() {
  formatCNIC(this);
});

document.getElementById('addCustomerForm').addEventListener('submit', function(e) {
  const cnicField = document.getElementById('addCustomerCnic');
  const cnicValue = cnicField.value;

  if (!validateCNIC(cnicValue)) {
    e.preventDefault();
    alert('Please enter a valid CNIC in the format: XXXXX-XXXXXXX-X');
    cnicField.focus();
    return;
  }

  e.preventDefault();
  const formData = new FormData(this);

  fetch('<?= BASE_URL ?>/actions/insert_customer.php', {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    alert('Failed to add customer. User with this CNIC already exists');
  });
});

function editCustomer(id) {
  fetch('<?= BASE_URL ?>/actions/get_customer.php?id=' + id)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('editCustomerId').value = data.customer.id;
        document.getElementById('editCustomerName').value = data.customer.name;
        document.getElementById('editCustomerCnic').value = data.customer.cnic;
        document.getElementById('editCustomerPhone').value = data.customer.phone;
        document.getElementById('editCustomerAddress').value = data.customer.address || '';
        document.getElementById('editCustomerGuarantor1').value = data.customer.guarantor_1 || '';
        document.getElementById('editCustomerGuarantor2').value = data.customer.guarantor_2 || '';
        document.getElementById('editCustomerGuarantor1Phone').value = data.customer.guarantor_1_phone || '';
        document.getElementById('editCustomerGuarantor2Phone').value = data.customer.guarantor_2_phone || '';
        document.getElementById('editCustomerGuarantor1Address').value = data.customer.guarantor_1_address || '';
        document.getElementById('editCustomerGuarantor2Address').value = data.customer.guarantor_2_address || '';

        new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
      } else {
        alert('Error loading customer data');
      }
    })
    .catch(error => {
      alert('Error loading customer data');
    });
}

document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
  const cnicField = document.getElementById('editCustomerCnic');
  const cnicValue = cnicField.value;

  if (!validateCNIC(cnicValue)) {
    e.preventDefault();
    alert('Please enter a valid CNIC in the format: XXXXX-XXXXXXX-X');
    cnicField.focus();
    return;
  }

  e.preventDefault();
  const formData = new FormData(this);

  fetch('<?= BASE_URL ?>/actions/update_customer.php', {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    alert('Error updating customer');
  });
});
</script>

<?php include '../includes/footer.php'; ?>