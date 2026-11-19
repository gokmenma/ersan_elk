-- Giden Evrak Varsayılan İmza Yetkilileri Kaydetme API Yetki Politikası
-- evrak-kaydet yetkisi ile aynı permission_id üzerinden bağlanır (sabit ID kullanılmaz).

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'evrak-varsayilan-imza-kaydet', 'POST', 'write', src.`permission_id`,
       src.`superadmin_only`, src.`authenticated_only`, 1, NOW(), NOW()
FROM `permission_policies` src
WHERE src.`resource` = 'evrak-takip/api'
  AND src.`action` = 'evrak-kaydet'
  AND src.`is_active` = 1
  AND NOT EXISTS (
      SELECT 1 FROM `permission_policies`
      WHERE `resource` = 'evrak-takip/api' AND `action` = 'evrak-varsayilan-imza-kaydet'
  )
LIMIT 1;
