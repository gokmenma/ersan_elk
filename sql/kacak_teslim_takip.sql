-- Kaçak İşlemleri / Teslim Alma Listesi kalıcı teslim takibi
CREATE TABLE IF NOT EXISTS `kacak_teslim_takip` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `kacak_id` INT(11) NOT NULL,
    `teslim_alindi` TINYINT(1) NOT NULL DEFAULT 0,
    `teslim_tarihi` DATETIME DEFAULT NULL,
    `teslim_alan_user_id` INT(11) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_firma_kacak` (`firma_id`, `kacak_id`),
    KEY `idx_teslim_durumu` (`firma_id`, `teslim_alindi`, `is_active`, `deleted_at`),
    KEY `idx_kacak_id` (`kacak_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
