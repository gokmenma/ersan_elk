-- Veritabanı Yedekleri sayfası için yetki, menü ve erişim politikası tanımları.
-- Sayfa ve API yalnızca Süper Admin rollerine açıktır (Gate::isSuperAdmin + superadmin_only politikası).
-- Sabit kayıt ID'leri kullanılmaz; bütün bağlantılar permission_key ('yedekleme_yonetimi') üzerinden çözülür.

START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    (
        'Veritabanı Yedekleri',
        'Veritabanı yedeklerini listeleme, elle yedek alma, Google Drive senkronu, geri yükleme testi ve yedek indirme (yalnız Süper Admin)',
        'yedekleme_yonetimi',
        'yedekleme_yonetimi',
        'Yönetim',
        1,
        0,
        1,
        1
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 1;

SET @yedekleme_permission_id = (
    SELECT id
    FROM permissions
    WHERE permission_key = 'yedekleme_yonetimi'
    LIMIT 1
);

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, @yedekleme_permission_id, NULL
FROM user_roles roles
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND @yedekleme_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = @yedekleme_permission_id
  );

INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT
    'Veritabanı Yedekleri',
    'Yerel ve Google Drive veritabanı yedekleri, elle yedekleme ve geri yükleme testleri',
    0,
    'Yönetim',
    5,
    'yedekleme/list',
    @yedekleme_permission_id,
    'database',
    88,
    1,
    1,
    1
FROM DUAL
WHERE @yedekleme_permission_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM menus WHERE menu_link = 'yedekleme/list'
);

UPDATE menus
SET menu_name = 'Veritabanı Yedekleri',
    page_description = 'Yerel ve Google Drive veritabanı yedekleri, elle yedekleme ve geri yükleme testleri',
    parent_id = 0,
    group_name = 'Yönetim',
    group_order = 5,
    menu_icon = 'database',
    is_active = 1,
    is_menu = 1,
    is_authorized = 1,
    permission_id = @yedekleme_permission_id
WHERE menu_link = 'yedekleme/list'
  AND @yedekleme_permission_id IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT definitions.scope, definitions.resource, definitions.action, definitions.http_method,
       definitions.access_mode, @yedekleme_permission_id, 1, 0, 1
FROM (
    SELECT 'page' AS scope, 'yedekleme/list' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode
    UNION ALL
    SELECT 'api', 'yedekleme/api', 'liste', '*', 'read'
    UNION ALL
    SELECT 'api', 'yedekleme/api', 'is-durumu', '*', 'read'
    UNION ALL
    SELECT 'api', 'yedekleme/api', 'test-detay', '*', 'read'
    UNION ALL
    SELECT 'api', 'yedekleme/api', 'is-baslat', '*', 'write'
    UNION ALL
    SELECT 'api', 'yedekleme/api', 'indir', '*', 'write'
) definitions
WHERE @yedekleme_permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode),
    permission_id = VALUES(permission_id),
    superadmin_only = 1,
    authenticated_only = 0,
    is_active = 1;

COMMIT;
