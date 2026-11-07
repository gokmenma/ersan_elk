CREATE TABLE IF NOT EXISTS ik_ilan_goruntulemeler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    ilan_id INT UNSIGNED NOT NULL,
    ziyaretci_hash CHAR(64) NOT NULL,
    goruntuleme_sayisi INT UNSIGNED NOT NULL DEFAULT 1,
    ilk_goruntuleme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    son_goruntuleme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ik_ilan_ziyaretci (ilan_id, ziyaretci_hash),
    KEY idx_ik_ilan_gor_firma (firma_id, ilan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
