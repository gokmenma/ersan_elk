CREATE TABLE IF NOT EXISTS `maliyet_kalem_eslestirme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `kaynak` varchar(20) NOT NULL,
  `eslesme_degeri` varchar(255) NOT NULL,
  `eslesme_etiketi` varchar(255) DEFAULT NULL,
  `kalem` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mke_firma_kaynak` (`firma_id`, `kaynak`, `eslesme_degeri`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `maliyet_fatura_kalem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `kalem` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mfk_fatura` (`firma_id`, `fatura_id`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO maliyet_kalem_eslestirme (firma_id, kaynak, eslesme_degeri, eslesme_etiketi, kalem)
SELECT s.firm_id, 'fatura', s.vkn, s.unvan, s.kalem
FROM (
    SELECT f.firm_id, f.alici_vkn_tckn AS vkn, MAX(f.alici_unvan) AS unvan,
           CASE
               WHEN MAX(f.alici_unvan) LIKE '%GİRAY F%' OR MAX(f.alici_unvan) LIKE '%GÜLKAR F%' THEN 'arac_kirasi'
               WHEN MAX(f.alici_unvan) LIKE '%HALİS PETROL%' THEN 'yakit'
               WHEN MAX(f.alici_unvan) LIKE 'Turkcell%' THEN 'telefon_internet'
           END AS kalem
    FROM faturalar f
    WHERE f.yon = 'GELEN'
      AND f.deleted_at IS NULL
      AND COALESCE(f.alici_vkn_tckn, '') <> ''
      AND (f.alici_unvan LIKE '%GİRAY F%' OR f.alici_unvan LIKE '%GÜLKAR F%'
           OR f.alici_unvan LIKE '%HALİS PETROL%' OR f.alici_unvan LIKE 'Turkcell%')
    GROUP BY f.firm_id, f.alici_vkn_tckn
) s
WHERE s.kalem IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM maliyet_kalem_eslestirme e
      WHERE e.firma_id = s.firm_id AND e.kaynak = 'fatura'
        AND e.eslesme_degeri = s.vkn AND e.deleted_at IS NULL
  );

INSERT INTO maliyet_kalem_eslestirme (firma_id, kaynak, eslesme_degeri, eslesme_etiketi, kalem)
SELECT fr.id, 'gelir_gider', d.kategori, d.kategori, d.kalem
FROM firmalar fr
CROSS JOIN (
    SELECT 'AKARYAKIT' AS kategori, 'yakit' AS kalem
    UNION ALL SELECT 'AD BLUE', 'yakit'
    UNION ALL SELECT 'BENZIN VE YAKIT ISTASYONLARI', 'yakit'
    UNION ALL SELECT 'İLETİŞİM', 'telefon_internet'
    UNION ALL SELECT 'HAT BEDELİ', 'telefon_internet'
    UNION ALL SELECT 'ARAÇ BAKIM', 'arac_bakim'
    UNION ALL SELECT 'ARAÇ BAKIMI', 'arac_bakim'
    UNION ALL SELECT 'YAĞ BAKIMI', 'arac_bakim'
    UNION ALL SELECT 'LASTİK', 'arac_bakim'
    UNION ALL SELECT 'OTO YIKAMA', 'arac_bakim'
    UNION ALL SELECT 'AVANS', 'haric'
    UNION ALL SELECT 'PERSONEL AVANS', 'haric'
    UNION ALL SELECT 'MAAŞ', 'haric'
    UNION ALL SELECT 'MAAŞ KALANI', 'haric'
    UNION ALL SELECT 'iCRA', 'haric'
    UNION ALL SELECT 'KREDİ KARTI ÖDEME', 'haric'
) d
WHERE NOT EXISTS (
    SELECT 1 FROM maliyet_kalem_eslestirme e
    WHERE e.firma_id = fr.id AND e.kaynak = 'gelir_gider'
      AND e.eslesme_degeri = d.kategori AND e.deleted_at IS NULL
);

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
SELECT 'Maliyet Kalem Eşleştirme', 'Maliyet raporunda fatura firmalarını ve gelir-gider kategorilerini maliyet kalemlerine bağlama, faturaya kalem atama', 'maliyet_raporu_eslestirme', 'maliyet_raporu_eslestirme', 'Finansal İşlemler', 1, 0, 1, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_key = 'maliyet_raporu_eslestirme');

UPDATE permissions SET is_active = 1 WHERE permission_key = 'maliyet_raporu_eslestirme';

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, p.id, NULL
FROM user_roles roles
INNER JOIN permissions p ON p.permission_key = 'maliyet_raporu_eslestirme' AND p.is_active = 1
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = p.id
  );

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'maliyet-raporu/api', d.action, '*', d.access_mode, p.id, 0, 0, 1
FROM (
    SELECT 'maliyet-ajax-list' AS action, 'read' AS access_mode
    UNION ALL SELECT 'eslesmemis-faturalar', 'read'
) d
INNER JOIN permissions p ON p.permission_key = 'maliyet_raporu' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'maliyet-raporu/api', d.action, '*', d.access_mode, p.id, 0, 0, 1
FROM (
    SELECT 'eslestirme-listesi' AS action, 'read' AS access_mode
    UNION ALL SELECT 'eslestirme-kaydet', 'write'
    UNION ALL SELECT 'eslestirme-sil', 'write'
    UNION ALL SELECT 'fatura-kalem-ata', 'write'
) d
INNER JOIN permissions p ON p.permission_key = 'maliyet_raporu_eslestirme' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode), permission_id = VALUES(permission_id),
    superadmin_only = 0, authenticated_only = 0, is_active = 1;
