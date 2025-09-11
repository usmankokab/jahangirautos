-- User Roles Table
CREATE TABLE user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name ENUM('super_admin', 'admin', 'manager', 'employee', 'customer') NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    role_id INT,
    admin_id INT NULL, -- The admin who created this user
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(id),
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Link customers table to users
ALTER TABLE customers ADD COLUMN user_id INT NULL;
ALTER TABLE customers ADD FOREIGN KEY (user_id) REFERENCES users(id);

-- Module Permissions Table
CREATE TABLE modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_name VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    parent_id INT NULL,
    FOREIGN KEY (parent_id) REFERENCES modules(id)
);

-- User Permissions Table
CREATE TABLE user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    module_id INT NOT NULL,
    can_view BOOLEAN DEFAULT false,
    can_add BOOLEAN DEFAULT false,
    can_edit BOOLEAN DEFAULT false,
    can_delete BOOLEAN DEFAULT false,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (module_id) REFERENCES modules(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Insert default roles
INSERT INTO user_roles (role_name, description) VALUES
('super_admin', 'Super Administrator with full system access'),
('admin', 'Administrator with limited system access'),
('manager', 'Manager with supervisory access'),
('employee', 'Regular employee user');

-- Insert default modules
INSERT INTO modules (module_name, description) VALUES
('dashboard', 'Dashboard Access'),
('users', 'User Management'),
('customers', 'Customer Management'),
('products', 'Product Management'),
('sales', 'Sales Management'),
('rents', 'Rent Management'),
('reports', 'Reports Access');

-- Insert child modules for reports
INSERT INTO modules (module_name, description, parent_id) VALUES
('customer_performance', 'Customer Performance Report', 7),
('sales_summary', 'Sales Summary Report', 7),
('rent_summary', 'Rent Summary Report', 7),
('installment_analysis', 'Installment Analysis Report', 7),
('overdue_report', 'Overdue Payments Report', 7),
('rental_utilization_report', 'Rental Utilization Report', 7),
('rental_profitability_report', 'Rental Profitability Report', 7),
('rent_payment_report', 'Rent Payment Report', 7),
('rent_customer_report', 'Rent Customer Report', 7),
('product_performance_report', 'Product Performance Report', 7);
