ALTER TABLE `evrak_takip`
    ADD COLUMN IF NOT EXISTS `ceza_odendi` tinyint(1) NOT NULL DEFAULT 0 AFTER `ceza_personel_id`,
    ADD COLUMN IF NOT EXISTS `ceza_odeme_tarihi` date DEFAULT NULL AFTER `ceza_odendi`,
    ADD COLUMN IF NOT EXISTS `ceza_odenen_tutar` decimal(10,2) DEFAULT NULL AFTER `ceza_odeme_tarihi`,
    ADD COLUMN IF NOT EXISTS `ceza_dekont_yolu` varchar(555) DEFAULT NULL AFTER `ceza_odenen_tutar`,
    ADD COLUMN IF NOT EXISTS `ceza_dekont_adi` varchar(255) DEFAULT NULL AFTER `ceza_dekont_yolu`;
