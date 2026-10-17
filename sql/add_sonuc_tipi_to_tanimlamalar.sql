-- tanimlamalar tablosuna is_turu sonuc tipi (olumlu/olumsuz) alani ekleme
-- Tarih: 2026-10-17

SET @columnExists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'tanimlamalar' 
      AND COLUMN_NAME = 'sonuc_tipi'
);

SET @sql = IF(@columnExists = 0, 
    'ALTER TABLE `tanimlamalar` ADD COLUMN `sonuc_tipi` VARCHAR(20) NOT NULL DEFAULT \'olumlu\' AFTER `rapor_sekmesi`', 
    'SELECT "Kolon zaten mevcut" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Olumsuz olarak değerlendirilen temel kesme/açma sonuçlarını başlangıçta 'olumsuz' olarak güncelle
UPDATE `tanimlamalar`
SET `sonuc_tipi` = 'olumsuz'
WHERE `grup` = 'is_turu'
  AND (
      TRIM(`is_emri_sonucu`) IN (
          'Kapalı',
          'ADREŞ YANLIŞ ABONE BULUNAMADI',
          'ADRES YANLIŞ ABONE BULUNAMADI',
          'SAYAÇ KASKİYE ALINDI',
          'ABONE MÜSADE ETMEDİ.',
          'ABONE MÜSADE ETMEDİ',
          'TAPA SAĞLAM',
          'MÜHÜR VE CONTA',
          'MÜHÜR  VE CONTA',
          'DEPREMDE YIKILMIŞ',
          'Yoldan Kapama',
          'Dilekçeli Özel Kapama',
          'Tahliyeden',
          'ADRES YETERSİZ'
      )
  );
