<?php
// includes/header.php
// must be included after config/db.php so BASE_URL is available
include 'permissions.php';

// Check if permissions need to be refreshed (for real-time updates)
check_and_refresh_permissions_if_needed();

// Get total overdue notifications count
$overdue_count = 0;
$overdue_installments_count = 0;
$overdue_rents_count = 0;
try {
    // Count overdue installments
    $installments_query = "SELECT COUNT(*) as count FROM installments WHERE status != 'paid' AND due_date < CURDATE()";
    $installments_result = $conn->query($installments_query);
    $overdue_installments_count = $installments_result->fetch_assoc()['count'];

    // Count overdue rents
    $rents_query = "SELECT COUNT(*) as count FROM rent_payments WHERE status != 'paid' AND rent_date < CURDATE()";
    $rents_result = $conn->query($rents_query);
    $overdue_rents_count = $rents_result->fetch_assoc()['count'];

    $overdue_count = $overdue_installments_count + $overdue_rents_count;
} catch (Exception $e) {
    $overdue_count = 0; // Fallback to 0 if query fails
    $overdue_installments_count = 0;
    $overdue_rents_count = 0;
}

// Restrict customers to only allowed pages
$allowed_customer_pages = ['customer_dashboard.php', 'view_rent.php', 'view_installments.php', 'profile.php', 'change_password.php', 'help.php', 'about.php', 'user_settings.php'];
if (isset($_SESSION['customer_id']) && !in_array(basename($_SERVER['PHP_SELF']), $allowed_customer_pages)) {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    header("Location: " . $scheme . "://" . $_SERVER['HTTP_HOST'] . "/installment_app/views/customer_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Jahangir Autos & Electronics</title>
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
        margin: 0.5in !important;
      }
      .container-fluid {
        width: 100% !important;
        padding: 0 !important;
      }
      .print-header {
        display: block !important;
      }
    }
    @page {
      margin: 0;
      @top-center { content: none; }
      @bottom-center { content: none; }
      @bottom-left { content: none; }
      @bottom-right { content: none; }
    }
  </style>

  <style>
    .main-wrapper.full-width {
      margin-left: 0 !important;
    }
  </style>
</head>
<body>
<div class="print-header" style="display: none; text-align: center; margin-bottom: 20px; font-size: 14px;">
  <div style="display: flex; align-items: center; justify-content: center;">
    <img src="<?= BASE_URL ?>/assets/images/0929-yellow.png" alt="Jahangir Autos" height="40" style="margin-right: 10px;">
    <div>
      <div style="font-weight: bold;">Jahangir Autos & Electronics</div>
      <div>Printed on <?php echo date('Y-m-d H:i:s'); ?></div>
    </div>
  </div>
