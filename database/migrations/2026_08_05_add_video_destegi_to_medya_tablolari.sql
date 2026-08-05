-- -----------------------------------------------------
-- Faz 2: İhbar ve kaçak kayıtlarına video desteği
--
-- Videolar mevcut fotoğraf tablolarında tutulur, medya_tipi ile ayrılır.
-- Böylece silme, arşivleme, yetki ve servis akışları tek yerde kalır.
--
-- medya_tipi = 'foto'  -> kucuk_yol küçültülmüş fotoğraftır
-- medya_tipi = 'video' -> kucuk_yol istemcide üretilen kapak karesidir,
--                         sure_saniye videonun uzunluğudur
--
-- Mevcut kayıtların tamamı fotoğraftır; DEFAULT 'foto' ile geriye dönük uyumludur.
-- Fotoğraf sayısı gösteren sorgular medya_tipi = 'foto' ile filtrelenir,
-- aksi halde kaçak modülündeki beklenen_foto_sayisi mutabakatı bozulur.
-- -----------------------------------------------------

ALTER TABLE `kacak_kontrol_fotograflari`
    ADD COLUMN `medya_tipi` ENUM('foto','video') NOT NULL DEFAULT 'foto' AFTER `tur`,
    ADD COLUMN `sure_saniye` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `kucuk_yol`,
    ADD INDEX `idx_kacak_medya` (`kacak_id`, `medya_tipi`);

ALTER TABLE `ihbar_fotograflari`
    ADD COLUMN `medya_tipi` ENUM('foto','video') NOT NULL DEFAULT 'foto' AFTER `ihbar_id`,
    ADD COLUMN `sure_saniye` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `kucuk_yol`,
    ADD INDEX `idx_ihbar_medya` (`ihbar_id`, `medya_tipi`);
