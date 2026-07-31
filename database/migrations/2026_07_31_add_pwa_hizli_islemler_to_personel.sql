-- Migration: Personel PWA Hızlı İşlemler Özelleştirme Sütunu Eklenmesi
-- Tarih: 2026-07-31

ALTER TABLE `personel` 
ADD COLUMN `pwa_hizli_islemler` TEXT NULL DEFAULT NULL COMMENT 'Personel mobil PWA hızlı işlem butonları sıralaması (JSON)';
