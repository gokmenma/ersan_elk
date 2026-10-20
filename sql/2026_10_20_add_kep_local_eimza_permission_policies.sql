-- Yerel e-imza ile KEP gonderim akisi icin eksik API politikalarini ekler.
-- Mevcut KEP yetkisini kullanir; yeni bir yetki olusturmaz.

SET @perm_kep_id := (
    SELECT `id`
    FROM `permissions`
    WHERE `permission_key` = 'evrak-takip/kep'
      AND `is_active` = 1
    LIMIT 1
);

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT
    'api', 'evrak-takip/api', 'kep-mime-hazirla', 'POST', 'write', @perm_kep_id,
    0, 0, 1, NOW(), NOW()
WHERE @perm_kep_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `permission_policies`
      WHERE `scope` = 'api'
        AND `resource` = 'evrak-takip/api'
        AND `action` = 'kep-mime-hazirla'
        AND `http_method` IN ('POST', '*')
  );

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT
    'api', 'evrak-takip/api', 'kep-smime-sevk', 'POST', 'write', @perm_kep_id,
    0, 0, 1, NOW(), NOW()
WHERE @perm_kep_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `permission_policies`
      WHERE `scope` = 'api'
        AND `resource` = 'evrak-takip/api'
        AND `action` = 'kep-smime-sevk'
        AND `http_method` IN ('POST', '*')
  );

