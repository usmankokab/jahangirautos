<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0"><i class="bi bi-info-circle me-2"></i>About Installment Manager</h2>
                    <small class="text-muted">Learn more about our system and features</small>
                </div>
                <a href="<?= BASE_URL ?>/views/profile.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Profile
                </a>
            </div>

            <div class="row">
                <!-- System Overview -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>System Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4 class="text-primary">Installment Manager Pro</h4>
                                    <p class="text-muted">Advanced Installment Management System</p>
                                    <div class="mb-3">
                                        <strong>Version:</strong> 2.1.0<br>
                                        <strong>Release Date:</strong> January 2025<br>
                                        <strong>License:</strong> Commercial License
                                    </div>
                                </div>
                                <div class="col-md-6 text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-calculator display-4 text-primary"></i>
                                    </div>
                                    <p class="lead">Streamline your installment business operations</p>
                                </div>
                            </div>

                            <hr>

                            <h5>Key Features</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Customer Management</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Product Catalog</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Sales Tracking</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Installment Scheduling</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Payment Processing</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Advanced Reporting</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>User Management</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Data Export</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- What's New -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-star me-2"></i>What's New in v2.1.0</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Enhanced User Interface</h6>
                                        <p class="timeline-text">Complete redesign with modern Bootstrap 5 components and improved user experience.</p>
                                        <small class="text-muted">December 2024</small>
                                    </div>
                                </div>

                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Advanced Reporting System</h6>
                                        <p class="timeline-text">New comprehensive reporting dashboard with charts, filters, and export capabilities.</p>
                                        <small class="text-muted">November 2024</small>
                                    </div>
                                </div>

                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Improved Security</h6>
                                        <p class="timeline-text">Enhanced authentication system with role-based access control and secure password policies.</p>
                                        <small class="text-muted">October 2024</small>
                                    </div>
                                </div>

                                <div class="timeline-item">
                                    <div class="timeline-marker bg-warning"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Mobile Optimization</h6>
                                        <p class="timeline-text">Responsive design improvements for better mobile and tablet experience.</p>
                                        <small class="text-muted">September 2024</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Information & Support -->
                <div class="col-lg-4 mb-4">
                    <!-- System Information -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-cpu me-2"></i>System Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>PHP Version:</strong><br>
                                <span class="badge bg-info"><?= phpversion() ?></span>
                            </div>

                            <div class="mb-3">
                                <strong>Database:</strong><br>
                                <span class="badge bg-success">MySQL/MariaDB</span>
                            </div>

                            <div class="mb-3">
                                <strong>Web Server:</strong><br>
                                <span class="badge bg-primary">Apache/Nginx</span>
                            </div>

                            <div class="mb-3">
                                <strong>Operating System:</strong><br>
                                <span class="badge bg-secondary">Cross-platform</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>System Stats</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get some basic stats
                            try {
                                $customer_count = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'] ?? 0;
                                $product_count = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
                                $sale_count = $conn->query("SELECT COUNT(*) as count FROM sales")->fetch_assoc()['count'] ?? 0;
                                $user_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
                            } catch (Exception $e) {
                                $customer_count = $product_count = $sale_count = $user_count = 0;
                            }
                            ?>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="h5 text-primary mb-1"><?= number_format($customer_count) ?></div>
                                    <small class="text-muted">Customers</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="h5 text-success mb-1"><?= number_format($product_count) ?></div>
                                    <small class="text-muted">Products</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="h5 text-info mb-1"><?= number_format($sale_count) ?></div>
                                    <small class="text-muted">Sales</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="h5 text-warning mb-1"><?= number_format($user_count) ?></div>
                                    <small class="text-muted">Users</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Support & Resources -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-life-preserver me-2"></i>Support & Resources</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?= BASE_URL ?>/views/help.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-question-circle me-2"></i>Help Center
                                </a>
                                <a href="mailto:support@example.com" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-envelope me-2"></i>Email Support
                                </a>
                                <a href="https://github.com/example/installment-manager" target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-github me-2"></i>GitHub Repository
                                </a>
                                <a href="https://docs.example.com" target="_blank" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-book me-2"></i>Documentation
                                </a>
                            </div>

                            <hr>

                            <div class="text-center">
                                <small class="text-muted">
                                    Need help? Contact our support team<br>
                                    <strong>support@example.com</strong>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- License Information -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>License</h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <p class="mb-2"><strong>Installment Manager Pro</strong></p>
                                <p class="small text-muted mb-2">Licensed to: Your Company Name</p>
                                <p class="small text-muted mb-0">© 2024 All Rights Reserved</p>
                            </div>

                            <hr>

                            <div class="text-center">
                                <small class="text-muted">
                                    This software is protected by copyright law.<br>
                                    Unauthorized distribution is prohibited.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Technologies Used -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-code-slash me-2"></i>Technologies & Frameworks</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <i class="bi bi-filetype-php fs-1 text-primary mb-2"></i>
                                        <h6>PHP</h6>
                                        <small class="text-muted">Backend Language</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <i class="bi bi-database fs-1 text-success mb-2"></i>
                                        <h6>MySQL</h6>
                                        <small class="text-muted">Database System</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <i class="bi bi-bootstrap fs-1 text-info mb-2"></i>
                                        <h6>Bootstrap 5</h6>
                                        <small class="text-muted">Frontend Framework</small>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <i class="bi bi-bar-chart fs-1 text-warning mb-2"></i>
                                        <h6>Chart.js</h6>
                                        <small class="text-muted">Data Visualization</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 0;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #e9ecef;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
}

.timeline-text {
    color: #6c757d;
    margin-bottom: 5px;
}
</style>

<?php include '../includes/footer.php'; ?>