-- Görevdeki personelin yönlendirme amacıyla kullanılan son canlı konumu.
CREATE TABLE IF NOT EXISTS `personel_canli_konumlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `firma_id` int(11) NOT NULL,
  `enlem` decimal(10,7) NOT NULL,
  `boylam` decimal(10,7) NOT NULL,
  `hassasiyet` decimal(10,2) DEFAULT NULL,
  `son_guncelleme` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personel_canli_konum` (`personel_id`),
  KEY `idx_firma_guncelleme` (`firma_id`, `son_guncelleme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
