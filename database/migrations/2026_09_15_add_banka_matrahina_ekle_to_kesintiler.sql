-- Migration: personel_kesintileri tablosuna banka_matrahina_ekle kolonu ekleme
ALTER TABLE `personel_kesintileri` 
ADD COLUMN IF NOT EXISTS `banka_matrahina_ekle` TINYINT(1) NOT NULL DEFAULT 1 
AFTER `oran`;
