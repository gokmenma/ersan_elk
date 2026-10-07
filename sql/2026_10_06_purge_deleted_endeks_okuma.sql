-- ==============================================================================
-- Endeks Okuma Tablosundaki Silinmiş Kayıtları Hızlı Temizleme Scripti
-- Tarih: 2026-10-06
-- Açıklama: Milyonlarca satırlık silinmiş kaydı (silinme_tarihi IS NOT NULL)
--           InnoDB transaction/undo log kilitlenmesi olmadan saniyeler içinde
--           temizlemek için "Copy-Swap-Drop" yöntemi kullanılır.
-- ==============================================================================

-- 1. Varsa eski geçici tabloları temizle
DROP TABLE IF EXISTS `endeks_okuma_purge_temp`, `endeks_okuma_purge_old`;

-- 2. Yeni tabloyu mevcut yapı ve indekslerle birebir oluştur
CREATE TABLE `endeks_okuma_purge_temp` LIKE `endeks_okuma`;

-- 3. Sadece aktif (silinmemiş) kayıtları yeni tabloya kopyala
INSERT INTO `endeks_okuma_purge_temp` 
SELECT * FROM `endeks_okuma` 
WHERE `silinme_tarihi` IS NULL;

-- 4. Atomik olarak tabloları takas et (Sıfır kesinti)
RENAME TABLE `endeks_okuma` TO `endeks_okuma_old`, `endeks_okuma_purge_temp` TO `endeks_okuma`;

-- 5. Eski silinmiş kayıtları içeren tabloyu anında kaldır
DROP TABLE IF EXISTS `endeks_okuma_old`;