</div>
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
<?php if (!$auth->isCustomer()): ?>



  <!-- Enhanced Sidebar -->
  <nav id="sidebarMenu" class="d-lg-block sidebar">


    <!-- Sidebar Header -->
    <div class="p-4 text-center border-bottom border-secondary">
      <div class="d-flex align-items-center justify-content-center mb-2">
        <div class="rounded-circle bg-light p-2 me-2">
          <i class="bi bi-graph-up-arrow text-primary fs-4"></i>
        </div>
        <div>
          <h6 class="text-white mb-0 fw-bold">Itechnism</h6>
          <small class="text-white-50">Installment Pro 2.0</small>
        </div>
      </div>
    </div>

    <ul class="nav flex-column px-2">

      <!-- Dashboard -->
      <?php if (check_permission('dashboard', 'view')): ?>
      <li class="nav-item">
        <a
          class="nav-link <?= ($_SERVER['PHP_SELF'] == '/installment_app/index.php')?'active':'' ?>"
          href="<?= BASE_URL ?>/index.php"
          data-title="Dashboard">
          <i class="bi bi-speedometer2 me-2 sidebar-icon"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Customers -->
      <?php if (check_permission('customers', 'view')): ?>
      <li class="nav-item">
        <a
          class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'list_customers.php')?'active':'' ?>"
          href="<?= BASE_URL ?>/views/list_customers.php"
          data-title="Customers">
          <i class="bi bi-people me-2 sidebar-icon"></i>
          <span>Customers</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Products -->
      <?php if (check_permission('products', 'view')): ?>
      <li class="nav-item">
        <a
          class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'list_products.php')?'active':'' ?>"
          href="<?= BASE_URL ?>/views/list_products.php"
          data-title="Products">
          <i class="bi bi-box-seam me-2 sidebar-icon"></i>
          <span>Products</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Sales -->
      <?php if (check_permission('sales', 'view')): ?>
      <li class="nav-item">
        <a
          class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'list_sales.php')?'active':'' ?>"
          href="<?= BASE_URL ?>/views/list_sales.php"
          data-title="Sales">
          <i class="bi bi-cart-check me-2 sidebar-icon"></i>
          <span>Sales</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Rent -->
      <?php if (check_permission('rents', 'view')): ?>
      <li class="nav-item">
        <a
          class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'list_rents.php')?'active':'' ?>"
          href="<?= BASE_URL ?>/views/list_rents.php"
          data-title="Rent">
          <i class="bi bi-house-door me-2 sidebar-icon"></i>
          <span>Rent</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Reports -->
      <?php
      $hasReportAccess = check_permission('sales_summary', 'view') ||
                        check_permission('customer_performance', 'view') ||
                        check_permission('installment_analysis', 'view') ||
                        check_permission('overdue_report', 'view') ||
                        check_permission('product_performance_report', 'view') ||
                        check_permission('rent_summary', 'view') ||
                        check_permission('rent_customer_report', 'view') ||
                        check_permission('rent_payment_report', 'view') ||
                        check_permission('rental_profitability_report', 'view') ||
                        check_permission('rental_utilization_report', 'view');

      // Check if current page is a reports page
      $currentPage = basename($_SERVER['PHP_SELF']);
      $isReportsPage = in_array($currentPage, [
        'sales_summary_report.php',
        'customer_performance_report.php',
        'installment_analysis_report.php',
        'overdue_report.php',
        'product_performance_report.php',
        'rent_summary_report.php',
        'rent_customer_report.php',
        'rent_payment_report.php',
        'rental_profitability_report.php',
        'rental_utilization_report.php',
        'reports_dashboard.php'
      ]);
      ?>
      <?php if ($hasReportAccess): ?>
      <li class="nav-item">
        <a
          class="nav-link <?= $isReportsPage ? 'active' : '' ?>"
          data-bs-toggle="collapse"
          href="#reportsMenu"
          role="button"
          aria-expanded="false"
          aria-controls="reportsMenu"
          data-title="Reports">
          <i class="bi bi-bar-chart-line me-2"></i>
          <span>Reports</span>
          <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse" id="reportsMenu">
          <ul class="nav flex-column ms-3">
            <!-- Sales Reports -->
            <li class="nav-item">
              <a class="nav-link text-info fw-bold" href="#" onclick="return false;">
                <i class="bi bi-cart-fill me-2"></i>
                <span>Sales Reports</span>
              </a>
            </li>
            <?php if (check_permission('sales_summary', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'sales_summary_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/sales_summary_report.php">
                <i class="bi bi-graph-up me-2"></i>
                <span>Sales Summary</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (check_permission('customer_performance', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'customer_performance_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/customer_performance_report.php">
                <i class="bi bi-person-badge me-2"></i>
                <span>Customer Performance</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (check_permission('installment_analysis', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'installment_analysis_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/installment_analysis_report.php">
                <i class="bi bi-calendar-check me-2"></i>
                <span>Installment Analysis</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (check_permission('overdue_report', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'overdue_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/overdue_report.php">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <span>Overdue Analysis</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (check_permission('product_performance_report', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'product_performance_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/product_performance_report.php">
                <i class="bi bi-box-seam me-2"></i>
                <span>Product Performance</span>
              </a>
            </li>
            <?php endif; ?>

            <!-- Rent Reports -->
            <li class="nav-item mt-2">
              <a class="nav-link text-info fw-bold" href="#" onclick="return false;">
                <i class="bi bi-house-door me-2"></i>
                <span>Rent Reports</span>
              </a>
            </li>
            <?php if (check_permission('rent_summary', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'rent_summary_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/rent_summary_report.php">
                <i class="bi bi-house-gear me-2"></i>
                <span>Rent Summary</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (check_permission('rent_customer_report', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'rent_customer_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/rent_customer_report.php">
                <i class="bi bi-people me-2"></i>
                <span>Rent Customer Analysis</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (check_permission('rent_payment_report', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'rent_payment_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/rent_payment_report.php">
                <i class="bi bi-credit-card me-2"></i>
                <span>Rent Payment Tracking</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (check_permission('rental_profitability_report', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'rental_profitability_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/rental_profitability_report.php">
                <i class="bi bi-currency-dollar me-2"></i>
                <span>Rental Profitability</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (check_permission('rental_utilization_report', 'view')): ?>
            <li class="nav-item">
              <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'rental_utilization_report.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/rental_utilization_report.php">
                <i class="bi bi-calendar-range me-2"></i>
                <span>Rental Utilization</span>
              </a>
            </li>
            <?php endif; ?>

            <!-- Reports Dashboard -->
            <li class="nav-item mt-2">
              <a class="nav-link text-info fw-bold <?= (basename($_SERVER['PHP_SELF']) == 'reports_dashboard.php')?'active':'' ?>" href="<?= BASE_URL ?>/views/reports_dashboard.php">
                <i class="bi bi-grid-3x3-gap me-2"></i>
                <span>All Reports Dashboard</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>

    </ul>
 
  </nav>
  <?php endif; ?>

  <!-- Main Content Wrapper -->
  <div class="main-wrapper<?= $auth->isCustomer() ? ' full-width' : '' ?>">
    <!-- Enhanced Top Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top">
      <div class="container-fluid">




        <!-- Mobile Menu Toggle -->
        <button
          class="navbar-toggler border-0"
          type="button"
          id="sidebarToggleMobile"
          aria-controls="sidebarMenu"
          aria-expanded="false"
          aria-label="Toggle navigation">
          <i class="bi bi-list fs-4 text-white"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/index.php">
          
        <img src="<?= BASE_URL ?>/assets/images/0929-yellow.png" alt="Jahangir Autos" height="60" class="me-2">
        <!-- <div class="rounded-circle bg-white p-2 me-2 shadow-sm">
            <i class="bi bi-graph-up-arrow text-primary fs-5"></i>
          </div> -->
          <!-- <div>
            <span class="fw-bold">Jahangir Autos</span>
          </div> -->
        </a>
        
        <!-- Right Side Items - Separated for Independent Functionality -->
        <div class="d-flex align-items-center gap-3">
          <!-- Notifications Section -->
          <span class="navbar-notifications-container" id="navbarNotificationsContainer">
            <button class="btn btn-link text-white p-2 position-relative navbar-notification-btn"
                    id="navbarNotificationBtn"
                    type="button">
              <i class="bi bi-bell fs-5"></i>
              <?php if ($overdue_count > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $overdue_count ?>
              </span>
              <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 navbar-notification-menu"
                 id="navbarNotificationMenu">
              <li><h6 class="dropdown-header">Notifications</h6></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/overdue_installments_notifications.php"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Overdue Installments <span class="badge bg-danger ms-2"><?= $overdue_installments_count ?></span></a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/overdue_rents_notifications.php"><i class="bi bi-house-x text-warning me-2"></i>Overdue Rents <span class="badge bg-warning ms-2"><?= $overdue_rents_count ?></span></a></li>
            </ul>
          </span>

          <!-- User Menu Section -->
          <span class="navbar-user-container" id="navbarUserContainer">
            <div class="dropdown">
              <button class="btn btn-link nav-link dropdown-toggle text-white d-flex align-items-center navbar-user-btn"
                      id="navbarUserBtn"
                      data-bs-toggle="dropdown"
                      data-bs-target="#navbarUserMenu"
                      aria-expanded="false"
                      type="button">
                <div class="rounded-circle bg-primary p-2 me-2">
                  <i class="bi bi-person-fill text-white"></i>
                </div>
                <div class="d-none d-md-block">
                  <span class="fw-medium"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                  <small class="d-block text-white-50">User</small>
                </div>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 navbar-user-menu"
                  id="navbarUserMenu"
                  aria-labelledby="navbarUserBtn">
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
          </span>
        </div>
      </div>
    </nav>

    <!-- Page Content Area -->
    <div class="content-area fade-in-up" style="padding-top: 70px;">

    <!-- Responsive Sidebar JavaScript -->
    <script>
      // Wait for both DOM and Bootstrap to be ready
      function initializeSidebar() {
        try {
          const sidebar = document.getElementById('sidebarMenu');
          const sidebarToggleDesktop = document.getElementById('sidebarToggleDesktop');
          const sidebarToggleMobile = document.getElementById('sidebarToggleMobile');

          // Exit if required elements don't exist
          if (!sidebar) {
            console.warn('Sidebar element not found');
            return;
          }

        let isMobile = window.innerWidth < 768;
        let isTablet = window.innerWidth >= 768 && window.innerWidth < 992;
        let isDesktop = window.innerWidth >= 993;
        let isMobileOrTablet = window.innerWidth < 992; // Mobile and tablet use hamburger menu
        let useHamburgerMenu = true; // All devices now use hamburger menu

        // Function to update responsive behavior
        function updateResponsiveBehavior() {
           isMobile = window.innerWidth < 768;
           isTablet = window.innerWidth >= 768 && window.innerWidth < 992;
           isDesktop = window.innerWidth >= 993;
           isMobileOrTablet = window.innerWidth < 992;

           // All devices now use hamburger menu behavior
           sidebar.classList.remove('show', 'collapsed');
           document.body.style.overflow = '';

           // Show hamburger button on all devices
           if (sidebarToggleDesktop) sidebarToggleDesktop.style.display = 'none';
           if (sidebarToggleMobile) {
             sidebarToggleMobile.style.display = 'block';
             sidebarToggleMobile.setAttribute('aria-expanded', 'false');
           }

           // Ensure hamburger button is visible on desktop
           if (isDesktop && sidebarToggleMobile) {
             sidebarToggleMobile.style.display = 'block';
           }

           // Keep sidebar interactive
           sidebar.style.pointerEvents = 'auto';
           sidebar.style.opacity = '1';

           // Ensure navbar always remains interactable
           const navbar = document.querySelector('.navbar');
           if (navbar) {
             navbar.style.pointerEvents = 'auto';
             navbar.style.opacity = '1';
             navbar.style.visibility = 'visible';
           }
         }

        // Function to open sidebar
        function openSidebar() {
          sidebar.classList.add('show');
          // No overlay, no body overflow lock for any device
          document.body.style.overflow = '';

          if (sidebarToggleDesktop) sidebarToggleDesktop.setAttribute('aria-expanded', 'true');
          if (sidebarToggleMobile) sidebarToggleMobile.setAttribute('aria-expanded', 'true');

          // Ensure sidebar is interactive when opened
          if (sidebar.classList.contains('show')) {
            sidebar.style.pointerEvents = 'auto';
            sidebar.style.opacity = '1';
            sidebar.style.visibility = 'visible';
            sidebar.style.display = 'block';

            // Ensure nav content is visible
            const nav = sidebar.querySelector('.nav');
            if (nav) {
              nav.style.display = 'block';
              nav.style.visibility = 'visible';
              nav.style.opacity = '1';
            }

            // Ensure proper positioning for all devices
            sidebar.style.position = 'fixed';
            sidebar.style.left = '0';
            sidebar.style.top = '0';
            sidebar.style.zIndex = '1050';

            // Ensure navbar remains interactable
            const navbar = document.querySelector('.navbar');
            if (navbar) {
              navbar.style.pointerEvents = 'auto';
              navbar.style.opacity = '1';
              navbar.style.visibility = 'visible';
            }
          }
        }

        // Function to close sidebar
        function closeSidebar() {
          sidebar.classList.remove('show');
          // No overlay, no body overflow lock for any device
          document.body.style.overflow = '';

          if (sidebarToggleDesktop) sidebarToggleDesktop.setAttribute('aria-expanded', 'false');
          if (sidebarToggleMobile) sidebarToggleMobile.setAttribute('aria-expanded', 'false');

          // Ensure sidebar doesn't interfere with page interactions after closing
          if (!sidebar.classList.contains('show')) {
            // Make sidebar non-interactive when closed on all devices
            sidebar.style.pointerEvents = 'none';
            sidebar.style.opacity = '0';
            sidebar.style.visibility = 'hidden';

            // Ensure ALL navbar elements remain interactable - target separated containers
            const navbar = document.querySelector('.navbar');
            const navbarDropdowns = document.querySelectorAll('.navbar .dropdown-toggle');
            const navbarMenus = document.querySelectorAll('.navbar .dropdown-menu');
            const notificationContainer = document.getElementById('navbarNotificationsContainer');
            const userContainer = document.getElementById('navbarUserContainer');
            const notificationBtn = document.getElementById('navbarNotificationBtn');
            const userBtn = document.getElementById('navbarUserBtn');

            if (navbar) {
              navbar.style.pointerEvents = 'auto';
              navbar.style.opacity = '1';
              navbar.style.visibility = 'visible';
            }

            // Ensure separated containers work independently
            if (notificationContainer) {
              notificationContainer.style.pointerEvents = 'auto';
            }
            if (userContainer) {
              userContainer.style.pointerEvents = 'auto';
            }
            if (notificationBtn) {
              notificationBtn.style.pointerEvents = 'auto';
              notificationBtn.style.cursor = 'pointer';
            }
            if (userBtn) {
              userBtn.style.pointerEvents = 'auto';
              userBtn.style.cursor = 'pointer';
            }

            navbarDropdowns.forEach(dropdown => {
              dropdown.style.pointerEvents = 'auto';
              dropdown.style.cursor = 'pointer';
            });

            navbarMenus.forEach(menu => {
              menu.style.pointerEvents = 'auto';
            });
          }
        }





        // Toggle sidebar on button click (all devices)
        if (sidebarToggleMobile) {
          sidebarToggleMobile.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            // Works on all devices now
            if (sidebar.classList.contains('show')) {
              closeSidebar();
            } else {
              openSidebar();
            }
          });
        }


        // Close button and overlay functionality removed

        // Close sidebar when clicking outside (all devices)
        document.addEventListener('click', function(e) {
          // Process on all devices when sidebar is open
          if (sidebar.classList.contains('show')) {
            // Small delay to allow button click to process first
            setTimeout(function() {
              if (sidebar.classList.contains('show')) {
                // Check if click is outside the sidebar and not on the toggle button
                if (!sidebar.contains(e.target) && e.target !== sidebarToggleMobile && !sidebarToggleMobile.contains(e.target)) {
                  closeSidebar();
                }
              }
            }, 10);
          }
        }, true); // Use capture phase

        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
          }
        });

        // Close sidebar when clicking on nav links (all devices) - exclude dropdown toggles
        const navLinks = sidebar.querySelectorAll('.nav-link:not([data-bs-toggle])');
        navLinks.forEach(link => {
          link.addEventListener('click', function(e) {
            try {
              // Close if it's not a dropdown toggle
              if (!this.hasAttribute('data-bs-toggle') && !this.closest('.collapse')) {
                // Close sidebar immediately after navigation
                setTimeout(closeSidebar, 100); // Small delay to allow navigation
              }
            } catch (error) {
              console.warn('Error handling nav link click:', error);
            }
          });
        });

        // Handle dropdown toggles manually for better control
        const dropdownToggles = sidebar.querySelectorAll('.nav-link[data-bs-toggle="collapse"]');
        dropdownToggles.forEach(toggle => {
          toggle.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default link behavior
            e.stopPropagation(); // Stop event bubbling

            const targetId = this.getAttribute('href');
            const target = document.querySelector(targetId);
            const chevron = this.querySelector('.bi-chevron-down');

            if (target && chevron) {
              // Toggle the collapse state manually
              const isCurrentlyExpanded = target.classList.contains('show');

              if (isCurrentlyExpanded) {
                // Collapse the menu
                target.classList.remove('show');
                this.setAttribute('aria-expanded', 'false');
                chevron.style.transform = 'rotate(0deg)';
              } else {
                // Expand the menu
                target.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
                chevron.style.transform = 'rotate(180deg)';
              }

              chevron.style.transition = 'transform 0.3s ease';
            }
          });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
          updateResponsiveBehavior();
        });

        // Initialize responsive behavior
        updateResponsiveBehavior();

        // Simple manual dropdown toggle for user menu
        const setupUserDropdown = function() {
          const userBtn = document.getElementById('navbarUserBtn');
          const userMenu = document.getElementById('navbarUserMenu');

          if (userBtn && userMenu) {
            // Ensure button is interactable
            userBtn.style.pointerEvents = 'auto';
            userBtn.style.cursor = 'pointer';

            // Add click handler for manual toggle
            userBtn.addEventListener('click', function(e) {
              e.preventDefault();
              e.stopPropagation();

              // Toggle the dropdown menu visibility
              const isVisible = userMenu.classList.contains('show');

              if (isVisible) {
                // Hide the menu
                userMenu.classList.remove('show');
                userBtn.setAttribute('aria-expanded', 'false');
                console.log('User dropdown hidden');
              } else {
                // Show the menu
                userMenu.classList.add('show');
                userBtn.setAttribute('aria-expanded', 'true');
                console.log('User dropdown shown');
              }
            });

            console.log('User dropdown setup complete');
          } else {
            console.warn('User dropdown elements not found');
          }
        };

        // Simple manual dropdown toggle for notification menu
        const setupNotificationDropdown = function() {
          const notificationBtn = document.getElementById('navbarNotificationBtn');
          const notificationMenu = document.getElementById('navbarNotificationMenu');

          if (notificationBtn && notificationMenu) {
            // Ensure button is interactable
            notificationBtn.style.pointerEvents = 'auto';
            notificationBtn.style.cursor = 'pointer';

            // Add click handler for manual toggle
            notificationBtn.addEventListener('click', function(e) {
              e.preventDefault();
              e.stopPropagation();

              // Toggle the dropdown menu visibility
              const isVisible = notificationMenu.classList.contains('show');

              if (isVisible) {
                // Hide the menu
                notificationMenu.classList.remove('show');
                notificationBtn.setAttribute('aria-expanded', 'false');
                console.log('Notification dropdown hidden');
              } else {
                // Show the menu
                notificationMenu.classList.add('show');
                notificationBtn.setAttribute('aria-expanded', 'true');
                console.log('Notification dropdown shown');
              }
            });

            console.log('Notification dropdown setup complete');
          } else {
            console.warn('Notification dropdown elements not found');
          }
        };

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
          const userBtn = document.getElementById('navbarUserBtn');
          const userMenu = document.getElementById('navbarUserMenu');
          const notificationBtn = document.getElementById('navbarNotificationBtn');
          const notificationMenu = document.getElementById('navbarNotificationMenu');

          // Close user dropdown if clicking outside
          if (userBtn && userMenu && !userBtn.contains(e.target) && !userMenu.contains(e.target)) {
            userMenu.classList.remove('show');
            userBtn.setAttribute('aria-expanded', 'false');
          }

          // Close notification dropdown if clicking outside
          if (notificationBtn && notificationMenu && !notificationBtn.contains(e.target) && !notificationMenu.contains(e.target)) {
            notificationMenu.classList.remove('show');
            notificationBtn.setAttribute('aria-expanded', 'false');
          }
        });

        // Initialize both dropdowns
        setupUserDropdown();
        setupNotificationDropdown();

        } catch (error) {
          console.error('Sidebar initialization error:', error);
          // Fallback: ensure sidebar is accessible even if JavaScript fails
          const sidebar = document.getElementById('sidebarMenu');
          const sidebarToggleDesktop = document.getElementById('sidebarToggleDesktop');
          if (sidebar) {
            sidebar.style.display = 'block';
            sidebar.classList.add('show');
          }
          if (sidebarToggleDesktop) {
            sidebarToggleDesktop.style.display = 'block';
            sidebarToggleDesktop.setAttribute('aria-expanded', 'true');
          }
        }
      }

      // Initialize sidebar after DOM is ready
      document.addEventListener('DOMContentLoaded', function() {
        // Check if Bootstrap is loaded, if not, wait for it
        if (typeof bootstrap !== 'undefined') {
          initializeSidebar();
        } else {
          // Wait for Bootstrap to load
          let bootstrapCheckInterval = setInterval(function() {
            if (typeof bootstrap !== 'undefined') {
              clearInterval(bootstrapCheckInterval);
              initializeSidebar();
            }
          }, 50);

          // Timeout after 5 seconds
          setTimeout(function() {
            clearInterval(bootstrapCheckInterval);
            console.warn('Bootstrap loading timeout, initializing sidebar without Bootstrap');
            initializeSidebar();
          }, 5000);
        }
      });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Bootstrap Loading and Dropdown Initialization -->
    <script>
      // Ensure Bootstrap is loaded and initialize navbar dropdowns
      window.addEventListener('load', function() {
        if (typeof bootstrap === 'undefined') {
          console.warn('Bootstrap not loaded, navbar dropdowns may not work');
        } else {
          // Re-initialize navbar dropdowns after Bootstrap is fully loaded
          setTimeout(function() {
            const notificationBtn = document.getElementById('navbarNotificationBtn');
            const userBtn = document.getElementById('navbarUserBtn');

            if (notificationBtn) {
              notificationBtn.style.pointerEvents = 'auto';
              notificationBtn.style.cursor = 'pointer';
            }
            if (userBtn) {
              userBtn.style.pointerEvents = 'auto';
              userBtn.style.cursor = 'pointer';
            }

            console.log('Navbar dropdowns initialized');
          }, 100);
        }
      });
    </script>