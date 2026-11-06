CREATE TABLE IF NOT EXISTS personel_karsilama_sablonlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    mesaj_metni TEXT NOT NULL,
    sayfa_baslik VARCHAR(255) NOT NULL,
    evraklar TEXT NOT NULL,
    banka_adimi TEXT NULL,
    kase_bilgisi TEXT NULL,
    guncelleyen_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_karsilama_sablon_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS personel_karsilama_mesajlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    personel_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_sifreli TEXT NOT NULL,
    telefon VARCHAR(20) NULL,
    durum ENUM('hazir','gonderildi','atlandi') NOT NULL DEFAULT 'hazir',
    gonderim_tarihi DATETIME NULL,
    gecerlilik_tarihi DATETIME NOT NULL,
    acilma_sayisi INT UNSIGNED NOT NULL DEFAULT 0,
    ilk_acilma_tarihi DATETIME NULL,
    son_acilma_tarihi DATETIME NULL,
    olusturan_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_karsilama_token (token_hash),
    KEY idx_karsilama_personel (firma_id, personel_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Personel Karşılama Mesajı Gönderme', 'Yeni personele WhatsApp karşılama/işe alım bilgilendirme mesajı hazırlama ve gönderildi olarak işaretleme yetkisi', 'personel_karsilama_mesaji', 'personel_karsilama_mesaji', 'Personel Yönetim', 0, 0, 1, 0),
    ('Personel Karşılama Şablonu Düzenleme', 'Karşılama mesajı metni, evrak listesi ve kaşe bilgisi şablonunu düzenleme yetkisi', 'personel_karsilama_sablon', 'personel_karsilama_sablon', 'Personel Yönetim', 0, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT 'api', 'personel/api', a.action, '*', a.access_mode, p.id, 0, 0, 1, NOW(), NOW()
FROM (
    SELECT 'karsilama-mesaji-hazirla' AS action, 'write' AS access_mode, 'personel_karsilama_mesaji' AS permission_key
    UNION ALL SELECT 'karsilama-mesaji-durum', 'write', 'personel_karsilama_mesaji'
    UNION ALL SELECT 'karsilama-sablon-getir', 'read', 'personel_karsilama_sablon'
    UNION ALL SELECT 'karsilama-sablon-kaydet', 'write', 'personel_karsilama_sablon'
) a
INNER JOIN permissions p ON p.permission_key = a.permission_key AND p.is_active = 1
WHERE NOT EXISTS (
    SELECT 1 FROM permission_policies pp
    WHERE pp.scope = 'api' AND pp.resource = 'personel/api' AND pp.action = a.action AND pp.http_method = '*'
);

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, p.id, NULL
FROM user_roles roles
INNER JOIN permissions p ON p.permission_key IN ('personel_karsilama_mesaji', 'personel_karsilama_sablon') AND p.is_active = 1
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = p.id
  );
