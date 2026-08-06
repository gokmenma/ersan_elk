-- Firmalar tablosuna vergi_dairesi_no, mersis_no ve ticaret_sicil_no sütunlarının eklenmesi
ALTER TABLE `firmalar` 
ADD COLUMN `vergi_dairesi_no` VARCHAR(50) NULL AFTER `vergi_dairesi`,
ADD COLUMN `mersis_no` VARCHAR(50) NULL AFTER `vergi_dairesi_no`,
ADD COLUMN `ticaret_sicil_no` VARCHAR(50) NULL AFTER `mersis_no`;
