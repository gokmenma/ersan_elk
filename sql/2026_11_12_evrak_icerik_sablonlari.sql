CREATE TABLE IF NOT EXISTS `evrak_icerik_sablonlari` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` int NOT NULL,
  `adi` varchar(150) NOT NULL,
  `icerik` longtext NOT NULL,
  `olusturan_kullanici_id` int DEFAULT NULL,
  `guncelleyen_kullanici_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_evrak_icerik_sablon_firma` (`firma_id`,`is_active`,`deleted_at`,`adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'evrak-takip/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'evrak-icerik-sablon-listele' AS action
    UNION ALL SELECT 'evrak-icerik-sablon-kaydet'
    UNION ALL SELECT 'evrak-icerik-sablon-sil'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'evrak-takip/list' OR p.auth_name = 'evrak-takip/list')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

UPDATE permission_policies
SET access_mode = 'read'
WHERE scope = 'api' AND resource = 'evrak-takip/api' AND action = 'evrak-icerik-sablon-listele';

UPDATE permission_policies
SET access_mode = 'write'
WHERE scope = 'api' AND resource = 'evrak-takip/api'
  AND action IN ('evrak-icerik-sablon-kaydet', 'evrak-icerik-sablon-sil');
