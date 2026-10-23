-- Turkcell Mobil İmza (MSSP) ile giden evrak PAdES imzası.
-- Tekrar çalıştırılabilir; sabit kayıt ID'si kullanılmaz, yetkiler permission_key üzerinden çözülür.

SET @has_mobil_imza_msisdn := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mobil_imza_msisdn'
);
SET @sql := IF(@has_mobil_imza_msisdn = 0,
    'ALTER TABLE users ADD COLUMN mobil_imza_msisdn VARCHAR(255) NULL DEFAULT NULL COMMENT ''Security::encrypt ile şifreli mobil imza hattı'' AFTER evrak_imza_yetkisi',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_imza_yontemi := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip_onaylari' AND COLUMN_NAME = 'imza_yontemi'
);
SET @sql := IF(@has_imza_yontemi = 0,
    'ALTER TABLE evrak_takip_onaylari ADD COLUMN imza_yontemi VARCHAR(20) NULL DEFAULT NULL COMMENT ''akilli_kart veya mobil_imza'' AFTER imza_turu',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS mobil_imza_islemleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    evrak_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    token CHAR(48) NOT NULL,
    msisdn_maskeli VARCHAR(20) NOT NULL,
    mssp_trans_id VARCHAR(64) NULL DEFAULT NULL,
    kaynak_pdf_sha256 CHAR(64) NOT NULL,
    hazir_pdf_yolu VARCHAR(255) NOT NULL,
    byte_range VARCHAR(100) NOT NULL,
    imzali_oznitelikler TEXT NOT NULL,
    sertifika_ozeti CHAR(64) NOT NULL,
    durum ENUM('bekliyor','imzalandi','hata','iptal','zaman_asimi') NOT NULL DEFAULT 'bekliyor',
    hata_mesaji VARCHAR(500) NULL DEFAULT NULL,
    son_gecerlilik DATETIME NOT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mobil_imza_token (token),
    KEY idx_mobil_imza_evrak (firma_id, evrak_id, kullanici_id, durum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Mobil İmza Ayarları Sekmesi',
     'Turkcell mobil imza hizmetini açma/kapama, imzacı hatlarını tanımlama ve profil sorgulama',
     'mobil_imza_ayarlari_sekmesi', 'mobil_imza_ayarlari_sekmesi', 'Ayarlar', 0, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    is_active = 1;

SET @mobil_imza_permission_id := (
    SELECT id FROM permissions WHERE permission_key = 'mobil_imza_ayarlari_sekmesi' AND is_active = 1 LIMIT 1
);

INSERT INTO user_role_permissions (role_id, permission_id)
SELECT roles.id, @mobil_imza_permission_id
FROM user_roles roles
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND @mobil_imza_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = @mobil_imza_permission_id
  );

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT 'api', 'ayarlar/api', actions.action, 'POST', 'write', @mobil_imza_permission_id, 0, 0, 1, NOW(), NOW()
FROM (
    SELECT 'save_mobil_imza_ayarlari' AS action
    UNION ALL SELECT 'test_mobil_imza_profil'
) actions
WHERE @mobil_imza_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies pp
      WHERE pp.scope = 'api' AND pp.resource = 'ayarlar/api'
        AND pp.action = actions.action AND pp.http_method IN ('POST', '*')
  );

SET @evrak_permission_id := (
    SELECT id FROM permissions WHERE permission_key = 'evrak-takip/list' AND is_active = 1 LIMIT 1
);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT 'api', 'evrak-takip/api', actions.action, 'POST', 'write', @evrak_permission_id, 0, 0, 1, NOW(), NOW()
FROM (
    SELECT 'evrak-mobil-imza-baslat' AS action
    UNION ALL SELECT 'evrak-mobil-imza-durum'
    UNION ALL SELECT 'evrak-mobil-imza-iptal'
) actions
WHERE @evrak_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies pp
      WHERE pp.scope = 'api' AND pp.resource = 'evrak-takip/api'
        AND pp.action = actions.action AND pp.http_method IN ('POST', '*')
  );
