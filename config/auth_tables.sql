-- Create user roles table if not exists
CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name ENUM('super_admin', 'admin', 'employee', 'customer') NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create users table if not exists
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    role_id INT,
    admin_id INT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(id),
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Create modules table if not exists
CREATE TABLE IF NOT EXISTS modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    parent_id INT NULL,
    FOREIGN KEY (parent_id) REFERENCES modules(id)
);

-- Create user permissions table if not exists
CREATE TABLE IF NOT EXISTS user_permissions (
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

-- Add user_id column to customers table if not exists
ALTER TABLE customers ADD COLUMN IF NOT EXISTS user_id INT NULL;
ALTER TABLE customers ADD FOREIGN KEY IF NOT EXISTS (user_id) REFERENCES users(id);
