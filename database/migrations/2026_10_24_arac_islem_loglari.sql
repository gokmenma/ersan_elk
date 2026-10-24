-- Araç Takip: araç bazlı işlem geçmişi (kim ekledi, kim güncelledi, ne değişti)

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'araclar' AND COLUMN_NAME = 'olusturan_kullanici_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `araclar` ADD COLUMN `olusturan_kullanici_id` INT(11) NULL DEFAULT NULL AFTER `ruhsat_yukleyen_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'araclar' AND COLUMN_NAME = 'guncelleyen_kullanici_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `araclar` ADD COLUMN `guncelleyen_kullanici_id` INT(11) NULL DEFAULT NULL AFTER `olusturan_kullanici_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `arac_islem_loglari` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `arac_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL DEFAULT 0,
    `islem_turu` VARCHAR(50) NOT NULL,
    `aciklama` VARCHAR(500) DEFAULT NULL,
    `degisiklikler` LONGTEXT DEFAULT NULL,
    `ip_adresi` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_arac_islem_arac` (`firma_id`, `arac_id`, `created_at`),
    KEY `idx_arac_islem_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'arac-takip/api', 'arac-islem-gecmisi', '*', 'read', p.id, 0, 0, 1, NOW(), NOW()
FROM `permissions` p
WHERE p.permission_key = 'arac_takip_yonetim'
LIMIT 1
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`), `is_active` = 1, `updated_at` = NOW();
