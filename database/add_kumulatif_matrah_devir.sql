-- Database update: Add kumulatif_matrah_devir to personel table
-- Created at: 2026-07-05

ALTER TABLE `personel` ADD COLUMN `kumulatif_matrah_devir` DECIMAL(15,2) DEFAULT 0.00;
