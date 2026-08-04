-- =====================================================
-- KESME-AÇMA APARAT TAKİP MODÜLÜ
-- Ekip bazlı aparat stoğu, saha işlem kaydı, ekipler arası
-- transfer, depo hareketleri ve sayım (mutabakat) şeması.
--
-- Havuzlar: depo / ekip / saha (sayaç üstünde takılı) / hurda / kayip
-- Doğrulama: depo + Σekip + saha + hurda + kayip = toplam alınan
-- =====================================================

-- -----------------------------------------------------
-- 1. Aparat tipleri
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `aparat_tipleri` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `ad` VARCHAR(100) NOT NULL,
    `kod` VARCHAR(20) NOT NULL,
    `renk` VARCHAR(50) NOT NULL DEFAULT 'primary',
    `sira` INT(11) NOT NULL DEFAULT 1,
    `aciklama` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `olusturma_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
    `olusturan_id` INT(11) DEFAULT NULL,
    `guncelleme_tarihi` DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_firma_kod` (`firma_id`, `kod`, `silinme_tarihi`),
    KEY `idx_firma_aktif` (`firma_id`, `is_active`, `silinme_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Anlık bakiye (hızlı okuma için; gerçeğin kaynağı aparat_hareket'tir)
--    sahip_id: ekip için tanimlamalar.id (grup='ekip_kodu'), diğer havuzlarda 0
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `aparat_stok` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `sahip_tipi` ENUM('ekip','depo','saha','hurda','kayip') NOT NULL,
    `sahip_id` INT(11) NOT NULL DEFAULT 0,
    `aparat_tip_id` INT(11) NOT NULL,
    `adet` INT(11) NOT NULL DEFAULT 0,
    `guncelleme_tarihi` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sahip_tip` (`firma_id`, `sahip_tipi`, `sahip_id`, `aparat_tip_id`),
    KEY `idx_tip` (`aparat_tip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Ana defter (ledger). Stoğu değiştiren her olay burada iz bırakır.
--    adet işaretlidir: havuzdan çıkış negatif, girişte pozitif.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `aparat_hareket` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `aparat_tip_id` INT(11) NOT NULL,
    `hareket_tipi` ENUM('kesme','acma','transfer','depo_giris','depo_cikis','depo_iade','hurda','kayip','sayim_duzeltme','acilis') NOT NULL,
    `sahip_tipi` ENUM('ekip','depo','saha','hurda','kayip') NOT NULL,
    `sahip_id` INT(11) NOT NULL DEFAULT 0,
    `adet` INT(11) NOT NULL,
    `ekip_id` INT(11) DEFAULT NULL,
    `personel_id` INT(11) DEFAULT NULL,
    `referans_tipi` VARCHAR(30) DEFAULT NULL,
    `referans_id` INT(11) DEFAULT NULL,
    `aciklama` VARCHAR(255) DEFAULT NULL,
    `iptal_mi` TINYINT(1) NOT NULL DEFAULT 0,
    `ters_hareket_id` INT(11) DEFAULT NULL,
    `tarih` DATETIME NOT NULL DEFAULT current_timestamp(),
    `kaydeden_id` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_firma_tarih` (`firma_id`, `tarih`),
    KEY `idx_sahip` (`firma_id`, `sahip_tipi`, `sahip_id`, `aparat_tip_id`),
    KEY `idx_referans` (`referans_tipi`, `referans_id`),
    KEY `idx_ekip_tarih` (`ekip_id`, `tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4. Saha işlem kaydı (kesme / açma)
--    client_uuid: çevrimdışı kuyruktan gelen kaydın mükerrer açılmaması için.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `kesme_acma_islem` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `client_uuid` VARCHAR(36) DEFAULT NULL,
    `islem_tipi` ENUM('kesme','acma') NOT NULL,
    `ekip_id` INT(11) NOT NULL,
    `ekip_adi` VARCHAR(100) DEFAULT NULL,
    `personel_id` INT(11) DEFAULT NULL,
    `abone_no` VARCHAR(50) DEFAULT NULL,
    `sayac_no` VARCHAR(50) DEFAULT NULL,
    `abone_adi` VARCHAR(150) DEFAULT NULL,
    `ilce` VARCHAR(50) DEFAULT NULL,
    `mahalle` VARCHAR(100) DEFAULT NULL,
    `adres` VARCHAR(255) DEFAULT NULL,
    `enlem` DECIMAL(10,7) DEFAULT NULL,
    `boylam` DECIMAL(10,7) DEFAULT NULL,
    `aparat_tip_id` INT(11) DEFAULT NULL,
    `adet` INT(11) NOT NULL DEFAULT 1,
    `aparatsiz` TINYINT(1) NOT NULL DEFAULT 0,
    `aparat_durumu` ENUM('alindi','hasarli','bulunamadi') DEFAULT NULL,
    `kaynak` ENUM('pwa','masaustu') NOT NULL DEFAULT 'pwa',
    `cihaz_zamani` DATETIME DEFAULT NULL,
    `offline_olusturma` DATETIME DEFAULT NULL,
    `negatif_stok` TINYINT(1) NOT NULL DEFAULT 0,
    `mukerrer_uyari` TINYINT(1) NOT NULL DEFAULT 0,
    `durum` ENUM('aktif','iptal') NOT NULL DEFAULT 'aktif',
    `iptal_aciklama` TEXT DEFAULT NULL,
    `iptal_tarihi` DATETIME DEFAULT NULL,
    `iptal_eden` INT(11) DEFAULT NULL,
    `aciklama` VARCHAR(255) DEFAULT NULL,
    `tarih` DATE NOT NULL,
    `olusturma_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
    `kaydeden_id` INT(11) DEFAULT NULL,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_client_uuid` (`firma_id`, `client_uuid`),
    KEY `idx_firma_tarih` (`firma_id`, `tarih`, `durum`),
    KEY `idx_ekip_tarih` (`ekip_id`, `tarih`),
    KEY `idx_abone` (`firma_id`, `abone_no`),
    KEY `idx_personel` (`personel_id`),
    KEY `idx_tip` (`aparat_tip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 5. İşlem fotoğrafları (sayaç + aparat)
--    UNIQUE(islem_id, tur): parçalı/tekrar yüklemede aynı foto ikinci kez kaydedilmez.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `kesme_acma_islem_foto` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `islem_id` INT(11) NOT NULL,
    `tur` ENUM('sayac','aparat','iptal') NOT NULL DEFAULT 'sayac',
    `dosya_yolu` VARCHAR(255) NOT NULL,
    `orijinal_ad` VARCHAR(255) DEFAULT NULL,
    `yukleyen_personel_id` INT(11) DEFAULT NULL,
    `yukleyen_user_id` INT(11) DEFAULT NULL,
    `arsivlendi` TINYINT(1) NOT NULL DEFAULT 0,
    `arsiv_tarihi` DATETIME DEFAULT NULL,
    `olusturma_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_islem_tur` (`islem_id`, `tur`),
    KEY `idx_firma_tur` (`firma_id`, `tur`),
    KEY `idx_arsiv` (`arsivlendi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 6. Ekipler arası transfer (çift onaylı)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `aparat_transfer` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `client_uuid` VARCHAR(36) DEFAULT NULL,
    `veren_ekip_id` INT(11) NOT NULL,
    `alan_ekip_id` INT(11) NOT NULL,
    `aparat_tip_id` INT(11) NOT NULL,
    `adet` INT(11) NOT NULL,
    `onaylanan_adet` INT(11) DEFAULT NULL,
    `durum` ENUM('beklemede','onaylandi','reddedildi','iptal') NOT NULL DEFAULT 'beklemede',
    `olusturan_personel_id` INT(11) DEFAULT NULL,
    `olusturan_user_id` INT(11) DEFAULT NULL,
    `onaylayan_personel_id` INT(11) DEFAULT NULL,
    `onaylayan_user_id` INT(11) DEFAULT NULL,
    `red_nedeni` VARCHAR(255) DEFAULT NULL,
    `aciklama` VARCHAR(255) DEFAULT NULL,
    `tarih` DATETIME NOT NULL DEFAULT current_timestamp(),
    `onay_tarihi` DATETIME DEFAULT NULL,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_client_uuid` (`firma_id`, `client_uuid`),
    KEY `idx_firma_durum` (`firma_id`, `durum`),
    KEY `idx_veren` (`veren_ekip_id`),
    KEY `idx_alan` (`alan_ekip_id`, `durum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 7. Sayım (mutabakat) başlığı ve satırları
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `aparat_sayim` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `baslik` VARCHAR(100) NOT NULL,
    `durum` ENUM('acik','tamamlandi','iptal') NOT NULL DEFAULT 'acik',
    `baslatan_id` INT(11) DEFAULT NULL,
    `baslangic_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
    `bitis_tarihi` DATETIME DEFAULT NULL,
    `aciklama` VARCHAR(255) DEFAULT NULL,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_firma_durum` (`firma_id`, `durum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aparat_sayim_detay` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `sayim_id` INT(11) NOT NULL,
    `ekip_id` INT(11) NOT NULL,
    `aparat_tip_id` INT(11) NOT NULL,
    `sistem_adet` INT(11) NOT NULL DEFAULT 0,
    `sayilan_adet` INT(11) DEFAULT NULL,
    `fark` INT(11) DEFAULT NULL,
    `aciklama` VARCHAR(255) DEFAULT NULL,
    `islendi` TINYINT(1) NOT NULL DEFAULT 0,
    `giren_personel_id` INT(11) DEFAULT NULL,
    `giris_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sayim_ekip_tip` (`sayim_id`, `ekip_id`, `aparat_tip_id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_ekip` (`ekip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 8. Menü kaydı (parent_id = 5 -> İş Takip)
--    MenuModel erişimi "permissions.id = menus.id" üzerinden de eşleştirdiği için
--    menü id'si her iki tabloda da boş olan bir id olmalıdır.
-- -----------------------------------------------------
SET @aparat_menu_id = (
    SELECT GREATEST(
        (SELECT COALESCE(MAX(`id`), 0) FROM `menus`),
        (SELECT COALESCE(MAX(`id`), 0) FROM `permissions`)
    ) + 1
);

SET @aparat_menu_id = COALESCE(
    (SELECT `id` FROM `menus` WHERE `menu_link` = 'aparat-takip/list' LIMIT 1),
    @aparat_menu_id
);

INSERT INTO `menus` (`id`, `menu_name`, `page_description`, `parent_id`, `group_name`, `group_order`, `menu_link`, `menu_icon`, `menu_order`, `is_active`, `is_menu`, `is_authorized`)
SELECT @aparat_menu_id, 'Aparat Takip', 'Kesme-açma aparatlarının ekip bazlı stok ve hareket takibi', 22, 'İş Takip Yönetim', 1, 'aparat-takip/list', 'package', 4, 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `menu_link` = 'aparat-takip/list');

-- -----------------------------------------------------
-- 9. Yetkiler
-- -----------------------------------------------------
INSERT INTO `permissions` (`id`, `name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT @aparat_menu_id, 'Aparat Takip', 'aparat_takip', 'Aparat takip ekranını görüntüleme', 'İş Takip', 0, 1, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`id` = @aparat_menu_id OR p.`auth_name` = 'aparat_takip');

INSERT INTO `permissions` (`name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT * FROM (
    SELECT 'Aparat Depo İşlemleri' AS n, 'aparat_depo' AS a, 'Depo girişi, ekibe çıkış, iade, hurda ve kayıp kaydı' AS d, 'İş Takip' AS g, 0 AS l, 1 AS ia, 0 AS sa, 0 AS ir
    UNION ALL SELECT 'Aparat Kayıt İptali', 'aparat_iptal', 'Hatalı saha işlem kaydını iptal etme (ters hareket)', 'İş Takip', 0, 1, 0, 0
    UNION ALL SELECT 'Aparat Sayımı', 'aparat_sayim', 'Sayım başlatma, ekip girişlerini işleme ve kapatma', 'İş Takip', 0, 1, 0, 0
    UNION ALL SELECT 'Aparat Tanımları', 'aparat_tanim', 'Aparat tiplerini ekleme, düzenleme ve pasife alma', 'İş Takip', 0, 1, 0, 0
    UNION ALL SELECT 'Aparat Transfer Müdahalesi', 'aparat_transfer_yonet', 'Bekleyen transferleri iptal etme ve tüm ekipler adına görüntüleme', 'İş Takip', 0, 1, 0, 0
) t
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`auth_name` = t.a);

-- Kaçak İşlemleri yetkisi olan rollere aparat takip yetkilerini de ver
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT urp.`role_id`, p.`id`
FROM `user_role_permissions` urp
INNER JOIN `permissions` kaynak ON kaynak.`id` = urp.`permission_id` AND kaynak.`auth_name` = 'kacak_islemleri'
CROSS JOIN `permissions` p
WHERE p.`auth_name` IN ('aparat_takip', 'aparat_depo', 'aparat_iptal', 'aparat_sayim', 'aparat_tanim', 'aparat_transfer_yonet')
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp2
      WHERE urp2.`role_id` = urp.`role_id` AND urp2.`permission_id` = p.`id`
  );
