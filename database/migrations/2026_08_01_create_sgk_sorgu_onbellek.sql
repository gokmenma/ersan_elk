-- SGK Vizite Web Servisi Sorgu Önbelleği
-- SGK bazı sorgu metotlarında (örn. onayliRaporlarTarihile) dakikada 1 sorgu sınırı uygular.
-- Bu tablo dönen cevabı kısa süreli saklayarak aynı verinin arka arkaya birden çok kez
-- sorgulanmasını engeller. Cevap gövdesi kişisel veri içerdiği için şifreli tutulur.

CREATE TABLE IF NOT EXISTS `sgk_sorgu_onbellek` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hesap_anahtari` char(64) NOT NULL,
  `sorgu_anahtari` char(64) NOT NULL,
  `metot` varchar(80) NOT NULL,
  `firma_id` int(11) DEFAULT NULL,
  `cevap` longtext DEFAULT NULL,
  `son_sorgu_zamani` datetime DEFAULT NULL,
  `gecerlilik_bitis` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sgk_sorgu_onbellek` (`hesap_anahtari`, `sorgu_anahtari`),
  KEY `idx_sgk_sorgu_onbellek_metot` (`hesap_anahtari`, `metot`, `son_sorgu_zamani`),
  KEY `idx_sgk_sorgu_onbellek_gecerlilik` (`gecerlilik_bitis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
