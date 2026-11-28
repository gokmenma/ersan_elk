-- Bozuk/hasarlı aparat iadelerinde aparat başına ceza tutarı ve personel kesintisi bağlantısı

ALTER TABLE aparat_hareket
    ADD COLUMN IF NOT EXISTS birim_ceza DECIMAL(10,2) NULL DEFAULT NULL AFTER adet,
    ADD COLUMN IF NOT EXISTS ceza_tutari DECIMAL(10,2) NULL DEFAULT NULL AFTER birim_ceza;

ALTER TABLE personel_kesintileri
    ADD COLUMN IF NOT EXISTS aparat_hareket_grup_id INT NULL DEFAULT NULL AFTER ana_kesinti_id,
    ADD INDEX IF NOT EXISTS idx_pk_aparat_grup (aparat_hareket_grup_id);
