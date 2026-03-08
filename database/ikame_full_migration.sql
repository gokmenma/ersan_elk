-- 1. Araçlar tablosuna ikame_mi ve kategori güncellemelerini ekle
ALTER TABLE `araclar` 
ADD COLUMN `ikame_mi` TINYINT(1) DEFAULT 0 AFTER `aktif_mi`,
MODIFY COLUMN `arac_tipi` ENUM('binek', 'kamyonet', 'kamyon', 'minibus', 'otobus', 'motosiklet', 'diger', 'ikame') DEFAULT 'binek';

-- 2. Servis kayıtları tablosuna ikame araç takip sütunlarını ekle
ALTER TABLE `arac_servis_kayitlari` 
ADD COLUMN `ikame_arac_id` INT(11) DEFAULT NULL,
ADD COLUMN `ikame_plaka` VARCHAR(20) DEFAULT NULL,
ADD COLUMN `ikame_marka` VARCHAR(100) DEFAULT NULL,
ADD COLUMN `ikame_model` VARCHAR(100) DEFAULT NULL,
ADD COLUMN `ikame_teslim_km` INT(11) DEFAULT NULL,
ADD COLUMN `ikame_iade_km` INT(11) DEFAULT NULL,
ADD COLUMN `ikame_iade_tarihi` DATE DEFAULT NULL;

-- 3. İkame araç ID için indeks ekle
ALTER TABLE `arac_servis_kayitlari` 
ADD INDEX `idx_ikame_arac` (`ikame_arac_id`);

-- 4. Mevcut kayıtları senkronize et (Eğer daha önce manuel veri girildiyse)
UPDATE `araclar` 
SET `mulkiyet` = 'İkame Araç', 
    `arac_tipi` = 'ikame' 
WHERE `ikame_mi` = 1;
