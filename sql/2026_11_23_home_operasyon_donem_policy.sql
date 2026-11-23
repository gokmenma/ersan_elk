INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`)
SELECT 'api', 'home/api', 'get-operasyon-donem', '*', 'read', NULL, 0, 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_policies`
    WHERE `scope` = 'api' AND `resource` = 'home/api' AND `action` = 'get-operasyon-donem'
);
