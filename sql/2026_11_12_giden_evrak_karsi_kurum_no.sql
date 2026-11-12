-- Giden evrak: karşı kurumun kendi kayıtlarına aldıktan sonra verdiği evrak numarası ve açıklaması.
-- İmzalı/donmuş evrak içeriğine dokunulmaz; bu bilgi ayrı sütunlarda tutulur, değişiklikler evrak_tarihce'ye yazılır.
-- Sabit ID kullanılmaz; bağlantılar permission_key üzerinden çözülür. Tekrar çalıştırılabilir.

ALTER TABLE `evrak_takip`
    ADD COLUMN IF NOT EXISTS `karsi_evrak_no` VARCHAR(100) NULL DEFAULT NULL AFTER `evrak_no`,
    ADD COLUMN IF NOT EXISTS `karsi_evrak_aciklama` VARCHAR(500) NULL DEFAULT NULL AFTER `karsi_evrak_no`,
    ADD COLUMN IF NOT EXISTS `karsi_evrak_guncelleyen_id` INT NULL DEFAULT NULL AFTER `karsi_evrak_aciklama`,
    ADD COLUMN IF NOT EXISTS `karsi_evrak_guncelleme_tarihi` DATETIME NULL DEFAULT NULL AFTER `karsi_evrak_guncelleyen_id`;

START TRANSACTION;

INSERT INTO `permissions`
    (`name`, `description`, `auth_name`, `permission_key`, `group_name`, `permission_level`, `is_required`, `is_active`, `superadmin`)
VALUES
    ('Karşı Kurum Evrak No Girişi', 'Giden evrak için karşı kurumun verdiği evrak numarasını ve açıklamasını girme/düzeltme yetkisi', 'evrak-takip/karsi-evrak-no', 'evrak-takip/karsi-evrak-no', 'Evrak Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `auth_name` = VALUES(`auth_name`),
    `group_name` = VALUES(`group_name`),
    `permission_level` = VALUES(`permission_level`),
    `is_active` = 1,
    `superadmin` = 0;

INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_by`)
SELECT roles.`id`, perm.`id`, NULL
FROM `user_roles` roles
JOIN `permissions` perm ON perm.`permission_key` = 'evrak-takip/karsi-evrak-no'
WHERE (roles.`role_type` = 'superadmin' OR roles.`superadmin` = 1)
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` existing_grant
      WHERE existing_grant.`role_id` = roles.`id` AND existing_grant.`permission_id` = perm.`id`
  );

SET @karsi_no_perm := (SELECT `id` FROM `permissions` WHERE `permission_key` = 'evrak-takip/karsi-evrak-no' LIMIT 1);

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', d.`action`, 'POST', d.`access_mode`, @karsi_no_perm, 0, 0, 1, NOW(), NOW()
FROM (
    SELECT 'evrak-karsi-no-getir' AS `action`, 'read' AS `access_mode`
    UNION ALL SELECT 'evrak-karsi-no-kaydet', 'write'
) d
WHERE @karsi_no_perm IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `permission_policies` p
      WHERE p.`scope` = 'api' AND p.`resource` = 'evrak-takip/api' AND p.`action` = d.`action`
        AND p.`http_method` IN ('POST', '*')
  );

COMMIT;
