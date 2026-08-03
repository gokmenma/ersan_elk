-- =====================================================
-- KAÇAK İŞLEMLERİ - ÇEVRİMDIŞI KUYRUK DESTEĞİ
-- PWA sahada internet yokken kaydı cihazda kuyruğa alır,
-- bağlantı gelince gönderir. Aynı kaydın iki kez düşmemesi
-- için istemcide üretilen UUID benzersiz tutulur.
-- =====================================================

ALTER TABLE `kacak_kontrol`
    ADD COLUMN IF NOT EXISTS `client_uuid` VARCHAR(36) DEFAULT NULL AFTER `kaynak`,
    ADD COLUMN IF NOT EXISTS `offline_olusturma` DATETIME DEFAULT NULL AFTER `client_uuid`;

-- NULL değerler tekrar edebildiği için çevrimiçi kayıtlar bu indeksten etkilenmez.
ALTER TABLE `kacak_kontrol`
    ADD UNIQUE INDEX IF NOT EXISTS `uniq_client_uuid` (`client_uuid`);
