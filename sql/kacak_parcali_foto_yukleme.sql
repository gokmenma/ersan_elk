-- =====================================================
-- KAÇAK İŞLEMLERİ - PARÇALI FOTOĞRAF YÜKLEME
-- Saha fotoğrafları tek büyük istek yerine tek tek gönderilir.
-- client_sira, istemcideki fotoğraf sırasını taşır; aynı fotoğrafın
-- yeniden denemede ikinci kez kaydedilmesini benzersiz indeks engeller.
-- =====================================================

ALTER TABLE `kacak_kontrol_fotograflari`
    ADD COLUMN IF NOT EXISTS `client_sira` INT(11) DEFAULT NULL AFTER `tur`;

-- NULL değerler tekrar edebildiği için mevcut fotoğraflar bu indeksten etkilenmez.
ALTER TABLE `kacak_kontrol_fotograflari`
    ADD UNIQUE INDEX IF NOT EXISTS `uniq_kacak_tur_sira` (`kacak_id`, `tur`, `client_sira`);
