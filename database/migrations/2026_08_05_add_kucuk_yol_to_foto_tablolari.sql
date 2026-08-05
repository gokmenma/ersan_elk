-- -----------------------------------------------------
-- Faz 1: Fotoğraf küçük boyut (thumbnail) desteği
-- Liste ekranlarında orijinal görsel yerine küçük boyut servis edilir.
-- kucuk_yol NULL ise kayıt eski sürümden gelmiştir ve orijinal dosya gösterilir.
-- -----------------------------------------------------

ALTER TABLE `kacak_kontrol_fotograflari`
    ADD COLUMN `kucuk_yol` VARCHAR(255) NULL DEFAULT NULL AFTER `dosya_yolu`;

ALTER TABLE `ihbar_fotograflari`
    ADD COLUMN `kucuk_yol` VARCHAR(255) NULL DEFAULT NULL AFTER `dosya_yolu`;
