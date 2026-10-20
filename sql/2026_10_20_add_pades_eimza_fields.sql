ALTER TABLE `evrak_takip`
    ADD COLUMN `pades_pdf_yolu` VARCHAR(500) NULL AFTER `e_imza_belge_ozeti`,
    ADD COLUMN `pades_pdf_sha256` CHAR(64) NULL AFTER `pades_pdf_yolu`;

ALTER TABLE `evrak_takip_onaylari`
    ADD COLUMN `imza_turu` VARCHAR(20) NULL AFTER `ip_adresi`,
    ADD COLUMN `sertifika_ozne` TEXT NULL AFTER `imza_turu`,
    ADD COLUMN `sertifika_veren` TEXT NULL AFTER `sertifika_ozne`,
    ADD COLUMN `sertifika_seri_no` VARCHAR(128) NULL AFTER `sertifika_veren`,
    ADD COLUMN `sertifika_parmak_izi` CHAR(64) NULL AFTER `sertifika_seri_no`,
    ADD COLUMN `sertifika_gecerlilik_baslangic` DATETIME NULL AFTER `sertifika_parmak_izi`,
    ADD COLUMN `sertifika_gecerlilik_bitis` DATETIME NULL AFTER `sertifika_gecerlilik_baslangic`,
    ADD COLUMN `imzali_pdf_sha256` CHAR(64) NULL AFTER `sertifika_gecerlilik_bitis`,
    ADD COLUMN `imza_dogrulama_durumu` VARCHAR(20) NULL AFTER `imzali_pdf_sha256`;

CREATE TABLE `kullanici_eimza_sertifikalari` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `firma_id` INT NOT NULL,
    `kullanici_id` INT NOT NULL,
    `sertifika_parmak_izi` CHAR(64) NOT NULL,
    `sertifika_ozne` TEXT NOT NULL,
    `sertifika_seri_no` VARCHAR(128) NULL,
    `tckn_hash` CHAR(64) NULL,
    `ilk_kullanim_tarihi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `son_kullanim_tarihi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kullanici_eimza_parmak_izi` (`firma_id`, `kullanici_id`, `sertifika_parmak_izi`),
    KEY `idx_kullanici_eimza_aktif` (`firma_id`, `kullanici_id`, `is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
