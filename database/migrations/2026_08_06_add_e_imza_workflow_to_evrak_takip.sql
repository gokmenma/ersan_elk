ALTER TABLE `evrak_takip`
    ADD COLUMN `onay_durumu` ENUM('taslak','onay_bekliyor','onaylandi') NOT NULL DEFAULT 'taslak' AFTER `imza_kimin_adina_json`,
    ADD COLUMN `e_imza_onay_tarihi` DATETIME NULL AFTER `onay_durumu`,
    ADD COLUMN `e_imza_belge_ozeti` CHAR(64) NULL AFTER `e_imza_onay_tarihi`;

CREATE TABLE `evrak_takip_onaylari` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `evrak_id` INT NOT NULL,
    `firma_id` INT NOT NULL,
    `kullanici_id` INT NOT NULL,
    `durum` ENUM('bekliyor','onaylandi') NOT NULL DEFAULT 'bekliyor',
    `onay_tarihi` DATETIME NULL,
    `ip_adresi` VARCHAR(45) NULL,
    `olusturma_tarihi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_evrak_onay_kullanici` (`evrak_id`, `kullanici_id`),
    KEY `idx_evrak_onay_firma` (`firma_id`, `evrak_id`, `durum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
