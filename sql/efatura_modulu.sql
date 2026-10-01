-- ============================================================================
-- E-Fatura, E-Arşiv ve EDM Bilişim Entegrasyonu Veritabanı Şeması
-- ============================================================================

-- 1. Firma Bazlı E-Fatura / EDM Ayarları
CREATE TABLE IF NOT EXISTS `efatura_ayarlar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `entegrator` VARCHAR(50) NOT NULL DEFAULT 'EDM',
  `api_username` VARCHAR(100) NOT NULL,
  `api_password` TEXT NOT NULL,
  `environment` ENUM('TEST', 'LIVE') NOT NULL DEFAULT 'TEST',
  `test_wsdl_url` VARCHAR(255) DEFAULT 'https://efaturatest.edmbilisim.com.tr/EFaturaEDM21/EFaturaEDM.svc?wsdl',
  `live_wsdl_url` VARCHAR(255) DEFAULT 'https://efatura.edmbilisim.com.tr/EFaturaEDM/EFaturaEDM.svc?wsdl',
  `efatura_seri` VARCHAR(3) NOT NULL DEFAULT 'ERS',
  `earsiv_seri` VARCHAR(3) NOT NULL DEFAULT 'ERA',
  `varsayilan_gonderici_alias` VARCHAR(150) DEFAULT 'urn:mail:defaultgb',
  `otomatik_gonder` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_efatura_ayarlar_firm_id` (`firm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Fatura Sayaç / Numaratör Yönetimi (Mükerrer Numara Önleme)
CREATE TABLE IF NOT EXISTS `efatura_numarator` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `belge_turu` ENUM('EFATURA', 'EARSIV', 'EIRSALIYE', 'ESMM') NOT NULL,
  `yil` INT NOT NULL,
  `seri` VARCHAR(3) NOT NULL,
  `son_numara` INT NOT NULL DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_firm_tur_yil_seri` (`firm_id`, `belge_turu`, `yil`, `seri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. E-Fatura & E-Arşiv Ana Tablosu
CREATE TABLE IF NOT EXISTS `faturalar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `cari_id` INT NULL,
  `yon` ENUM('GIDEN', 'GELEN') NOT NULL DEFAULT 'GIDEN',
  `belge_turu` ENUM('EFATURA', 'EARSIV') NOT NULL,
  `fatura_profili` ENUM('TICARIFATURA', 'TEMELFATURA', 'EARSIVFATURA', 'KAMU', 'IHRACAT') NOT NULL DEFAULT 'TICARIFATURA',
  `fatura_tipi` ENUM('SATIS', 'IADE', 'TEVKIFAT', 'ISTISNA', 'OZELMATRAH', 'IHRACKAYITLI') NOT NULL DEFAULT 'SATIS',
  `ettn` VARCHAR(36) NOT NULL UNIQUE,
  `fatura_no` VARCHAR(16) NULL,
  `fatura_tarihi` DATE NOT NULL,
  `duzenleme_saati` TIME NOT NULL,
  `vade_tarihi` DATE NULL,
  
  -- Alıcı / Müşteri Bilgileri
  `alici_vkn_tckn` VARCHAR(11) NOT NULL,
  `alici_unvan` VARCHAR(255) NOT NULL,
  `alici_vergi_dairesi` VARCHAR(100) NULL,
  `alici_adres` TEXT NULL,
  `alici_il` VARCHAR(50) NULL,
  `alici_ilce` VARCHAR(50) NULL,
  `alici_ulke` VARCHAR(50) DEFAULT 'Türkiye',
  `alici_eposta` VARCHAR(100) NULL,
  `alici_telefon` VARCHAR(30) NULL,
  `alici_posta_kutusu` VARCHAR(150) NULL,
  
  -- Para ve Tutar Bilgileri
  `para_birimi` VARCHAR(3) DEFAULT 'TRY',
  `doviz_kuru` DECIMAL(15, 4) DEFAULT 1.0000,
  `satir_toplami` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `iskonto_toplami` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `kdv_matrahi` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `hesaplanan_kdv` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `tevkifat_tutari` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `odenecek_tutar` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `yuvarlama_tutari` DECIMAL(15, 2) DEFAULT 0.00,
  `yaziyla_tutar` VARCHAR(255) NULL,
  
  -- Notlar ve Açıklama
  `notlar` TEXT NULL,
  `siparis_no` VARCHAR(50) NULL,
  `siparis_tarihi` DATE NULL,
  `irsaliye_no` VARCHAR(50) NULL,
  `irsaliye_tarihi` DATE NULL,

  -- EDM ve GİB Durum Bilgileri
  `entegrator_durum_kodu` VARCHAR(50) DEFAULT 'TASLAK',
  `gib_durum_kodu` INT NULL,
  `gib_durum_aciklamasi` TEXT NULL,
  `edm_referans_no` VARCHAR(100) NULL,
  `ticari_yanit` ENUM('BEKLIYOR', 'KABUL', 'RED') DEFAULT 'BEKLIYOR',
  
  -- Dosya Yolları
  `ubl_xml_path` VARCHAR(255) NULL,
  `pdf_path` VARCHAR(255) NULL,
  
  `olusturan_user_id` INT NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_faturalar_firm_tarih` (`firm_id`, `fatura_tarihi`),
  INDEX `idx_faturalar_fatura_no` (`fatura_no`),
  INDEX `idx_faturalar_ettn` (`ettn`),
  INDEX `idx_faturalar_durum` (`entegrator_durum_kodu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Fatura Kalemleri (Satırlar)
CREATE TABLE IF NOT EXISTS `fatura_satirlari` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fatura_id` INT NOT NULL,
  `sira_no` INT NOT NULL DEFAULT 1,
  `urun_hizmet_adi` VARCHAR(255) NOT NULL,
  `urun_kodu` VARCHAR(50) NULL,
  `miktar` DECIMAL(15, 4) NOT NULL DEFAULT 1.0000,
  `birim` VARCHAR(20) NOT NULL DEFAULT 'C62',
  `birim_fiyat` DECIMAL(15, 4) NOT NULL DEFAULT 0.0000,
  `iskonto_orani` DECIMAL(5, 2) DEFAULT 0.00,
  `iskonto_tutari` DECIMAL(15, 2) DEFAULT 0.00,
  `kdv_orani` DECIMAL(5, 2) NOT NULL DEFAULT 20.00,
  `kdv_tutari` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `tevkifat_kodu` VARCHAR(10) NULL,
  `tevkifat_orani` DECIMAL(5, 2) NULL,
  `tevkifat_tutari` DECIMAL(15, 2) DEFAULT 0.00,
  `istisna_kodu` VARCHAR(10) NULL,
  `satir_toplami` DECIMAL(15, 2) NOT NULL,
  INDEX `idx_fatura_satirlari_fatura_id` (`fatura_id`),
  FOREIGN KEY (`fatura_id`) REFERENCES `faturalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. E-Fatura Entegrasyon ve SOAP İşlem Logları
CREATE TABLE IF NOT EXISTS `efatura_loglari` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `fatura_id` INT NULL,
  `islem_turu` VARCHAR(50) NOT NULL,
  `istek_payload` MEDIUMTEXT NULL,
  `yanit_payload` MEDIUMTEXT NULL,
  `durum` ENUM('BASARILI', 'BASARISIZ') NOT NULL,
  `hata_kodu` VARCHAR(50) NULL,
  `hata_mesaji` TEXT NULL,
  `ip_adresi` VARCHAR(45) NULL,
  `user_id` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_efatura_loglari_firm` (`firm_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
