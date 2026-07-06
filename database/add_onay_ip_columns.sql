ALTER TABLE personel_avanslari ADD COLUMN onay_ip VARCHAR(45) NULL DEFAULT NULL AFTER onay_tarihi;

ALTER TABLE izin_onaylari ADD COLUMN onay_ip VARCHAR(45) NULL DEFAULT NULL AFTER onay_tarihi;

ALTER TABLE personel_talepleri ADD COLUMN onay_ip VARCHAR(45) NULL DEFAULT NULL AFTER cozum_tarihi;
