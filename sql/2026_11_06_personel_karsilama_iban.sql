SET @db = DATABASE();

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE personel_karsilama_mesajlari ADD COLUMN iban_girildi_tarihi DATETIME NULL AFTER ilk_acilma_tarihi',
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'personel_karsilama_mesajlari' AND COLUMN_NAME = 'iban_girildi_tarihi');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE personel_karsilama_mesajlari ADD COLUMN iban_degisim_sayisi TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER iban_girildi_tarihi',
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'personel_karsilama_mesajlari' AND COLUMN_NAME = 'iban_degisim_sayisi');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
