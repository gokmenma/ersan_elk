-- Tüm Cari Hesap Hareketleri Sayfası ve API Yetki Politikaları
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
VALUES 
('page', 'cari/tum-hareketler', '', 'GET', 'read', 919, 0, 0, 1, NOW(), NOW()),
('api', 'cari/api', 'tum-hareketler-ajax-list', 'POST', 'read', 919, 0, 0, 1, NOW(), NOW()),
('api', 'cari/api', 'tum_hareketler_ajax_list', 'POST', 'read', 919, 0, 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW();

-- Menü Tanımı
INSERT INTO menus (id, menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
VALUES (920, 'Tüm Hesap Hareketleri', 'Tüm cariler genelindeki hesap hareketleri ve bakiye dökümü', 0, 'Finans', 1, 'cari/tum-hareketler', 919, 'bx bx-history', 43, 1, 0, 1, NOW())
ON DUPLICATE KEY UPDATE menu_name = 'Tüm Hesap Hareketleri', menu_link = 'cari/tum-hareketler', is_active = 1;
