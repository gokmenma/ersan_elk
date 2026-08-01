-- Menü Yönetimi Modülü - Veritabanı ve Yetkilendirme Scripti

-- 1) menus tablosuna soft delete sütunu ekle (yoksa)
ALTER TABLE `menus` ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL DEFAULT NULL AFTER `created_by`;

-- 2) Menü Yönetimi Yetki Kaydı (permissions)
INSERT INTO `permissions` (`name`, `description`, `auth_name`, `group_name`, `permission_level`, `is_required`, `is_active`, `superadmin`)
SELECT 'Menü Yönetimi', 'Sistem menü haritası ve menü elemanlarını yönetme yetkisi', 'menu-yonetimi/list', 'Yönetim', 1, 0, 1, 1
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `auth_name` = 'menu-yonetimi/list' OR `name` = 'Menü Yönetimi'
);

-- 3) Menü Yönetimi Menü Kaydı (menus) - "Ayarlar" menüsünden hemen sonrasına (group_name: Yönetim, menu_order: 100)
INSERT INTO `menus` (`menu_name`, `page_description`, `parent_id`, `group_name`, `group_order`, `menu_link`, `menu_icon`, `menu_order`, `is_active`, `is_menu`, `is_authorized`, `created_by`)
SELECT 'Menü Yönetimi', 'Sistem menü ve yetki haritası yönetimi', 0, 'Yönetim', 5, 'menu-yonetimi/list', 'sliders', 100, 1, 1, 1, 0
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM `menus` WHERE `menu_link` = 'menu-yonetimi/list'
);

-- 4) Yetkiyi Superadmin rollerine bağla (user_role_permissions)
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_by`)
SELECT ur.id, p.id, 0
FROM `user_roles` ur
JOIN `permissions` p ON p.auth_name = 'menu-yonetimi/list'
WHERE (ur.role_type = 'superadmin' OR ur.superadmin = 1 OR ur.role_name = 'Süper Admin')
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp WHERE urp.role_id = ur.id AND urp.permission_id = p.id
  );
