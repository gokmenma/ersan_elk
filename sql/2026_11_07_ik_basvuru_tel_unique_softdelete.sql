ALTER TABLE ik_basvurular
    DROP INDEX uq_ik_basvuru_ilan_tel,
    ADD COLUMN aktif_anahtar TINYINT(1) GENERATED ALWAYS AS (IF(deleted_at IS NULL AND is_active = 1, 1, NULL)) STORED AFTER is_active,
    ADD UNIQUE KEY uq_ik_basvuru_ilan_tel_aktif (ilan_id, telefon, aktif_anahtar);
