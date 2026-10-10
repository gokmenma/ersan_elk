-- Cari Dashboard Sayfası ve API Aksiyonları Yetki Politikaları ve Menü Tanımı
INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
VALUES 
('page', 'cari/dashboard', '', 'GET', 'read', 918, 0, 0, 1, NOW(), NOW()),
('api', 'cari/api', 'dashboard-data', 'POST', 'read', 918, 0, 0, 1, NOW(), NOW()),
('api', 'cari/api', 'dashboard_data', 'POST', 'read', 918, 0, 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW(), `is_active` = 1, `permission_id` = 918;

-- Cari Dashboard Menü Tanımı (Sol Menüde Görünmez, is_menu = 0)
INSERT INTO `menus` (`menu_name`, `page_description`, `parent_id`, `group_name`, `group_order`, `menu_link`, `permission_id`, `menu_icon`, `menu_order`, `is_active`, `is_menu`, `is_authorized`, `created_at`, `created_by`)
SELECT 'Cari Dashboard', 'Cari hesap analiz, grafik ve finansal durum gösterge paneli', 0, 'Finans', 3, 'cari/dashboard', 918, 'pie-chart', 40, 1, 0, 1, NOW(), 1
WHERE NOT EXISTS (
    SELECT 1 FROM `menus` WHERE `menu_link` = 'cari/dashboard' AND `deleted_at` IS NULL
);
