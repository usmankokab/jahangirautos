<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';


include '../includes/header.php';

// Get search parameters
$search_name = $_GET['search_name'] ?? '';
$search_cnic = $_GET['search_cnic'] ?? '';
$search_phone = $_GET['search_phone'] ?? '';

// Build WHERE conditions for search
$where_conditions = ["i.status != 'paid' AND i.due_date < CURDATE() AND DAY(CURDATE()) >= 10"];

if (!empty($search_name)) {
    $where_conditions[] = "c.name LIKE '%" . $conn->real_escape_string($search_name) . "%'";
}
if (!empty($search_cnic)) {
    $where_conditions[] = "c.cnic LIKE '%" . $conn->real_escape_string($search_cnic) . "%'";
}
if (!empty($search_phone)) {
    $where_conditions[] = "c.phone LIKE '%" . $conn->real_escape_string($search_phone) . "%'";
}

$where_clause = implode(" AND ", $where_conditions);

// Get overdue installments with customer and guarantor details
$query = "
    SELECT
        i.*,
        ROW_NUMBER() OVER (PARTITION BY i.sale_id ORDER BY i.due_date) as installment_number,
        s.sale_date,
        s.total_amount,
        p.name as product_name,
        p.model,
        c.name as customer_name,
        c.phone as customer_phone,
        c.cnic as customer_cnic,
        c.address as customer_address,
        c.guarantor_1,
        c.guarantor_1_phone,
        c.guarantor_1_address,
        c.guarantor_2,
        c.guarantor_2_phone,
        c.guarantor_2_address,
        DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM installments i
    JOIN sales s ON i.sale_id = s.id
    JOIN products p ON s.product_id = p.id
    JOIN customers c ON s.customer_id = c.id
    WHERE $where_clause
    ORDER BY i.due_date ASC, i.amount DESC
";

$result = $conn->query($query);
$overdue_installments = $result->fetch_all(MYSQLI_ASSOC);

// Calculate summary
$total_overdue = count($overdue_installments);

