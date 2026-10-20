-- Dashboard Görev & Evrak Takvimi dinamik ay sorgusu policy kaydı
INSERT INTO `permission_policies` 
(`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`)
SELECT 'api', 'home/api', 'get-calendar-alarms', '*', 'read', NULL, 0, 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_policies` 
    WHERE `resource` = 'home/api' AND `action` = 'get-calendar-alarms'
);
