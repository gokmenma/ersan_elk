-- Fotoğrafın cihazda çekildiği an ile sisteme yüklendiği anın kıyaslanabilmesi için.
-- cekim_kaynak: exif = fotoğrafın kendi meta verisi, dosya = dosyanın cihazdaki değişim tarihi.
ALTER TABLE `kacak_kontrol_fotograflari`
    ADD COLUMN `cekim_tarihi` DATETIME NULL DEFAULT NULL AFTER `orijinal_ad`,
    ADD COLUMN `cekim_kaynak` ENUM('exif','dosya') NULL DEFAULT NULL AFTER `cekim_tarihi`;

ALTER TABLE `kacak_kontrol_fotograflari`
    ADD KEY `idx_cekim` (`kacak_id`, `cekim_tarihi`);
