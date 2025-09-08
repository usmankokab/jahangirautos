-- Add missing fields to products table
-- Run this SQL in phpMyAdmin

-- Add modal field (if not already exists)
ALTER TABLE products ADD COLUMN IF NOT EXISTS modal VARCHAR(255) AFTER name;

-- Add stock_status field (if not already exists)
ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_status ENUM('in_stock', 'out_of_stock') DEFAULT 'in_stock' AFTER modal;

-- Add description field
ALTER TABLE products ADD COLUMN IF NOT EXISTS description TEXT AFTER interest_rate;

-- Update existing records to have default stock_status if null
UPDATE products SET stock_status = 'in_stock' WHERE stock_status IS NULL;