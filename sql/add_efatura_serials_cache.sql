-- efatura_ayarlar tablosuna seri önbellekleme (serials_cache) sütunları ekleme
ALTER TABLE `efatura_ayarlar` 
ADD COLUMN `serials_cache` TEXT NULL DEFAULT NULL AFTER `kontor_esik`,
ADD COLUMN `serials_cached_at` DATETIME NULL DEFAULT NULL AFTER `serials_cache`;
