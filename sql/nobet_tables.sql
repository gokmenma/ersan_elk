-- =====================================================
-- NÖBET YÖNETİM SİSTEMİ VERİTABANI TABLOLARI
-- =====================================================

-- 1. Ana Nöbet Tablosu
CREATE TABLE IF NOT EXISTS `nobetler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `nobet_tarihi` date NOT NULL,
  `baslangic_saati` time DEFAULT '18:00:00',
  `bitis_saati` time DEFAULT '08:00:00',
  `nobet_tipi` enum('standart','hafta_sonu','resmi_tatil','ozel') DEFAULT 'standart',
  `durum` enum('planli','devir_alindi','tamamlandi','iptal') DEFAULT 'planli',
  `aciklama` text DEFAULT NULL,
  `olusturan_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT NULL,
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `bildirim_gonderildi` tinyint(1) DEFAULT 0,
  `bildirim_tarihi` datetime DEFAULT NULL,
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_firma_id` (`firma_id`),
  KEY `idx_personel_id` (`personel_id`),
  KEY `idx_nobet_tarihi` (`nobet_tarihi`),
  KEY `idx_durum` (`durum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Nöbet Değişim Talepleri Tablosu
CREATE TABLE IF NOT EXISTS `nobet_degisim_talepleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nobet_id` int(11) NOT NULL COMMENT 'Değiştirilmek istenen nöbet',
  `talep_eden_id` int(11) NOT NULL COMMENT 'Talebi oluşturan personel',
  `talep_edilen_id` int(11) NOT NULL COMMENT 'Nöbeti devralması istenen personel',
  `aciklama` text DEFAULT NULL,
  `durum` enum('beklemede','personel_onayladi','onaylandi','reddedildi','iptal') DEFAULT 'beklemede',
  `talep_tarihi` datetime NOT NULL,
  `personel_onay_tarihi` datetime DEFAULT NULL,
  `amir_onaylayan_id` int(11) DEFAULT NULL,
  `amir_onay_tarihi` datetime DEFAULT NULL,
  `red_nedeni` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nobet_id` (`nobet_id`),
  KEY `idx_talep_eden_id` (`talep_eden_id`),
  KEY `idx_talep_edilen_id` (`talep_edilen_id`),
  KEY `idx_durum` (`durum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Nöbet Devir Kayıtları Tablosu (Zaman Damgası için)
CREATE TABLE IF NOT EXISTS `nobet_devir_kayitlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nobet_id` int(11) NOT NULL,
  `devralan_personel_id` int(11) NOT NULL,
  `devir_zamani` datetime NOT NULL,
  `konum_lat` decimal(10,8) DEFAULT NULL,
  `konum_lng` decimal(11,8) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nobet_id` (`nobet_id`),
  KEY `idx_devralan_personel_id` (`devralan_personel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Nöbet Hatırlatma Bildirimleri (Opsiyonel - bildirim logları için)
CREATE TABLE IF NOT EXISTS `nobet_bildirim_loglari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nobet_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `bildirim_tipi` enum('push','sms','email') NOT NULL,
  `bildirim_zamani` datetime NOT NULL,
  `durum` enum('gonderildi','basarisiz') DEFAULT 'gonderildi',
  `hata_mesaji` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nobet_id` (`nobet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
