-- Dosya Yönetimi: klasörler için elle sıralama.
-- Ön koşul: 2026_11_03_dosya_yonetimi.sql

START TRANSACTION;

SET @kolon_var = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dosya_klasorleri' AND COLUMN_NAME = 'sira'
);
SET @alter_sql = IF(@kolon_var = 0,
    "ALTER TABLE dosya_klasorleri ADD COLUMN sira INT NOT NULL DEFAULT 0 AFTER ad",
    'SELECT 1');
PREPARE alter_stmt FROM @alter_sql;
EXECUTE alter_stmt;
DEALLOCATE PREPARE alter_stmt;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'dosya-yonetimi/api', 'klasor-sirala', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.permission_key = 'dosya_yonetimi_duzenle' AND p.is_active = 1
ON DUPLICATE KEY UPDATE
    access_mode = VALUES(access_mode),
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

COMMIT;
