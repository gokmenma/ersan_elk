CREATE TABLE IF NOT EXISTS `evrak_takip_ekleri` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `evrak_id` INT NOT NULL,
    `firma_id` INT NOT NULL,
    `dosya_adi` VARCHAR(255) NOT NULL,
    `dosya_yolu` VARCHAR(500) NOT NULL,
    `mime_tipi` VARCHAR(100) NULL,
    `dosya_boyutu` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sira` INT NOT NULL DEFAULT 0,
    `yukleyen_kullanici_id` INT NULL,
    `olusturma_tarihi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_evrak_ekleri_evrak` (`evrak_id`, `firma_id`, `silinme_tarihi`, `sira`),
    KEY `idx_evrak_ekleri_yukleyen` (`yukleyen_kullanici_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
