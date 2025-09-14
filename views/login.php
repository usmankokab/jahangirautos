<?php
session_start();
require_once '../config/app.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/db.php';
    
    if (isset($_POST['cnic'])) {
        // Customer login with CNIC - direct lookup in customers table
        $cnic = trim($_POST['cnic']);

        $stmt = $conn->prepare("
            SELECT c.*, 'customer' as role_name
            FROM customers c
            WHERE c.cnic = ?
        ");
        $stmt->bind_param("s", $cnic);
        
    } else {
        // Staff login with username/password
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        $stmt = $conn->prepare("
            SELECT u.*, r.role_name 
            FROM users u 
            INNER JOIN user_roles r ON u.role_id = r.id 
            WHERE u.username = ? AND u.is_active = 1
        ");
        $stmt->bind_param("s", $username);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        if (isset($_POST['cnic']) || password_verify($password, $user['password'])) {
            // Login successful
            if ($user['role_name'] === 'customer') {
                // Customer login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = 'customer';
                $_SESSION['username'] = $user['name'];
                $_SESSION['customer_id'] = $user['id'];

                header('Location: ' . BASE_URL . '/views/customer_dashboard.php');
            } else {
                // Staff login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['username'] = $user['username'];

                if (isset($user['customer_id'])) {
                    $_SESSION['customer_id'] = $user['customer_id'];
                }

                // Fetch user permissions
                $stmt = $conn->prepare("
                    SELECT m.module_name, up.*
                    FROM user_permissions up
                    INNER JOIN modules m ON up.module_id = m.id
                    WHERE up.user_id = ?
                ");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $permissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                $_SESSION['permissions'] = [];
                foreach ($permissions as $perm) {
                    $_SESSION['permissions'][$perm['module_name']] = [
                        'view' => $perm['can_view'],
                        'add' => $perm['can_add'],
                        'edit' => $perm['can_edit'],
                        'delete' => $perm['can_delete']
                    ];
                }

                header('Location: ' . BASE_URL . '/index.php');
            }
            exit;
        } else {
            $login_error = 'Invalid password';
        }
    } else {
        $login_error = isset($_POST['cnic']) ? 'Invalid CNIC number' : 'Invalid username';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Installment Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h4 class="mb-0">Installment Manager Login</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($login_error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div>
                        <?php endif; ?>
                        
                        <!-- Login Type Selector -->
                        <div class="btn-group w-100 mb-4" role="group">
                            <input type="radio" class="btn-check" name="login_type" id="staff_login" value="staff" checked>
                            <label class="btn btn-outline-primary" for="staff_login">Staff Login</label>
                            
                            <input type="radio" class="btn-check" name="login_type" id="customer_login" value="customer">
                            <label class="btn btn-outline-primary" for="customer_login">Customer Login</label>
                        </div>
                        
                        <!-- Staff Login Form -->
                        <form id="staffForm" method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        
                        <!-- Customer Login Form -->
                        <form id="customerForm" method="POST" class="needs-validation d-none" novalidate>
                             <div class="mb-3">
                                 <label class="form-label">CNIC Number</label>
                                 <input type="text" name="cnic" id="customerCnic" class="form-control" pattern="^\d{5}-\d{7}-\d{1}$" required
                                        placeholder="XXXXX-XXXXXXX-X" maxlength="15" title="CNIC must be in format: XXXXX-XXXXXXX-X">
                                 <div class="form-text">Format: 12345-1234567-1</div>
                             </div>
                             <button type="submit" class="btn btn-primary w-100">Login</button>
                         </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // CNIC formatting and validation functions
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

    document.addEventListener('DOMContentLoaded', function() {
        const staffForm = document.getElementById('staffForm');
        const customerForm = document.getElementById('customerForm');
        const loginTypeInputs = document.querySelectorAll('input[name="login_type"]');

        // Add CNIC formatting to customer login
        const customerCnicInput = document.getElementById('customerCnic');
        if (customerCnicInput) {
            customerCnicInput.addEventListener('input', function() {
                formatCNIC(this);
            });
        }

        loginTypeInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value === 'staff') {
                    staffForm.classList.remove('d-none');
                    customerForm.classList.add('d-none');
                } else {
                    staffForm.classList.add('d-none');
                    customerForm.classList.remove('d-none');
                }
            });
        });

        // Form validation
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });
    </script>
</body>
</html>
