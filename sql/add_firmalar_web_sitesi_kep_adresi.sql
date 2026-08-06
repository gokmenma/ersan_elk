-- Firmalar tablosuna web_sitesi ve kep_adresi sütunlarının eklenmesi
ALTER TABLE `firmalar` 
ADD COLUMN `web_sitesi` VARCHAR(255) NULL AFTER `telefon`,
ADD COLUMN `kep_adresi` VARCHAR(255) NULL AFTER `web_sitesi`;
