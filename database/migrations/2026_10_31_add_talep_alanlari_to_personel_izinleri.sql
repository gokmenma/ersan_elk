ALTER TABLE personel_izinleri
    ADD COLUMN talep_baslangic_tarihi DATE NULL AFTER bitis_tarihi,
    ADD COLUMN talep_bitis_tarihi DATE NULL AFTER talep_baslangic_tarihi,
    ADD COLUMN talep_gun DECIMAL(6,2) NULL AFTER talep_bitis_tarihi;
