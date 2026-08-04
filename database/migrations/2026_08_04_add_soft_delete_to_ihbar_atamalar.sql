-- Mevcut ihbar atamalarını koruyarak yeniden yönlendirmeyi destekler.
ALTER TABLE `ihbar_atamalar`
  ADD COLUMN `silinme_tarihi` datetime DEFAULT NULL AFTER `created_at`,
  ADD INDEX `idx_ihbar_aktif` (`ihbar_id`, `silinme_tarihi`);
