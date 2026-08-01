-- =====================================================
-- KAÇAK İŞLEMLERİ MODÜLÜ
-- İş Takip > Kaçak İşlemleri menüsü, PWA bildirim/onay akışı,
-- iptal yönetimi, fotoğraf yönetimi ve raporlar için şema.
-- =====================================================

-- -----------------------------------------------------
-- 1. kacak_kontrol tablosu yeni alanlar
-- -----------------------------------------------------
ALTER TABLE `kacak_kontrol`
    ADD COLUMN IF NOT EXISTS `bildiren_personel_id` INT(11) DEFAULT NULL AFTER `personel_ids`,
    ADD COLUMN IF NOT EXISTS `kaynak` ENUM('masaustu','pwa','excel') NOT NULL DEFAULT 'masaustu' AFTER `bildiren_personel_id`,
    ADD COLUMN IF NOT EXISTS `onay_durumu` ENUM('beklemede','onaylandi','reddedildi') NOT NULL DEFAULT 'onaylandi' AFTER `kaynak`,
    ADD COLUMN IF NOT EXISTS `onaylayan_id` INT(11) DEFAULT NULL AFTER `onay_durumu`,
    ADD COLUMN IF NOT EXISTS `onay_tarihi` DATETIME DEFAULT NULL AFTER `onaylayan_id`,
    ADD COLUMN IF NOT EXISTS `red_nedeni` TEXT DEFAULT NULL AFTER `onay_tarihi`,
    ADD COLUMN IF NOT EXISTS `durum` ENUM('aktif','iptal') NOT NULL DEFAULT 'aktif' AFTER `red_nedeni`,
    ADD COLUMN IF NOT EXISTS `iptal_aciklama` TEXT DEFAULT NULL AFTER `durum`,
    ADD COLUMN IF NOT EXISTS `hakedisten_dus` TINYINT(1) NOT NULL DEFAULT 0 AFTER `iptal_aciklama`,
    ADD COLUMN IF NOT EXISTS `iptal_tarihi` DATETIME DEFAULT NULL AFTER `hakedisten_dus`,
    ADD COLUMN IF NOT EXISTS `iptal_eden` INT(11) DEFAULT NULL AFTER `iptal_tarihi`,
    ADD COLUMN IF NOT EXISTS `guncelleme_tarihi` DATETIME DEFAULT NULL ON UPDATE current_timestamp() AFTER `olusturma_tarihi`;

ALTER TABLE `kacak_kontrol`
    ADD INDEX IF NOT EXISTS `idx_onay_durumu` (`onay_durumu`),
    ADD INDEX IF NOT EXISTS `idx_durum` (`durum`),
    ADD INDEX IF NOT EXISTS `idx_bildiren` (`bildiren_personel_id`),
    ADD INDEX IF NOT EXISTS `idx_firma_tarih_durum` (`firma_id`, `tarih`, `durum`);

-- Mevcut kayıtlar masaüstünden girilmiş ve onaylı kabul edilir.
UPDATE `kacak_kontrol`
SET `onay_durumu` = 'onaylandi', `durum` = 'aktif', `kaynak` = 'masaustu'
WHERE `onay_durumu` IS NULL OR `durum` IS NULL;

