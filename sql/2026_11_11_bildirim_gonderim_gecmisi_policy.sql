-- Push Bildirim Gönder sayfası: gönderim geçmişi (server-side DataTables) API aksiyonu.
-- Mevcut 'bildirim/gonder' sayfa yetkisine bağlanır. Yeni yetki oluşturulmaz.

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'bildirim/api', 'gonderim-gecmisi', '*', 'read', p.id, 0, 0, 1
FROM permissions p
WHERE p.is_active = 1
  AND p.permission_key = 'bildirim/gonder'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'read',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
