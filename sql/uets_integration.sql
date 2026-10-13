-- ============================================================================
-- UETS (Ulusal Elektronik Tebligat Sistemi) KEP Entegrasyonu Tabloları ve Yetkileri
-- Versiyon: 1.0
-- Tarih: 2026-10-12
-- ============================================================================

-- 1. UETS Firma Ayarları Tablosu
CREATE TABLE IF NOT EXISTS `uets_ayarlar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firm_id` INT(11) NOT NULL,
  `uets_adresi` VARCHAR(50) NOT NULL,
  `api_key` TEXT NOT NULL,
  `api_environment` ENUM('test', 'prod') NOT NULL DEFAULT 'test',
  `otomatik_senkronizasyon` TINYINT(1) NOT NULL DEFAULT 1,
  `son_gelen_sync_zamani` DATETIME NULL DEFAULT NULL,
  `son_giden_sync_zamani` DATETIME NULL DEFAULT NULL,
  `bakiye` DECIMAL(10,2) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_uets_ayarlar_firm` (`firm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. UETS Tebligat Mesajları Tablosu
CREATE TABLE IF NOT EXISTS `uets_mesajlar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firm_id` INT(11) NOT NULL,
  `evrak_id` INT(11) NULL DEFAULT NULL,
  `uets_message_id` VARCHAR(64) NOT NULL,
  `yon` ENUM('gelen', 'giden') NOT NULL,
  `sender_uets` VARCHAR(50) NOT NULL,
  `sender_name` VARCHAR(255) NULL DEFAULT NULL,
  `recipient_uets` VARCHAR(50) NOT NULL,
  `recipient_name` VARCHAR(255) NULL DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `folders_id` INT(11) NOT NULL DEFAULT 1,
  `size` INT(11) NOT NULL DEFAULT 0,
  `etag` VARCHAR(64) NULL DEFAULT NULL,
  `barcode` VARCHAR(64) NULL DEFAULT NULL,
  `reference_id` VARCHAR(100) NULL DEFAULT NULL,
  `inserttime` DATETIME NULL DEFAULT NULL,
  `updatetime` DATETIME NULL DEFAULT NULL,
  `hukuki_teblig_tarihi` DATETIME NULL DEFAULT NULL,
  `okunma_tarihi` DATETIME NULL DEFAULT NULL,
  `yerel_dosya_yolu` VARCHAR(500) NULL DEFAULT NULL,
  `ham_eyp_yolu` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_uets_msg_id` (`firm_id`, `uets_message_id`),
  KEY `idx_uets_msg_status` (`status`),
  KEY `idx_uets_msg_evrak` (`evrak_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. UETS Tebligat Delilleri Tablosu
CREATE TABLE IF NOT EXISTS `uets_deliller` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `uets_mesaj_id` INT(11) NOT NULL,
  `evidence_id` VARCHAR(64) NOT NULL,
  `delil_tipi` VARCHAR(50) NOT NULL,
  `olay` VARCHAR(50) NOT NULL,
  `olay_zamani` DATETIME NOT NULL,
  `delil_pdf_yolu` VARCHAR(500) NULL DEFAULT NULL,
  `delil_hash` VARCHAR(64) NULL DEFAULT NULL,
  `raw_json` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_uets_evid_id` (`evidence_id`),
  KEY `idx_uets_evid_msg` (`uets_mesaj_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. UETS Alıcı Rehberi / Önbellek Tablosu
CREATE TABLE IF NOT EXISTS `uets_rehber` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firm_id` INT(11) NOT NULL,
  `uets_adresi` VARCHAR(50) NOT NULL,
  `tckn_vkn` VARCHAR(20) NULL DEFAULT NULL,
  `unvan` VARCHAR(255) NOT NULL,
  `yetkili_ad_soyad` VARCHAR(255) NULL DEFAULT NULL,
  `son_sorgu_tarihi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uets_rehber_firm` (`firm_id`),
  KEY `idx_uets_rehber_addr` (`uets_adresi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. evrak_takip Tablosuna UETS Alanlarının Eklenmesi
SET @exist_kaynak := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'kaynak_tipi');
SET @sql_kaynak := IF(@exist_kaynak = 0, 'ALTER TABLE `evrak_takip` ADD COLUMN `kaynak_tipi` ENUM(\'manuel\', \'uets\') NOT NULL DEFAULT \'manuel\' AFTER `evrak_tipi`', 'SELECT 1');
PREPARE stmt_kaynak FROM @sql_kaynak;
EXECUTE stmt_kaynak;
DEALLOCATE PREPARE stmt_kaynak;

SET @exist_uets_id := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'uets_mesaj_id');
SET @sql_uets_id := IF(@exist_uets_id = 0, 'ALTER TABLE `evrak_takip` ADD COLUMN `uets_mesaj_id` VARCHAR(64) NULL DEFAULT NULL AFTER `kaynak_tipi`', 'SELECT 1');
PREPARE stmt_uets_id FROM @sql_uets_id;
EXECUTE stmt_uets_id;
DEALLOCATE PREPARE stmt_uets_id;

-- 6. Yetkilendirme (Permissions) ve Rol Atamaları
-- Kural: Yeni aktif yetki benzersiz permission_key ile oluşturulur ve superadmin=1 rollere otomatik atanır.
INSERT INTO `permissions` (`name`, `auth_name`, `permission_key`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT 'UETS / KEP Yönetimi', 'uets.management', 'evrak-takip/uets', 'UETS E-Tebligat gelen ve giden işlemleri, senkronizasyon ve ayarlar', 'Evrak Yönetimi', 1, 1, 0, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permission_key` = 'evrak-takip/uets'
);

-- Süper Admin ve Firma Sahibi rollerine atama
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_at`, `created_by`)
SELECT ur.`id`, p.`id`, NOW(), 1
FROM `user_roles` ur
CROSS JOIN `permissions` p
WHERE ur.`superadmin` = 1
  AND p.`permission_key` = 'evrak-takip/uets'
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp 
      WHERE urp.`role_id` = ur.`id` AND urp.`permission_id` = p.`id`
  );

-- 7. Permission Policies (evrak-takip/api altındaki UETS aksiyonları)
SET @perm_id := (SELECT `id` FROM `permissions` WHERE `permission_key` = 'evrak-takip/uets' LIMIT 1);

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-ayarlari-getir', '*', 'read', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-ayarlari-getir');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-ayarlari-kaydet', '*', 'write', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-ayarlari-kaydet');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-baglanti-test', '*', 'read', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-baglanti-test');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-bakiye-sorgula', '*', 'read', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-bakiye-sorgula');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-alici-ara', '*', 'read', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-alici-ara');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-senkronize-et', '*', 'write', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-senkronize-et');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-delil-listesi', '*', 'read', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-delil-listesi');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-delil-pdf-indir', '*', 'read', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-delil-pdf-indir');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-eyp-indir', '*', 'read', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-eyp-indir');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-gonder', '*', 'write', @perm_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-gonder');
