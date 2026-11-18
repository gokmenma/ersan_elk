INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'demirbas/api', 'gecmis-hareket-sil', '*', p.id, 0
FROM permissions p
WHERE p.is_active = 1
  AND (p.permission_key = 'demirbas/list' OR p.auth_name = 'demirbas/list')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;
