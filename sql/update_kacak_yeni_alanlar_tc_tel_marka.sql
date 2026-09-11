-- =====================================================
-- KAÇAK KONTROL TABLOSU YENİ ALANLAR
-- Telefon No ve Sayaç Markası alanları ekleme
-- (abone_tc ve abone_dogum_tarihi zaten mevcuttur)
-- =====================================================

ALTER TABLE `kacak_kontrol`
    ADD COLUMN IF NOT EXISTS `abone_tel` VARCHAR(20) DEFAULT NULL AFTER `abone_dogum_tarihi`,
    ADD COLUMN IF NOT EXISTS `sayac_markasi` VARCHAR(100) DEFAULT NULL AFTER `sayac_no`;
