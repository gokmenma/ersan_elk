-- =====================================================
-- KAÇAK KONTROL TABLOSU YENİ ALANLAR GÜNCELLEMESİ
-- Tutanak No, Abone Adı, Sayaç No ve Endeks alanları ekleme
-- =====================================================

ALTER TABLE `kacak_kontrol`
ADD COLUMN IF NOT EXISTS `tutanak_no` VARCHAR(100) DEFAULT NULL AFTER `tur`,
ADD COLUMN IF NOT EXISTS `abone_adi` VARCHAR(255) DEFAULT NULL AFTER `tutanak_no`,
ADD COLUMN IF NOT EXISTS `sayac_no` VARCHAR(100) DEFAULT NULL AFTER `abone_adi`,
ADD COLUMN IF NOT EXISTS `endeks` VARCHAR(50) DEFAULT NULL AFTER `sayac_no`;
