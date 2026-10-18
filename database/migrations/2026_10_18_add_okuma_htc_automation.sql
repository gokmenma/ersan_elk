-- Okuma personelinin pazar çalışmasını otomatik HTÇ'ye dönüştürme izleme alanları.
-- Mevcut kayıtlar NULL bırakılır; böylece geliştirme öncesindeki HT kayıtları ilk
-- otomasyon çalışmasında dönüştürülebilir. Yeni manuel kayıt yolları kaynak='manuel'
-- ve manuel_kilit=1 yazar.

ALTER TABLE personel_izinleri
    ADD COLUMN kaynak VARCHAR(20) NULL AFTER silinme_aciklama,
    ADD COLUMN manuel_kilit TINYINT(1) NOT NULL DEFAULT 0 AFTER kaynak,
    ADD COLUMN otomatik_kural VARCHAR(50) NULL AFTER manuel_kilit,
    ADD COLUMN otomatik_okuma_sayisi DECIMAL(12,2) NULL AFTER otomatik_kural,
    ADD INDEX idx_personel_izinleri_personel_tarih_aktif
        (personel_id, baslangic_tarihi, bitis_tarihi, silinme_tarihi);

CREATE TABLE IF NOT EXISTS puantaj_otomasyon_kilitleri (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    personel_id INT NOT NULL,
    tarih DATE NOT NULL,
    otomatik_kural VARCHAR(50) NOT NULL,
    secilen_kod VARCHAR(50) NULL,
    kilitleyen_kullanici_id INT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_puantaj_otomasyon_kilidi (firma_id, personel_id, tarih, otomatik_kural),
    KEY idx_puantaj_otomasyon_kilit_tarih (firma_id, tarih, otomatik_kural),
    CONSTRAINT fk_puantaj_otomasyon_kilit_personel
        FOREIGN KEY (personel_id) REFERENCES personel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO settings (set_name, set_value, firma_id, user_id)
SELECT 'okuma_htc_otomatik_aktif', '1', f.id, NULL
FROM firmalar f
WHERE f.silinme_tarihi IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM settings s
      WHERE s.set_name = 'okuma_htc_otomatik_aktif'
        AND s.firma_id = f.id
        AND s.user_id IS NULL
  );

INSERT INTO settings (set_name, set_value, firma_id, user_id)
SELECT 'okuma_htc_minimum_okuma', '10', f.id, NULL
FROM firmalar f
WHERE f.silinme_tarihi IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM settings s
      WHERE s.set_name = 'okuma_htc_minimum_okuma'
        AND s.firma_id = f.id
        AND s.user_id IS NULL
  );
