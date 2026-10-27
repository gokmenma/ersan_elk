INSERT INTO permission_policies (
    scope, resource, action, http_method, access_mode,
    permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at
)
SELECT 'api', 'mail-sms/api/log', a.action, '*', 'read',
       p.id, 0, 0, 1, NOW(), NOW()
FROM permissions p
JOIN (SELECT 'log-ajax-list' AS action UNION ALL SELECT 'log-detay') a
WHERE p.permission_key = 'mail-sms/list'
AND p.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM permission_policies pp
    WHERE pp.scope = 'api'
    AND pp.resource = 'mail-sms/api/log'
    AND pp.action = a.action
);

ALTER TABLE mesaj_log ADD INDEX idx_firma_created (firma_id, created_at);
