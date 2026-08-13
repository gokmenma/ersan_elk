-- =====================================================================
-- Kesme / Açma Modülü — tablolar, indeksler, menü ve yetki kayıtları
-- Döküman: Kesme-Acma-Modulu-Teknik-Dokuman.docx (v1.1)
-- Veritabanı: ersantrc_personel
-- Menü konumu: İş Takip (menus.parent_id = 5)
--
-- Çalıştırmadan önce doğrulanmalı:
--   SELECT id, menu_name FROM menus WHERE id = 5;
--   SELECT id FROM menus WHERE menu_link = 'kesme-acma/list';
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. mahalle — mahalle havuzu
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mahalle` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `ad` VARCHAR(120) NOT NULL,
    `ilce` ENUM('onikisubat','dulkadiroglu') NOT NULL,
    `kod_araligi` VARCHAR(60) DEFAULT NULL,
    `havuzda` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_firma_ad` (`firma_id`, `ad`),
    KEY `idx_firma_ilce` (`firma_id`, `ilce`, `havuzda`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. mahalle_mesaj — mesaj ve hazır olma takibi (M1)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mahalle_mesaj` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `mahalle_id` INT(11) NOT NULL,
    `mesaj_tarihi` DATE NOT NULL,
    `hazir_tarihi` DATE NOT NULL,
    `kaydeden_id` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_firma_mahalle` (`firma_id`, `mahalle_id`, `mesaj_tarihi`),
    KEY `idx_hazir` (`firma_id`, `hazir_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. ekip_mahalle_atama — atama geçmişi (M3, M10-M12)
--    Geçmiş sekmesi ve sıradaki ilçe döngüsü bu tablodan okunur.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ekip_mahalle_atama` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `ekip_id` INT(11) NOT NULL,
    `mahalle_id` INT(11) NOT NULL,
    `baslangic` DATE NOT NULL,
    `bitis` DATE DEFAULT NULL,
    `durum` ENUM('aktif','bitti') NOT NULL DEFAULT 'aktif',
    `atayan_id` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ekip_gecmis` (`firma_id`, `ekip_id`, `baslangic`),
    KEY `idx_mahalle_gecmis` (`firma_id`, `mahalle_id`, `bitis`),
    KEY `idx_aktif` (`firma_id`, `durum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. ekip_gunluk_durum — günlük kalan iş girişi (M5)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ekip_gunluk_durum` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `ekip_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `kalan_is` INT(11) NOT NULL,
    `giren_id` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ekip_gun` (`firma_id`, `ekip_id`, `tarih`),
    KEY `idx_firma_tarih` (`firma_id`, `tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. telefon_nobet — ofis telefon nöbeti
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `telefon_nobet` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `personel_id` INT(11) NOT NULL,
    `elle_degistirildi` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_gun` (`firma_id`, `tarih`),
    KEY `idx_personel` (`firma_id`, `personel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Merkez saha nöbeti taslağı
--    Mevcut `nobetler` tablosu yalnızca salt okunur gösterilir. Bu modüldeki
--    otomatik ve elle planlar eski nöbet/talep/değişim verilerini etkilemez.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kesme_acma_nobet_taslak` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `personel_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `nobet_tipi` ENUM('standart','hafta_sonu','resmi_tatil','ozel') NOT NULL DEFAULT 'standart',
    `elle_degistirildi` TINYINT(1) NOT NULL DEFAULT 0,
    `olusturan_id` INT(11) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_firma_tarih` (`firma_id`, `tarih`, `is_active`, `deleted_at`),
    KEY `idx_personel_tarih` (`firma_id`, `personel_id`, `tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. nobet_ilce_plani — haftalık ilçe görevi (K3, K4, K5)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nobet_ilce_plani` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `hafta_basi` DATE NOT NULL,
    `ilce` ENUM('turkoglu','pazarcik') NOT NULL,
    `ekip_id` INT(11) NOT NULL,
    `elle_degistirildi` TINYINT(1) NOT NULL DEFAULT 0,
    `olusturan_id` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_hafta_ilce` (`firma_id`, `hafta_basi`, `ilce`),
    KEY `idx_ekip_hafta` (`firma_id`, `ekip_id`, `hafta_basi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. personel.sirket_araci — K3: şirket aracı kullanan personelin ekibi
--    ilçeye gönderilmez. Sütun yoksa eklenir.
-- ---------------------------------------------------------------------
SET @sutun_var = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel' AND COLUMN_NAME = 'sirket_araci'
);
SET @sql = IF(@sutun_var = 0,
    'ALTER TABLE `personel` ADD COLUMN `sirket_araci` TINYINT(1) NOT NULL DEFAULT 0 AFTER `arac_kullanim`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 9. Menü kaydı — İş Takip (parent_id = 5) altına
--    MenuModel "permissions.id = menus.id" eşleşmesi de kullandığı için
--    id her iki tabloda da boş olan bir değer olmalıdır.
-- ---------------------------------------------------------------------
SET @kesme_menu_id = (
    SELECT GREATEST(
        (SELECT COALESCE(MAX(`id`), 0) FROM `menus`),
        (SELECT COALESCE(MAX(`id`), 0) FROM `permissions`)
    ) + 1
);

SET @kesme_menu_id = COALESCE(
    (SELECT `id` FROM `menus` WHERE `menu_link` = 'kesme-acma/list' LIMIT 1),
    @kesme_menu_id
);

INSERT INTO `menus`
    (`id`, `menu_name`, `parent_id`, `group_name`, `group_order`, `menu_link`, `menu_icon`, `menu_order`, `is_active`, `is_menu`, `is_authorized`, `created_by`)
SELECT @kesme_menu_id, 'Kesme / Açma', 5, 'İş Takip Yönetim', 1, 'kesme-acma/list', 'scissors', 7, 1, 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `menu_link` = 'kesme-acma/list');

-- ---------------------------------------------------------------------
-- 10. Yetkiler
-- ---------------------------------------------------------------------
INSERT INTO `permissions`
    (`id`, `name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT @kesme_menu_id, 'Kesme / Açma', 'kesme_acma',
       'Kesme/açma modülü ekranlarını görüntüleme (dashboard, mahalleler, ekipler, geçmiş, nöbet, günlük işlem)',
       'İş Takip', 0, 1, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`id` = @kesme_menu_id OR p.`auth_name` = 'kesme_acma');

INSERT INTO `permissions`
    (`name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT * FROM (
    SELECT 'Mahalle Tanımları' AS n, 'kesme_mahalle_tanim' AS a,
           'Mahalle ekleme, kod aralığı düzenleme ve havuz dışına alma' AS d, 'İş Takip' AS g,
           0 AS l, 1 AS ia, 0 AS sa, 0 AS ir
    UNION ALL SELECT 'Mahalle Mesaj Kaydı', 'kesme_mesaj',
           'Mahalleye mesaj atıldı kaydı girme ve hazır tarihini güncelleme', 'İş Takip', 0, 1, 0, 0
    UNION ALL SELECT 'Mahalle Ekibe Atama', 'kesme_atama',
           'Ekibe mahalle atama, aktif atamayı kapatma ve geçmiş kaydını düzeltme', 'İş Takip', 0, 1, 0, 0
    UNION ALL SELECT 'Kalan İş Girişi', 'kesme_kalan_is',
           'Ekiplerin günlük kalan iş sayısını girme ve düzeltme', 'İş Takip', 0, 1, 0, 0
    UNION ALL SELECT 'Nöbet Planı Yönetimi', 'kesme_nobet',
           'Merkez, ilçe ve telefon nöbet planını üretme ve elle değiştirme', 'İş Takip', 0, 1, 0, 0
) t
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`auth_name` = t.a);

-- Aparat Takip yetkisi olan rollere kesme/açma yetkilerini de ver
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT urp.`role_id`, p.`id`
FROM `user_role_permissions` urp
INNER JOIN `permissions` kaynak
        ON kaynak.`id` = urp.`permission_id` AND kaynak.`auth_name` = 'aparat_takip'
CROSS JOIN `permissions` p
WHERE p.`auth_name` IN ('kesme_acma','kesme_mahalle_tanim','kesme_mesaj','kesme_atama','kesme_kalan_is','kesme_nobet')
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp2
      WHERE urp2.`role_id` = urp.`role_id` AND urp2.`permission_id` = p.`id`
  );

-- Doğrulama:
--   SELECT * FROM menus WHERE menu_link = 'kesme-acma/list';
--   SELECT id, name, auth_name FROM permissions WHERE auth_name LIKE 'kesme%';
--   SELECT role_id, permission_id FROM user_role_permissions
--     WHERE permission_id IN (SELECT id FROM permissions WHERE auth_name LIKE 'kesme%');
