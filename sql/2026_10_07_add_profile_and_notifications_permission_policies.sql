-- Profil sayfası ve bildirim listesi için yetki politikaları
-- Bu sayfalar oturum açmış tüm kullanıcılar için açıktır (authenticated_only = 1).

INSERT INTO `permission_policies` 
(`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
VALUES
('page', 'profil/index', '', 'GET', 'read', NULL, 0, 1, 1, NOW(), NOW()),
('page', 'profil', '', 'GET', 'read', NULL, 0, 1, 1, NOW(), NOW()),
('page', 'bildirim/list', '', 'GET', 'read', NULL, 0, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
`permission_id` = NULL, 
`superadmin_only` = 0, 
`authenticated_only` = 1, 
`access_mode` = 'read', 
`is_active` = 1,
`updated_at` = NOW();

-- Bildirim listesi DataTable verisi oturum sahibinin kendi bildirimlerini listeler
UPDATE `permission_policies`
SET `permission_id` = NULL,
    `superadmin_only` = 0,
    `authenticated_only` = 1,
    `access_mode` = 'read',
    `is_active` = 1,
    `updated_at` = NOW()
WHERE `scope` = 'api' AND `resource` = 'bildirim/api' AND `action` = 'datatable-list';
