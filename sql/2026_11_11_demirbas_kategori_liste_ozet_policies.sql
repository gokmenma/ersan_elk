-- Demirbaş Kategorileri sayfası yeni sayfa standardına (server-side DataTables + KPI) geçirildi.
-- Yeni API aksiyonları mevcut 'tanimlamalar/demirbas-kategorileri' yetkisine bağlanır. Yeni yetki oluşturulmaz.

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'tanimlamalar/api', policies.action, '*', 'read', p.id, 0, 0, 1
FROM (
    SELECT 'demirbas-kategorisi-liste' AS action
    UNION ALL SELECT 'demirbas-kategorisi-ozet'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND p.permission_key = 'tanimlamalar/demirbas-kategorileri'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'read',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
