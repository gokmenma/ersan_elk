INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT 'api', 'cari/api', 'cari-hareket-aktar', 'POST', 'write', p.id, 0, 0, 1, NOW(), NOW()
FROM permissions p
WHERE p.is_active = 1
  AND p.permission_key = 'cari_hesap_hareketleri'
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies pp
      WHERE pp.scope = 'api' AND pp.resource = 'cari/api' AND pp.action = 'cari-hareket-aktar' AND pp.http_method = 'POST'
  );
