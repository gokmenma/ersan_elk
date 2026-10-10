-- Gelir-gider yürüyen bakiyesini tek ve filtrelerden bağımsız bir view üzerinden sunar.
-- Normal view fiziksel veri saklamaz; hesap tüm aktif hareketler üzerinde yapılır.

CREATE OR REPLACE
ALGORITHM = UNDEFINED
SQL SECURITY INVOKER
VIEW `sql_gelir_gider` AS
SELECT
    g.`id`,
    g.`type`,
    g.`tarih`,
    g.`kategori`,
    g.`hesap_adi`,
    g.`tutar`,
    g.`aciklama`,
    g.`plaka`,
    g.`odeme_sekli`,
    g.`banka_adi`,
    g.`kayit_tarihi`,
    g.`silinme_tarihi`,
    g.`silen_kullanici`,
    g.`kayit_yapan`,
    g.`duplicate_hash`,
    g.`excel_import_id`,
    ROUND(
        SUM(
            CASE
                WHEN g.`type` = 1 THEN CAST(g.`tutar` AS DECIMAL(15, 2))
                WHEN g.`type` = 2 THEN -CAST(g.`tutar` AS DECIMAL(15, 2))
                ELSE 0
            END
        ) OVER (ORDER BY g.`tarih`, g.`id`),
        2
    ) AS `bakiye`
FROM `gelir_gider` AS g
WHERE g.`silinme_tarihi` IS NULL;

ALTER TABLE `gelir_gider`
    ADD INDEX IF NOT EXISTS `idx_gelir_gider_active_tarih_id`
        (`silinme_tarihi`, `tarih`, `id`);
