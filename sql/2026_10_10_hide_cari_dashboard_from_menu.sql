-- Cari Dashboard Menüden Gizleme (is_menu = 0)
UPDATE `menus` 
SET `is_menu` = 0, `updated_at` = NOW() 
WHERE `menu_link` = 'cari/dashboard';
