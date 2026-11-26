SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gorevler' AND COLUMN_NAME = 'evrak_id');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `gorevler` ADD COLUMN `evrak_id` INT NULL DEFAULT NULL AFTER `gorev_kullanicilari`, ADD INDEX `idx_gorevler_evrak_id` (`evrak_id`)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @perm_gorevler_id := (SELECT id FROM permissions WHERE permission_key = 'gorevler' AND is_active = 1 LIMIT 1);

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'evrak-gorev-hazirla', 'POST', 'read', @perm_gorevler_id, 0, 0, 1, NOW(), NOW()
WHERE @perm_gorevler_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `permission_policies`
    WHERE `resource` = 'evrak-takip/api' AND `action` = 'evrak-gorev-hazirla');

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'evrak-gorev-olustur', 'POST', 'write', @perm_gorevler_id, 0, 0, 1, NOW(), NOW()
WHERE @perm_gorevler_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `permission_policies`
    WHERE `resource` = 'evrak-takip/api' AND `action` = 'evrak-gorev-olustur');
