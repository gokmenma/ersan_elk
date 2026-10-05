-- ============================================================================
-- E-Fatura Modülü Cari Listesi ve Mal/Hizmet Tanımları Tabloları & Menüleri
-- ============================================================================

-- 1. E-Fatura Cari Listesi Tablosu
CREATE TABLE IF NOT EXISTS `efatura_cariler` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firm_id` INT(11) NOT NULL,
  `cari_kodu` VARCHAR(50) NULL DEFAULT NULL,
  `unvan` VARCHAR(255) NOT NULL,
  `kisa_ad` VARCHAR(100) NULL DEFAULT NULL,
  `vkn_tckn` VARCHAR(20) NOT NULL,
  `vergi_dairesi` VARCHAR(100) NULL DEFAULT NULL,
  `alici_turu` ENUM('KURUMSAL','BIREYSEL') NOT NULL DEFAULT 'KURUMSAL',
  `belge_turu` ENUM('OTOMATIK','EFATURA','EARSIV') NOT NULL DEFAULT 'OTOMATIK',
  `posta_kutusu` VARCHAR(255) NULL DEFAULT NULL,
  `telefon` VARCHAR(30) NULL DEFAULT NULL,
  `eposta` VARCHAR(150) NULL DEFAULT NULL,
  `web_sitesi` VARCHAR(255) NULL DEFAULT NULL,
  `ticaret_sicil_no` VARCHAR(50) NULL DEFAULT NULL,
  `mersis_no` VARCHAR(50) NULL DEFAULT NULL,
  `ulke` VARCHAR(100) NULL DEFAULT 'Türkiye',
  `il` VARCHAR(100) NULL DEFAULT NULL,
  `ilce` VARCHAR(100) NULL DEFAULT NULL,
  `posta_kodu` VARCHAR(20) NULL DEFAULT NULL,
  `adres` TEXT NULL DEFAULT NULL,
  `notlar` TEXT NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_efatura_cariler_firm` (`firm_id`),
  KEY `idx_efatura_cariler_vkn` (`vkn_tckn`),
  KEY `idx_efatura_cariler_del` (`deleted_at`),
  KEY `idx_efatura_cariler_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. E-Fatura Mal/Hizmet Tanımları Tablosu
CREATE TABLE IF NOT EXISTS `efatura_mal_hizmet` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firm_id` INT(11) NOT NULL,
  `tur` ENUM('MAL','HIZMET') NOT NULL DEFAULT 'MAL',
  `stok_kodu` VARCHAR(100) NULL DEFAULT NULL,
  `urun_adi` VARCHAR(255) NOT NULL,
  `barkod` VARCHAR(100) NULL DEFAULT NULL,
  `birim` VARCHAR(50) NOT NULL DEFAULT 'C62',
  `para_birimi` VARCHAR(10) NOT NULL DEFAULT 'TRY',
  `kdv_orani` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  `alis_fiyati` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `satis_fiyati` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `kdv_dahil_mi` TINYINT(1) NOT NULL DEFAULT 0,
  `tevkifat_kodu` VARCHAR(20) NULL DEFAULT NULL,
  `tevkifat_orani` VARCHAR(20) NULL DEFAULT NULL,
  `gtip_no` VARCHAR(50) NULL DEFAULT NULL,
  `aciklama` TEXT NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_efatura_mal_hizmet_firm` (`firm_id`),
  KEY `idx_efatura_mal_hizmet_stok` (`stok_kodu`),
  KEY `idx_efatura_mal_hizmet_tur` (`tur`),
  KEY `idx_efatura_mal_hizmet_del` (`deleted_at`),
  KEY `idx_efatura_mal_hizmet_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Yetkiler (Permissions)
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura Cari Listesi', 'E-Fatura cari hesaplarını görüntüleme ve yönetme yetkisi', 'efatura/cari-list', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/cari-list');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura Mal/Hizmet Tanımları', 'E-Fatura ürün ve hizmet tanımlarını yönetme yetkisi', 'efatura/mal-hizmet-list', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/mal-hizmet-list');

-- 4. Menüler (Menus)
-- Parent ID bul (E-Fatura & E-Arşiv)
SET @parent_id = COALESCE((SELECT id FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = 0 LIMIT 1), 975);

-- Cari Listesi Menüsü
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'Cari Listesi', 'E-Fatura ve E-Arşiv Müşteri & Tedarikçi Cari Tanımları', @parent_id, 'Finans & Muhasebe', 4, 'efatura/cari-list', 'users', 25, 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/cari-list');

-- Mal / Hizmet Tanımları Menüsü
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'Mal/Hizmet Tanımları', 'E-Fatura Ürün, Hizmet, Stok ve Fiyat Tanımları', @parent_id, 'Finans & Muhasebe', 4, 'efatura/mal-hizmet-list', 'package', 26, 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/mal-hizmet-list');

-- 5. Yetkileri Rollere Bağla
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT ur.id, p.id, 0
FROM user_roles ur
JOIN permissions p ON p.auth_name IN ('efatura/cari-list', 'efatura/mal-hizmet-list')
WHERE ur.role_name IN ('Süper Admin', 'Firma Sahibi', 'Muhasebe Sorumlusu', 'Yönetici')
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp2 WHERE urp2.role_id = ur.id AND urp2.permission_id = p.id
  );
