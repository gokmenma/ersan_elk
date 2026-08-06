-- Migration: Users tablosuna mobil hızlı işlemler özelleştirme sütunu eklenmesi
-- Tarih: 2026-08-06

ALTER TABLE `users` 
ADD COLUMN `mobile_hizli_islemler` TEXT NULL DEFAULT NULL COMMENT 'Kullanıcı mobil hızlı işlem butonları sıralaması (JSON)';
