SET @has_onay_turu := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'onay_turu'
);
SET @sql := IF(@has_onay_turu = 0,
    "ALTER TABLE evrak_takip ADD COLUMN onay_turu ENUM('eimza','islak_imza') NOT NULL DEFAULT 'eimza' AFTER onay_durumu",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @perm_id := (SELECT id FROM permissions WHERE permission_key = 'evrak-takip/list' AND is_active = 1 LIMIT 1);

INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT 'api', 'evrak-takip/api', actions.action, 'POST', 'write', @perm_id, 0, 0, 1, NOW(), NOW()
FROM (
    SELECT 'evrak-islak-imza-onaya-sun' AS action
    UNION ALL SELECT 'evrak-islak-imza-onayla'
) actions
WHERE @perm_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies pp
      WHERE pp.scope = 'api' AND pp.resource = 'evrak-takip/api' AND pp.action = actions.action AND pp.http_method IN ('POST', '*')
  );
