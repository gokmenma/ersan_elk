ALTER TABLE `evrak_takip`
    ADD INDEX IF NOT EXISTS `idx_evrak_list_firma_tarih` (`firma_id`, `silinme_tarihi`, `tarih`, `id`),
    ADD INDEX IF NOT EXISTS `idx_evrak_list_firma_tip` (`firma_id`, `silinme_tarihi`, `evrak_tipi`, `tarih`),
    ADD INDEX IF NOT EXISTS `idx_evrak_list_firma_onay` (`firma_id`, `silinme_tarihi`, `onay_durumu`, `id`);

ALTER TABLE `evrak_takip_onaylari`
    ADD INDEX IF NOT EXISTS `idx_evrak_onay_bekleyen_sira` (`firma_id`, `durum`, `kullanici_id`, `evrak_id`, `sira`);
