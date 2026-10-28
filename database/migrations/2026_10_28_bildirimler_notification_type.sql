SET @database_name = DATABASE();

SET @add_type_column = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @database_name
              AND TABLE_NAME = 'bildirimler'
              AND COLUMN_NAME = 'notification_type'
        ),
        'SELECT 1',
        'ALTER TABLE `bildirimler` ADD COLUMN `notification_type` VARCHAR(64) NULL DEFAULT NULL AFTER `color`'
    )
);
PREPARE add_type_column_statement FROM @add_type_column;
EXECUTE add_type_column_statement;
DEALLOCATE PREPARE add_type_column_statement;

SET @add_type_index = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = @database_name
              AND TABLE_NAME = 'bildirimler'
              AND INDEX_NAME = 'idx_bildirimler_user_type_read'
        ),
        'SELECT 1',
        'ALTER TABLE `bildirimler` ADD INDEX `idx_bildirimler_user_type_read` (`user_id`, `notification_type`, `is_read`)'
    )
);
PREPARE add_type_index_statement FROM @add_type_index;
EXECUTE add_type_index_statement;
DEALLOCATE PREPARE add_type_index_statement;

UPDATE `bildirimler` SET `notification_type` = CASE
    WHEN `title` LIKE '%KEP%' THEN 'kep'
    WHEN `title` LIKE '%ücret pusulası%' OR `link` LIKE '%bordro/%' THEN 'payroll'
    WHEN `link` LIKE '%kesme-acma%' THEN 'kesme_acma'
    WHEN `link` LIKE '%km-onaylari%' OR `link` LIKE '%km-bildirim%' OR `title` LIKE 'KM Bildirimi%' THEN 'km'
    WHEN `link` LIKE '%p=yardim%' THEN 'support'
    WHEN `link` LIKE '%tab=avans%' THEN 'advance_request'
    WHEN `link` LIKE '%tab=izin%' THEN 'leave_request'
    WHEN `link` LIKE '%tab=talep%' AND `title` LIKE '%arıza%' THEN 'fault_request'
    WHEN `link` LIKE '%tab=talep%' THEN 'general_request'
    WHEN `link` LIKE '%p=kacak/%' THEN 'kacak_created'
    WHEN `link` LIKE '%p=ihbar/%' THEN 'ihbar_created'
    WHEN `link` LIKE '%p=nobet%' THEN 'shift'
    WHEN `link` LIKE '%evrak-takip%' THEN 'document'
    WHEN `link` LIKE '%gorev%' THEN 'task'
    WHEN `link` LIKE '%e-fatura%' OR `link` LIKE '%einvoice%' THEN 'einvoice'
    ELSE NULL
END
WHERE `notification_type` IS NULL;