// Debug log
error_log("Overdue Installments Notifications - Total Overdue: " . $total_overdue);
$total_amount = array_sum(array_column($overdue_installments, 'amount'));
$total_paid = array_sum(array_column($overdue_installments, 'paid_amount'));
$remaining_amount = $total_amount - $total_paid;
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Overdue Installments</h2>
            <small class="text-muted">Follow up on overdue payments and recover outstanding amounts</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
            <a href="overdue_report.php" class="btn btn-outline-info">
                <i class="bi bi-bar-chart me-2"></i>Detailed Report
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4 no-print">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small opacity-75">Total Overdue</div>
                            <div class="h4 mb-0"><?= number_format($total_overdue) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small opacity-75">Outstanding Amount</div>
                            <div class="h4 mb-0">₨<?= number_format($remaining_amount, 0) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small opacity-75">Avg Days Overdue</div>
                            <div class="h4 mb-0">
                                <?php
                                if ($total_overdue > 0) {
                                    $avg_days = array_sum(array_column($overdue_installments, 'days_overdue')) / $total_overdue;
                                    echo number_format($avg_days, 1);
                                } else {
                                    echo '0';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-x fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small opacity-75">Action Required</div>
                            <div class="h4 mb-0">High Priority</div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-telephone fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="card mb-4 no-print">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-search me-2"></i>Search Customers</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search_name" class="form-label">Customer Name</label>
                    <input type="text" class="form-control" id="search_name" name="search_name"
                           value="<?= htmlspecialchars($search_name) ?>" placeholder="Enter customer name">
                </div>
                <div class="col-md-4">
                    <label for="search_cnic" class="form-label">CNIC</label>
                    <input type="text" class="form-control" id="search_cnic" name="search_cnic"
                           value="<?= htmlspecialchars($search_cnic) ?>" placeholder="Enter CNIC number">
                </div>
                <div class="col-md-4">
                    <label for="search_phone" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="search_phone" name="search_phone"
                           value="<?= htmlspecialchars($search_phone) ?>" placeholder="Enter phone number">
                </div>
                <div class="col-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Search
                        </button>
                        <a href="overdue_installments_notifications.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Clear Search
                        </a>
                        <?php if (!empty($search_name) || !empty($search_cnic) || !empty($search_phone)): ?>
                            <div class="ms-auto">
                                <span class="badge bg-info">Found <?= number_format($total_overdue) ?> results</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Overdue Installments List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Overdue Installment Details</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($overdue_installments)): ?>
                        <div class="text-center py-5 screen-only">
                            <i class="bi bi-check-circle-fill text-success fa-4x mb-3"></i>
                            <h4 class="text-success">No Overdue Installments!</h4>
                            <p class="text-muted">All installments are up to date. Great job!</p>
                        </div>
                    <?php else: ?>
                        <div class="row screen-only">
                            <?php foreach ($overdue_installments as $installment): ?>
                                <div class="col-xl-6 mb-4">
                                    <div class="card border-danger h-100">
                                        <div class="card-header bg-danger text-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-person-circle me-2"></i>
                                                    <?= htmlspecialchars($installment['customer_name']) ?>
                                                </h6>
                                                <span class="badge bg-light text-danger">
                                                    <?= $installment['days_overdue'] ?> days overdue
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Customer Info -->
                                            <div class="row mb-3">
                                                <div class="col-sm-6">
                                                    <small class="text-muted d-block">CNIC</small>
                                                    <strong><?= htmlspecialchars($installment['customer_cnic']) ?></strong>
                                                </div>
                                                <div class="col-sm-6">
                                                    <small class="text-muted d-block">Phone</small>
                                                    <div class="d-flex gap-2">
                                                        <strong id="phone-<?= $installment['id'] ?>"><?= htmlspecialchars($installment['customer_phone']) ?></strong>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="callCustomer('<?= $installment['customer_phone'] ?>')" title="Call Customer">
                                                            <i class="bi bi-telephone"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('phone-<?= $installment['id'] ?>')" title="Copy Phone">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Product Info -->
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Product</small>
                                                <strong class="text-primary">
                                                    <?= htmlspecialchars($installment['product_name']) ?>
                                                    <?php if ($installment['model']): ?>
                                                        (<?= htmlspecialchars($installment['model']) ?>)
                                                    <?php endif; ?>
                                                </strong>
                                            </div>

                                            <!-- Installment Details -->
                                            <div class="row mb-3">
                                                <div class="col-sm-4">
                                                    <small class="text-muted d-block">Installment #</small>
                                                    <strong class="text-info"><?= $installment['installment_number'] ?></strong>
                                                </div>
                                                <div class="col-sm-4">
                                                    <small class="text-muted d-block">Due Date</small>
                                                    <strong class="text-danger">
                                                        <?= date('M d, Y', strtotime($installment['due_date'])) ?>
                                                    </strong>
                                                </div>
                                                <div class="col-sm-4">
                                                    <small class="text-muted d-block">Amount Due</small>
                                                    <strong class="text-warning">₨<?= number_format($installment['amount'], 0) ?></strong>
                                                </div>
                                            </div>

                                            <!-- Payment Status -->
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Payment Status</small>
                                                <div class="progress" style="height: 25px;">
                                                    <div class="progress-bar bg-<?= $installment['paid_amount'] > 0 ? 'warning' : 'danger' ?>"
                                                         style="width: <?= ($installment['paid_amount'] / $installment['amount']) * 100 ?>%">
                                                        ₨<?= number_format($installment['paid_amount'], 0) ?> / ₨<?= number_format($installment['amount'], 0) ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Guarantor Information -->
                                            <?php if ($installment['guarantor_1'] || $installment['guarantor_2']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted d-block mb-2">Guarantors</small>
                                                    <div class="row">
                                                        <?php if ($installment['guarantor_1']): ?>
                                                            <div class="col-sm-6">
                                                                <div class="border rounded p-2 mb-2">
                                                                    <small class="text-muted d-block">Guarantor 1</small>
                                                                    <strong class="d-block"><?= htmlspecialchars($installment['guarantor_1']) ?></strong>
                                                                    <?php if ($installment['guarantor_1_phone']): ?>
                                                                        <div class="d-flex gap-1 mt-1">
                                                                            <small class="text-success">
                                                                                <i class="bi bi-telephone me-1"></i>
                                                                                <span id="g1-phone-<?= $installment['id'] ?>"><?= htmlspecialchars($installment['guarantor_1_phone']) ?></span>
                                                                            </small>
                                                                            <button class="btn btn-xs btn-outline-success ms-1" onclick="callCustomer('<?= $installment['guarantor_1_phone'] ?>')" title="Call Guarantor">
                                                                                <i class="bi bi-telephone-fill"></i>
                                                                            </button>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($installment['guarantor_2']): ?>
                                                            <div class="col-sm-6">
                                                                <div class="border rounded p-2 mb-2">
                                                                    <small class="text-muted d-block">Guarantor 2</small>
                                                                    <strong class="d-block"><?= htmlspecialchars($installment['guarantor_2']) ?></strong>
                                                                    <?php if ($installment['guarantor_2_phone']): ?>
                                                                        <div class="d-flex gap-1 mt-1">
                                                                            <small class="text-success">
                                                                                <i class="bi bi-telephone me-1"></i>
                                                                                <span id="g2-phone-<?= $installment['id'] ?>"><?= htmlspecialchars($installment['guarantor_2_phone']) ?></span>
                                                                            </small>
                                                                            <button class="btn btn-xs btn-outline-success ms-1" onclick="callCustomer('<?= $installment['guarantor_2_phone'] ?>')" title="Call Guarantor">
                                                                                <i class="bi bi-telephone-fill"></i>
                                                                            </button>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Action Buttons -->
                                            <div class="d-flex gap-2">
                                                <a href="tel:<?= $installment['customer_phone'] ?>" class="btn btn-success btn-sm">
                                                    <i class="bi bi-telephone me-1"></i>Call Customer
                                                </a>
                                                <a href="view_installments.php?sale_id=<?= $installment['sale_id'] ?>&filter_overdue=1" class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-eye me-1"></i>View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Print-only Table -->
                        <div class="print-only">
                            <h3>Overdue Installments Report</h3>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>CNIC</th>
                                        <th>Phone</th>
                                        <th>Product</th>
                                        <th>Installment #</th>
                                        <th>Due Date</th>
                                        <th>Amount Due</th>
                                        <th>Days Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdue_installments as $installment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($installment['customer_name']) ?></td>
                                        <td><?= htmlspecialchars($installment['customer_cnic']) ?></td>
                                        <td><?= htmlspecialchars($installment['customer_phone']) ?></td>
                                        <td><?= htmlspecialchars($installment['product_name']) ?> <?= htmlspecialchars($installment['model']) ? '(' . htmlspecialchars($installment['model']) . ')' : '' ?></td>
                                        <td><?= $installment['installment_number'] ?></td>
                                        <td><?= date('M d, Y', strtotime($installment['due_date'])) ?></td>
                                        <td>₨<?= number_format($installment['amount'], 0) ?></td>
                                        <td><?= $installment['days_overdue'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Call customer function
function callCustomer(phone) {
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

// Auto-refresh every 5 minutes
setInterval(function() {
    if (confirm('Page will refresh to show latest overdue installments. Continue?')) {
        location.reload();
    }
}, 300000);
</script>

<style>
.card {
    transition: transform 0.2s ease-in-out;
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.btn-xs {
    padding: 0.2rem 0.4rem;
    font-size: 0.75rem;
    line-height: 1.2;
    border-radius: 0.2rem;
}

.progress {
    font-size: 0.75rem;
}

.screen-only {
    display: block;
}

.print-only {
    display: none;
}

@media print {
    .no-print {
        display: none !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    .screen-only {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>