-- İş Takip > API İstemcisi (yalnızca Superadmin)
-- Uygulama dosyaları: views/api-istemcisi/list.php ve views/api-istemcisi/api.php

INSERT INTO `permissions` (`name`, `description`, `auth_name`, `group_name`, `permission_level`, `is_required`, `is_active`, `superadmin`)
SELECT 'API İstemcisi', 'GET ve POST API isteklerini test etme ekranı', 'api-istemcisi/list', 'İş Takip', 1, 0, 1, 1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `auth_name` = 'api-istemcisi/list');

INSERT INTO `menus` (`menu_name`, `page_description`, `parent_id`, `group_name`, `group_order`, `menu_link`, `menu_icon`, `menu_order`, `is_active`, `is_menu`, `is_authorized`, `created_by`)
SELECT 'API İstemcisi', 'Endeks, kesme/açma ve özel endpoint testleri', 5, 'İş Takip Yönetim', 5, 'api-istemcisi/list', 'terminal', 6, 1, 1, 1, 0
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `menu_link` = 'api-istemcisi/list');

INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_by`)
SELECT ur.id, p.id, 0
FROM `user_roles` ur
JOIN `permissions` p ON p.auth_name = 'api-istemcisi/list'
WHERE (ur.role_type = 'superadmin' OR ur.superadmin = 1 OR ur.role_name = 'Süper Admin')
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp
      WHERE urp.role_id = ur.id AND urp.permission_id = p.id
  );
