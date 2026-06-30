-- Add onaylanan_km column to store the approved KM value separately
ALTER TABLE `arac_km_bildirimleri` ADD COLUMN `onaylanan_km` INT(11) DEFAULT NULL AFTER `bitis_km`;

-- Sync existing approved notifications so they default to bitis_km
UPDATE `arac_km_bildirimleri` SET `onaylanan_km` = `bitis_km` WHERE `durum` = 'onaylandi';
