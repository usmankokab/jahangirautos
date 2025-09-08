<?php 
include '../config/db.php';
include '../includes/header.php';
?>

<main class="flex-fill p-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between mb-2">
      <h2 class="mb-0" style="color: #0d6efd; font-weight: bold;">Product Catalog</h2>
      <div class="d-flex gap-2 no-print">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
          <i class="bi bi-plus-circle"></i> Add Product
        </button>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>
    
    <!-- Filter Form -->
    <div class="card mb-4 no-print">
      <div class="card-header">
        <h5 class="mb-0">Filter Products</h5>
      </div>
      <div class="card-body">
        <form method="GET" class="row g-3">
          <div class="col-md-3">
            <label class="form-label">ID</label>
            <input type="number" name="id" class="form-control" value="<?= isset($_GET['id']) ? htmlspecialchars($_GET['id']) : '' ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Product Name</label>
            <input type="text" name="name" class="form-control" value="<?= isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '' ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Model</label>
            <input type="text" name="model" class="form-control" value="<?= isset($_GET['model']) ? htmlspecialchars($_GET['model']) : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">&nbsp;</label>
            <div class="d-flex">
              <button type="submit" class="btn btn-primary me-2">Filter</button>
              <a href="<?= BASE_URL ?>/views/list_products.php" class="btn btn-secondary">Clear</a>
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
            <th style="color: white; font-weight: bold; background-color: #007bff !important;">Model</th>
            <th style="color: white; font-weight: bold; background-color: #007bff !important;">Price (PKR)</th>
            <th style="color: white; font-weight: bold; background-color: #007bff !important;">Stock Status</th>
            <th style="color: white; font-weight: bold; background-color: #007bff !important;">Term (months)</th>
            <th style="color: white; font-weight: bold; background-color: #007bff !important;">Interest (%)</th>
            <th style="color: white; font-weight: bold; background-color: #007bff !important;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
            // Pagination variables
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) && $_GET['limit'] === 'all' ? 0 : 20;
            $offset = ($page - 1) * ($limit > 0 ? $limit : 0);
            
            // Build SQL query with filters
            $sql = "SELECT *, COUNT(*) OVER() as total_records FROM products WHERE 1=1";
            
            $params = [];
            $types = "";
            
            // Add filters
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
            
            if (!empty($_GET['model'])) {
              $sql .= " AND model LIKE ?";
              $params[] = '%' . $_GET['model'] . '%';
              $types .= "s";
            }

            if (!empty($_GET['min_price'])) {
              $sql .= " AND price >= ?";
              $params[] = $_GET['min_price'];
              $types .= "d";
            }

            if (!empty($_GET['max_price'])) {
              $sql .= " AND price <= ?";
              $params[] = $_GET['max_price'];
              $types .= "d";
            }

            if (!empty($_GET['stock_status'])) {
              $sql .= " AND stock_status = ?";
              $params[] = $_GET['stock_status'];
              $types .= "s";
            }
            
            if ($limit > 0) {
              $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
              $params[] = $limit;
              $params[] = $offset;
              $types .= "ii";
            } else {
              $sql .= " ORDER BY created_at DESC";
            }
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
              $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $total_records = 0;
            $products = [];
            while($row = $result->fetch_assoc()) {
              $total_records = $row['total_records'];
              unset($row['total_records']);
              $products[] = $row;
            }
            
            $total_pages = $limit > 0 ? ceil($total_records / $limit) : 1;
            
            foreach($products as $row):
          ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= isset($row['model']) ? htmlspecialchars($row['model']) : 'N/A' ?></td>
              <td><?= number_format($row['price'], 0) ?></td>
              <td><?= isset($row['stock_status']) ? ucfirst(str_replace('_', ' ', $row['stock_status'])) : 'N/A' ?></td>
              <td><?= $row['installment_months'] ?></td>
              <td><?= $row['interest_rate'] ?></td>
              <td>
                <a href="<?= BASE_URL ?>/views/edit_product.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Product">
                    <i class="bi bi-pencil-square"></i>
                </a>
                <a href="<?= BASE_URL ?>/actions/delete_product.php?id=<?= $row['id'] ?>" 
                   class="btn btn-sm btn-outline-danger" 
                   onclick="return confirm('Are you sure you want to delete this product?')"
                   title="Delete Product">
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
    <nav aria-label="Products pagination" class="no-print">
      <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
        <li class="page-item">
          <a class="page-link" href="?page=1<?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['name']) ? '&name='.$_GET['name'] : '' ?>" aria-label="First">
            <span aria-hidden="true">&laquo;&laquo;</span>
          </a>
        </li>
        <li class="page-item">
          <a class="page-link" href="?page=<?= $page-1 ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['name']) ? '&name='.$_GET['name'] : '' ?>" aria-label="Previous">
            <span aria-hidden="true">&laquo;</span>
          </a>
        </li>
        <?php endif; ?>
        
        <?php
        // Show page numbers
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        if ($start > 1) {
          echo '<li class="page-item"><a class="page-link" href="?page=1'.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['name']) ? '&name='.$_GET['name'] : '').'">1</a></li>';
          if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        for ($i = $start; $i <= $end; $i++) {
          $active = ($i == $page) ? 'active' : '';
          echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['name']) ? '&name='.$_GET['name'] : '').'">'.$i.'</a></li>';
        }
        
        if ($end < $total_pages) {
          if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
          echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.(isset($_GET['id']) ? '&id='.$_GET['id'] : '').(isset($_GET['name']) ? '&name='.$_GET['name'] : '').'">'.$total_pages.'</a></li>';
        }
        ?>
        
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
          <a class="page-link" href="?page=<?= $page+1 ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['name']) ? '&name='.$_GET['name'] : '' ?>" aria-label="Next">
            <span aria-hidden="true">&raquo;</span>
          </a>
        </li>
        <li class="page-item">
          <a class="page-link" href="?page=<?= $total_pages ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['name']) ? '&name='.$_GET['name'] : '' ?>" aria-label="Last">
            <span aria-hidden="true">&raquo;&raquo;</span>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</main>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?= BASE_URL ?>/actions/insert_product.php" method="POST">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Product Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Model</label>
              <input type="text" name="model" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Price (PKR)</label>
              <input type="number" name="price" class="form-control" step="0.01" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Stock Status</label>
              <select name="stock_status" class="form-select" required>
                <option value="in_stock" selected>In Stock</option>
                <option value="out_of_stock">Out of Stock</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Term (months)</label>
              <input type="number" name="installment_months" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Interest Rate (%)</label>
              <input type="number" name="interest_rate" class="form-control" step="0.01" required>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save Product</button>
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
</script>

<style>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>

<?php include '../includes/footer.php'; ?>