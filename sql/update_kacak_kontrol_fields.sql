-- =====================================================
-- KAÇAK KONTROL TABLOSU GÜNCELLEMELERİ
-- İlçe ve Tür (Kaçak, Abonesiz) alanları ekleme
-- =====================================================

ALTER TABLE `kacak_kontrol`
ADD COLUMN IF NOT EXISTS `ilce` VARCHAR(100) DEFAULT NULL AFTER `ekip_adi`,
ADD COLUMN IF NOT EXISTS `tur` ENUM('Kaçak', 'Abonesiz') DEFAULT 'Kaçak' AFTER `ilce`;
