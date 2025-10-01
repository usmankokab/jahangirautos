<?php
include '../config/db.php';
include '../config/auth.php';
include '../includes/permissions.php';

$auth->requireLogin();
require_permission_or_lock('rents', 'view');

include '../includes/header.php';

// Get search parameters
$search_name = $_GET['search_name'] ?? '';
$search_cnic = $_GET['search_cnic'] ?? '';
$search_phone = $_GET['search_phone'] ?? '';

// Build WHERE conditions for search
$where_conditions = ["rp.status != 'paid' AND rp.rent_date < CURDATE()"];

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

// Get overdue rent payments with customer and guarantor details
$query = "
    SELECT
        rp.*,
        r.start_date,
        r.end_date,
        r.rent_type,
        r.daily_rent,
        r.total_rent,
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
        DATEDIFF(CURDATE(), rp.rent_date) as days_overdue
    FROM rent_payments rp
    JOIN rents r ON rp.rent_id = r.id
    JOIN customers c ON r.customer_id = c.id
    WHERE $where_clause
    ORDER BY rp.rent_date ASC, rp.amount DESC
";

$result = $conn->query($query);
$overdue_rents = $result->fetch_all(MYSQLI_ASSOC);

// Calculate summary
$total_overdue = count($overdue_rents);
$total_amount = array_sum(array_column($overdue_rents, 'amount'));
$total_paid = array_sum(array_column($overdue_rents, 'paid_amount'));
$remaining_amount = $total_amount - $total_paid;
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-house-x-fill text-warning me-2"></i>Overdue Rents</h2>
            <small class="text-muted">Follow up on overdue rental payments and recover outstanding amounts</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
            <a href="rent_payment_report.php" class="btn btn-outline-info">
                <i class="bi bi-bar-chart me-2"></i>Detailed Report
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small opacity-75">Total Overdue</div>
                            <div class="h4 mb-0"><?= number_format($total_overdue) ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-house-x fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-danger text-white h-100">
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
                                    $avg_days = array_sum(array_column($overdue_rents, 'days_overdue')) / $total_overdue;
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
    <div class="card mb-4">
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
                        <a href="overdue_rents_notifications.php" class="btn btn-outline-secondary">
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

    <!-- Overdue Rents List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Overdue Rent Payment Details</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($overdue_rents)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle-fill text-success fa-4x mb-3"></i>
                            <h4 class="text-success">No Overdue Rents!</h4>
                            <p class="text-muted">All rent payments are up to date. Great job!</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($overdue_rents as $rent): ?>
                                <div class="col-xl-6 mb-4">
                                    <div class="card border-warning h-100">
                                        <div class="card-header bg-warning text-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-house-door me-2"></i>
                                                    <?= htmlspecialchars($rent['customer_name']) ?>
                                                </h6>
                                                <span class="badge bg-light text-warning">
                                                    <?= $rent['days_overdue'] ?> days overdue
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Customer Info -->
                                            <div class="row mb-3">
                                                <div class="col-sm-6">
                                                    <small class="text-muted d-block">CNIC</small>
                                                    <strong><?= htmlspecialchars($rent['customer_cnic']) ?></strong>
                                                </div>
                                                <div class="col-sm-6">
                                                    <small class="text-muted d-block">Phone</small>
                                                    <div class="d-flex gap-2">
                                                        <strong id="phone-<?= $rent['id'] ?>"><?= htmlspecialchars($rent['customer_phone']) ?></strong>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="callCustomer('<?= $rent['customer_phone'] ?>')" title="Call Customer">
                                                            <i class="bi bi-telephone"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('phone-<?= $rent['id'] ?>')" title="Copy Phone">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Rent Info -->
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Rental Period</small>
                                                <strong class="text-primary">
                                                    <?= date('M d, Y', strtotime($rent['start_date'])) ?> -
                                                    <?= date('M d, Y', strtotime($rent['end_date'])) ?>
                                                </strong>
                                                <br>
                                                <small class="text-info">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    <?= ucfirst($rent['rent_type']) ?> Rent
                                                    <?php if ($rent['rent_type'] === 'daily'): ?>
                                                        (₨<?= number_format($rent['daily_rent'], 0) ?>/day)
                                                    <?php else: ?>
                                                        (Total: ₨<?= number_format($rent['total_rent'], 0) ?>)
                                                    <?php endif; ?>
                                                </small>
                                            </div>

                                            <!-- Payment Details -->
                                            <div class="row mb-3">
                                                <div class="col-sm-4">
                                                    <small class="text-muted d-block">Due Date</small>
                                                    <strong class="text-danger">
                                                        <?= date('M d, Y', strtotime($rent['rent_date'])) ?>
                                                    </strong>
                                                </div>
                                                <div class="col-sm-4">
                                                    <small class="text-muted d-block">Amount Due</small>
                                                    <strong class="text-warning">₨<?= number_format($rent['amount'], 0) ?></strong>
                                                </div>
                                                <div class="col-sm-4">
                                                    <small class="text-muted d-block">Paid Amount</small>
                                                    <strong class="text-success">₨<?= number_format($rent['paid_amount'], 0) ?></strong>
                                                </div>
                                            </div>

                                            <!-- Payment Status -->
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Payment Status</small>
                                                <div class="progress" style="height: 25px;">
                                                    <div class="progress-bar bg-<?= $rent['paid_amount'] > 0 ? 'warning' : 'danger' ?>"
                                                         style="width: <?= ($rent['paid_amount'] / $rent['amount']) * 100 ?>%">
                                                        ₨<?= number_format($rent['paid_amount'], 0) ?> / ₨<?= number_format($rent['amount'], 0) ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Guarantor Information -->
                                            <?php if ($rent['guarantor_1'] || $rent['guarantor_2']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted d-block mb-2">Guarantors</small>
                                                    <div class="row">
                                                        <?php if ($rent['guarantor_1']): ?>
                                                            <div class="col-sm-6">
                                                                <div class="border rounded p-2 mb-2">
                                                                    <small class="text-muted d-block">Guarantor 1</small>
                                                                    <strong class="d-block"><?= htmlspecialchars($rent['guarantor_1']) ?></strong>
                                                                    <?php if ($rent['guarantor_1_phone']): ?>
                                                                        <div class="d-flex gap-1 mt-1">
                                                                            <small class="text-success">
                                                                                <i class="bi bi-telephone me-1"></i>
                                                                                <span id="g1-phone-<?= $rent['id'] ?>"><?= htmlspecialchars($rent['guarantor_1_phone']) ?></span>
                                                                            </small>
                                                                            <button class="btn btn-xs btn-outline-success ms-1" onclick="callCustomer('<?= $rent['guarantor_1_phone'] ?>')" title="Call Guarantor">
                                                                                <i class="bi bi-telephone-fill"></i>
                                                                            </button>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($rent['guarantor_2']): ?>
                                                            <div class="col-sm-6">
                                                                <div class="border rounded p-2 mb-2">
                                                                    <small class="text-muted d-block">Guarantor 2</small>
                                                                    <strong class="d-block"><?= htmlspecialchars($rent['guarantor_2']) ?></strong>
                                                                    <?php if ($rent['guarantor_2_phone']): ?>
                                                                        <div class="d-flex gap-1 mt-1">
                                                                            <small class="text-success">
                                                                                <i class="bi bi-telephone me-1"></i>
                                                                                <span id="g2-phone-<?= $rent['id'] ?>"><?= htmlspecialchars($rent['guarantor_2_phone']) ?></span>
                                                                            </small>
                                                                            <button class="btn btn-xs btn-outline-success ms-1" onclick="callCustomer('<?= $rent['guarantor_2_phone'] ?>')" title="Call Guarantor">
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
                                                <a href="tel:<?= $rent['customer_phone'] ?>" class="btn btn-success btn-sm">
                                                    <i class="bi bi-telephone me-1"></i>Call Customer
                                                </a>
                                                <a href="view_rent.php?rent_id=<?= $rent['rent_id'] ?>&filter_overdue=1" class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-eye me-1"></i>View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
    if (confirm('Page will refresh to show latest overdue rents. Continue?')) {
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

@media print {
    .no-print {
        display: none !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>