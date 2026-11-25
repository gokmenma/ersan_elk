ALTER TABLE `tanimlamalar`
    ADD COLUMN IF NOT EXISTS `aparat_hurda` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sonuc_tipi`;

UPDATE `tanimlamalar`
SET `aparat_hurda` = 1
WHERE `grup` = 'is_turu'
  AND `silinme_tarihi` IS NULL
  AND UPPER(`is_emri_sonucu`) LIKE '%APARAT KIRMA%';

ALTER TABLE `aparat_tipleri`
    ADD COLUMN IF NOT EXISTS `varsayilan_kirma` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ekip_kritik_stok`;

CREATE TABLE IF NOT EXISTS `aparat_otomatik_kirma` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `personel_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `is_emri_sonucu_id` INT(11) NOT NULL,
    `ekip_id` INT(11) DEFAULT NULL,
    `aparat_tip_id` INT(11) DEFAULT NULL,
    `islenen_adet` INT(11) NOT NULL DEFAULT 0,
    `olusturma_tarihi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_aparat_otomatik_kirma` (`firma_id`, `personel_id`, `tarih`, `is_emri_sonucu_id`),
    KEY `idx_aok_firma_tarih` (`firma_id`, `tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`user_id`, `firma_id`, `set_name`, `set_value`)
SELECT NULL, NULL, 'aparat_otomatik_kirma_baslangic', DATE_FORMAT(CURDATE(), '%Y-%m-%d')
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `settings`
    WHERE `set_name` = 'aparat_otomatik_kirma_baslangic' AND `firma_id` IS NULL AND `user_id` IS NULL
);
