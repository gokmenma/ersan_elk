-- Migration: nobetler tablosuna bildirim_gonderildi alanı ekleme
-- Tarih: 2026-02-07

ALTER TABLE `nobetler` 
ADD COLUMN `bildirim_gonderildi` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Personele bildirim gönderildi mi?' AFTER `durum`,
ADD COLUMN `bildirim_tarihi` DATETIME NULL DEFAULT NULL COMMENT 'Bildirim gönderim tarihi' AFTER `bildirim_gonderildi`;

-- Index ekleme (bildirim durumuna göre filtreleme için)
ALTER TABLE `nobetler` ADD INDEX `idx_bildirim_gonderildi` (`bildirim_gonderildi`);
