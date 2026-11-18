-- Giden evrak gönderim kanallarına Posta, UYAP ve Elden Teslim eklenir; dış sistem referans numarası sütunu eklenir.
-- Tekrarlanabilir migration.

ALTER TABLE evrak_takip
    MODIFY COLUMN gonderim_kanali ENUM('normal','kep','posta','uyap','elden') NOT NULL DEFAULT 'normal';

SET @has_ref := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'gonderim_referans_no'
);
SET @sql := IF(@has_ref = 0,
    'ALTER TABLE evrak_takip ADD COLUMN gonderim_referans_no VARCHAR(120) NULL AFTER gonderim_kanali',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
