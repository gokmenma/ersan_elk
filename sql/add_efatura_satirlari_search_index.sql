-- ============================================================================
-- E-Fatura Kalem/İçerik Arama Performans İndeksleri
-- ============================================================================

-- fatura_satirlari tablosunda ürün adı ve koduna göre arama hızlandırma
SET @exist_idx1 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fatura_satirlari' AND INDEX_NAME = 'idx_fatura_satirlari_urun_adi');
SET @sql1 := IF(@exist_idx1 = 0, 'ALTER TABLE `fatura_satirlari` ADD INDEX `idx_fatura_satirlari_urun_adi` (`urun_hizmet_adi`(191))', 'SELECT "Index idx_fatura_satirlari_urun_adi already exists"');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @exist_idx2 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fatura_satirlari' AND INDEX_NAME = 'idx_fatura_satirlari_urun_kodu');
SET @sql2 := IF(@exist_idx2 = 0, 'ALTER TABLE `fatura_satirlari` ADD INDEX `idx_fatura_satirlari_urun_kodu` (`urun_kodu`)', 'SELECT "Index idx_fatura_satirlari_urun_kodu already exists"');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
