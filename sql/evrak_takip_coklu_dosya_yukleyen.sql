SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'evrak_takip_ekleri'
      AND COLUMN_NAME = 'yukleyen_kullanici_id'
);
SET @column_sql := IF(
    @column_exists = 0,
    'ALTER TABLE `evrak_takip_ekleri` ADD COLUMN `yukleyen_kullanici_id` INT NULL AFTER `sira`',
    'SELECT 1'
);
PREPARE column_statement FROM @column_sql;
EXECUTE column_statement;
DEALLOCATE PREPARE column_statement;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'evrak_takip_ekleri'
      AND INDEX_NAME = 'idx_evrak_ekleri_yukleyen'
);
SET @index_sql := IF(
    @index_exists = 0,
    'ALTER TABLE `evrak_takip_ekleri` ADD KEY `idx_evrak_ekleri_yukleyen` (`yukleyen_kullanici_id`)',
    'SELECT 1'
);
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;

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
