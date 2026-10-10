-- Cari hareketleri tablosuna ekleyen_kullanici ve fatura_id alanlarının eklenmesi
ALTER TABLE `cari_hareketleri` 
ADD COLUMN `ekleyen_kullanici` INT NULL DEFAULT NULL AFTER `alacak`,
ADD COLUMN `fatura_id` INT NULL DEFAULT NULL AFTER `ekleyen_kullanici`,
ADD INDEX `idx_cari_hareketleri_ekleyen_kullanici` (`ekleyen_kullanici`),
ADD INDEX `idx_cari_hareketleri_fatura_id` (`fatura_id`),
ADD INDEX `idx_cari_hareketleri_cari_silinme` (`cari_id`, `silinme_tarihi`);
