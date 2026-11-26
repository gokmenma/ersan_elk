ALTER TABLE `tanimlamalar`
    ADD COLUMN IF NOT EXISTS `aparat_islemi` VARCHAR(10) NOT NULL DEFAULT '' AFTER `aparat_hurda`;

UPDATE `tanimlamalar`
SET `aparat_islemi` = 'hurda'
WHERE `grup` = 'is_turu' AND `aparat_hurda` = 1 AND `aparat_islemi` = '';

UPDATE `tanimlamalar`
SET `aparat_islemi` = 'kesme'
WHERE `grup` = 'is_turu' AND `silinme_tarihi` IS NULL AND `aparat_islemi` = ''
  AND `is_emri_sonucu` LIKE '%APARATLA KES%';

UPDATE `tanimlamalar`
SET `aparat_islemi` = 'acma'
WHERE `grup` = 'is_turu' AND `silinme_tarihi` IS NULL AND `aparat_islemi` = ''
  AND `is_emri_sonucu` LIKE 'SAYAÇ KULLANIMA A%';

ALTER TABLE `aparat_otomatik_kirma`
    ADD COLUMN IF NOT EXISTS `islem` VARCHAR(10) NOT NULL DEFAULT 'hurda' AFTER `is_emri_sonucu_id`,
    ADD COLUMN IF NOT EXISTS `uygulanan_adet` INT(11) NOT NULL DEFAULT 0 AFTER `islenen_adet`;

UPDATE `aparat_otomatik_kirma`
SET `uygulanan_adet` = `islenen_adet`
WHERE `islem` = 'hurda' AND `uygulanan_adet` = 0 AND `islenen_adet` <> 0;

INSERT INTO `settings` (`user_id`, `firma_id`, `set_name`, `set_value`)
SELECT NULL, NULL, 'aparat_otomatik_kesme_acma_baslangic', DATE_FORMAT(CURDATE(), '%Y-%m-%d')
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `settings`
    WHERE `set_name` = 'aparat_otomatik_kesme_acma_baslangic' AND `firma_id` IS NULL AND `user_id` IS NULL
);
