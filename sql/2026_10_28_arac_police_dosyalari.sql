CREATE TABLE IF NOT EXISTS arac_police_dosyalari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    arac_id INT NOT NULL,
    police_turu ENUM('sigorta','kasko') NOT NULL,
    sirket_adi VARCHAR(150) NULL,
    police_no VARCHAR(100) NULL,
    baslangic_tarihi DATE NULL,
    bitis_tarihi DATE NULL,
    prim_tutari DECIMAL(12,2) NULL,
    dosya_adi VARCHAR(100) NULL,
    orijinal_ad VARCHAR(255) NULL,
    mime_tipi VARCHAR(100) NULL,
    boyutu INT UNSIGNED NULL,
    yukleyen_id INT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    silinme_tarihi DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_police_arac (firma_id, arac_id, police_turu, silinme_tarihi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_policies (
    scope, resource, action, http_method, access_mode,
    permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at
)
SELECT 'api', 'arac-takip/api', a.action, '*', a.access_mode,
       p.id, 0, 0, 1, NOW(), NOW()
FROM permissions p
INNER JOIN (
    SELECT 'arac-police-listele' AS action, 'read' AS access_mode
    UNION ALL SELECT 'arac-police-kaydet', 'write'
    UNION ALL SELECT 'arac-police-sil', 'write'
) a
WHERE p.permission_key = 'arac_takip_yonetim'
AND NOT EXISTS (
    SELECT 1 FROM permission_policies pp
    WHERE pp.scope = 'api'
    AND pp.resource = 'arac-takip/api'
    AND pp.action = a.action
);