-- -----------------------------------------------------
-- 2. Kaçak tutanak / saha / iptal fotoğrafları
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `kacak_kontrol_fotograflari` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `kacak_id` INT(11) NOT NULL,
    `tur` ENUM('tutanak','saha','iptal') NOT NULL DEFAULT 'saha',
    `dosya_yolu` VARCHAR(255) NOT NULL,
    `orijinal_ad` VARCHAR(255) DEFAULT NULL,
    `yukleyen_personel_id` INT(11) DEFAULT NULL,
    `yukleyen_user_id` INT(11) DEFAULT NULL,
    `arsivlendi` TINYINT(1) NOT NULL DEFAULT 0,
    `arsiv_tarihi` DATETIME DEFAULT NULL,
    `olusturma_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_kacak` (`kacak_id`),
    KEY `idx_firma_tur` (`firma_id`, `tur`),
    KEY `idx_arsiv` (`arsivlendi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Menü ve ana yetki kaydı (parent_id = 5 -> İş Takip)
--    Bu projede MenuModel erişimi "permissions.id = menus.id" üzerinden de
--    eşleştirdiği için menü id'si HER İKİ tabloda da boş olan bir id olmalıdır.
--    Aksi halde aynı id'ye sahip alakasız bir yetki menüyü açar.
-- -----------------------------------------------------
SET @kacak_menu_id = (
    SELECT GREATEST(
        (SELECT COALESCE(MAX(`id`), 0) FROM `menus`),
        (SELECT COALESCE(MAX(`id`), 0) FROM `permissions`)
    ) + 1
);

SET @kacak_menu_id = COALESCE(
    (SELECT `id` FROM `menus` WHERE `menu_link` = 'kacak/list' LIMIT 1),
    @kacak_menu_id
);

INSERT INTO `menus` (`id`, `menu_name`, `parent_id`, `group_name`, `group_order`, `menu_link`, `menu_icon`, `menu_order`, `is_active`, `is_menu`, `is_authorized`)
SELECT @kacak_menu_id, 'Kaçak İşlemleri', 5, 'İş Takip Yönetim', 1, 'kacak/list', 'shield-off', 3, 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `menu_link` = 'kacak/list');

-- -----------------------------------------------------
-- 4. Yetki kayıtları (ana yetki menü id'si ile aynı id üzerinden)
-- -----------------------------------------------------
INSERT INTO `permissions` (`id`, `name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT @kacak_menu_id, 'Kaçak İşlemleri', 'kacak_islemleri', 'Kaçak İşlemleri yönetim ekranını görüntüleme', 'İş Takip', 0, 1, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`id` = @kacak_menu_id OR p.`auth_name` = 'kacak_islemleri');

-- Alt yetkiler (menüsüz)
INSERT INTO `permissions` (`name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT * FROM (
    SELECT 'Kaçak Kayıt Düzenle' AS n, 'kacak_duzenle' AS a, 'Kaçak kaydı ekleme, güncelleme ve silme' AS d, 'İş Takip' AS g, 0 AS l, 1 AS ia, 0 AS sa, 0 AS ir
    UNION ALL SELECT 'Kaçak Bildirim Onayı', 'kacak_onay', 'Personel PWA kaçak bildirimlerini onaylama/reddetme', 'İş Takip', 0, 1, 0, 0
    UNION ALL SELECT 'Kaçak Tutanak İptali', 'kacak_iptal', 'Kaçak tutanağını iptal etme ve iptali geri alma', 'İş Takip', 0, 1, 0, 0
    UNION ALL SELECT 'Kaçak Fotoğraf Arşivi', 'kacak_arsiv', 'Kaçak fotoğraflarını arşivleyip sunucudan silme', 'İş Takip', 0, 1, 0, 0
) t
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`auth_name` = t.a);

-- -----------------------------------------------------
-- 5. Detaylı Rapor (menü 62) yetkisi olan rollere kaçak yetkilerini ver
-- -----------------------------------------------------
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT urp.`role_id`, p.`id`
FROM `user_role_permissions` urp
CROSS JOIN `permissions` p
WHERE urp.`permission_id` = 62
  AND p.`auth_name` IN ('kacak_islemleri', 'kacak_duzenle', 'kacak_onay', 'kacak_iptal', 'kacak_arsiv')
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp2
      WHERE urp2.`role_id` = urp.`role_id` AND urp2.`permission_id` = p.`id`
  );

-- -----------------------------------------------------
-- 6. Tür listesine "Usülsüz" eklendi
-- -----------------------------------------------------
ALTER TABLE `kacak_kontrol`
    MODIFY COLUMN `tur` ENUM('Kaçak','Abonesiz','Usülsüz') DEFAULT 'Kaçak';
