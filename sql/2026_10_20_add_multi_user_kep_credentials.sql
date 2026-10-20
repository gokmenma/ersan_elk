-- Aynı firma KEP adresini kullanan her uygulama kullanıcısının PTT kimlik bilgilerini ayrı saklar.
CREATE TABLE IF NOT EXISTS `kep_kullanici_hesaplari` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `kep_ayar_id` INT(11) NOT NULL,
  `firm_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `yetkili_tcno` TEXT NOT NULL,
  `parola` TEXT NOT NULL,
  `sifre` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kep_kullanici_hesabi` (`kep_ayar_id`, `user_id`),
  KEY `idx_kep_kullanici_firma_user` (`firm_id`, `user_id`, `is_active`),
  CONSTRAINT `fk_kep_kullanici_ayar` FOREIGN KEY (`kep_ayar_id`) REFERENCES `kep_ayarlar` (`id`),
  CONSTRAINT `fk_kep_kullanici_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

