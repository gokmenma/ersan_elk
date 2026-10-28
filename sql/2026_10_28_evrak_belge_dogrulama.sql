CREATE TABLE IF NOT EXISTS `evrak_dogrulama` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `evrak_id` int(11) NOT NULL,
  `dogrulama_kodu` varchar(32) NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `pin_sifreli` text NOT NULL,
  `pades_pdf_sha256` char(64) DEFAULT NULL,
  `dogrulama_sayisi` int(11) NOT NULL DEFAULT 0,
  `son_dogrulama_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evrak_dogrulama_kod` (`dogrulama_kodu`),
  UNIQUE KEY `uq_evrak_dogrulama_evrak` (`firma_id`, `evrak_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `evrak_dogrulama_deneme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_adresi` varchar(45) NOT NULL,
  `dogrulama_kodu` varchar(32) DEFAULT NULL,
  `basarili` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_evrak_dogrulama_deneme_ip` (`ip_adresi`, `basarili`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
