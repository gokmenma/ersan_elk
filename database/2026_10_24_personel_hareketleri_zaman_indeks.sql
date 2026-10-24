SET @idx_var := (SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'personel_hareketleri'
                   AND index_name = 'idx_zaman_kapsayan');

SET @sql := IF(@idx_var = 0,
    'ALTER TABLE personel_hareketleri ADD INDEX idx_zaman_kapsayan (zaman, silinme_tarihi, personel_id, islem_tipi)',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
