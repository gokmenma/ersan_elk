-- Migration: personel_ek_odemeler tablosuna banka_matrahina_ekle kolonu ekleme
ALTER TABLE `personel_ek_odemeler` 
ADD COLUMN IF NOT EXISTS `banka_matrahina_ekle` TINYINT(1) NOT NULL DEFAULT 1 
AFTER `resmi_tutar`;
