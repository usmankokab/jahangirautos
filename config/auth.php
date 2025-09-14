<?php
session_start();
require_once __DIR__ . '/db.php';

class Auth {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            // Get role name
            $role_stmt = $this->conn->prepare("SELECT role_name FROM user_roles WHERE id = ?");
            $role_stmt->bind_param("i", $user['role']);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            $role = $role_result->fetch_assoc();
            $_SESSION['role_name'] = $role['role_name'];

            return true;
        }
        return false;
    }
    
    public function logout() {
        session_destroy();
        header("Location: " . BASE_URL . "/views/login.php");
        exit();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: " . BASE_URL . "/views/login.php");
            exit();
        }
    }
    
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function isCustomer() {
        if (!isset($_SESSION['role_name']) && isset($_SESSION['user_id'])) {
            $stmt = $this->conn->prepare("SELECT r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            if ($user) {
                $_SESSION['role_name'] = $user['role_name'];
            }
        }
        return isset($_SESSION['role_name']) && strtolower($_SESSION['role_name']) === 'customer';
    }

    public function requireNonCustomer() {
        if (isset($_SESSION['customer_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
    }
}

// Initialize auth
$auth = new Auth($conn);
?>