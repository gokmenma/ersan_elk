-- Endeks Okuma Detay (Excel yukleme) modulu
-- KASKI okuma API'si saat dondurmedigi icin saat bazli mesai denetimi
-- personelin verdigi Excel okuma listesinden beslenir.

CREATE TABLE IF NOT EXISTS `endeks_okuma_detay_dosya` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `firma_id` int(11) NOT NULL,
    `orijinal_adi` varchar(255) NOT NULL,
    `dosya_hash` varchar(64) DEFAULT NULL,
    `dosya_boyutu` int(11) DEFAULT NULL,
    `satir_sayisi` int(11) NOT NULL DEFAULT 0,
    `atlanan_tarih` int(11) NOT NULL DEFAULT 0,
    `atlanan_tekrar` int(11) NOT NULL DEFAULT 0,
    `ilk_tarih` date DEFAULT NULL,
    `son_tarih` date DEFAULT NULL,
    `durum` enum('basarili','hatali') NOT NULL DEFAULT 'basarili',
    `hata_mesaji` text DEFAULT NULL,
    `yukleyen` int(11) NOT NULL DEFAULT 0,
    `kayit_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
    `silinme_tarihi` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_okuma_dosya_firma` (`firma_id`, `silinme_tarihi`),
    KEY `idx_okuma_dosya_hash` (`firma_id`, `dosya_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `endeks_okuma_detay` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `firma_id` int(11) NOT NULL,
    `dosya_id` int(11) NOT NULL,
    `satir_hash` char(32) NOT NULL,
    `ekip_kodu` varchar(50) DEFAULT NULL,
    `ekip_adi` varchar(255) DEFAULT NULL,
    `ekip_kodu_id` int(11) DEFAULT NULL,
    `personel_id` int(11) DEFAULT NULL,
    `bolge` varchar(255) DEFAULT NULL,
    `defter` varchar(50) DEFAULT NULL,
    `sayfa` varchar(50) DEFAULT NULL,
    `sira_no` varchar(50) DEFAULT NULL,
    `mahalle` varchar(255) DEFAULT NULL,
    `abone_no` varchar(50) DEFAULT NULL,
    `abone_adsoyad` varchar(255) DEFAULT NULL,
    `sayac_durum` varchar(255) DEFAULT NULL,
    `okuma_zamani` datetime NOT NULL,
    `tarih` date NOT NULL,
    `kayit_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_okuma_detay_satir` (`firma_id`, `satir_hash`),
    KEY `idx_okuma_detay_tarih` (`firma_id`, `tarih`),
    KEY `idx_okuma_detay_ekip` (`firma_id`, `ekip_kodu`, `tarih`),
    KEY `idx_okuma_detay_zaman` (`firma_id`, `ekip_kodu`, `okuma_zamani`),
    KEY `idx_okuma_detay_dosya` (`dosya_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Excel yukleme, ayri bir sayfa degil; puantaj/okuma-denetim sayfasinin
-- "Excel yukle" veri kaynagi olarak calisir. Menu/yetki kaydi
-- database/okuma_denetim_menu.sql icinde tanimlidir, burada tekrar eklenmez.

-- Onceki surumde acilmis ayri menu kaydi varsa temizlenir.
DELETE urp FROM `user_role_permissions` urp
JOIN `permissions` p ON p.`id` = urp.`permission_id`
WHERE p.`auth_name` = 'puantaj/okuma-mesai';

DELETE FROM `menus` WHERE `menu_link` = 'puantaj/okuma-mesai';
DELETE FROM `permissions` WHERE `auth_name` = 'puantaj/okuma-mesai';

-- Dogrulama:
--   SHOW TABLES LIKE 'endeks_okuma_detay%';
--   SELECT * FROM menus WHERE menu_link = 'puantaj/okuma-mesai';   -- bos donmeli
