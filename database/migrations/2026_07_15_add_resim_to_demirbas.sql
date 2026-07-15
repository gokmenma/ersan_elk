-- Demirbaş tablosuna resim_yolu alanı eklenmesi
ALTER TABLE `demirbas` ADD COLUMN `resim_yolu` VARCHAR(255) DEFAULT NULL AFTER `aciklama`;
