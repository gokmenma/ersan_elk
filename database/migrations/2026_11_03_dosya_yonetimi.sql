-- Dosya Yönetimi modülü: tablolar, yetkiler, menü ve API politikaları.
-- Sabit kayıt ID'leri kullanılmaz; bütün bağlantılar permission_key üzerinden çözülür.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS dosya_klasorleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    parent_id INT UNSIGNED NULL DEFAULT NULL,
    ad VARCHAR(190) NOT NULL,
    olusturan_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dk_firma_parent (firma_id, parent_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dosya_klasor_rolleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    klasor_id INT UNSIGNED NOT NULL,
    role_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dkr_klasor_rol (klasor_id, role_id),
    KEY idx_dkr_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dosyalar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    klasor_id INT UNSIGNED NULL DEFAULT NULL,
    dosya_adi VARCHAR(190) NOT NULL,
    orijinal_ad VARCHAR(255) NOT NULL,
    uzanti VARCHAR(10) NOT NULL,
    mime_tipi VARCHAR(150) NOT NULL,
    boyut BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sha256 CHAR(64) NOT NULL,
    aciklama VARCHAR(500) NULL,
    etiketler VARCHAR(255) NULL,
    yukleyen_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_d_firma_klasor (firma_id, klasor_id, is_active),
    KEY idx_d_firma_ad (firma_id, orijinal_ad),
    KEY idx_d_created (firma_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dosya_paylasimlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    dosya_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    bitis_tarihi DATETIME NOT NULL,
    sifre_hash VARCHAR(255) NULL,
    indirme_limiti INT UNSIGNED NULL,
    indirme_sayisi INT UNSIGNED NOT NULL DEFAULT 0,
    olusturan_id INT NULL,
    iptal_tarihi DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dp_token (token_hash),
    KEY idx_dp_dosya (dosya_id, iptal_tarihi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dosya_erisim_loglari (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    dosya_id INT UNSIGNED NULL,
    paylasim_id INT UNSIGNED NULL,
    islem VARCHAR(30) NOT NULL,
    user_id INT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_del_dosya (dosya_id, created_at),
    KEY idx_del_firma (firma_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Dosya Yönetimi', 'Dosya ve klasörleri görüntüleme, arama, önizleme ve indirme', 'dosya_yonetimi_goruntule', 'dosya_yonetimi_goruntule', 'Dosya Yönetimi', 1, 0, 1, 0),
    ('Dosya Yükleme', 'Dosya yükleme', 'dosya_yonetimi_yukle', 'dosya_yonetimi_yukle', 'Dosya Yönetimi', 2, 0, 1, 0),
    ('Dosya Düzenleme', 'Dosya ve klasör yeniden adlandırma ve taşıma', 'dosya_yonetimi_duzenle', 'dosya_yonetimi_duzenle', 'Dosya Yönetimi', 2, 0, 1, 0),
    ('Dosya Silme', 'Dosya ve klasör silme', 'dosya_yonetimi_sil', 'dosya_yonetimi_sil', 'Dosya Yönetimi', 3, 0, 1, 0),
    ('Dosya Paylaşma', 'Dosyalar için süreli paylaşım bağlantısı oluşturma ve iptal etme', 'dosya_yonetimi_paylas', 'dosya_yonetimi_paylas', 'Dosya Yönetimi', 2, 0, 1, 0),
    ('Klasör Yönetimi', 'Klasör oluşturma ve klasör bazlı rol erişimi tanımlama', 'dosya_yonetimi_klasor_yonet', 'dosya_yonetimi_klasor_yonet', 'Dosya Yönetimi', 2, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, p.id, NULL
FROM user_roles roles
INNER JOIN permissions p ON p.permission_key IN (
    'dosya_yonetimi_goruntule', 'dosya_yonetimi_yukle', 'dosya_yonetimi_duzenle',
    'dosya_yonetimi_sil', 'dosya_yonetimi_paylas', 'dosya_yonetimi_klasor_yonet'
) AND p.is_active = 1
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = p.id
  );

SET @dosya_goruntule_id = (SELECT id FROM permissions WHERE permission_key = 'dosya_yonetimi_goruntule' LIMIT 1);

INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT
    'Dosya Yönetimi',
    'Şirket evrakları, bilançolar ve belgeler için klasörlü dosya yönetimi',
    0,
    'İş Takip Yönetim',
    2,
    'dosya-yonetimi/list',
    @dosya_goruntule_id,
    'folder',
    20,
    1,
    1,
    1
FROM DUAL
WHERE @dosya_goruntule_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'dosya-yonetimi/list');

UPDATE menus
SET permission_id = @dosya_goruntule_id,
    is_active = 1,
    is_menu = 1,
    is_authorized = 1
WHERE menu_link = 'dosya-yonetimi/list'
  AND @dosya_goruntule_id IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, p.id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'dosya-yonetimi/list' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, 'dosya_yonetimi_goruntule' AS permission_key
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'agac', '*', 'read', 'dosya_yonetimi_goruntule'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'liste', '*', 'read', 'dosya_yonetimi_goruntule'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'ozet', '*', 'read', 'dosya_yonetimi_goruntule'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'klasor-icerik', '*', 'read', 'dosya_yonetimi_goruntule'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'paylasim-listele', '*', 'read', 'dosya_yonetimi_paylas'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'klasor-rolleri', '*', 'read', 'dosya_yonetimi_klasor_yonet'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'yukle', '*', 'write', 'dosya_yonetimi_yukle'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'dosya-yeniden-adlandir', '*', 'write', 'dosya_yonetimi_duzenle'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'dosya-tasi', '*', 'write', 'dosya_yonetimi_duzenle'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'klasor-yeniden-adlandir', '*', 'write', 'dosya_yonetimi_duzenle'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'klasor-tasi', '*', 'write', 'dosya_yonetimi_duzenle'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'dosya-sil', '*', 'write', 'dosya_yonetimi_sil'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'klasor-sil', '*', 'write', 'dosya_yonetimi_sil'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'paylasim-olustur', '*', 'write', 'dosya_yonetimi_paylas'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'paylasim-iptal', '*', 'write', 'dosya_yonetimi_paylas'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'klasor-olustur', '*', 'write', 'dosya_yonetimi_klasor_yonet'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'klasor-rolleri-kaydet', '*', 'write', 'dosya_yonetimi_klasor_yonet'
    UNION ALL SELECT 'page', 'dosya-yonetimi/indir', '', 'GET', 'read', 'dosya_yonetimi_goruntule'
) d
INNER JOIN permissions p ON p.permission_key = d.permission_key AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode),
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

COMMIT;
