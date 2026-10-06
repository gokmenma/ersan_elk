-- Gelir-Gider Tablosuna Plaka, Ödeme Şekli ve Banka Adı Eklenmesi ve View Güncellemesi
-- Tarih: 2026-10-06

ALTER TABLE `gelir_gider` 
ADD COLUMN IF NOT EXISTS `plaka` VARCHAR(50) NULL DEFAULT NULL AFTER `aciklama`,
ADD COLUMN IF NOT EXISTS `odeme_sekli` VARCHAR(50) NULL DEFAULT NULL AFTER `plaka`,
ADD COLUMN IF NOT EXISTS `banka_adi` VARCHAR(100) NULL DEFAULT NULL AFTER `odeme_sekli`;

CREATE OR REPLACE VIEW `sql_gelir_gider` AS 
SELECT 
    `gelir_gider`.`id` AS `id`,
    `gelir_gider`.`tarih` AS `tarih`,
    `gelir_gider`.`type` AS `TYPE`,
    `gelir_gider`.`kategori` AS `kategori`,
    `gelir_gider`.`hesap_adi` AS `hesap_adi`,
    `gelir_gider`.`tutar` AS `tutar`,
    `gelir_gider`.`aciklama` AS `aciklama`,
    `gelir_gider`.`plaka` AS `plaka`,
    `gelir_gider`.`odeme_sekli` AS `odeme_sekli`,
    `gelir_gider`.`banka_adi` AS `banka_adi`,
    `gelir_gider`.`kayit_tarihi` AS `kayit_tarihi`,
    `gelir_gider`.`silinme_tarihi` AS `silinme_tarihi`,
    `gelir_gider`.`kayit_yapan` AS `kayit_yapan`,
    ROUND(SUM(CASE WHEN `gelir_gider`.`type` = 1 THEN `gelir_gider`.`tutar` WHEN `gelir_gider`.`type` = 2 THEN -`gelir_gider`.`tutar` ELSE 0 END) OVER (ORDER BY `gelir_gider`.`tarih`,`gelir_gider`.`id`), 2) AS `bakiye`
FROM `gelir_gider`
WHERE `gelir_gider`.`silinme_tarihi` IS NULL
ORDER BY `gelir_gider`.`tarih` DESC, `gelir_gider`.`id` DESC;
