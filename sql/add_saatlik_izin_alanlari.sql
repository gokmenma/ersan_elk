ALTER TABLE `personel_izinleri`
    ADD COLUMN IF NOT EXISTS `baslangic_saati` TIME NULL DEFAULT NULL AFTER `bitis_tarihi`,
    ADD COLUMN IF NOT EXISTS `izin_saati` DECIMAL(4,2) NULL DEFAULT NULL AFTER `baslangic_saati`,
    ADD COLUMN IF NOT EXISTS `talep_baslangic_saati` TIME NULL DEFAULT NULL AFTER `izin_saati`,
    ADD COLUMN IF NOT EXISTS `talep_izin_saati` DECIMAL(4,2) NULL DEFAULT NULL AFTER `talep_baslangic_saati`;

UPDATE `personel_izinleri`
SET `talep_baslangic_saati` = `baslangic_saati`, `talep_izin_saati` = `izin_saati`
WHERE `izin_saati` IS NOT NULL AND `talep_izin_saati` IS NULL;
