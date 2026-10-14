-- Bordro kapanışlarının değişmez, sürümlü ve hash zincirli hukuki arşivi.
CREATE TABLE IF NOT EXISTS bordro_kapanis_arsiv (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    donem_id INT NOT NULL,
    surum_no INT NOT NULL,
    kapatan_kullanici_id INT NULL,
    kapatan_adi VARCHAR(255) NULL,
    onceki_arsiv_hash CHAR(64) NULL,
    manifest_hash CHAR(64) NOT NULL,
    manifest_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bordro_kapanis_arsiv_surumu (firma_id, donem_id, surum_no),
    UNIQUE KEY uq_bordro_kapanis_manifest_hash (manifest_hash),
    KEY idx_bordro_kapanis_arsiv_donem (donem_id, created_at),
    CONSTRAINT fk_bordro_kapanis_arsiv_donem FOREIGN KEY (donem_id) REFERENCES bordro_donemi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bordro_kapanis_arsiv_dokum (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    arsiv_id BIGINT UNSIGNED NOT NULL,
    bordro_personel_id INT UNSIGNED NOT NULL,
    personel_id INT NOT NULL,
    dokum_hash CHAR(64) NOT NULL,
    snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bordro_kapanis_arsiv_dokum (arsiv_id, bordro_personel_id),
    KEY idx_bordro_kapanis_dokum_personel (personel_id, created_at),
    CONSTRAINT fk_bordro_kapanis_dokum_arsiv FOREIGN KEY (arsiv_id) REFERENCES bordro_kapanis_arsiv(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
