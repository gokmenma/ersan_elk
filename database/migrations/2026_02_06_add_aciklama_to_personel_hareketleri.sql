-- Migration: personel_hareketleri tablosuna aciklama kolonu ekleme
-- Tarih: 2026-02-06
-- Açıklama: Otomatik görev sonlandırma için aciklama alanı ekleniyor

-- Kolon var mı kontrol et ve yoksa ekle
SET @dbname = DATABASE();
SET @tablename = 'personel_hareketleri';
SET @columnname = 'aciklama';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT "Kolon zaten mevcut"',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(255) NULL COMMENT "İşlem açıklaması (otomatik sonlandırma vb)" AFTER ip_adresi')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
