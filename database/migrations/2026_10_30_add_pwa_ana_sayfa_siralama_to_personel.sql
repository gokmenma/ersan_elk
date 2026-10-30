-- Migration: Personel PWA Ana Sayfa Kart Sıralaması Sütunu
-- Tarih: 2026-10-30

ALTER TABLE `personel`
ADD COLUMN `pwa_ana_sayfa_siralama` TEXT NULL DEFAULT NULL COMMENT 'Personel PWA ana sayfa kart sıralaması (JSON)';
