-- Personel tablosuna opsiyonel yıllık izin hakediş baz tarihi alanı ekler.
-- Boş bırakıldığında hakediş hesaplamalarında çalışma geçmişindeki en eski işe giriş tarihi baz alınır.
-- Betik tekrar çalıştırılabilir.

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'personel'
      AND COLUMN_NAME = 'izin_hakedis_baslangic_tarihi'
);

SET @sql_stmt := IF(
    @col_exists = 0,
    'ALTER TABLE personel ADD COLUMN izin_hakedis_baslangic_tarihi DATE NULL DEFAULT NULL COMMENT ''Yıllık izin kıdem/hakediş hesaplamasında baz alınacak özel başlangıç tarihi'' AFTER ise_giris_tarihi',
    'SELECT ''izin_hakedis_baslangic_tarihi kolonu zaten mevcut'' AS bilgi'
);

PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
