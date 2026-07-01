-- Şifreli değerlerin sığması için kolon boyutlarını büyütme
-- AES-256-CBC + base64 değerleri orijinalden ~3x büyük olabilir

ALTER TABLE personel
    MODIFY COLUMN iban_numarasi VARCHAR(512) DEFAULT NULL,
    MODIFY COLUMN ek_odeme_iban_numarasi VARCHAR(512) DEFAULT NULL,
    MODIFY COLUMN kaski_sifre VARCHAR(512) DEFAULT NULL,
    MODIFY COLUMN kaski_kullanici_adi VARCHAR(512) DEFAULT NULL,
    MODIFY COLUMN kan_grubu VARCHAR(128) DEFAULT NULL;
