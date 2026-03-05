-- Görev bildirim sistemi için gorevler tablosuna bildirim takip kolonu ekleme
-- Bu kolon, bir göreve bildirim gönderilip gönderilmediğini takip eder

ALTER TABLE gorevler ADD COLUMN bildirim_gonderildi TINYINT(1) DEFAULT 0 AFTER tamamlandi;

-- INDEX: Cron sorgusu performansı için
CREATE INDEX idx_gorevler_bildirim ON gorevler (tarih, tamamlandi, bildirim_gonderildi);
