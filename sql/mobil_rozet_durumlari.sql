CREATE TABLE IF NOT EXISTS `mobil_rozet_durumlari` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `firma_id` INT(11) NOT NULL,
  `anahtar` VARCHAR(50) NOT NULL,
  `son_gorulen_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_firma_anahtar` (`user_id`, `firma_id`, `anahtar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
