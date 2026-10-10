-- Son hesap hareketleri API aksiyonu yetki politikası
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
VALUES 
('api', 'cari/api', 'son-hareketler-getir', 'POST', 'read', 918, 0, 0, 1, NOW(), NOW()),
('api', 'cari/api', 'son_hareketler_getir', 'POST', 'read', 918, 0, 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW();
