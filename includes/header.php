<?php
// includes/header.php
// must be included after config/db.php so BASE_URL is available
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Installment Manager - Advanced Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Advanced Installment Management System with comprehensive reporting and analytics">
  
  <!-- Preconnect for performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap CSS + Icons -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
    rel="stylesheet">
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <!-- Advanced Design System -->
  <link href="<?= BASE_URL ?>/assets/css/advanced-design.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/icons.css" rel="stylesheet">
  
  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

  <!-- Print Styles -->
  <style type="text/css" media="print">
    @media print {
      .sidebar, .no-print, .navbar {
        display: none !important;
      }
      .content {
        margin-left: 0 !important;
        width: 100% !important;
      }
      .table {
        width: 100% !important;
      }
      body {
        padding: 0 !important;
        margin: 0 !important;
      }
      .container-fluid {
        width: 100% !important;
        padding: 0 !important;
      }
    }
  </style>
</head>
<body>
<?php
if (isset($_GET['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show m-3" role="alert">';
    echo htmlspecialchars($_GET['success']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}
if (isset($_GET['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show m-3" role="alert">';
    echo htmlspecialchars($_GET['error']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}
?>
<div class="full-layout">
  <!-- Enhanced Sidebar -->
  <nav id="sidebarMenu" class="collapse d-lg-block sidebar">
    <!-- Sidebar Header -->
    <div class="p-4 text-center border-bottom border-secondary">
      <div class="d-flex align-items-center justify-content-center mb-2">
        <div class="rounded-circle bg-light p-2 me-2">
          <i class="bi bi-graph-up-arrow text-primary fs-4"></i>
        </div>
        <div>
          <h6 class="text-white mb-0 fw-bold">Installment</h6>
          <small class="text-white-50">Manager Pro</small>
        </div>
      </div>
    </div>
    
    <div class="sidebar-heading">
      <i class="bi bi-grid-3x3-gap me-2"></i>Main Menu
    </div>
    <ul class="nav flex-column px-2">

      <!-- Dashboard -->
      <li class="nav-item">
        <a
          class="nav-link <?= ($_SERVER['PHP_SELF'] == '/installment_app/index.php')?'active':'' ?>"
          href="<?= BASE_URL ?>/index.php">
          <i class="bi bi-speedometer2 me-2 sidebar-icon"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <!-- Customers -->
      <li class="nav-item">
        <a
          class="nav-link"
          href="<?= BASE_URL ?>/views/list_customers.php">
          <i class="bi bi-people me-2 sidebar-icon"></i>
          <span>Customers</span>
        </a>
      </li>

      <!-- Products -->
      <li class="nav-item">
        <a
          class="nav-link"
          href="<?= BASE_URL ?>/views/list_products.php">
          <i class="bi bi-box-seam me-2 sidebar-icon"></i>
          <span>Products</span>
        </a>
      </li>

      <!-- Sales -->
      <li class="nav-item">
        <a
          class="nav-link"
          href="<?= BASE_URL ?>/views/list_sales.php">
          <i class="bi bi-cart-check me-2 sidebar-icon"></i>
          <span>Sales</span>
        </a>
      </li>

      <!-- Rent -->
      <li class="nav-item">
        <a
          class="nav-link"
          href="<?= BASE_URL ?>/views/list_rents.php">
          <i class="bi bi-house-door me-2 sidebar-icon"></i>
          <span>Rent</span>
        </a>
      </li>

      <!-- Reports -->
      <li class="nav-item">
        <a
          class="nav-link"
          data-bs-toggle="collapse"
          href="#reportsMenu"
          role="button"
          aria-expanded="false"
          aria-controls="reportsMenu">
          <i class="bi bi-bar-chart-line me-2"></i>
          <span>Reports</span>
          <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse" id="reportsMenu">
          <ul class="nav flex-column ms-3">
            <!-- Sales Reports -->
            <li class="nav-item">
              <a class="nav-link text-primary fw-bold" href="#" onclick="return false;">
                <i class="bi bi-cart-fill me-2"></i>
                <span>Sales Reports</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/sales_summary_report.php">
                <i class="bi bi-graph-up me-2"></i>
                <span>Sales Summary</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/customer_performance_report.php">
                <i class="bi bi-person-badge me-2"></i>
                <span>Customer Performance</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/installment_analysis_report.php">
                <i class="bi bi-calendar-check me-2"></i>
                <span>Installment Analysis</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/overdue_report.php">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <span>Overdue Analysis</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/product_performance_report.php">
                <i class="bi bi-box-seam me-2"></i>
                <span>Product Performance</span>
              </a>
            </li>

            <!-- Rent Reports -->
            <li class="nav-item mt-2">
              <a class="nav-link text-success fw-bold" href="#" onclick="return false;">
                <i class="bi bi-house-door me-2"></i>
                <span>Rent Reports</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/rent_summary_report.php">
                <i class="bi bi-house-gear me-2"></i>
                <span>Rent Summary</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/rent_customer_report.php">
                <i class="bi bi-people me-2"></i>
                <span>Rent Customer Analysis</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/rent_payment_report.php">
                <i class="bi bi-credit-card me-2"></i>
                <span>Rent Payment Tracking</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/rental_profitability_report.php">
                <i class="bi bi-currency-dollar me-2"></i>
                <span>Rental Profitability</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/views/rental_utilization_report.php">
                <i class="bi bi-calendar-range me-2"></i>
                <span>Rental Utilization</span>
              </a>
            </li>

            <!-- Reports Dashboard -->
            <li class="nav-item mt-2">
              <a class="nav-link text-info fw-bold" href="<?= BASE_URL ?>/views/reports_dashboard.php">
                <i class="bi bi-grid-3x3-gap me-2"></i>
                <span>All Reports Dashboard</span>
              </a>
            </li>
          </ul>
        </div>
      </li>

    </ul>
 
  </nav>

  <!-- Main Content Wrapper -->
  <div class="main-wrapper">
    <!-- Enhanced Top Navbar -->
    <nav class="navbar navbar-expand-lg">
      <div class="container-fluid">
        <!-- Mobile Menu Toggle -->
        <button
          class="navbar-toggler border-0"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#sidebarMenu"
          aria-controls="sidebarMenu"
          aria-expanded="false"
          aria-label="Toggle navigation">
          <i class="bi bi-list fs-4 text-white"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/index.php">
          <div class="rounded-circle bg-white p-2 me-3 shadow-sm">
            <i class="bi bi-graph-up-arrow text-primary fs-5"></i>
          </div>
          <div>
            <span class="fw-bold">Installment Manager</span>
            </div>
        </a>
        
        <!-- Right Side Items -->
        <div class="d-flex align-items-center gap-3">
          <!-- Notifications -->
          <div class="dropdown">
            <button class="btn btn-link text-white p-2 position-relative" data-bs-toggle="dropdown">
              <i class="bi bi-bell fs-5"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                3
              </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
              <li><h6 class="dropdown-header">Notifications</h6></li>
              <li><a class="dropdown-item" href="#"><i class="bi bi-exclamation-triangle text-warning me-2"></i>3 Overdue payments</a></li>
              <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle text-info me-2"></i>New customer registered</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
            </ul>
          </div>
          
          <!-- User Menu -->
          <div class="dropdown">
            <a
              class="nav-link dropdown-toggle text-white d-flex align-items-center"
              href="#"
              id="userMenu"
              data-bs-toggle="dropdown"
              aria-expanded="false">
              <div class="rounded-circle bg-primary p-2 me-2">
                <i class="bi bi-person-fill text-white"></i>
              </div>
              <div class="d-none d-md-block">
                <span class="fw-medium"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                <small class="d-block text-white-50">User</small>
              </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
              <li><h6 class="dropdown-header">Account</h6></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/change_password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/user_settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Support</h6></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/help.php"><i class="bi bi-question-circle me-2"></i>Help & Support</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/about.php"><i class="bi bi-info-circle me-2"></i>About</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/actions/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <!-- Page Content Area -->
    <div class="content-area fade-in-up">