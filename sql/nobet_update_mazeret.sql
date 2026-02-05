-- =====================================================
-- NÖBET TABLOSU GÜNCELLEMELERİ
-- Mazeret bildirimi için gerekli alanlar
-- =====================================================

-- 1. nobetler tablosuna mazeret alanları ekle
ALTER TABLE `nobetler` 
ADD COLUMN IF NOT EXISTS `mazeret_aciklama` TEXT DEFAULT NULL AFTER `aciklama`,
ADD COLUMN IF NOT EXISTS `mazeret_tarihi` DATETIME DEFAULT NULL AFTER `mazeret_aciklama`;

-- 2. durum enum'una mazeret_bildirildi değerini ekle
ALTER TABLE `nobetler` 
MODIFY COLUMN `durum` ENUM('planli', 'devir_alindi', 'tamamlandi', 'iptal', 'mazeret_bildirildi') DEFAULT 'planli';

-- 3. nobet_bildirim_loglari tablosunu güncelle (baslik ve mesaj alanları ekle)
ALTER TABLE `nobet_bildirim_loglari`
ADD COLUMN IF NOT EXISTS `bildirim_turu` VARCHAR(50) DEFAULT NULL AFTER `bildirim_tipi`,
ADD COLUMN IF NOT EXISTS `baslik` VARCHAR(255) DEFAULT NULL AFTER `bildirim_turu`,
ADD COLUMN IF NOT EXISTS `mesaj` TEXT DEFAULT NULL AFTER `baslik`,
ADD COLUMN IF NOT EXISTS `gonderim_durumu` ENUM('bekliyor', 'gonderildi', 'basarisiz') DEFAULT 'gonderildi' AFTER `mesaj`,
ADD COLUMN IF NOT EXISTS `gonderim_tarihi` DATETIME DEFAULT NULL AFTER `gonderim_durumu`;
