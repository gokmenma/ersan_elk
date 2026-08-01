-- SGK Vizite Web Servisi wsToken (GUID) Önbelleği
-- SGK, her işveren için aynı anda tek bir 30 dakikalık wsToken (36 karakterlik GUID) üretir
-- ve bu token'ı talep eden IP adresine bağlar. Her istekte yeniden wsLogin çağrılması
-- "BU İŞVEREN İÇİN FARKLI IP DEN ALINMIŞ GEÇERLİ GUID MEVCUT. YENİSİ VERİLEMEZ!" hatasına yol açar.
-- Bu tablo alınan token'ı süresi dolana kadar saklayarak wsLogin çağrısını 30 dakikada 1'e indirir.

CREATE TABLE IF NOT EXISTS `sgk_ws_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hesap_anahtari` char(64) NOT NULL,
  `firma_id` int(11) DEFAULT NULL,
  `kullanici_adi` varchar(50) NOT NULL,
  `isyeri_kodu` varchar(50) NOT NULL,
  `sifre_ozeti` char(64) NOT NULL,
  `ws_token` text NOT NULL,
  `sunucu_adresi` varchar(100) DEFAULT NULL,
  `gecerlilik_bitis` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sgk_ws_tokens_hesap` (`hesap_anahtari`),
  KEY `idx_sgk_ws_tokens_gecerlilik` (`gecerlilik_bitis`),
  KEY `idx_sgk_ws_tokens_firma` (`firma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
