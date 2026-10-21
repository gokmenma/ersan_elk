-- Mobil personel zimmet listesindeki firma/personel/durum/tarih erişimini hızlandırır.
-- Tekrarlanabilir script.

SET @index_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'demirbas_zimmet' AND index_name = 'idx_demirbas_zimmet_personel_liste'),
    'SELECT 1',
    'ALTER TABLE `demirbas_zimmet` ADD INDEX `idx_demirbas_zimmet_personel_liste` (`personel_id`, `durum`, `teslim_tarihi`, `id`)'
);
PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;
