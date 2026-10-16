-- Ücret pusulası durum, kanıt ve itiraz iş akışı alanları.
ALTER TABLE bordro_yayin_dokum
    ADD COLUMN IF NOT EXISTS pusula_no VARCHAR(40) NULL AFTER bordro_personel_id,
    ADD COLUMN IF NOT EXISTS olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER icerik_hash,
    ADD COLUMN IF NOT EXISTS gonderim_tarihi DATETIME NULL AFTER olusturma_tarihi,
    ADD COLUMN IF NOT EXISTS onay_ip VARCHAR(45) NULL AFTER beyan_metin_surumu,
    ADD COLUMN IF NOT EXISTS onay_user_agent VARCHAR(500) NULL AFTER onay_ip,
    ADD COLUMN IF NOT EXISTS onay_session_hash CHAR(64) NULL AFTER onay_user_agent,
    ADD COLUMN IF NOT EXISTS onay_icerik_hash CHAR(64) NULL AFTER onay_session_hash,
    ADD UNIQUE KEY IF NOT EXISTS uq_bordro_yayin_pusula_no (pusula_no);

ALTER TABLE bordro_yayin_talep
    ADD COLUMN IF NOT EXISTS konu VARCHAR(40) NOT NULL DEFAULT 'diger' AFTER dokum_id,
    ADD COLUMN IF NOT EXISTS atanan_kullanici_id INT NULL AFTER durum,
    ADD COLUMN IF NOT EXISTS sonuc_tarihi DATETIME NULL AFTER tarih,
    ADD KEY IF NOT EXISTS ix_bordro_yayin_talep_durum (durum, tarih);

UPDATE bordro_yayin_dokum d
INNER JOIN bordro_yayin y ON y.id = d.yayin_id
INNER JOIN bordro_donemi bd ON bd.id = y.donem_id
SET d.pusula_no = COALESCE(d.pusula_no, CONCAT(DATE_FORMAT(bd.baslangic_tarihi, '%Y/%m/'), LPAD(d.id, 6, '0'))),
    d.olusturma_tarihi = COALESCE(y.yayin_tarihi, d.olusturma_tarihi),
    d.gonderim_tarihi = COALESCE(d.gonderim_tarihi, y.yayin_tarihi)
WHERE d.is_active = 1;

CREATE TABLE IF NOT EXISTS bordro_yayin_talep_ek (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    talep_id BIGINT UNSIGNED NOT NULL,
    dosya_adi VARCHAR(255) NOT NULL,
    dosya_yolu VARCHAR(500) NOT NULL,
    mime_tipi VARCHAR(100) NOT NULL,
    dosya_boyutu INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    KEY ix_bordro_yayin_talep_ek (talep_id, is_active),
    CONSTRAINT fk_bordro_yayin_talep_ek_talep FOREIGN KEY (talep_id) REFERENCES bordro_yayin_talep(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
