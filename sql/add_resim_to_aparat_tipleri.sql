-- Aparat tiplerine resim ekleme sütunu
ALTER TABLE `aparat_tipleri` ADD COLUMN `resim` VARCHAR(255) DEFAULT NULL AFTER `aciklama`;
