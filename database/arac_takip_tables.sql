-- Araç Takip Modülü Veritabanı Tabloları
-- Oluşturulma Tarihi: 2026-01-20

-- =============================================
-- 1. ARAÇLAR TABLOSU
-- =============================================
CREATE TABLE IF NOT EXISTS `araclar` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `plaka` VARCHAR(20) NOT NULL,
    `marka` VARCHAR(100) DEFAULT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `model_yili` YEAR DEFAULT NULL,
    `arac_tipi` ENUM('binek', 'kamyonet', 'kamyon', 'minibus', 'otobus', 'motosiklet', 'diger') DEFAULT 'binek',
    `yakit_tipi` ENUM('benzin', 'dizel', 'lpg', 'elektrik', 'hibrit') DEFAULT 'dizel',
    `renk` VARCHAR(50) DEFAULT NULL,
    `sase_no` VARCHAR(50) DEFAULT NULL,
    `motor_no` VARCHAR(50) DEFAULT NULL,
    `ruhsat_sahibi` VARCHAR(150) DEFAULT NULL,
    `muayene_tarihi` DATE DEFAULT NULL,
    `sigorta_bitis_tarihi` DATE DEFAULT NULL,
    `kasko_bitis_tarihi` DATE DEFAULT NULL,
    `baslangic_km` INT(11) DEFAULT 0,
    `guncel_km` INT(11) DEFAULT 0,
    `aktif_mi` TINYINT(1) DEFAULT 1,
    `notlar` TEXT DEFAULT NULL,
    `resim_yolu` VARCHAR(255) DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `plaka_firma` (`plaka`, `firma_id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_aktif` (`aktif_mi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 2. ARAÇ ZİMMET TABLOSU (Personele Atama)
-- =============================================
CREATE TABLE IF NOT EXISTS `arac_zimmetleri` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `arac_id` INT(11) NOT NULL,
    `personel_id` INT(11) NOT NULL,
    `zimmet_tarihi` DATE NOT NULL,
    `iade_tarihi` DATE DEFAULT NULL,
    `teslim_km` INT(11) DEFAULT NULL,
    `iade_km` INT(11) DEFAULT NULL,
    `durum` ENUM('aktif', 'iade_edildi', 'iptal') DEFAULT 'aktif',
    `notlar` TEXT DEFAULT NULL,
    `olusturan_kullanici_id` INT(11) DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_arac` (`arac_id`),
    KEY `idx_personel` (`personel_id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_durum` (`durum`),
    CONSTRAINT `fk_arac_zimmet_arac` FOREIGN KEY (`arac_id`) REFERENCES `araclar` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_arac_zimmet_personel` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 3. ARAÇ YAKIT KAYITLARI TABLOSU
-- =============================================
CREATE TABLE IF NOT EXISTS `arac_yakit_kayitlari` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `arac_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `km` INT(11) NOT NULL COMMENT 'Yakıt alım anındaki kilometre',
    `onceki_km` INT(11) DEFAULT NULL COMMENT 'Önceki kayıttaki kilometre',
    `yakit_miktari` DECIMAL(10,2) NOT NULL COMMENT 'Litre',
    `birim_fiyat` DECIMAL(10,2) DEFAULT NULL COMMENT 'TL/Litre',
    `toplam_tutar` DECIMAL(10,2) NOT NULL COMMENT 'TL',
    `yakit_tipi` ENUM('benzin', 'dizel', 'lpg', 'elektrik') DEFAULT 'dizel',
    `tam_depo_mu` TINYINT(1) DEFAULT 0,
    `istasyon` VARCHAR(150) DEFAULT NULL,
    `fatura_no` VARCHAR(50) DEFAULT NULL,
    `notlar` TEXT DEFAULT NULL,
    `olusturan_kullanici_id` INT(11) DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_arac` (`arac_id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_tarih` (`tarih`),
    KEY `idx_arac_tarih` (`arac_id`, `tarih`),
    CONSTRAINT `fk_yakit_arac` FOREIGN KEY (`arac_id`) REFERENCES `araclar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 4. ARAÇ KM KAYITLARI TABLOSU (Günlük KM Takibi)
-- =============================================
CREATE TABLE IF NOT EXISTS `arac_km_kayitlari` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `arac_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `baslangic_km` INT(11) NOT NULL,
    `bitis_km` INT(11) NOT NULL,
    `yapilan_km` INT(11) GENERATED ALWAYS AS (`bitis_km` - `baslangic_km`) STORED,
    `notlar` TEXT DEFAULT NULL,
    `olusturan_kullanici_id` INT(11) DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_arac` (`arac_id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_tarih` (`tarih`),
    UNIQUE KEY `unique_arac_tarih` (`arac_id`, `tarih`),
    CONSTRAINT `fk_km_arac` FOREIGN KEY (`arac_id`) REFERENCES `araclar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 5. ARAÇ BAKIM/ONARIM KAYITLARI TABLOSU
-- =============================================
CREATE TABLE IF NOT EXISTS `arac_bakim_kayitlari` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `arac_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `km` INT(11) DEFAULT NULL,
    `bakim_tipi` ENUM('periyodik_bakim', 'yag_degisimi', 'lastik', 'fren', 'motor', 'elektrik', 'kaporta', 'diger') DEFAULT 'periyodik_bakim',
    `aciklama` TEXT NOT NULL,
    `servis_adi` VARCHAR(150) DEFAULT NULL,
    `tutar` DECIMAL(10,2) DEFAULT 0,
    `fatura_no` VARCHAR(50) DEFAULT NULL,
    `sonraki_bakim_km` INT(11) DEFAULT NULL,
    `sonraki_bakim_tarihi` DATE DEFAULT NULL,
    `notlar` TEXT DEFAULT NULL,
    `olusturan_kullanici_id` INT(11) DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_arac` (`arac_id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_tarih` (`tarih`),
    CONSTRAINT `fk_bakim_arac` FOREIGN KEY (`arac_id`) REFERENCES `araclar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 6. ARAÇ SİGORTA KAYITLARI TABLOSU
-- =============================================
CREATE TABLE IF NOT EXISTS `arac_sigorta_kayitlari` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `arac_id` INT(11) NOT NULL,
    `sigorta_tipi` ENUM('trafik', 'kasko', 'diger') DEFAULT 'trafik',
    `sigorta_sirketi` VARCHAR(150) DEFAULT NULL,
    `police_no` VARCHAR(100) DEFAULT NULL,
    `baslangic_tarihi` DATE NOT NULL,
    `bitis_tarihi` DATE NOT NULL,
    `prim_tutari` DECIMAL(10,2) DEFAULT 0,
    `acente` VARCHAR(150) DEFAULT NULL,
    `notlar` TEXT DEFAULT NULL,
    `olusturan_kullanici_id` INT(11) DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_arac` (`arac_id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_bitis` (`bitis_tarihi`),
    CONSTRAINT `fk_sigorta_arac` FOREIGN KEY (`arac_id`) REFERENCES `araclar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
