INSERT INTO `permissions` (`name`, `auth_name`, `permission_key`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT 'UETS Evraklarını Görüntüle ve Sevk Et', 'uets.evrak.goruntule', 'evrak-takip/uets-goruntule',
       'UETS ile gelen tebligat evraklarını görme, sevk etme ve sevk/erişim tarihçesini izleme', 'Evrak Yönetimi', 1, 1, 0, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permission_key` = 'evrak-takip/uets-goruntule'
);

INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_at`, `created_by`)
SELECT ur.`id`, p.`id`, NOW(), 1
FROM `user_roles` ur
CROSS JOIN `permissions` p
WHERE (ur.`role_type` = 'superadmin' OR ur.`superadmin` = 1)
  AND p.`permission_key` = 'evrak-takip/uets-goruntule'
  AND p.`is_active` = 1
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp
      WHERE urp.`role_id` = ur.`id` AND urp.`permission_id` = p.`id`
  );

CREATE TABLE IF NOT EXISTS `evrak_sevk` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firma_id` INT(11) NOT NULL,
  `evrak_id` INT(11) NOT NULL,
  `sevk_eden_user_id` INT(11) NOT NULL,
  `alan_user_id` INT(11) NOT NULL,
  `sevk_notu` VARCHAR(500) NULL DEFAULT NULL,
  `durum` ENUM('bekliyor','goruldu','iletildi') NOT NULL DEFAULT 'bekliyor',
  `goruldu_at` DATETIME NULL DEFAULT NULL,
  `iletildi_at` DATETIME NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_evrak_sevk_evrak` (`firma_id`, `evrak_id`, `is_active`),
  KEY `idx_evrak_sevk_alan` (`firma_id`, `alan_user_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evrak_tarihce` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `firma_id` INT(11) NOT NULL,
  `evrak_id` INT(11) NOT NULL,
  `islem` VARCHAR(40) NOT NULL,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `hedef_user_id` INT(11) NULL DEFAULT NULL,
  `aciklama` VARCHAR(500) NULL DEFAULT NULL,
  `ip_adresi` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evrak_tarihce_evrak` (`firma_id`, `evrak_id`, `created_at`),
  KEY `idx_evrak_tarihce_user` (`firma_id`, `user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @perm_uets_gor_id := (
    SELECT `id` FROM `permissions`
    WHERE `permission_key` = 'evrak-takip/uets-goruntule' AND `is_active` = 1 LIMIT 1
);

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-sevk-et', 'POST', 'write', NULL, 0, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_policies`
    WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-sevk-et'
);

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-sevk-personel-listesi', 'POST', 'read', NULL, 0, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_policies`
    WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-sevk-personel-listesi'
);

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'evrak-tarihce', 'POST', 'read', @perm_uets_gor_id, 0, 0, 1, NOW(), NOW()
WHERE @perm_uets_gor_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `permission_policies`
    WHERE `resource` = 'evrak-takip/api' AND `action` = 'evrak-tarihce'
);

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`,
     `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'uets-sevk-gecmisi', 'POST', 'read', NULL, 0, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_policies`
    WHERE `resource` = 'evrak-takip/api' AND `action` = 'uets-sevk-gecmisi'
);
