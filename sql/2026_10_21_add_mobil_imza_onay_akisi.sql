ALTER TABLE `evrak_takip`
    MODIFY COLUMN `onay_turu` ENUM('eimza','islak_imza','mobil_imza') NOT NULL DEFAULT 'eimza';

SET @evrak_permission_id := (
    SELECT `id` FROM `permissions`
    WHERE `permission_key` = 'evrak-takip/list' AND `is_active` = 1
    LIMIT 1
);

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'evrak-mobil-imza-onaya-sun', 'POST', 'write', @evrak_permission_id,
       0, 0, 1, NOW(), NOW()
WHERE @evrak_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `permission_policies`
      WHERE `scope` = 'api' AND `resource` = 'evrak-takip/api'
        AND `action` = 'evrak-mobil-imza-onaya-sun' AND `http_method` IN ('POST', '*')
  );
