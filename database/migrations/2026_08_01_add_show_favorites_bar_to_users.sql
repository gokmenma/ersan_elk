-- Add show_favorites_bar column to users table
-- Date: 2026-08-01

SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'show_favorites_bar';

SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_SCHEMA = @dbname
            AND TABLE_NAME = @tablename
            AND COLUMN_NAME = @columnname
    ) > 0,
    "SELECT 1",
    "ALTER TABLE `users` ADD COLUMN `show_favorites_bar` TINYINT(1) DEFAULT 1 AFTER `mobile_menu_order`;"
));

PREPARE addColumnIfNotExists FROM @preparedStatement;
EXECUTE addColumnIfNotExists;
DEALLOCATE PREPARE addColumnIfNotExists;
