INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`)
SELECT 'api', 'home/api', 'get-saha-arac-detay', '*', 'read', p.id, 0, 0, 1
FROM `permissions` p
WHERE p.is_active = 1
  AND (p.permission_key = 'arac_takip_yonetim' OR p.auth_name = 'arac_takip_yonetim')
  AND NOT EXISTS (
    SELECT 1 FROM `permission_policies`
    WHERE `scope` = 'api' AND `resource` = 'home/api' AND `action` = 'get-saha-arac-detay'
  )
LIMIT 1;
