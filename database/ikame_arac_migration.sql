-- İkame Araç Sistemi Migration
-- Tarih: 2026-03-08

-- 1. araclar tablosuna ikame_mi sütunu ekle
ALTER TABLE `araclar` 
ADD COLUMN `ikame_mi` TINYINT(1) NOT NULL DEFAULT 0 AFTER `aktif_mi`;

-- 2. arac_servis_kayitlari tablosuna ikame araç sütunları ekle
ALTER TABLE `arac_servis_kayitlari`
ADD COLUMN `ikame_arac_id` INT(11) DEFAULT NULL AFTER `fatura_no`,
ADD COLUMN `ikame_plaka` VARCHAR(20) DEFAULT NULL AFTER `ikame_arac_id`,
ADD COLUMN `ikame_marka` VARCHAR(100) DEFAULT NULL AFTER `ikame_plaka`,
ADD COLUMN `ikame_model` VARCHAR(100) DEFAULT NULL AFTER `ikame_marka`,
ADD COLUMN `ikame_teslim_km` INT(11) DEFAULT NULL AFTER `ikame_model`,
ADD COLUMN `ikame_iade_km` INT(11) DEFAULT NULL AFTER `ikame_teslim_km`,
ADD COLUMN `ikame_iade_tarihi` DATETIME DEFAULT NULL AFTER `ikame_iade_km`;

-- 3. Index ekle
ALTER TABLE `arac_servis_kayitlari`
ADD KEY `idx_ikame_arac` (`ikame_arac_id`);
