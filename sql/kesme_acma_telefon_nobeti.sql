-- =====================================================================
-- Kesme / Açma Modülü — telefon nöbeti personel ayarı
-- personel.telefon_nobeti_tutar: ofis telefon nöbetine girecek personel
-- Nöbet ekranındaki telefon havuzu bu sütuna göre süzülür.
-- =====================================================================

SET @sutun_var = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel' AND COLUMN_NAME = 'telefon_nobeti_tutar'
);
SET @sql = IF(@sutun_var = 0,
    'ALTER TABLE `personel` ADD COLUMN `telefon_nobeti_tutar` TINYINT(1) NOT NULL DEFAULT 0',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Doğrulama:
--   SELECT id, adi_soyadi, telefon_nobeti_tutar FROM personel WHERE telefon_nobeti_tutar = 1;
