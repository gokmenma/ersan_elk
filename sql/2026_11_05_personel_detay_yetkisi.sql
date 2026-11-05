INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    (
        'Personel Detayı',
        'Personel detay sayfasını (personel/manage) görüntüleme yetkisi',
        'personel_detay',
        'personel_detay',
        'Personel Yönetim',
        0,
        0,
        1,
        0
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    is_active = 1;

SET @personel_detay_permission_id = (
    SELECT id FROM permissions WHERE permission_key = 'personel_detay' LIMIT 1
);

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'page', 'personel/manage', '', 'GET', @personel_detay_permission_id, 0, 0
FROM DUAL
WHERE @personel_detay_permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, @personel_detay_permission_id, NULL
FROM user_roles roles
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND @personel_detay_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = @personel_detay_permission_id
  );
