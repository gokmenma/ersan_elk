INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/site-mesajlar', actions.action, actions.method, p.id, 0, 0, 1
FROM (
    SELECT 'list' AS action, 'GET' AS method
    UNION ALL SELECT 'detail', 'GET'
    UNION ALL SELECT 'update_status', 'POST'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND p.permission_key = 'site_mesaj_yonetimi'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), http_method = VALUES(http_method),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
