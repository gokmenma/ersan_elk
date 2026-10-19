CREATE TABLE IF NOT EXISTS `kep_adres_defteri` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firm_id` INT(11) NOT NULL,
  `kep_adresi` VARCHAR(255) NOT NULL,
  `son_dogrulama_tarihi` DATETIME NOT NULL,
  `son_kullanim_tarihi` DATETIME NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kep_adres_defteri_firma_adres` (`firm_id`, `kep_adresi`),
  KEY `idx_kep_adres_defteri_aktif` (`firm_id`, `is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @perm_kep_id := (SELECT `id` FROM `permissions` WHERE `permission_key` = 'evrak-takip/kep' LIMIT 1);

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'kep-adres-defteri-listele', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE @perm_kep_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'kep-adres-defteri-listele');

INSERT INTO `permission_policies` (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'evrak-takip/api', 'eimza-kart-bilgisi', '*', 'read', @perm_kep_id, 0, 0, 1, NOW(), NOW()
WHERE @perm_kep_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `permission_policies` WHERE `resource` = 'evrak-takip/api' AND `action` = 'eimza-kart-bilgisi');

