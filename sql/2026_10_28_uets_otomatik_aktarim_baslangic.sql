ALTER TABLE `uets_ayarlar`
  ADD COLUMN IF NOT EXISTS `otomatik_aktarim_baslangic` DATE NULL DEFAULT NULL AFTER `otomatik_senkronizasyon`;

UPDATE `uets_ayarlar`
SET `otomatik_aktarim_baslangic` = '2026-10-27'
WHERE `otomatik_aktarim_baslangic` IS NULL;
