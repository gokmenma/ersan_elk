ALTER TABLE `evrak_takip_onaylari`
    ADD COLUMN `sira` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `kullanici_id`,
    ADD KEY `idx_evrak_onay_sira` (`evrak_id`, `sira`);

ALTER TABLE `evrak_takip`
    ADD COLUMN `e_imza_iade_gerekcesi` TEXT NULL AFTER `e_imza_belge_ozeti`,
    ADD COLUMN `e_imza_iade_kullanici_id` INT NULL AFTER `e_imza_iade_gerekcesi`,
    ADD COLUMN `e_imza_iade_tarihi` DATETIME NULL AFTER `e_imza_iade_kullanici_id`;
