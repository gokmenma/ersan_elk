-- Cari Banka Ekstresi Yükleme ve İçe Aktarma API Politikaları
SET @perm_id = (SELECT `id` FROM `permissions` WHERE `permission_key` = 'cari_takibi' LIMIT 1);

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
VALUES 
('api', 'cari/api', 'banka-ekstre-analiz', 'POST', 'read', @perm_id, 0, 0, 1, NOW(), NOW()),
('api', 'cari/api', 'banka_ekstre_analiz', 'POST', 'read', @perm_id, 0, 0, 1, NOW(), NOW()),
('api', 'cari/api', 'banka-ekstre-aktar', 'POST', 'write', @perm_id, 0, 0, 1, NOW(), NOW()),
('api', 'cari/api', 'banka_ekstre_aktar', 'POST', 'write', @perm_id, 0, 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW(), `is_active` = 1, `permission_id` = @perm_id;
