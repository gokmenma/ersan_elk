-- Araçlar tablosuna şifreli ruhsat görseli alanları eklenir.
-- Dosyanın kendisi diskte AES-256-CBC ile şifreli tutulur; bu tabloda sadece
-- şifreli dosyanın meta bilgileri (ad, tip, boyut, yükleyen) saklanır.

ALTER TABLE `araclar`
    ADD COLUMN `ruhsat_dosya_adi` VARCHAR(255) DEFAULT NULL COMMENT 'Şifreli ruhsat dosyasının diskteki adı' AFTER `resim_yolu`,
    ADD COLUMN `ruhsat_orijinal_ad` VARCHAR(255) DEFAULT NULL COMMENT 'Yüklenen dosyanın orijinal adı' AFTER `ruhsat_dosya_adi`,
    ADD COLUMN `ruhsat_mime_tipi` VARCHAR(100) DEFAULT NULL AFTER `ruhsat_orijinal_ad`,
    ADD COLUMN `ruhsat_boyutu` INT(11) DEFAULT NULL AFTER `ruhsat_mime_tipi`,
    ADD COLUMN `ruhsat_yukleme_tarihi` DATETIME DEFAULT NULL AFTER `ruhsat_boyutu`,
    ADD COLUMN `ruhsat_yukleyen_id` INT(11) DEFAULT NULL AFTER `ruhsat_yukleme_tarihi`;
