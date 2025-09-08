-- User Management Tables

-- Users table
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  user_type ENUM('super_admin', 'admin', 'employee', 'customer') NOT NULL,
  customer_id INT NULL,
  created_by INT NULL,
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- User permissions table
CREATE TABLE user_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  permission_key VARCHAR(100) NOT NULL,
  granted BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_permission (user_id, permission_key)
);

-- Available permissions
CREATE TABLE permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(100) UNIQUE NOT NULL,
  permission_name VARCHAR(255) NOT NULL,
  category VARCHAR(50) NOT NULL,
  description TEXT
);

-- Insert default permissions
INSERT INTO permissions (permission_key, permission_name, category, description) VALUES
('dashboard_view', 'View Dashboard', 'Dashboard', 'Access to main dashboard'),
('customers_view', 'View Customers', 'Customers', 'View customer list'),
('customers_add', 'Add Customers', 'Customers', 'Add new customers'),
('customers_edit', 'Edit Customers', 'Customers', 'Edit customer details'),
('customers_delete', 'Delete Customers', 'Customers', 'Delete customers'),
('products_view', 'View Products', 'Products', 'View product list'),
('products_add', 'Add Products', 'Products', 'Add new products'),
('products_edit', 'Edit Products', 'Products', 'Edit product details'),
('products_delete', 'Delete Products', 'Products', 'Delete products'),
('sales_view', 'View Sales', 'Sales', 'View sales list'),
('sales_add', 'Add Sales', 'Sales', 'Record new sales'),
('sales_edit', 'Edit Sales', 'Sales', 'Edit sales records'),
('sales_delete', 'Delete Sales', 'Sales', 'Delete sales records'),
('rents_view', 'View Rents', 'Rents', 'View rent list'),
('rents_add', 'Add Rents', 'Rents', 'Add new rents'),
('rents_edit', 'Edit Rents', 'Rents', 'Edit rent records'),
('rents_delete', 'Delete Rents', 'Rents', 'Delete rent records'),
('reports_sales', 'Sales Reports', 'Reports', 'Access sales reports'),
('reports_installments', 'Installment Reports', 'Reports', 'Access installment reports'),
('reports_customers', 'Customer Reports', 'Reports', 'Access customer reports'),
('reports_rents', 'Rent Reports', 'Reports', 'Access rent reports'),
('users_view', 'View Users', 'User Management', 'View user list'),
('users_add', 'Add Users', 'User Management', 'Add new users'),
('users_edit', 'Edit Users', 'User Management', 'Edit user details'),
('users_delete', 'Delete Users', 'User Management', 'Delete users'),
('permissions_manage', 'Manage Permissions', 'User Management', 'Manage user permissions');

-- Insert default super admin user (password: admin123)
INSERT INTO users (username, password, user_type, status) VALUES 
('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active');

-- Add user_id column to customers table to link with users
ALTER TABLE customers ADD COLUMN user_id INT NULL AFTER id;
ALTER TABLE customers ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;