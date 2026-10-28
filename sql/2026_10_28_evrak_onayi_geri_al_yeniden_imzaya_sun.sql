SET @evrak_id := 91;
SET @islem_user_id := 0;
SET @geri_alma_notu := 'Yönetici müdahalesiyle onay süreci geri alındı ve evrak yeniden imzaya sunuldu';

START TRANSACTION;

SET @firma_id := (
    SELECT `firma_id` FROM `evrak_takip`
    WHERE `id` = @evrak_id AND `evrak_tipi` = 'giden' AND `silinme_tarihi` IS NULL
      AND (`onay_durumu` IN ('onaylandi', 'onay_bekliyor')
           OR (`onay_durumu` = 'taslak' AND EXISTS (SELECT 1 FROM `evrak_takip_onaylari` ot WHERE ot.`evrak_id` = @evrak_id AND ot.`durum` = 'onaylandi')))
      AND (`kep_mesaj_id` IS NULL OR `kep_mesaj_id` = '')
      AND NOT EXISTS (SELECT 1 FROM `kep_mesajlar` km WHERE km.`evrak_id` = @evrak_id AND km.`yon` = 'giden' AND km.`deleted_at` IS NULL)
    LIMIT 1
);

INSERT INTO `evrak_tarihce` (`firma_id`, `evrak_id`, `islem`, `user_id`, `hedef_user_id`, `aciklama`, `created_at`)
SELECT @firma_id, @evrak_id, 'onay_geri_alindi', @islem_user_id, NULL,
       CONCAT(@geri_alma_notu, ' (önceki durum: ', et.`onay_durumu`, ')'), NOW()
FROM `evrak_takip` et
WHERE @firma_id IS NOT NULL AND et.`id` = @evrak_id;

UPDATE `evrak_takip_onaylari`
SET `durum` = 'bekliyor', `onay_tarihi` = NULL, `ip_adresi` = NULL,
    `imza_turu` = NULL, `imza_yontemi` = NULL, `sertifika_ozne` = NULL, `sertifika_veren` = NULL,
    `sertifika_seri_no` = NULL, `sertifika_parmak_izi` = NULL,
    `sertifika_gecerlilik_baslangic` = NULL, `sertifika_gecerlilik_bitis` = NULL,
    `imzali_pdf_sha256` = NULL, `imza_dogrulama_durumu` = NULL
WHERE @firma_id IS NOT NULL AND `evrak_id` = @evrak_id AND `firma_id` = @firma_id;

UPDATE `evrak_takip`
SET `onay_durumu` = 'onay_bekliyor',
    `e_imza_onay_tarihi` = NULL, `e_imza_belge_ozeti` = NULL,
    `pades_pdf_yolu` = NULL, `pades_pdf_sha256` = NULL
WHERE @firma_id IS NOT NULL AND `id` = @evrak_id AND `firma_id` = @firma_id;

INSERT INTO `evrak_tarihce` (`firma_id`, `evrak_id`, `islem`, `user_id`, `hedef_user_id`, `aciklama`, `created_at`)
SELECT @firma_id, @evrak_id, 'onay_sunuldu', @islem_user_id,
       (SELECT o.`kullanici_id` FROM `evrak_takip_onaylari` o WHERE o.`evrak_id` = @evrak_id AND o.`firma_id` = @firma_id ORDER BY o.`sira` ASC, o.`id` ASC LIMIT 1),
       'Evrak yeniden imza sürecine alındı; imzalar sıfırlandı', NOW()
FROM `evrak_takip` et
WHERE @firma_id IS NOT NULL AND et.`id` = @evrak_id;

SELECT @firma_id IS NOT NULL AS islem_yapildi;

COMMIT;
