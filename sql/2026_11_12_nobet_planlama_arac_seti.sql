-- Nöbet Planlama araç seti: işlem geçmişi (geri al) tablosu ve yeni API aksiyon politikaları.
-- Yeni yetki oluşturulmaz; tüm yeni aksiyonlar mevcut 'nobet/list' yetkisine bağlanır.

CREATE TABLE IF NOT EXISTS `nobet_islem_gecmisi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `grup_kodu` varchar(40) DEFAULT NULL,
  `islem` varchar(30) NOT NULL,
  `nobet_id` int(11) DEFAULT NULL,
  `onceki_veri` longtext DEFAULT NULL,
  `yeni_veri` longtext DEFAULT NULL,
  `aciklama` varchar(255) DEFAULT NULL,
  `geri_alindi` tinyint(1) NOT NULL DEFAULT 0,
  `geri_alma_tarihi` datetime DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_firma_tarih` (`firma_id`, `olusturma_tarihi`),
  KEY `idx_grup` (`grup_kodu`),
  KEY `idx_nobet` (`nobet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'nobet/api', policies.action, '*', policies.access_mode, p.id, 0, 0, 1
FROM (
    SELECT 'get-nobet-gecmisi' AS action, 'read' AS access_mode
    UNION ALL SELECT 'geri-al-nobet', 'write'
    UNION ALL SELECT 'toplu-nobet-ekle', 'write'
    UNION ALL SELECT 'nobet-kopyala', 'write'
    UNION ALL SELECT 'dagitim-oneri', 'read'
    UNION ALL SELECT 'dagitim-kaydet', 'write'
    UNION ALL SELECT 'get-bordro-ozeti', 'read'
    UNION ALL SELECT 'get-adalet-raporu', 'read'
    UNION ALL SELECT 'send-yarin-hatirlatma', 'write'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND p.permission_key = 'nobet/list'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = VALUES(access_mode),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
