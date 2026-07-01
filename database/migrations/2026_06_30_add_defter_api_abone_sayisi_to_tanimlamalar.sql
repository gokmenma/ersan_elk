-- Database migration to add defter_api_abone_sayisi to tanimlamalar
ALTER TABLE `tanimlamalar` ADD COLUMN `defter_api_abone_sayisi` INT(11) DEFAULT NULL AFTER `defter_abone_sayisi`;
