-- Araç zimmet teslim/iade anındaki durum fotoğraflarını tutar.
-- Dosyaların kendisi diskte AES-256-CBC ile şifreli saklanır; bu tabloda
-- yalnızca şifreli dosyanın meta bilgileri bulunur.

CREATE TABLE IF NOT EXISTS `arac_zimmet_fotograflari` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `zimmet_id` INT(11) NOT NULL,
    `foto_turu` ENUM('teslim', 'iade') NOT NULL DEFAULT 'teslim',
    `dosya_adi` VARCHAR(255) NOT NULL COMMENT 'Şifreli dosyanın diskteki adı',
    `orijinal_ad` VARCHAR(255) DEFAULT NULL,
    `mime_tipi` VARCHAR(100) DEFAULT NULL,
    `boyutu` INT(11) DEFAULT NULL,
    `yukleyen_id` INT(11) DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_zimmet` (`zimmet_id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_zimmet_tur` (`zimmet_id`, `foto_turu`),
    CONSTRAINT `fk_zimmet_foto_zimmet` FOREIGN KEY (`zimmet_id`) REFERENCES `arac_zimmetleri` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
