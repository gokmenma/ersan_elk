INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'duyuru/api', 'ajax-list', '*', p.id, 0, 0
FROM permissions p
WHERE p.is_active = 1
  AND (p.permission_key = 'duyuru_etkinlik' OR p.auth_name = 'duyuru_etkinlik')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;
