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
                    <h2 class="mb-0"><i class="bi bi-question-circle me-2"></i>Help & Support</h2>
                    <small class="text-muted">Find answers to common questions and get support</small>
                </div>
                <a href="<?= BASE_URL ?>/views/profile.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Profile
                </a>
            </div>

            <!-- Search Help -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="helpSearch" placeholder="Search for help topics...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid">
                                <button class="btn btn-outline-primary" onclick="contactSupport()">
                                    <i class="bi bi-envelope me-2"></i>Contact Support
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Quick Help Topics -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Help Topics</h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="helpAccordion">

                                <!-- Getting Started -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#gettingStarted">
                                            <i class="bi bi-play-circle me-2"></i>Getting Started
                                        </button>
                                    </h2>
                                    <div id="gettingStarted" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <h6>Welcome to Installment Manager!</h6>
                                            <p>This system helps you manage installment payments, track customer information, and generate reports.</p>
                                            <ul>
                                                <li><strong>Dashboard:</strong> Overview of your business metrics</li>
                                                <li><strong>Customers:</strong> Manage customer information and history</li>
                                                <li><strong>Products:</strong> Add and manage products for sale</li>
                                                <li><strong>Sales:</strong> Record sales and track payments</li>
                                                <li><strong>Reports:</strong> Generate detailed business reports</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Managing Customers -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#customers">
                                            <i class="bi bi-people me-2"></i>Managing Customers
                                        </button>
                                    </h2>
                                    <div id="customers" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <h6>How to manage customers:</h6>
                                            <ol>
                                                <li>Go to <strong>Customers</strong> section from the sidebar</li>
                                                <li>Click <strong>"Add Customer"</strong> to add new customers</li>
                                                <li>Use the search and filter options to find customers</li>
                                                <li>Click on customer names to view detailed information</li>
                                                <li>Edit customer details using the edit button</li>
                                            </ol>
                                            <p><strong>Tip:</strong> Keep customer contact information up to date for better communication.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recording Sales -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sales">
                                            <i class="bi bi-cart-check me-2"></i>Recording Sales
                                        </button>
                                    </h2>
                                    <div id="sales" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <h6>How to record a sale:</h6>
                                            <ol>
                                                <li>Navigate to <strong>Sales</strong> section</li>
                                                <li>Click <strong>"Add Sale"</strong> button</li>
                                                <li>Select customer and product</li>
                                                <li>Enter sale details (price, terms, down payment)</li>
                                                <li>The system will automatically calculate installments</li>
                                                <li>Save the sale to generate payment schedule</li>
                                            </ol>
                                            <p><strong>Note:</strong> Installments are automatically created based on the terms you specify.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Managing Payments -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#payments">
                                            <i class="bi bi-cash-coin me-2"></i>Managing Payments
                                        </button>
                                    </h2>
                                    <div id="payments" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <h6>How to manage payments:</h6>
                                            <ol>
                                                <li>Go to <strong>Sales</strong> section</li>
                                                <li>Click on a sale to view installments</li>
                                                <li>Find the installment that was paid</li>
                                                <li>Enter the payment amount and date</li>
                                                <li>Add any comments if necessary</li>
                                                <li>Click <strong>"Save Payment"</strong></li>
                                            </ol>
                                            <p><strong>Tip:</strong> Regular payment tracking helps maintain good customer relationships.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Generating Reports -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#reports">
                                            <i class="bi bi-bar-chart-line me-2"></i>Generating Reports
                                        </button>
                                    </h2>
                                    <div id="reports" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <h6>How to generate reports:</h6>
                                            <ol>
                                                <li>Go to <strong>Reports</strong> section from sidebar</li>
                                                <li>Choose the type of report you need</li>
                                                <li>Set date ranges and filters</li>
                                                <li>Click <strong>"Generate Report"</strong></li>
                                                <li>Use <strong>"Export"</strong> to download reports</li>
                                            </ol>
                                            <p><strong>Available Reports:</strong></p>
                                            <ul>
                                                <li>Sales Summary - Overview of sales performance</li>
                                                <li>Customer Performance - Customer-wise analysis</li>
                                                <li>Installment Analysis - Payment tracking</li>
                                                <li>Rent Summary - Rental business overview</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Account Management -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#account">
                                            <i class="bi bi-person-gear me-2"></i>Account Management
                                        </button>
                                    </h2>
                                    <div id="account" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <h6>Managing your account:</h6>
                                            <ul>
                                                <li><strong>Profile:</strong> Update your personal information</li>
                                                <li><strong>Change Password:</strong> Update your login password</li>
                                                <li><strong>Settings:</strong> Customize your preferences</li>
                                                <li><strong>Logout:</strong> Securely sign out of your account</li>
                                            </ul>
                                            <p><strong>Security Tips:</strong></p>
                                            <ul>
                                                <li>Use a strong, unique password</li>
                                                <li>Change your password regularly</li>
                                                <li>Log out when using public computers</li>
                                                <li>Keep your contact information current</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support & Contact -->
                <div class="col-lg-4 mb-4">
                    <!-- Contact Support -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-headset me-2"></i>Contact Support</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Need additional help? Our support team is here to assist you.</p>

                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="contactSupport()">
                                    <i class="bi bi-envelope me-2"></i>Email Support
                                </button>
                                <button class="btn btn-outline-primary" onclick="liveChat()">
                                    <i class="bi bi-chat-dots me-2"></i>Live Chat
                                </button>
                                <button class="btn btn-outline-primary" onclick="callSupport()">
                                    <i class="bi bi-telephone me-2"></i>Call Support
                                </button>
                            </div>

                            <hr>
                            <div class="small text-muted">
                                <p><strong>Support Hours:</strong></p>
                                <p>Monday - Friday: 9:00 AM - 6:00 PM</p>
                                <p>Saturday: 10:00 AM - 4:00 PM</p>
                                <p>Sunday: Closed</p>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Shortcuts -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-question-circle me-2"></i>Popular FAQs</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <a href="#" class="list-group-item list-group-item-action py-2" onclick="showFAQ('password')">
                                    <i class="bi bi-key me-2"></i>How to change password?
                                </a>
                                <a href="#" class="list-group-item list-group-item-action py-2" onclick="showFAQ('export')">
                                    <i class="bi bi-download me-2"></i>How to export data?
                                </a>
                                <a href="#" class="list-group-item list-group-item-action py-2" onclick="showFAQ('reports')">
                                    <i class="bi bi-bar-chart me-2"></i>How to generate reports?
                                </a>
                                <a href="#" class="list-group-item list-group-item-action py-2" onclick="showFAQ('backup')">
                                    <i class="bi bi-shield-check me-2"></i>How to backup data?
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- System Status -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>System Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="mb-2">
                                        <i class="bi bi-server text-primary fs-4"></i>
                                    </div>
                                    <small class="text-muted">System Status</small>
                                    <div class="text-success fw-bold">Online</div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-2">
                                        <i class="bi bi-database text-success fs-4"></i>
                                    </div>
                                    <small class="text-muted">Database</small>
                                    <div class="text-success fw-bold">Connected</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function contactSupport() {
    const subject = encodeURIComponent('Support Request - Installment Manager');
    const body = encodeURIComponent('Please describe your issue or question:\n\nSystem Details:\n- Page: ' + window.location.href + '\n- Browser: ' + navigator.userAgent + '\n- Time: ' + new Date().toLocaleString());
    window.location.href = 'mailto:support@example.com?subject=' + subject + '&body=' + body;
}

