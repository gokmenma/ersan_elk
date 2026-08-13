ALTER TABLE `evrak_takip_ekleri`
    ADD COLUMN `yukleyen_kullanici_id` INT NULL AFTER `sira`,
    ADD KEY `idx_evrak_ekleri_yukleyen` (`yukleyen_kullanici_id`);

UPDATE `evrak_takip_ekleri` ee
INNER JOIN `evrak_takip` et ON et.id = ee.evrak_id AND et.firma_id = ee.firma_id
SET ee.yukleyen_kullanici_id = et.olusturan_kullanici_id
WHERE ee.yukleyen_kullanici_id IS NULL;

INSERT INTO `evrak_takip_ekleri`
    (`evrak_id`, `firma_id`, `dosya_adi`, `dosya_yolu`, `mime_tipi`, `dosya_boyutu`, `sira`, `yukleyen_kullanici_id`, `olusturma_tarihi`)
SELECT et.id,
       et.firma_id,
       SUBSTRING_INDEX(et.dosya_yolu, '/', -1),
       et.dosya_yolu,
       NULL,
       0,
       1,
       et.olusturan_kullanici_id,
       COALESCE(et.olusturma_tarihi, NOW())
FROM `evrak_takip` et
WHERE et.dosya_yolu IS NOT NULL
  AND et.dosya_yolu <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM `evrak_takip_ekleri` ee
      WHERE ee.evrak_id = et.id
        AND ee.firma_id = et.firma_id
        AND ee.dosya_yolu = et.dosya_yolu
  );
