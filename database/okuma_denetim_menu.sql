-- Okuma Denetim Raporu modulu icin menu ve yetki kayitlari
-- Calistirmadan once id 969'un menus ve permissions tablolarinda bos oldugu dogrulanmali.
-- Kontrol:
--   SELECT id FROM menus WHERE id = 969;
--   SELECT id FROM permissions WHERE id = 969;

SET @menu_id = 969;
SET @menu_link = 'puantaj/okuma-denetim';

INSERT INTO `permissions`
    (`id`, `name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
VALUES
    (@menu_id, 'Okuma Denetimi', 'okuma_denetim_raporu',
     'Endeks okuma denetim ekranı (API gün bazlı + Excel saat bazlı) erişim yetkisi',
     'İş Takip', 0, 1, 0, 0)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `auth_name` = VALUES(`auth_name`),
    `description` = VALUES(`description`),
    `group_name` = VALUES(`group_name`),
    `is_active` = VALUES(`is_active`);

INSERT INTO `menus`
    (`id`, `menu_name`, `parent_id`, `group_name`, `group_order`, `menu_link`, `menu_icon`, `menu_order`, `is_active`, `is_menu`, `is_authorized`, `created_at`, `created_by`)
VALUES
    (@menu_id, 'Okuma Denetimi', 5, 'İş Takip Yönetim', 5, @menu_link, 'shield-check', 5, 1, 1, 1, NOW(), 0)
ON DUPLICATE KEY UPDATE
    `menu_name` = VALUES(`menu_name`),
    `parent_id` = VALUES(`parent_id`),
    `group_name` = VALUES(`group_name`),
    `menu_link` = VALUES(`menu_link`),
    `menu_icon` = VALUES(`menu_icon`),
    `menu_order` = VALUES(`menu_order`),
    `is_active` = VALUES(`is_active`);

-- Defter Bazli Rapor (permission_id = 67) yetkisi olan tum rollere ayni yetkiyi ver
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_at`, `created_by`)
SELECT DISTINCT urp.`role_id`, @menu_id, NOW(), 0
FROM `user_role_permissions` urp
WHERE urp.`permission_id` = 67
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` x
      WHERE x.`role_id` = urp.`role_id` AND x.`permission_id` = @menu_id
  );

-- Dogrulama:
--   SELECT * FROM menus WHERE id = 969;
--   SELECT * FROM permissions WHERE id = 969;
--   SELECT role_id FROM user_role_permissions WHERE permission_id = 969;
