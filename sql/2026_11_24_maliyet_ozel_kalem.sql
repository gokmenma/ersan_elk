CREATE TABLE IF NOT EXISTS `maliyet_ozel_kalem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `ad` varchar(80) NOT NULL,
  `renk` varchar(7) NOT NULL DEFAULT '#64748b',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mok_firma` (`firma_id`, `deleted_at`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'maliyet-raporu/api', 'kalem-ekle', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'maliyet_raporu_eslestirme' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
