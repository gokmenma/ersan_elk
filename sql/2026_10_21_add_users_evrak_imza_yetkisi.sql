SET @has_evrak_imza_yetkisi := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'evrak_imza_yetkisi'
);

SET @sql := IF(
    @has_evrak_imza_yetkisi = 0,
    'ALTER TABLE users ADD COLUMN evrak_imza_yetkisi TINYINT(1) NOT NULL DEFAULT 0 AFTER izin_onay_sirasi, ADD KEY idx_users_evrak_imza_yetkisi (owner_id, durum, evrak_imza_yetkisi)',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
