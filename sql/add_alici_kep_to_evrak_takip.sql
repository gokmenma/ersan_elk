-- Evrak Takip Tablosuna KEP Alıcı ve Paket Türü Sütunlarını Ekleme
-- Tarih: 2026-10-19

ALTER TABLE `evrak_takip`
ADD COLUMN `alici_kep` VARCHAR(255) NULL DEFAULT NULL AFTER `kep_mesaj_id`,
ADD COLUMN `kep_paket_turu` VARCHAR(50) NULL DEFAULT 'Standart' AFTER `alici_kep`;
