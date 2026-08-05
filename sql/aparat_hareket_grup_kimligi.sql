-- =====================================================
-- APARAT HAREKET GRUP KİMLİĞİ
-- Bir havuz hareketi birden fazla satır yazabilir (ör. depo çıkışı:
-- depo -N ve ekip +N). Hatalı girilen bir hareketi geri alabilmek için
-- bu satırların birlikte ele alınması gerekir.
--
-- Yeni kayıtlarda grup kimliği AparatHareketModel::grupKimligiAta() ile
-- atanır. Bu script, grup kimliği olmayan ESKİ manuel hareketleri
-- (firma, hareket tipi, tarih, kaydeden) kırılımında gruplayıp doldurur.
-- =====================================================

UPDATE `aparat_hareket` h
INNER JOIN (
    SELECT
        `firma_id`,
        `hareket_tipi`,
        `tarih`,
        COALESCE(`kaydeden_id`, 0) AS `kaydeden`,
        MIN(`id`) AS `grup_id`
    FROM `aparat_hareket`
    WHERE `referans_tipi` = 'manuel'
      AND `referans_id` IS NULL
    GROUP BY `firma_id`, `hareket_tipi`, `tarih`, COALESCE(`kaydeden_id`, 0)
) g
    ON  g.`firma_id`     = h.`firma_id`
    AND g.`hareket_tipi` = h.`hareket_tipi`
    AND g.`tarih`        = h.`tarih`
    AND g.`kaydeden`     = COALESCE(h.`kaydeden_id`, 0)
SET h.`referans_id` = g.`grup_id`
WHERE h.`referans_tipi` = 'manuel'
  AND h.`referans_id` IS NULL;

-- Grup sorgularını hızlandırmak için
ALTER TABLE `aparat_hareket`
    ADD INDEX IF NOT EXISTS `idx_iptal` (`firma_id`, `iptal_mi`);
