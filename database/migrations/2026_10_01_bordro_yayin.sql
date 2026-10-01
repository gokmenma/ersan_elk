-- Resmî bordro yayınları. Uygulama dağıtımından önce bir kez çalıştırın.
CREATE TABLE IF NOT EXISTS bordro_yayin (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 firma_id INT NOT NULL, donem_id INT NOT NULL, surum INT NOT NULL,
 durum VARCHAR(24) NOT NULL DEFAULT 'yayinda', yayinlayan_id INT NOT NULL,
 yayin_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_at DATETIME NULL, is_active TINYINT NOT NULL DEFAULT 1,
 UNIQUE KEY uq_donem_surum (firma_id, donem_id, surum),
 KEY ix_yayin_durum (firma_id, donem_id, durum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bordro_yayin_dokum (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, yayin_id BIGINT UNSIGNED NOT NULL,
 personel_id INT NOT NULL, bordro_personel_id INT NOT NULL,
 icerik LONGTEXT NOT NULL, icerik_hash CHAR(64) NOT NULL,
 goruntuleme_tarihi DATETIME NULL, beyan_tarihi DATETIME NULL,
 beyan_metni VARCHAR(255) NULL, beyan_metin_surumu INT NULL,
 deleted_at DATETIME NULL, is_active TINYINT NOT NULL DEFAULT 1,
 UNIQUE KEY uq_yayin_personel (yayin_id, personel_id), KEY ix_personel (personel_id, yayin_id),
 FOREIGN KEY (yayin_id) REFERENCES bordro_yayin(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bordro_yayin_olay (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, dokum_id BIGINT UNSIGNED NOT NULL,
 tur VARCHAR(40) NOT NULL, aktor_tipi VARCHAR(16) NOT NULL, aktor_id INT NOT NULL,
 detay TEXT NULL, tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_at DATETIME NULL, is_active TINYINT NOT NULL DEFAULT 1,
 KEY ix_dokum (dokum_id, tarih), FOREIGN KEY (dokum_id) REFERENCES bordro_yayin_dokum(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bordro_yayin_talep (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, dokum_id BIGINT UNSIGNED NOT NULL,
 mesaj TEXT NOT NULL, durum VARCHAR(16) NOT NULL DEFAULT 'acik',
 tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_at DATETIME NULL, is_active TINYINT NOT NULL DEFAULT 1,
 KEY ix_talep (dokum_id, durum), FOREIGN KEY (dokum_id) REFERENCES bordro_yayin_dokum(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bordro_yayin_yanit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, talep_id BIGINT UNSIGNED NOT NULL,
 kullanici_id INT NOT NULL, mesaj TEXT NOT NULL, tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_at DATETIME NULL, is_active TINYINT NOT NULL DEFAULT 1,
 FOREIGN KEY (talep_id) REFERENCES bordro_yayin_talep(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bordro_yayin_kuyruk (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, dokum_id BIGINT UNSIGNED NOT NULL,
 gun INT NOT NULL, planlanan DATETIME NOT NULL, durum VARCHAR(16) NOT NULL DEFAULT 'bekliyor',
 deneme INT NOT NULL DEFAULT 0, kilit_tarihi DATETIME NULL, kilit_token CHAR(32) NULL,
 son_hata VARCHAR(255) NULL, gonderim_tarihi DATETIME NULL,
 deleted_at DATETIME NULL, is_active TINYINT NOT NULL DEFAULT 1,
 UNIQUE KEY uq_dokum_gun (dokum_id, gun), KEY ix_kuyruk (durum, planlanan),
 FOREIGN KEY (dokum_id) REFERENCES bordro_yayin_dokum(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
