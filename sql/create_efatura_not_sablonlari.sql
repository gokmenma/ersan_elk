-- E-Fatura / E-Arşiv Alt Bilgi & Not Hazır Şablonları Tablosu
CREATE TABLE IF NOT EXISTS `efatura_not_sablonlari` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firm_id` INT NOT NULL,
  `baslik` VARCHAR(150) NOT NULL COMMENT 'Şablon Başlığı (Örn: Banka Hesap Bilgileri, Teslimat Şartları)',
  `icerik` TEXT NOT NULL COMMENT 'Şablon Metni / Alt Bilgi',
  `varsayilan_mi` TINYINT(1) DEFAULT 0 COMMENT '1: Yeni faturada otomatik yükle, 0: Manuel seçim',
  `sira` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `olusturan_user_id` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  INDEX `idx_efatura_sablon_firm` (`firm_id`, `is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
