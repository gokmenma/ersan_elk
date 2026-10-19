-- Giden evraklarda normal/KEP gönderim kanalı ve KEP taslak alanları.
-- Tekrarlanabilir migration: mevcut kurulumlarda yalnızca eksik sütunları ekler.

SET @has_gonderim_kanali := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'gonderim_kanali'
);
SET @sql := IF(@has_gonderim_kanali = 0,
    "ALTER TABLE evrak_takip ADD COLUMN gonderim_kanali ENUM('normal','kep') NOT NULL DEFAULT 'normal' AFTER evrak_tipi",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_kep_alicilar := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'kep_alicilar'
);
SET @sql := IF(@has_kep_alicilar = 0,
    'ALTER TABLE evrak_takip ADD COLUMN kep_alicilar TEXT NULL AFTER alici_kep',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_kep_ileti_tipi := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'kep_ileti_tipi'
);
SET @sql := IF(@has_kep_ileti_tipi = 0,
    "ALTER TABLE evrak_takip ADD COLUMN kep_ileti_tipi VARCHAR(30) NOT NULL DEFAULT 'resmi' AFTER kep_paket_turu",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_kep_ileti_detayi := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'kep_ileti_detayi'
);
SET @sql := IF(@has_kep_ileti_detayi = 0,
    'ALTER TABLE evrak_takip ADD COLUMN kep_ileti_detayi VARCHAR(500) NULL AFTER kep_ileti_tipi',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_kep_mesaj_metni := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip' AND COLUMN_NAME = 'kep_mesaj_metni'
);
SET @sql := IF(@has_kep_mesaj_metni = 0,
    'ALTER TABLE evrak_takip ADD COLUMN kep_mesaj_metni TEXT NULL AFTER kep_ileti_detayi',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Daha önce sevk edilmiş KEP kayıtlarını geriye uyumlu biçimde işaretle.
UPDATE evrak_takip
SET gonderim_kanali = 'kep'
WHERE silinme_tarihi IS NULL
  AND (kaynak_tipi = 'kep' OR (kep_mesaj_id IS NOT NULL AND kep_mesaj_id <> ''));
