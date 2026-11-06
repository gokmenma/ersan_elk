SET @database_name = DATABASE();

SET @add_admin_disabled = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @database_name
              AND TABLE_NAME = 'user_notification_preferences'
              AND COLUMN_NAME = 'admin_disabled'
        ),
        'SELECT 1',
        'ALTER TABLE `user_notification_preferences` ADD COLUMN `admin_disabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_enabled`'
    )
);

PREPARE add_admin_disabled_statement FROM @add_admin_disabled;
EXECUTE add_admin_disabled_statement;
DEALLOCATE PREPARE add_admin_disabled_statement;
