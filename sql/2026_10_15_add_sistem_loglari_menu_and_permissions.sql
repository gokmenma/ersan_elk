-- Sistem Logları sayfası için bağımsız menü, yetki ve erişim politikası tanımları.
-- Sabit kayıt ID'leri kullanılmaz; bütün bağlantılar permission_key ('log_kayitlari') üzerinden çözülür.

START TRANSACTION;

-- 1. Yetki Tanımı (Permissions)
INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    (
        'Sistem Logları',
        'Sistem aktiviteleri, denetim logları ve kullanıcı işlemlerini görüntüleme yetkisi',
        'log_kayitlari',
        'log_kayitlari',
        'Yönetim',
        1,
        0,
        1,
        0
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

SET @log_kayitlari_permission_id = (
    SELECT id
    FROM permissions
    WHERE permission_key = 'log_kayitlari'
    LIMIT 1
);

-- 2. Yetkiyi Süper Admin ve Firma Sahibi rollerine ata
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, @log_kayitlari_permission_id, NULL
FROM user_roles roles
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND @log_kayitlari_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = @log_kayitlari_permission_id
  );

-- 3. Menü Tanımı (Menus)
INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT
    'Sistem Logları',
    'Sistem aktiviteleri, denetim günlüğü, kullanıcı hareketleri ve güvenlik logları',
    0,
    'Yönetim',
    5,
    'logs/list',
    @log_kayitlari_permission_id,
    'shield',
    84,
    1,
    1,
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM menus WHERE menu_link = 'logs/list'
);

UPDATE menus
SET menu_name = 'Sistem Logları',
    page_description = 'Sistem aktiviteleri, denetim günlüğü, kullanıcı hareketleri ve güvenlik logları',
    parent_id = 0,
    group_name = 'Yönetim',
    group_order = 5,
    menu_icon = 'shield',
    menu_order = 84,
    is_active = 1,
    is_menu = 1,
    is_authorized = 1,
    permission_id = @log_kayitlari_permission_id
WHERE menu_link = 'logs/list'
  AND @log_kayitlari_permission_id IS NOT NULL;

-- 4. Erişim Politikaları (Permission Policies)
INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT definitions.scope, definitions.resource, definitions.action, definitions.http_method,
       'read', @log_kayitlari_permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'logs/list' AS resource, '' AS action, 'GET' AS http_method
    UNION ALL
    SELECT 'api', 'logs/api', 'get-unified-logs', '*'
    UNION ALL
    SELECT 'api', 'logs/api', 'get-system-logs', '*'
    UNION ALL
    SELECT 'api', 'logs/api', 'get-personel-logs', '*'
    UNION ALL
    SELECT 'api', 'logs/api', 'get-user-logs', '*'
    UNION ALL
    SELECT 'api', 'logs/api', 'get-ai-agent-logs', '*'
    UNION ALL
    SELECT 'api', 'logs/api', 'get-page-view-logs', '*'
) definitions
WHERE @log_kayitlari_permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode),
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

COMMIT;
