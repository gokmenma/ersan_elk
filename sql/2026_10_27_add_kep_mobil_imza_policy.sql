SET @perm_kep_id := (SELECT id FROM permissions WHERE permission_key = 'evrak-takip/kep' AND is_active = 1 LIMIT 1);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT 'api', 'evrak-takip/api', actions.action, 'POST', 'write', @perm_kep_id, 0, 0, 1, NOW(), NOW()
FROM (
    SELECT 'kep-mobil-imza-baslat' AS action
    UNION ALL SELECT 'kep-mobil-imza-durum'
) actions
WHERE @perm_kep_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies pp
      WHERE pp.scope = 'api' AND pp.resource = 'evrak-takip/api'
        AND pp.action = actions.action AND pp.http_method IN ('POST', '*')
  );
