-- Personel PWA / Ekip Takibi performans indeksleri
-- Tekrar çalıştırılabilir (MariaDB IF NOT EXISTS desteği).

ALTER TABLE `endeks_okuma`
    ADD INDEX IF NOT EXISTS `idx_eo_firma_ekip_tarih_silinme`
        (`firma_id`, `ekip_kodu_id`, `tarih`, `silinme_tarihi`),
    ADD INDEX IF NOT EXISTS `idx_eo_firma_defter_silinme_tarih`
        (`firma_id`, `defter`, `silinme_tarihi`, `tarih`);

ALTER TABLE `personel_ekip_gecmisi`
    ADD INDEX IF NOT EXISTS `idx_peg_firma_ekip_tarihler`
        (`firma_id`, `ekip_kodu_id`, `baslangic_tarihi`, `bitis_tarihi`);

ALTER TABLE `tanimlamalar`
    ADD INDEX IF NOT EXISTS `idx_tanim_firma_grup_bolge_silinme`
        (`firma_id`, `grup`, `ekip_bolge`, `silinme_tarihi`),
    ADD INDEX IF NOT EXISTS `idx_tanim_firma_grup_defter_bolge_silinme`
        (`firma_id`, `grup`, `defter_bolge`, `silinme_tarihi`);

ANALYZE TABLE `endeks_okuma`, `personel_ekip_gecmisi`, `tanimlamalar`;
