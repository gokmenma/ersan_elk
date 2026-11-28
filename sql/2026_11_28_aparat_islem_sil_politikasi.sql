INSERT INTO permissions (name, auth_name, permission_key, description, group_name, permission_level, is_active, superadmin, is_required)
SELECT 'Aparat Kayıt Silme', 'aparat_sil', 'aparat_sil',
       'İptal edilmiş saha işlem kaydını listelerden kaldırma',
       'İş Takip', 0, 1, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.permission_key = 'aparat_sil');

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, p.id, NULL
FROM user_roles roles
INNER JOIN permissions p ON p.permission_key = 'aparat_sil' AND p.is_active = 1
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = p.id
  );

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'aparat-takip/api', 'islem-sil', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'aparat_sil' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'aparat-takip/api', 'sayim-excel-yukle', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'aparat_sayim' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'aparat-takip/api', 'havuz-toplu', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'aparat_depo' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'aparat-takip/api', 'havuz-geri-al', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'aparat_depo' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'aparat-takip/api', 'sayim-satir-geri-al', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'aparat_sayim' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'aparat-takip/api', a.action, '*', 'write', p.id, 0, 0, 1
FROM (SELECT 'sayim-ekip-ekle' AS action UNION ALL SELECT 'sayim-ekip-cikar') a
INNER JOIN permissions p ON p.permission_key = 'aparat_sayim' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
