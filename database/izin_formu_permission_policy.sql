-- İzin Talep Formu Önizleme ve Word İndirme API Politikaları
INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
VALUES 
('api', 'talepler/api', 'izin-formu-onizle', '*', 'read', 926, 0, 0, 1, NOW(), NOW()),
('api', 'talepler/api', 'izin-formu-docx-indir', '*', 'read', 926, 0, 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `is_active` = 1, `updated_at` = NOW();
