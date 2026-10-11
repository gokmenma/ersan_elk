-- Talepler sayfasındaki aktif sekme, sayaç ve dashboard sorgularını hızlandırır.
SET @schema_name := DATABASE();

SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@schema_name AND table_name='personel_avanslari' AND index_name='idx_avans_personel_durum_silinme_tarih'),
    'SELECT 1', 'ALTER TABLE personel_avanslari ADD INDEX idx_avans_personel_durum_silinme_tarih (personel_id, durum, silinme_tarihi, talep_tarihi)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@schema_name AND table_name='personel_izinleri' AND index_name='idx_izin_personel_durum_silinme_tarih'),
    'SELECT 1', 'ALTER TABLE personel_izinleri ADD INDEX idx_izin_personel_durum_silinme_tarih (personel_id, onay_durumu, silinme_tarihi, talep_tarihi)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@schema_name AND table_name='personel_talepleri' AND index_name='idx_talep_personel_durum_silinme_tarih'),
    'SELECT 1', 'ALTER TABLE personel_talepleri ADD INDEX idx_talep_personel_durum_silinme_tarih (personel_id, durum, silinme_tarihi, olusturma_tarihi)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
