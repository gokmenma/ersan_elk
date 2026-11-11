-- Personel İşlemleri > Yıllık İzin Hakedişleri sayfası: yetki, menü, politika ve rol atamaları.
-- Betik tekrar çalıştırılabilir (idempotent). Sabit permission ID kullanılmaz.

INSERT INTO permissions (name, auth_name, permission_key, description, group_name, permission_level, is_active, superadmin, is_required)
SELECT 'Yıllık İzin Hakedişleri', 'personel/yillik-izin-hakedisleri', 'personel/yillik-izin-hakedisleri',
       'Personelin kıdemine göre yıllık izin hakedişlerini, kullanılan ve kalan izin günlerini görüntüleme', 'Personel Yönetim', 1, 1, 0, 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'personel/yillik-izin-hakedisleri'
);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Yıllık İzin Hakedişleri',
       'Personel yıllık izin hakedişleri, kullanılan ve kalan izin günleri',
       2, 'Personel Yönetim', 2, 'personel/yillik-izin-hakedisleri', p.id, 'calendar',
       (SELECT COALESCE(MAX(m2.menu_order), 0) + 1 FROM menus m2 WHERE m2.parent_id = 2 AND m2.deleted_at IS NULL),
       1, 1, 1, 0
FROM permissions p
WHERE p.permission_key = 'personel/yillik-izin-hakedisleri'
  AND NOT EXISTS (
      SELECT 1 FROM menus WHERE menu_link = 'personel/yillik-izin-hakedisleri' AND deleted_at IS NULL
  );

UPDATE menus m
INNER JOIN permissions p ON p.permission_key = 'personel/yillik-izin-hakedisleri'
SET m.permission_id = p.id
WHERE m.menu_link = 'personel/yillik-izin-hakedisleri' AND m.deleted_at IS NULL AND (m.permission_id IS NULL OR m.permission_id <> p.id);

INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'page', 'personel/yillik-izin-hakedisleri', '', '*', 'read', p.id, 0, 0, 1
FROM permissions p
WHERE p.is_active = 1 AND p.permission_key = 'personel/yillik-izin-hakedisleri'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'read',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'personel/api/yillik-izin-hakedisleri', policies.action, '*', 'read', p.id, 0, 0, 1
FROM (
    SELECT 'yillik-izin-hakedis-liste' AS action
    UNION ALL SELECT 'yillik-izin-hakedis-ozet'
    UNION ALL SELECT 'yillik-izin-hakedis-detay'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND p.permission_key = 'personel/yillik-izin-hakedisleri'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'read',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO user_role_permissions (role_id, permission_id, created_at, created_by)
SELECT ur.id, p.id, NOW(), 1
FROM user_roles ur
CROSS JOIN permissions p
WHERE (ur.role_type = 'superadmin' OR ur.superadmin = 1)
  AND p.permission_key = 'personel/yillik-izin-hakedisleri'
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp
      WHERE urp.role_id = ur.id AND urp.permission_id = p.id
  );

-- Manuel hakediş ekleme (modal / Excel) ve silme için ayrı yazma yetkisi.
INSERT INTO permissions (name, auth_name, permission_key, description, group_name, permission_level, is_active, superadmin, is_required)
SELECT 'Yıllık İzin Manuel Hakediş Yönetimi', 'personel/yillik-izin-hakedisleri-duzenle', 'personel/yillik-izin-hakedisleri-duzenle',
       'Yıllık izin hakedişlerine manuel (modal veya Excel ile) hakediş ekleme ve silme', 'Personel Yönetim', 1, 1, 0, 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'personel/yillik-izin-hakedisleri-duzenle'
);

INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'personel/api/yillik-izin-hakedisleri', policies.action, '*', 'write', p.id, 0, 0, 1
FROM (
    SELECT 'manuel-hakedis-kaydet' AS action
    UNION ALL SELECT 'manuel-hakedis-sil'
    UNION ALL SELECT 'manuel-hakedis-excel-yukle'
    UNION ALL SELECT 'manuel-hakedis-sablon'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND p.permission_key = 'personel/yillik-izin-hakedisleri-duzenle'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'write',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO user_role_permissions (role_id, permission_id, created_at, created_by)
SELECT ur.id, p.id, NOW(), 1
FROM user_roles ur
CROSS JOIN permissions p
WHERE (ur.role_type = 'superadmin' OR ur.superadmin = 1)
  AND p.permission_key = 'personel/yillik-izin-hakedisleri-duzenle'
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp
      WHERE urp.role_id = ur.id AND urp.permission_id = p.id
  );
