-- Araç Dashboard sayfası için bağımsız menü, yetki ve erişim politikası tanımları.
-- Sabit kayıt ID'leri kullanılmaz; bütün bağlantılar permission_key ('arac-takip/dashboard') üzerinden çözülür.

START TRANSACTION;

-- 1. Yetki Tanımı (Permissions)
INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    (
        'Araç Dashboard',
        'Araç filo durumu, kilometre ve yakıt sarfiyatı, servis ve evrak göstergeleri dashboard sayfasını görüntüleme yetkisi',
        'arac-takip/dashboard',
        'arac-takip/dashboard',
        'Araç Yönetimi',
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

SET @arac_dashboard_permission_id = (
    SELECT id
    FROM permissions
    WHERE permission_key = 'arac-takip/dashboard'
    LIMIT 1
);

-- 2. Yetkiyi Süper Admin ve Firma Sahibi rollerine ata
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, @arac_dashboard_permission_id, NULL
FROM user_roles roles
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND @arac_dashboard_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = @arac_dashboard_permission_id
  );

-- 3. Menü Tanımı (Menus)
-- Parent ID 56: 'Araç Takip/Yönetim' ana menü başlığı
SET @parent_arac_menu_id = (
    SELECT id FROM menus WHERE id = 56 OR (menu_name = 'Araç Takip/Yönetim' AND parent_id = 0) LIMIT 1
);

INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT
    'Araç Dashboard',
    'Araç filo durumu, kilometre, yakıt, bakım ve evrak gösterge paneli',
    @parent_arac_menu_id,
    'Yönetim',
    5,
    'arac-takip/dashboard',
    @arac_dashboard_permission_id,
    'pie-chart',
    49,
    1,
    1,
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM menus WHERE menu_link = 'arac-takip/dashboard'
);

UPDATE menus
SET menu_name = 'Araç Dashboard',
    page_description = 'Araç filo durumu, kilometre, yakıt, bakım ve evrak gösterge paneli',
    parent_id = @parent_arac_menu_id,
    group_name = 'Yönetim',
    group_order = 5,
    menu_icon = 'pie-chart',
    menu_order = 49,
    is_active = 1,
    is_menu = 1,
    is_authorized = 1,
    permission_id = @arac_dashboard_permission_id
WHERE menu_link = 'arac-takip/dashboard'
  AND @arac_dashboard_permission_id IS NOT NULL;

-- 4. Erişim Politikaları (Permission Policies)
INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT definitions.scope, definitions.resource, definitions.action, definitions.http_method,
       'read', @arac_dashboard_permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'arac-takip/dashboard' AS resource, '' AS action, 'GET' AS http_method
    UNION ALL
    SELECT 'api', 'arac-takip/api', 'get-dashboard-stats', '*'
    UNION ALL
    SELECT 'api', 'arac-takip/api', 'get-dashboard-kpi', '*'
) AS definitions
WHERE @arac_dashboard_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM permission_policies existing_policy
      WHERE existing_policy.scope = definitions.scope
        AND existing_policy.resource = definitions.resource
        AND existing_policy.action = definitions.action
        AND existing_policy.http_method = definitions.http_method
        AND existing_policy.permission_id = @arac_dashboard_permission_id
  );

COMMIT;
