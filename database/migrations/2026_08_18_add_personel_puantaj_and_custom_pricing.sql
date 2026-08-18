-- 1. Personel tablosuna puantaj dahil kolonu
ALTER TABLE `personel` ADD COLUMN `puantaj_hakedis_dahil` TINYINT(1) NOT NULL DEFAULT 1 AFTER `es_yardimi_dahil`;

-- 2. Personele özel iş türü birim fiyatları tablosu
CREATE TABLE IF NOT EXISTS `personel_is_turu_ucretleri` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firma_id` INT NOT NULL DEFAULT 1,
  `personel_id` INT NOT NULL,
  `is_turu_id` INT NOT NULL,
  `ucret` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `aracli_ucret` DECIMAL(10,2) NULL DEFAULT 0.00,
  `gecerlilik_baslangic` DATE NULL,
  `gecerlilik_bitis` DATE NULL,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `silinme_tarihi` DATETIME NULL,
  INDEX `idx_pers_isturu` (`personel_id`, `is_turu_id`, `silinme_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
