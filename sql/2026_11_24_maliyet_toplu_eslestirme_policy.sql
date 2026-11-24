INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'maliyet-raporu/api', 'eslestirme-toplu-kaydet', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'maliyet_raporu_eslestirme' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
