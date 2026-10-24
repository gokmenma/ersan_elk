INSERT INTO permission_policies (
    scope, resource, action, http_method, access_mode,
    permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at
)
SELECT 'api', 'arac-takip/api', 'zimmet-form-yazdir', '*', 'read',
       p.id, 0, 0, 1, NOW(), NOW()
FROM permissions p
WHERE p.permission_key = 'arac_takip_yonetim'
AND NOT EXISTS (
    SELECT 1 FROM permission_policies pp
    WHERE pp.scope = 'api'
    AND pp.resource = 'arac-takip/api'
    AND pp.action = 'zimmet-form-yazdir'
);
