ALTER TABLE evrak_takip
    ADD COLUMN IF NOT EXISTS gelen_tur ENUM('islem','bilgi') NOT NULL DEFAULT 'islem' AFTER evrak_tipi;

ALTER TABLE evrak_takip
    ADD COLUMN IF NOT EXISTS cevap_metni TEXT NULL AFTER cevap_tarihi;

CREATE TABLE IF NOT EXISTS evrak_takip_cevap_ekleri (
    id INT NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    evrak_id INT NOT NULL,
    dosya_adi VARCHAR(255) NOT NULL,
    dosya_yolu VARCHAR(555) NOT NULL,
    mime_tipi VARCHAR(120) NULL,
    dosya_boyutu INT NOT NULL DEFAULT 0,
    yukleyen_kullanici_id INT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    silinme_tarihi DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cevap_ek_evrak (evrak_id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
