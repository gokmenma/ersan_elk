ALTER TABLE `aparat_tipleri`
    ADD COLUMN IF NOT EXISTS `depo_kritik_stok` INT NOT NULL DEFAULT 0 AFTER `resim`,
    ADD COLUMN IF NOT EXISTS `ekip_kritik_stok` INT NOT NULL DEFAULT 0 AFTER `depo_kritik_stok`;

INSERT INTO permissions (name, auth_name, permission_key, description, group_name, permission_level, is_active, superadmin, is_required)
SELECT 'Aparat Gelişmiş İşlemler', 'aparat_gelismis', 'aparat_gelismis',
       'Saha işlemleri, hareket dökümü, transferler, sayım, raporlar, tanımlar, hurda/kayıp ve manuel saha kaydı',
       'İş Takip', 0, 1, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.permission_key = 'aparat_gelismis');

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, p.id, NULL
FROM user_roles roles
INNER JOIN permissions p ON p.permission_key = 'aparat_gelismis' AND p.is_active = 1
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = p.id
  );

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'aparat-takip/api', d.action, '*', 'read', p.id, 0, 0, 1
FROM (
    SELECT 'islem-listesi' AS action
    UNION ALL SELECT 'islem-detay'
    UNION ALL SELECT 'hareket-listesi'
    UNION ALL SELECT 'transfer-listesi'
    UNION ALL SELECT 'sayim-listesi'
    UNION ALL SELECT 'sayim-detay'
    UNION ALL SELECT 'sahada-takili'
    UNION ALL SELECT 'donemsel-ozet'
    UNION ALL SELECT 'api-karsilastirma'
    UNION ALL SELECT 'tutarlilik'
) d
INNER JOIN permissions p ON p.permission_key = 'aparat_gelismis' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'aparat-takip/api', 'son-depo-hareketleri', '*', 'read', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'aparat_takip' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
