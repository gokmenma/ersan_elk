CREATE TABLE IF NOT EXISTS dogum_gunu_bildirim_loglari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT UNSIGNED NOT NULL,
    personel_id INT UNSIGNED NOT NULL,
    bildirim_yili SMALLINT UNSIGNED NOT NULL,
    baslik VARCHAR(255) NOT NULL DEFAULT '',
    mesaj VARCHAR(500) NOT NULL DEFAULT '',
    gonderim_durumu ENUM('gonderildi','basarisiz') NOT NULL DEFAULT 'gonderildi',
    gonderim_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_personel_yil (personel_id, bildirim_yili),
    KEY idx_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
