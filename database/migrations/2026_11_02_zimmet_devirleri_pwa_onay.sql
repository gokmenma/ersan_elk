-- Zimmet devirleri: personel PWA üzerinden onaylı geçici devir talebi
-- Ön koşul: 2026_11_02_zimmet_devirleri.sql
-- beklemede : teslim eden personel talebi oluşturdu, teslim alacak personelin onayı bekleniyor
-- reddedildi: teslim alacak personel talebi reddetti
-- tamamlandi: araç asıl zimmet sahibine onaylı olarak geri teslim edildi

ALTER TABLE `zimmet_devirleri`
    MODIFY `durum` ENUM('beklemede','aktif','iade_edildi','tamamlandi','reddedildi','iptal') NOT NULL DEFAULT 'aktif';

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zimmet_devirleri' AND COLUMN_NAME = 'kaynak');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `zimmet_devirleri` ADD COLUMN `kaynak` ENUM(''web'',''pwa'') NOT NULL DEFAULT ''web'' AFTER `devir_turu`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zimmet_devirleri' AND COLUMN_NAME = 'talep_tarihi');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `zimmet_devirleri` ADD COLUMN `talep_tarihi` DATETIME DEFAULT NULL AFTER `alan_personel_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zimmet_devirleri' AND COLUMN_NAME = 'yanit_tarihi');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `zimmet_devirleri` ADD COLUMN `yanit_tarihi` DATETIME DEFAULT NULL AFTER `talep_tarihi`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zimmet_devirleri' AND COLUMN_NAME = 'red_nedeni');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `zimmet_devirleri` ADD COLUMN `red_nedeni` VARCHAR(500) DEFAULT NULL AFTER `iade_notu`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zimmet_devirleri' AND INDEX_NAME = 'idx_zd_veren_durum');
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `zimmet_devirleri` ADD KEY `idx_zd_veren_durum` (`firma_id`, `veren_personel_id`, `durum`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'arac-takip/api', 'zimmet-gecici-teslim-listesi', '*', 'read', p.id, 0, 0, 1, NOW(), NOW()
FROM `permissions` p
WHERE p.permission_key = 'arac_takip_yonetim'
LIMIT 1
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`), `access_mode` = VALUES(`access_mode`), `is_active` = 1, `updated_at` = NOW();
