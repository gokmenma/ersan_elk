-- İK İşe Alım: mülakat kayıtları, mülakatta düzeltilen aday bilgileri ve yetki/politika kayıtları.
-- Sabit ID kullanılmaz; bağlantılar permission_key üzerinden çözülür. Tekrar çalıştırılabilir.

CREATE TABLE IF NOT EXISTS ik_basvuru_mulakatlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    basvuru_id INT UNSIGNED NOT NULL,
    mulakat_tarihi DATETIME NOT NULL,
    tur ENUM('telefon','yuz_yuze','online') NOT NULL DEFAULT 'yuz_yuze',
    mulakat_yapan_id INT NULL,
    sonuc ENUM('olumlu','olumsuz','beklemede') NOT NULL DEFAULT 'beklemede',
    genel_puan TINYINT UNSIGNED NULL,
    genel_not TEXT NULL,
    sorular_cevaplar MEDIUMTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ik_mulakat_basvuru (basvuru_id, is_active, deleted_at),
    KEY idx_ik_mulakat_firma (firma_id, mulakat_tarihi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS ik_basvuru_bilgi_guncellemeleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    basvuru_id INT UNSIGNED NOT NULL,
    mulakat_id INT UNSIGNED NULL,
    alan VARCHAR(40) NOT NULL,
    eski_deger TEXT NULL,
    yeni_deger TEXT NULL,
    kullanici_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ik_bilgi_basvuru (basvuru_id, alan, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('İşe Alım Mülakat Kaydı', 'Aday mülakatı kaydetme, soru cevaplarını girme ve mülakatta düzeltilen aday bilgilerini işleme yetkisi', 'ik-ise-alim/mulakat', 'ik_basvuru_mulakat', 'İşe Alım', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'ik_basvuru_mulakat'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @ik_mulakat_perm = (SELECT id FROM permissions WHERE permission_key = 'ik_basvuru_mulakat' LIMIT 1);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'ik-ise-alim/api', d.action, '*', 'write', @ik_mulakat_perm, 0, 0, 1
FROM (
    SELECT 'mulakat-kaydet' AS action
    UNION ALL SELECT 'mulakat-sil'
) d
WHERE @ik_mulakat_perm IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = 'api' AND p.resource = 'ik-ise-alim/api' AND p.action = d.action
        AND p.http_method = '*' AND p.permission_id = @ik_mulakat_perm
  );

COMMIT;
