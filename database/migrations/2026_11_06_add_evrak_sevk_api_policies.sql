INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'evrak-takip/api', v.action, 'POST', v.access_mode, p.id, 0, 0, 1
FROM (
    SELECT 'uets-sevk-personel-listesi' AS action, 'read' AS access_mode
    UNION ALL SELECT 'uets-sevk-et', 'write'
    UNION ALL SELECT 'uets-sevk-gecmisi', 'read'
    UNION ALL SELECT 'evrak-tarihce', 'read'
) v
JOIN permissions p ON p.permission_key = 'evrak-takip/list' AND p.is_active = 1
WHERE NOT EXISTS (
    SELECT 1 FROM permission_policies pp
    WHERE pp.scope = 'api' AND pp.resource = 'evrak-takip/api' AND pp.action = v.action AND pp.http_method = 'POST'
);
