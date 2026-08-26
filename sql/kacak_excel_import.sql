-- Kaçak İşlemleri > Kayıtlar sekmesi Excel'den toplu veri yükleme
-- Uygulamadan önce ilgili firma veritabanının yedeği alınmalıdır.

ALTER TABLE `kacak_kontrol`
    ADD COLUMN IF NOT EXISTS `tutar` DECIMAL(12,2) DEFAULT NULL AFTER `sayi`,
    ADD COLUMN IF NOT EXISTS `kontrol_edildi` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tutar`,
    ADD COLUMN IF NOT EXISTS `usulsuz_notu` VARCHAR(255) DEFAULT NULL AFTER `kontrol_edildi`;

ALTER TABLE `kacak_kontrol`
    ADD INDEX IF NOT EXISTS `idx_firma_tutanak` (`firma_id`, `tutanak_no`);
