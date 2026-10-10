-- Tüm Hesap Hareketleri sayfası için bağımsız ve tutarlı yetkilendirme.
-- Sabit kayıt ID'leri kullanılmaz; bütün bağlantılar permission_key üzerinden çözülür.

START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    (
        'Tüm Hesap Hareketlerini Görüntüleme',
        'Tüm carilere ait hesap hareketleri sayfasını görüntüleme ve bu listenin dökümünü alma yetkisi',
        'cari/tum-hareketler',
        'cari/tum-hareketler',
        'Finansal İşlemler',
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

SET @tum_hareketler_permission_id = (
    SELECT id
    FROM permissions
    WHERE permission_key = 'cari/tum-hareketler'
    LIMIT 1
);

-- Yeni aktif yetkiler Süper Admin ve Firma Sahibi rollerine otomatik verilir.
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, @tum_hareketler_permission_id, NULL
FROM user_roles roles
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND @tum_hareketler_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = @tum_hareketler_permission_id
  );

-- Önceki erişimi koru: Cari Takibi veya Cari Hesap Hareketleri yetkisi olan
-- gruplar başlangıçta yeni sayfa yetkisini de alır. Sonrasında bağımsız yönetilebilir.
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT DISTINCT urp.role_id, @tum_hareketler_permission_id, urp.created_by
FROM user_role_permissions urp
INNER JOIN permissions source_permission
    ON source_permission.id = urp.permission_id
WHERE source_permission.is_active = 1
  AND source_permission.permission_key IN ('cari_takibi', 'cari_hesap_hareketleri')
  AND @tum_hareketler_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = urp.role_id
        AND existing_grant.permission_id = @tum_hareketler_permission_id
  );

UPDATE menus
SET permission_id = @tum_hareketler_permission_id,
    parent_id = 0,
    group_name = 'Finans',
    group_order = 3,
    menu_order = 43,
    is_authorized = 1,
    is_menu = 1,
    is_active = 1
WHERE menu_link = 'cari/tum-hareketler'
  AND @tum_hareketler_permission_id IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT definitions.scope, definitions.resource, definitions.action, definitions.http_method,
       'read', @tum_hareketler_permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'cari/tum-hareketler' AS resource, '' AS action, 'GET' AS http_method
    UNION ALL
    SELECT 'api', 'cari/api', 'tum-hareketler-ajax-list', 'POST'
    UNION ALL
    SELECT 'api', 'cari/api', 'tum_hareketler_ajax_list', 'POST'
) definitions
WHERE @tum_hareketler_permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode),
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

COMMIT;
