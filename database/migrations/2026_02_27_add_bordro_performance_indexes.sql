-- Bordro Sayfa Performans İyileştirmesi için İndeksler
-- Bu indeksler bordro list sayfasındaki JOINed subquery'leri hızlandırır

-- personel_kesintileri tablosu indeksleri
CREATE INDEX IF NOT EXISTS idx_pk_donem_personel 
    ON personel_kesintileri(donem_id, personel_id, silinme_tarihi, durum, tur);

CREATE INDEX IF NOT EXISTS idx_pk_donem_silinme 
    ON personel_kesintileri(donem_id, silinme_tarihi);

-- personel_ek_odemeler tablosu indeksleri
CREATE INDEX IF NOT EXISTS idx_peo_donem_personel 
    ON personel_ek_odemeler(donem_id, personel_id, silinme_tarihi, durum);

CREATE INDEX IF NOT EXISTS idx_peo_donem_silinme 
    ON personel_ek_odemeler(donem_id, silinme_tarihi);

-- personel_ekip_gecmisi tablosu indeksleri
CREATE INDEX IF NOT EXISTS idx_peg_personel_tarih 
    ON personel_ekip_gecmisi(personel_id, firma_id, baslangic_tarihi, bitis_tarihi);

-- bordro_personel tablosu - covering index
CREATE INDEX IF NOT EXISTS idx_bp_donem_silinme 
    ON bordro_personel(donem_id, silinme_tarihi, personel_id);
