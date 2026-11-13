ALTER TABLE `firmalar`
    ADD COLUMN IF NOT EXISTS `il` varchar(100) DEFAULT NULL AFTER `adres`,
    ADD COLUMN IF NOT EXISTS `ilce` varchar(100) DEFAULT NULL AFTER `il`,
    ADD COLUMN IF NOT EXISTS `ulke` varchar(100) DEFAULT NULL AFTER `ilce`,
    ADD COLUMN IF NOT EXISTS `email` varchar(255) DEFAULT NULL AFTER `kep_adresi`,
    ADD COLUMN IF NOT EXISTS `fax` varchar(50) DEFAULT NULL AFTER `telefon`;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
SELECT 'Firma Yönetimi', 'Firma kartlarını (ünvan, adres, vergi, iletişim, logo, IBAN) listeleme ve düzenleme', 'firma-yonetimi/list', 'firma-yonetimi/list', 'Yönetim', 1, 0, 1, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_key = 'firma-yonetimi/list');

UPDATE permissions SET is_active = 1 WHERE permission_key = 'firma-yonetimi/list';

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, p.id, NULL
FROM user_roles roles
INNER JOIN permissions p ON p.permission_key = 'firma-yonetimi/list' AND p.is_active = 1
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = p.id
  );

SET @firma_yonetimi_perm_id = (SELECT id FROM permissions WHERE permission_key = 'firma-yonetimi/list' LIMIT 1);

INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Firma Yönetimi', 'Firma kartları, fatura satıcı bilgileri, logo ve IBAN yönetimi', 0, 'Yönetim', 5, 'firma-yonetimi/list', @firma_yonetimi_perm_id, 'briefcase', 89, 1, 1, 1
FROM DUAL
WHERE @firma_yonetimi_perm_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'firma-yonetimi/list' AND deleted_at IS NULL);

UPDATE menus
SET permission_id = @firma_yonetimi_perm_id, is_active = 1, is_menu = 1, is_authorized = 1
WHERE menu_link = 'firma-yonetimi/list' AND deleted_at IS NULL AND @firma_yonetimi_perm_id IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, p.id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'firma-yonetimi/list' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode
    UNION ALL SELECT 'api', 'firma-yonetimi/api', 'list', '*', 'read'
    UNION ALL SELECT 'api', 'firma-yonetimi/api', 'summary', '*', 'read'
    UNION ALL SELECT 'api', 'firma-yonetimi/api', 'get', '*', 'read'
    UNION ALL SELECT 'api', 'firma-yonetimi/api', 'save', '*', 'write'
    UNION ALL SELECT 'api', 'firma-yonetimi/api', 'delete', '*', 'write'
    UNION ALL SELECT 'api', 'firma-yonetimi/api', 'set-default', '*', 'write'
) d
INNER JOIN permissions p ON p.permission_key = 'firma-yonetimi/list' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
