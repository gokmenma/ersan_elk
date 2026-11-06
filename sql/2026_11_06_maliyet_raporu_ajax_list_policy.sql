INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'maliyet-raporu/api', 'maliyet-ajax-list', '*', p.id, 0, 0
FROM permissions p
WHERE p.is_active = 1
  AND (p.permission_key = 'maliyet_raporu' OR p.auth_name = 'maliyet_raporu')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;
