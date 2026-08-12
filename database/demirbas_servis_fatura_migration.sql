ALTER TABLE `demirbas_servis_kayitlari`
    ADD COLUMN `fatura_dosya_adi` VARCHAR(255) NULL AFTER `fatura_no`,
    ADD COLUMN `fatura_orijinal_adi` VARCHAR(255) NULL AFTER `fatura_dosya_adi`,
    ADD COLUMN `fatura_mime_tipi` VARCHAR(100) NULL AFTER `fatura_orijinal_adi`,
    ADD COLUMN `fatura_boyutu` INT(11) NULL AFTER `fatura_mime_tipi`;
