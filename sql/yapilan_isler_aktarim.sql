-- =====================================================================
-- yapilan_isler: Sunucudan locale SADECE silinmemis kayitlarin aktarimi
-- Kriter: silinme_tarihi IS NULL
-- =====================================================================

-- ---------------------------------------------------------------------
-- BOLUM 1 - SUNUCUDA CALISTIRILACAK (kaynak veritabani)
-- Amac: aktif kayitlari ayri bir tabloya kopyalayip export almak.
-- SSH/mysqldump erisimi varsa bu bolume gerek yok, dogrudan BOLUM 2'ye gecin.
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `yapilan_isler_export`;
CREATE TABLE `yapilan_isler_export` LIKE `yapilan_isler`;

INSERT INTO `yapilan_isler_export`
SELECT * FROM `yapilan_isler`
WHERE `silinme_tarihi` IS NULL;

SELECT COUNT(*) AS aktarilacak_satir FROM `yapilan_isler_export`;

-- phpMyAdmin > yapilan_isler_export > Disa Aktar > SQL (Custom, "gzipped")
-- Export bittikten sonra sunucuda temizlik:
-- DROP TABLE `yapilan_isler_export`;


-- ---------------------------------------------------------------------
-- BOLUM 2 - LOCALDE CALISTIRILACAK (hedef veritabani)
-- 2.a Mevcut local veriyi yedekle
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `yapilan_isler_yedek`;
CREATE TABLE `yapilan_isler_yedek` LIKE `yapilan_isler`;
INSERT INTO `yapilan_isler_yedek` SELECT * FROM `yapilan_isler`;

SELECT COUNT(*) AS local_yedek_satir FROM `yapilan_isler_yedek`;

-- ---------------------------------------------------------------------
-- 2.b Local tabloyu bosalt
-- ---------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `yapilan_isler`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 2.c Sunucudan alinan dump'i burada import edin.
--     (mysqldump ciktisi ya da yapilan_isler_export tablosunun SQL export'u)
--
--     Eger export "yapilan_isler_export" adiyla geldiyse, import sonrasi:
--
--     INSERT INTO `yapilan_isler` SELECT * FROM `yapilan_isler_export`;
--     DROP TABLE `yapilan_isler_export`;
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- 2.d Dogrulama
-- ---------------------------------------------------------------------

SELECT
    COUNT(*)                                AS toplam,
    SUM(`silinme_tarihi` IS NULL)           AS aktif,
    SUM(`silinme_tarihi` IS NOT NULL)       AS silinmis_olmamali,
    MIN(`tarih`)                            AS ilk_tarih,
    MAX(`tarih`)                            AS son_tarih
FROM `yapilan_isler`;

-- Sonuc dogruysa yedegi silebilirsiniz:
-- DROP TABLE `yapilan_isler_yedek`;
