-- Aparat Takip Dashboard sayfası için bağımsız yetki, menü ve erişim politikası tanımları.
-- Sabit permission ID kullanılmaz; tüm bağlantılar permission_key ('aparat-takip/dashboard') üzerinden çözülür.

START TRANSACTION;

-- 1. Yetki Tanımı (permissions)
INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    (
        'Aparat Dashboard',
        'Aparat envanter dağılımı, ekip zimmetleri, kesme/açma saha trendi ve kritik stok gösterge panelini görüntüleme yetkisi',
        'aparat-takip/dashboard',
        'aparat-takip/dashboard',
        'İş Takip',
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

SET @aparat_dashboard_permission_id = (
    SELECT id
    FROM permissions
    WHERE permission_key = 'aparat-takip/dashboard'
      AND is_active = 1
    LIMIT 1
);

-- 2. Yeni oluşturulan aktif yetkiyi yalnızca Süper Admin ve Firma Sahibi rollerine ata
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, @aparat_dashboard_permission_id, NULL
FROM user_roles roles
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND @aparat_dashboard_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = @aparat_dashboard_permission_id
  );

-- 3. Menü Tanımı (menus) — Aparat Takip menüsü ile aynı üst menü altında konumlandırılır
SET @aparat_parent_menu_id = COALESCE(
    (SELECT parent_id FROM menus WHERE menu_link = 'aparat-takip/list' LIMIT 1),
    22
);

SET @aparat_group_name = COALESCE(
    (SELECT group_name FROM menus WHERE menu_link = 'aparat-takip/list' LIMIT 1),
    'Yönetim'
);

SET @aparat_group_order = COALESCE(
    (SELECT group_order FROM menus WHERE menu_link = 'aparat-takip/list' LIMIT 1),
    5
);

SET @aparat_menu_order = COALESCE(
    (SELECT menu_order FROM menus WHERE menu_link = 'aparat-takip/list' LIMIT 1),
    55
);

INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT
    'Aparat Dashboard',
    'Aparat envanter dağılımı, ekip zimmetleri, kesme-açma hareketleri ve kritik stok gösterge paneli',
    @aparat_parent_menu_id,
    @aparat_group_name,
    @aparat_group_order,
    'aparat-takip/dashboard',
    @aparat_dashboard_permission_id,
    'pie-chart',
    @aparat_menu_order,
    1,
    1,
    1
FROM DUAL
WHERE @aparat_dashboard_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM menus WHERE menu_link = 'aparat-takip/dashboard'
  );

UPDATE menus
SET menu_name = 'Aparat Dashboard',
    page_description = 'Aparat envanter dağılımı, ekip zimmetleri, kesme-açma hareketleri ve kritik stok gösterge paneli',
    parent_id = @aparat_parent_menu_id,
    group_name = @aparat_group_name,
    group_order = @aparat_group_order,
    menu_icon = 'pie-chart',
    menu_order = @aparat_menu_order,
    is_active = 1,
    is_menu = 1,
    is_authorized = 1,
    permission_id = @aparat_dashboard_permission_id
WHERE menu_link = 'aparat-takip/dashboard'
  AND @aparat_dashboard_permission_id IS NOT NULL;

-- 4. Erişim Politikaları (permission_policies) — sayfa ve API aynı yetki kaydına bağlanır
INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT definitions.scope, definitions.resource, definitions.action, definitions.http_method,
       'read', @aparat_dashboard_permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'aparat-takip/dashboard' AS resource, '' AS action, 'GET' AS http_method
    UNION ALL
    SELECT 'api', 'aparat-takip/api', 'dashboard-data', '*'
) AS definitions
WHERE @aparat_dashboard_permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode),
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

COMMIT;
