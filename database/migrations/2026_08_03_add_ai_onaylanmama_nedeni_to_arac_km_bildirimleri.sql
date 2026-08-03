ALTER TABLE `arac_km_bildirimleri`
    ADD COLUMN `ai_onaylanmama_nedeni` VARCHAR(500) NULL DEFAULT NULL AFTER `ai_onay_mi`;
