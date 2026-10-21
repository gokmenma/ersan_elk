-- Mobil personel detay/puantaj sorgularının kullandığı birleşik indeksler.
-- Mevcut indekslere dokunmaz; tekrar çalıştırılabilir.

SET @index_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'yapilan_isler' AND index_name = 'idx_yapilan_isler_personel_detay'),
    'SELECT 1',
    'ALTER TABLE `yapilan_isler` ADD INDEX `idx_yapilan_isler_personel_detay` (`personel_id`, `firma_id`, `silinme_tarihi`, `tarih`, `id`)'
);
PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'endeks_okuma' AND index_name = 'idx_endeks_okuma_personel_detay'),
    'SELECT 1',
    'ALTER TABLE `endeks_okuma` ADD INDEX `idx_endeks_okuma_personel_detay` (`personel_id`, `firma_id`, `silinme_tarihi`, `tarih`, `id`)'
);
PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'kacak_kontrol' AND index_name = 'idx_kacak_kontrol_firma_detay'),
    'SELECT 1',
    'ALTER TABLE `kacak_kontrol` ADD INDEX `idx_kacak_kontrol_firma_detay` (`firma_id`, `silinme_tarihi`, `tarih`, `id`)'
);
PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;
