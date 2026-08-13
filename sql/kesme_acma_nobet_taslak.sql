-- Kesme / Açma merkez saha nöbeti için izole taslak tablo.
-- Mevcut `nobetler` ve nöbet talep/değişim tablolarında değişiklik yapmaz.

CREATE TABLE IF NOT EXISTS `kesme_acma_nobet_taslak` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `personel_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `nobet_tipi` ENUM('standart','hafta_sonu','resmi_tatil','ozel') NOT NULL DEFAULT 'standart',
    `elle_degistirildi` TINYINT(1) NOT NULL DEFAULT 0,
    `olusturan_id` INT(11) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_firma_tarih` (`firma_id`, `tarih`, `is_active`, `deleted_at`),
    KEY `idx_personel_tarih` (`firma_id`, `personel_id`, `tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
