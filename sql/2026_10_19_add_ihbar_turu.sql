SET @schema_name := DATABASE();

SET @add_column_sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ihbarlar' AND COLUMN_NAME = 'ihbar_turu'
    ),
    'SELECT 1',
    'ALTER TABLE ihbarlar ADD COLUMN ihbar_turu ENUM(''Abonesiz'',''Kaçak'',''Pasif'',''Usülsüz'') NULL AFTER firma_id'
);
PREPARE add_column_stmt FROM @add_column_sql;
EXECUTE add_column_stmt;
DEALLOCATE PREPARE add_column_stmt;

UPDATE ihbarlar
SET ihbar_turu = CASE
    WHEN aciklama LIKE '%usülsüz%' OR aciklama LIKE '%usulsüz%' OR aciklama LIKE '%usulsuz%' THEN 'Usülsüz'
    WHEN aciklama LIKE '%pasif%' THEN 'Pasif'
    WHEN aciklama LIKE '%abonesiz%' OR aciklama LIKE '%abone yok%' OR aciklama LIKE '%abonelik yok%'
        OR aciklama LIKE '%abonelik bulunamadı%' OR aciklama LIKE '%abonelik bulunamadi%'
        OR aciklama LIKE '%kayıtsız%' OR aciklama LIKE '%kayitsiz%' OR aciklama LIKE '%saatsiz%'
        OR aciklama LIKE '%saat yok%' THEN 'Abonesiz'
    WHEN aciklama LIKE '%kaçak%' OR aciklama LIKE '%kacak%' THEN 'Kaçak'
    ELSE ihbar_turu
END
WHERE ihbar_turu IS NULL;

SET @add_index_sql := IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ihbarlar' AND INDEX_NAME = 'idx_ihbar_firma_tur'
    ),
    'SELECT 1',
    'ALTER TABLE ihbarlar ADD INDEX idx_ihbar_firma_tur (firma_id, ihbar_turu)'
);
PREPARE add_index_stmt FROM @add_index_sql;
EXECUTE add_index_stmt;
DEALLOCATE PREPARE add_index_stmt;
