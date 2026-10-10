-- =====================================================
-- KAÇAK KONTROL TABLOSU GÜNCELLEMESİ
-- Tür enum alanına 'Pasif Abone' seçeneği ekleme
-- =====================================================

ALTER TABLE `kacak_kontrol`
MODIFY COLUMN `tur` ENUM('Kaçak', 'Abonesiz', 'Usülsüz', 'Pasif Abone') DEFAULT 'Kaçak';
