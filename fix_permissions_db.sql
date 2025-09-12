-- fix_permissions_db.sql
-- Direct SQL fix for permission system database issues

-- Ensure the required columns exist in user_permissions table
ALTER TABLE user_permissions
ADD COLUMN IF NOT EXISTS can_paid_amount TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS can_save TINYINT(1) DEFAULT 0;

-- Verify the table structure
DESCRIBE user_permissions;

-- Test INSERT statement (optional - remove this section after testing)
-- INSERT INTO user_permissions (user_id, module_id, can_view, can_add, can_edit, can_delete, can_paid_amount, can_save, created_by, updated_at)
-- VALUES (999, 1, 1, 1, 1, 1, 1, 1, 1, NOW());

-- Clean up test data (optional - remove this section after testing)
-- DELETE FROM user_permissions WHERE user_id = 999 AND module_id = 1 AND created_by = 1;

-- Show current permissions structure for verification
SELECT
    up.user_id,
    m.module_name,
    up.can_view,
    up.can_add,
    up.can_edit,
    up.can_delete,
    up.can_paid_amount,
    up.can_save
FROM user_permissions up
JOIN modules m ON up.module_id = m.id
ORDER BY up.user_id, m.module_name
LIMIT 10;