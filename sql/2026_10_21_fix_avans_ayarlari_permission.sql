-- Avans ayarları sekmesi ve kaydetme API aksiyonu için eksik yetki/politika kaydı.
-- Tekrarlanabilir çalışır; mevcut rol seçimlerini değiştirmez, yalnızca yeni yetkiyi
-- Süper Admin ve Firma Sahibi kapsamındaki rollere otomatik atar.

INSERT INTO permissions
    (name, auth_name, permission_key, description, group_name, permission_level, is_active, superadmin, is_required)
SELECT
    'Avans Ayarları Sekmesi',
    'avans_ayarlari_sekmesi',
    'avans_ayarlari_sekmesi',
    'Avans talep kısıtı ayarını yönetme yetkisi',
    'Ayarlar',
    0,
    1,
    0,
    0
WHERE NOT EXISTS (
    SELECT 1
    FROM permissions
    WHERE permission_key = 'avans_ayarlari_sekmesi'
       OR auth_name = 'avans_ayarlari_sekmesi'
);

UPDATE permissions
SET permission_key = 'avans_ayarlari_sekmesi',
    is_active = 1
WHERE auth_name = 'avans_ayarlari_sekmesi'
  AND (permission_key IS NULL OR permission_key = '' OR is_active <> 1);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT
    'api',
    'ayarlar/api',
    'save_avans_ayarlari',
    '*',
    'write',
    p.id,
    0,
    0,
    1
FROM permissions p
WHERE p.permission_key = 'avans_ayarlari_sekmesi'
  AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode),
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

INSERT INTO user_role_permissions (role_id, permission_id)
SELECT roles.id, permission_record.id
FROM user_roles roles
INNER JOIN permissions permission_record
    ON permission_record.permission_key = 'avans_ayarlari_sekmesi'
   AND permission_record.is_active = 1
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions assigned
      WHERE assigned.role_id = roles.id
        AND assigned.permission_id = permission_record.id
  );
