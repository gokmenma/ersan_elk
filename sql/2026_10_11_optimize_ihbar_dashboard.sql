-- İhbar yönetimi Dashboard ve liste açılış sorgularını hızlandırır.
-- Tekrarlanabilir olması için mevcut indeksler INFORMATION_SCHEMA üzerinden kontrol edilir.

SET @schema_name := DATABASE();

SET @sql := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = @schema_name
          AND table_name = 'ihbarlar'
          AND index_name = 'idx_ihbar_firma_silinme_created'
    ),
    'SELECT 1',
    'ALTER TABLE ihbarlar ADD INDEX idx_ihbar_firma_silinme_created (firma_id, silinme_tarihi, created_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Yeni salt-okunur DataTables aksiyonlarını mevcut İhbar Yönetimi yetkisine bağla.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only, is_active)
SELECT 'api', 'ihbar/api', actions.action, '*', p.id, 0, 1
FROM (
    SELECT 'listData' AS action
    UNION ALL SELECT 'personelData'
) actions
INNER JOIN permissions p ON p.permission_key = 'ihbar/list' AND p.is_active = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM permission_policies pp
    WHERE pp.scope = 'api'
      AND pp.resource = 'ihbar/api'
      AND pp.action = actions.action
      AND pp.http_method = '*'
);

SET @sql := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = @schema_name
          AND table_name = 'ihbar_tarihce'
          AND index_name = 'idx_ihbar_tarihce_tip_ihbar_created'
    ),
    'SELECT 1',
    'ALTER TABLE ihbar_tarihce ADD INDEX idx_ihbar_tarihce_tip_ihbar_created (tip, ihbar_id, created_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
