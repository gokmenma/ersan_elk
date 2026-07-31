CREATE TABLE IF NOT EXISTS `hakedis_is_revizyonlari` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sozlesme_id` int NOT NULL,
  `revizyon_no` int unsigned NOT NULL,
  `revizyon_tarihi` date NOT NULL,
  `karar_no` varchar(100) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `olusturan_personel_id` int DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hakedis_is_revizyonu_no` (`sozlesme_id`,`revizyon_no`),
  KEY `idx_hakedis_is_revizyonu_sozlesme` (`sozlesme_id`,`revizyon_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hakedis_sure_uzatimlari` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sozlesme_id` int NOT NULL,
  `uzatim_no` int unsigned NOT NULL,
  `uzatim_tarihi` date NOT NULL,
  `karar_no` varchar(100) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `uzatim_gun` int unsigned NOT NULL,
  `onceki_bitis_tarihi` date NOT NULL,
  `yeni_bitis_tarihi` date NOT NULL,
  `olusturan_personel_id` int DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hakedis_sure_uzatimi_no` (`sozlesme_id`,`uzatim_no`),
  KEY `idx_hakedis_sure_uzatimi_sozlesme` (`sozlesme_id`,`uzatim_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hakedis_is_revizyon_kalemleri` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `revizyon_id` int unsigned NOT NULL,
  `kalem_id` int NOT NULL,
  `poz_no` varchar(100) DEFAULT NULL,
  `kalem_adi` varchar(500) NOT NULL,
  `birim` varchar(50) DEFAULT NULL,
  `onceki_miktar` decimal(18,4) NOT NULL,
  `degisim_miktari` decimal(18,4) NOT NULL,
  `yeni_miktar` decimal(18,4) NOT NULL,
  `birim_fiyat` decimal(18,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hakedis_is_revizyon_kalemi` (`revizyon_id`,`kalem_id`),
  KEY `idx_hakedis_is_revizyon_kalem_id` (`kalem_id`),
  CONSTRAINT `fk_hakedis_is_revizyon_detay`
    FOREIGN KEY (`revizyon_id`) REFERENCES `hakedis_is_revizyonlari` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
