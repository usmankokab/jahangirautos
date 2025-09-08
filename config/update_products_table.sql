-- Update products table to add modal field and change stock to stock_status
ALTER TABLE products ADD COLUMN modal VARCHAR(255) AFTER name;
ALTER TABLE products ADD COLUMN stock_status ENUM('in_stock', 'out_of_stock') DEFAULT 'in_stock' AFTER modal;
-- Keep the old stock column for now to avoid data loss, can be dropped later if needed