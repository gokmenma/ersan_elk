-- Personelin PWA'dan göndermeyi planladığı toplam belge sayısını takip eder.
-- Tutanak fotoğrafı ve saha fotoğrafları bu toplama dahildir.
ALTER TABLE `kacak_kontrol`
    ADD COLUMN IF NOT EXISTS `beklenen_foto_sayisi` TINYINT UNSIGNED NOT NULL DEFAULT 0
    AFTER `offline_olusturma`;