function liveChat() {
    alert('Live chat feature will be available in a future update. Please use email support for now.');
}

function callSupport() {
    if (confirm('Call support at +92-XXX-XXXXXXX?')) {
        window.location.href = 'tel:+92-XXX-XXXXXXX';
    }
}

function showFAQ(topic) {
    const faqs = {
        'password': 'To change your password:\n1. Click on your profile menu (top-right)\n2. Select "Change Password"\n3. Enter your current password\n4. Enter new password (minimum 6 characters)\n5. Confirm the new password\n6. Click "Change Password"',
        'export': 'To export your data:\n1. Go to Reports section\n2. Generate the desired report\n3. Click "Export" button\n4. Choose your preferred format (PDF/Excel/CSV)\n5. Save the file to your computer',
        'reports': 'To generate reports:\n1. Navigate to Reports section\n2. Select report type\n3. Set date range and filters\n4. Click "Generate Report"\n5. View or export the results',
        'backup': 'Data backup is performed automatically daily. For manual backup:\n1. Contact system administrator\n2. Request database backup\n3. Backup will be provided via secure download'
    };

    if (faqs[topic]) {
        alert(faqs[topic]);
    }
}

// Search functionality
document.getElementById('helpSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const accordionItems = document.querySelectorAll('.accordion-item');

    accordionItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(searchTerm) || searchTerm === '') {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>