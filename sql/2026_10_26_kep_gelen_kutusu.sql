SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kep_mesajlar' AND COLUMN_NAME = 'kep_sira_no');
SET @sql_col := IF(@col_exists = 0, 'ALTER TABLE `kep_mesajlar` ADD COLUMN `kep_sira_no` INT(11) NOT NULL DEFAULT 0 AFTER `kep_id`', 'SELECT 1');
PREPARE stmt_col FROM @sql_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

SET @perm_kep_id := (SELECT `id` FROM `permissions` WHERE `permission_key` = 'evrak-takip/kep' LIMIT 1);

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-gelen-onizleme', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE @perm_kep_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-gelen-onizleme');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-gelen-aktar', '*', 'write', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE @perm_kep_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-gelen-aktar');

SET @col_auto := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kep_ayarlar' AND COLUMN_NAME = 'otomatik_senkron_user_id');
SET @sql_auto := IF(@col_auto = 0, 'ALTER TABLE `kep_ayarlar` ADD COLUMN `otomatik_senkron_user_id` INT(11) NULL DEFAULT NULL, ADD COLUMN `son_gelen_senkron` DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt_auto FROM @sql_auto;
EXECUTE stmt_auto;
DEALLOCATE PREPARE stmt_auto;

SET @col_onay := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kep_ayarlar' AND COLUMN_NAME = 'gelen_alindi_onay_otomatik');
SET @sql_onay := IF(@col_onay = 0, 'ALTER TABLE `kep_ayarlar` ADD COLUMN `gelen_alindi_onay_otomatik` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt_onay FROM @sql_onay;
EXECUTE stmt_onay;
DEALLOCATE PREPARE stmt_onay;
