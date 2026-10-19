-- KEP Önizleme Yetki Politikası
-- AGENTS.md Standardı: Sabit permission_id yerine permission_key üzerinden dinamik bağlama
-- Benzersizlik: uq_permission_policy (scope, resource, action, http_method)

INSERT INTO permission_policies (
    scope,
    resource,
    action,
    http_method,
    access_mode,
    permission_id,
    superadmin_only,
    authenticated_only,
    is_active,
    created_at,
    updated_at
)
SELECT
    'api' AS scope,
    'evrak-takip/api' AS resource,
    'kep-detay-getir' AS action,
    '*' AS http_method,
    'read' AS access_mode,
    p.id AS permission_id,
    0 AS superadmin_only,
    0 AS authenticated_only,
    1 AS is_active,
    NOW() AS created_at,
    NOW() AS updated_at
FROM permissions p
WHERE p.permission_key = 'evrak-takip/list'
AND NOT EXISTS (
    SELECT 1 FROM permission_policies pp 
    WHERE pp.resource = 'evrak-takip/api' 
    AND pp.action = 'kep-detay-getir'
);
