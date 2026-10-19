-- ============================================================================
-- PTT KEP (Kayıtlı Elektronik Posta) Entegrasyonu Tabloları ve Yetkileri
-- Versiyon: 1.0
-- Tarih: 2026-10-19
-- ============================================================================

-- 1. Firma KEP Ayarları Tablosu
CREATE TABLE IF NOT EXISTS `kep_ayarlar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firm_id` INT(11) NOT NULL,
  `kep_adresi` VARCHAR(100) NOT NULL,
  `yetkili_tcno` TEXT NOT NULL,
  `parola` TEXT NOT NULL,
  `sifre` TEXT NOT NULL,
  `wsdl_url` VARCHAR(255) NOT NULL DEFAULT 'https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl',
  `ortam` ENUM('prod', 'test') NOT NULL DEFAULT 'prod',
  `bakiye_kontor` DECIMAL(10,2) NULL DEFAULT NULL,
  `bakiye_cins` VARCHAR(20) NULL DEFAULT 'Adet',
  `son_bakiye_sorgu` DATETIME NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_kep_ayarlar_firm` (`firm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. KEP İletileri / Mesajları Tablosu
CREATE TABLE IF NOT EXISTS `kep_mesajlar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firm_id` INT(11) NOT NULL,
  `evrak_id` INT(11) NULL DEFAULT NULL,
  `kep_id` VARCHAR(100) NOT NULL,
  `org_mesaj_id` VARCHAR(100) NULL DEFAULT NULL,
  `yon` ENUM('gelen', 'giden') NOT NULL DEFAULT 'giden',
  `gonderen_kep` VARCHAR(100) NOT NULL,
  `alici_kep` VARCHAR(255) NOT NULL,
  `alici_unvan` VARCHAR(255) NULL DEFAULT NULL,
  `konu` VARCHAR(255) NOT NULL,
  `icerik` LONGTEXT NULL DEFAULT NULL,
  `paket_turu` ENUM('Standart', 'EYazisma', 'ETebligat') NOT NULL DEFAULT 'Standart',
  `durum` VARCHAR(50) NOT NULL DEFAULT 'gonderildi',
  `hata_mesaji` TEXT NULL DEFAULT NULL,
  `delil_sayisi` INT(11) NOT NULL DEFAULT 0,
  `son_delil_durumu` VARCHAR(100) NULL DEFAULT NULL,
  `yerel_dosya_yolu` VARCHAR(500) NULL DEFAULT NULL,
  `ham_paket_yolu` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_kep_msg_id` (`firm_id`, `kep_id`),
  KEY `idx_kep_msg_status` (`durum`),
  KEY `idx_kep_msg_evrak` (`evrak_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. KEP Yasal Delilleri Tablosu
CREATE TABLE IF NOT EXISTS `kep_deliller` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `kep_mesaj_id` INT(11) NOT NULL,
  `delil_id` VARCHAR(100) NOT NULL,
  `delil_tur_id` INT(11) NOT NULL,
  `delil_aciklama` VARCHAR(255) NOT NULL,
  `olay_zamani` DATETIME NOT NULL,
  `kephs` VARCHAR(100) NULL DEFAULT NULL,
  `delil_xml_yolu` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_kep_delil_id` (`delil_id`),
  KEY `idx_kep_delil_msg` (`kep_mesaj_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. evrak_takip Tablosu Güncellemeleri
SET @exist_kep_msg := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'kep_mesaj_id');
SET @sql_kep_msg := IF(@exist_kep_msg = 0, 'ALTER TABLE `evrak_takip` ADD COLUMN `kep_mesaj_id` VARCHAR(100) NULL DEFAULT NULL AFTER `uets_mesaj_id`', 'SELECT 1');
PREPARE stmt_kep_msg FROM @sql_kep_msg;
EXECUTE stmt_kep_msg;
DEALLOCATE PREPARE stmt_kep_msg;

ALTER TABLE `evrak_takip` MODIFY COLUMN `kaynak_tipi` ENUM('manuel', 'uets', 'kep') NOT NULL DEFAULT 'manuel';

-- 5. Yetkilendirme (Permissions) ve Rol Atamaları
INSERT INTO `permissions` (`name`, `auth_name`, `permission_key`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT 'KEP Yönetimi ve Sevk', 'kep.management', 'evrak-takip/kep', 'PTT KEP giden evrak sevk, bakiye, rehber ve yasal delil yönetimi', 'Evrak Yönetimi', 1, 1, 0, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permission_key` = 'evrak-takip/kep'
);

-- Süper Admin rollerine otomatik yetki tanımlama
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_at`, `created_by`)
SELECT ur.`id`, p.`id`, NOW(), 1
FROM `user_roles` ur
CROSS JOIN `permissions` p
WHERE ur.`superadmin` = 1
  AND p.`permission_key` = 'evrak-takip/kep'
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp 
      WHERE urp.`role_id` = ur.`id` AND urp.`permission_id` = p.`id`
  );

-- 6. Permission Policies (evrak-takip/api altındaki KEP aksiyonları)
SET @perm_kep_id := (SELECT `id` FROM `permissions` WHERE `permission_key` = 'evrak-takip/kep' LIMIT 1);

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-ayarlari-getir', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-ayarlari-getir');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-ayarlari-kaydet', '*', 'write', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-ayarlari-kaydet');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-baglanti-test', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-baglanti-test');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-bakiye-sorgula', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-bakiye-sorgula');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-alici-dogrula', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-alici-dogrula');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-gonder', '*', 'write', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-gonder');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-dogrudan-gonder', '*', 'write', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-dogrudan-gonder');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-delil-listesi', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-delil-listesi');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-delil-indir', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-delil-indir');
