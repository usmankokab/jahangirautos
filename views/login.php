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
        // Customer login with CNIC
        $cnic = trim($_POST['cnic']);
        
        $stmt = $conn->prepare("
            SELECT u.*, c.id as customer_id, c.name, r.role_name 
            FROM users u 
            INNER JOIN customers c ON c.user_id = u.id 
            INNER JOIN user_roles r ON u.role_id = r.id 
            WHERE c.cnic = ? AND u.is_active = 1
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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['username'] = $user['username'] ?? $user['name'];
            
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
            exit;
        } else {
            $login_error = 'Invalid password';
        }
    } else {
        $login_error = isset($_POST['cnic']) ? 'Invalid CNIC' : 'Invalid username';
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
                                <input type="text" name="cnic" class="form-control" pattern="\d{5}-\d{7}-\d{1}" required>
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
    document.addEventListener('DOMContentLoaded', function() {
        const staffForm = document.getElementById('staffForm');
        const customerForm = document.getElementById('customerForm');
        const loginTypeInputs = document.querySelectorAll('input[name="login_type"]');
        
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
