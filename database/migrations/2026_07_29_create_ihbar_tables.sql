-- İhbar Sistemi (Kaçak Su/Su İhbarı) - Tablo Oluşturma
-- Bu betiği veritabanında çalıştırarak ihbar sistemine ait tabloları oluşturun.

CREATE TABLE IF NOT EXISTS `ihbarlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL DEFAULT 1,
  `ilce` varchar(100) DEFAULT NULL,
  `mahalle` varchar(150) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `komsu_abone_no` varchar(50) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `konum_lat` decimal(10,7) DEFAULT NULL,
  `konum_lng` decimal(10,7) DEFAULT NULL,
  `konum_dogruluk` decimal(10,2) DEFAULT NULL,
  `durum` enum('yeni','yonlendirildi','islemde','olumlu','olumsuz') NOT NULL DEFAULT 'yeni',
  `tutanak_no` varchar(100) DEFAULT NULL,
  `olumsuz_sebep` text DEFAULT NULL,
  `bildiren_personel_id` int(11) DEFAULT NULL,
  `olusturan_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_durum` (`durum`),
  KEY `idx_bildiren_personel` (`bildiren_personel_id`),
  KEY `idx_firma` (`firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ihbar_fotograflari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ihbar_id` int(11) NOT NULL,
  `dosya_yolu` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ihbar` (`ihbar_id`),
  CONSTRAINT `fk_ihbar_foto_ihbar` FOREIGN KEY (`ihbar_id`) REFERENCES `ihbarlar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ihbar_atamalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ihbar_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `atayan_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ihbar` (`ihbar_id`),
  KEY `idx_personel` (`personel_id`),
  CONSTRAINT `fk_ihbar_atama_ihbar` FOREIGN KEY (`ihbar_id`) REFERENCES `ihbarlar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ihbar_tarihce` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ihbar_id` int(11) NOT NULL,
  `tip` enum('olusturuldu','yonlendirildi','not','durum_degisti') NOT NULL,
  `aciklama` text NOT NULL,
  `ekleyen_tip` enum('personel','user') NOT NULL,
  `ekleyen_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ihbar` (`ihbar_id`),
  CONSTRAINT `fk_ihbar_tarihce_ihbar` FOREIGN KEY (`ihbar_id`) REFERENCES `ihbarlar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
