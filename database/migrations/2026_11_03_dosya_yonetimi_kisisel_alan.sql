-- Dosya Yönetimi: kişisel alan, ortak alan ve kullanıcı bazlı erişim.
-- Ön koşul: 2026_11_03_dosya_yonetimi.sql
-- Mevcut klasör ve dosyalar oluşturan/yükleyen kullanıcının kişisel alanında kalır.

START TRANSACTION;

SET @kolon_var = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dosya_klasorleri' AND COLUMN_NAME = 'alan'
);
SET @alter_sql = IF(@kolon_var = 0,
    "ALTER TABLE dosya_klasorleri ADD COLUMN alan ENUM('kisisel','ortak') NOT NULL DEFAULT 'kisisel' AFTER ad",
    'SELECT 1');
PREPARE alter_stmt FROM @alter_sql;
EXECUTE alter_stmt;
DEALLOCATE PREPARE alter_stmt;

CREATE TABLE IF NOT EXISTS dosya_erisimleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    nesne_tur ENUM('klasor','dosya') NOT NULL,
    nesne_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    seviye ENUM('goruntule','duzenle') NOT NULL DEFAULT 'goruntule',
    veren_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_de_nesne_kullanici (nesne_tur, nesne_id, user_id),
    KEY idx_de_kullanici (firma_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Dosya Ortak Alan Yönetimi', 'Dosya Yönetimi ortak alanında üst düzey klasör oluşturma', 'dosya_yonetimi_ortak_alan', 'dosya_yonetimi_ortak_alan', 'Dosya Yönetimi', 3, 0, 1, 0)
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
INNER JOIN permissions p ON p.permission_key = 'dosya_yonetimi_ortak_alan' AND p.is_active = 1
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = p.id
  );

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, p.id, 0, 0, 1
FROM (
    SELECT 'api' AS scope, 'dosya-yonetimi/api' AS resource, 'erisim-listele' AS action, '*' AS http_method, 'read' AS access_mode, 'dosya_yonetimi_paylas' AS permission_key
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'erisim-kullanicilar', '*', 'read', 'dosya_yonetimi_paylas'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'erisim-ver', '*', 'write', 'dosya_yonetimi_paylas'
    UNION ALL SELECT 'api', 'dosya-yonetimi/api', 'erisim-kaldir', '*', 'write', 'dosya_yonetimi_paylas'
) d
INNER JOIN permissions p ON p.permission_key = d.permission_key AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode),
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

COMMIT;
