-- =====================================================
-- ANA SAYFA (DASHBOARD) PERFORMANS İYİLEŞTİRMESİ
-- views/home.php içindeki "son güncelleme" sorgusu firma_id + created_at
-- filtresi ile MAX(created_at) hesaplıyor ancak bu üç tabloda bu kombinasyona
-- uygun index bulunmuyor; bu yüzden yüz binlerce satır taranıyor
-- (endeks_okuma: ~524k, yapilan_isler: tam tablo taraması ~254k).
-- Bu index'ler ana sayfa açılış süresini saniyelerden milisaniyelere düşürür.
-- =====================================================

ALTER TABLE `endeks_okuma`
ADD INDEX IF NOT EXISTS `idx_endeks_okuma_firma_created` (`firma_id`, `created_at`);

ALTER TABLE `yapilan_isler`
ADD INDEX IF NOT EXISTS `idx_yapilan_isler_firma_created` (`firma_id`, `created_at`);

ALTER TABLE `sayac_degisim`
ADD INDEX IF NOT EXISTS `idx_sayac_degisim_firma_created` (`firma_id`, `created_at`);
