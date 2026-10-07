-- Gelir-Gider Excel Yükleme Yetki Politikası
INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'gelir-gider/api', 'gelir-gider-excel-kaydet', 'POST', p.id, 0, 0, 1, NOW(), NOW()
FROM `permissions` p
WHERE p.auth_name = 'gelir_gider_takibi'
  AND NOT EXISTS (
      SELECT 1 FROM `permission_policies` pp 
      WHERE pp.resource = 'gelir-gider/api' AND pp.action = 'gelir-gider-excel-kaydet'
  );
