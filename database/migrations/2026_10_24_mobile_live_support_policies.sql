-- Mobil SPA canlı destek yöneticisi API aksiyonlarını mevcut canlı sohbet yetkisine bağlar.
-- Yeni bir sayfa yetkisi oluşturulmaz; masaüstü canlı destek yönetimiyle aynı yetki kullanılır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/support', actions.action, actions.method, p.id, 0, 0, 1
FROM (
    SELECT 'list' AS action, 'GET' AS method
    UNION ALL SELECT 'detail', 'GET'
    UNION ALL SELECT 'poll', 'GET'
    UNION ALL SELECT 'send', 'POST'
    UNION ALL SELECT 'update_status', 'POST'
    UNION ALL SELECT 'set_availability', 'POST'
    UNION ALL SELECT 'get_settings', 'GET'
    UNION ALL SELECT 'save_settings', 'POST'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'canli_sohbet_ayarlari_sekmesi' OR p.auth_name = 'canli_sohbet_ayarlari_sekmesi')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), http_method = VALUES(http_method),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
