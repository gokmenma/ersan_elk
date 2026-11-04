ALTER TABLE `hakedis_sozlesmeler`
    ADD COLUMN IF NOT EXISTS `cari_id` INT(11) NULL DEFAULT NULL AFTER `idare_baskanlik_adi`;

ALTER TABLE `hakedis_sozlesmeler`
    ADD INDEX IF NOT EXISTS `idx_hakedis_sozlesmeler_cari_id` (`cari_id`);
