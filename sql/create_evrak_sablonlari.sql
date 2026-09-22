CREATE TABLE IF NOT EXISTS `evrak_sablonlari` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` int NOT NULL,
  `adi` varchar(150) NOT NULL,
  `sablon_verisi` longtext NOT NULL,
  `olusturan_kullanici_id` int DEFAULT NULL,
  `guncelleyen_kullanici_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evrak_sablon_firma_adi` (`firma_id`,`adi`,`deleted_at`),
  KEY `idx_evrak_sablon_firma_aktif` (`firma_id`,`is_active`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evrak_sablon_ekleri` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sablon_id` int unsigned NOT NULL,
  `firma_id` int NOT NULL,
  `dosya_adi` varchar(255) NOT NULL,
  `dosya_yolu` varchar(500) NOT NULL,
  `mime_tipi` varchar(150) DEFAULT NULL,
  `dosya_boyutu` int unsigned NOT NULL DEFAULT 0,
  `sira` int unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_evrak_sablon_ekleri` (`sablon_id`,`firma_id`,`is_active`,`deleted_at`),
  CONSTRAINT `fk_evrak_sablon_ekleri_sablon` FOREIGN KEY (`sablon_id`) REFERENCES `evrak_sablonlari` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
