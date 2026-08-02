-- =====================================================
-- DETAYLI RAPOR (views/puantaj/veri-yukleme.php) PERFORMANS İYİLEŞTİRMESİ
-- =====================================================

-- 1) tanimlamalar tablosunda PRIMARY dışında hiç index yok.
-- Sayaç Sökme Takma / Kesme-Açma / Mühürleme sekmelerinde iş emri sonuçları
-- firma_id + grup + rapor_sekmesi filtresiyle okunuyor ve her istekte tam tablo
-- taraması yapılıyor (~2.400 satır x sorgu sayısı).
ALTER TABLE `tanimlamalar`
ADD INDEX IF NOT EXISTS `idx_tanim_firma_grup_sekme` (`firma_id`, `grup`, `rapor_sekmesi`);

-- 2) Okuma İşlemleri sekmesi her istekte defter -> mahalle eşleşme tablosunu
-- (grup = 'defter_kodu') yeniden üretiyor. Bu index tam tablo taramasını ve
-- GROUP BY sıralamasını ortadan kaldırır.
ALTER TABLE `tanimlamalar`
ADD INDEX IF NOT EXISTS `idx_tanim_grup_defter` (`grup`, `tur_adi`, `defter_bolge`, `firma_id`);

-- 3) İSTATİSTİK YENİLEME (asıl darboğaz)
-- endeks_okuma tablosunun index istatistikleri güncel değildi; optimizer tarih
-- aralığı yerine silinme_tarihi index'ini seçip ~60.000 satır tarıyordu.
-- ANALYZE sonrası Okuma sekmesi 2,5 sn'den ~0,1 sn'ye indi.
-- NOT: Bu komutlar toplu Excel/API veri yüklemelerinden sonra tekrar
-- çalıştırılmalıdır (innodb_stats_auto_recalc her zaman yetişmiyor).
ANALYZE TABLE `endeks_okuma`;
ANALYZE TABLE `sayac_degisim`;
ANALYZE TABLE `yapilan_isler`;
ANALYZE TABLE `tanimlamalar`;
ANALYZE TABLE `personel`;
