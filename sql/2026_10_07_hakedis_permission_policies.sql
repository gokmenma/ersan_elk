-- Sözleşme / Hakediş alt sayfa ve işlem politikaları
-- Ön koşul: permission_policies tablosu ve aktif "hakedis" yetkisi bulunmalıdır.
-- Tekrar çalıştırılabilir; başka modüllerin yetki veya rol kayıtlarını değiştirmez.

-- Sözleşme ve Hakediş detay ekranları ana Hakediş yetkisini devralır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'page', routes.resource, '', 'GET', p.id, 0, 0
FROM (
    SELECT 'hakedisler/sozlesme-detay' AS resource
    UNION ALL SELECT 'hakedisler/hakedis-detay'
) routes
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'hakedis' OR p.auth_name = 'hakedis')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Sözleşme/Hakediş AJAX işlemleri ile Excel çıktısı aynı yetkiye bağlanır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', endpoints.resource, endpoints.action, endpoints.http_method, p.id, 0, 0
FROM (
    SELECT 'hakedisler/online-api' AS resource, '*' AS action, '*' AS http_method
    UNION ALL SELECT 'hakedisler/export-excel', 'export', 'GET'
) endpoints
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'hakedis' OR p.auth_name = 'hakedis')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Doğrulama: beş kaydın tamamı aktif ve "hakedis" yetkisine bağlı görünmelidir.
SELECT pp.scope, pp.resource, pp.action, pp.http_method,
       COALESCE(NULLIF(p.permission_key, ''), p.auth_name) AS permission_key,
       pp.is_active
FROM permission_policies pp
INNER JOIN permissions p ON p.id = pp.permission_id
WHERE (pp.scope = 'page' AND pp.resource IN (
          'hakedisler/index',
          'hakedisler/sozlesme-detay',
          'hakedisler/hakedis-detay'
      ))
   OR (pp.scope = 'api' AND pp.resource IN (
          'hakedisler/online-api',
          'hakedisler/export-excel'
      ))
ORDER BY pp.scope, pp.resource, pp.action;
