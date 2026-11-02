START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_mutabakat_ucretsiz_sonuc;

CREATE TEMPORARY TABLE tmp_mutabakat_ucretsiz_sonuc (
    firma_id INT NOT NULL,
    tur_adi VARCHAR(90) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    is_emri_sonucu VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (firma_id, tur_adi, is_emri_sonucu)
) ENGINE=InnoDB;

INSERT IGNORE INTO tmp_mutabakat_ucretsiz_sonuc (firma_id, tur_adi, is_emri_sonucu)
SELECT t.firma_id,
       TRIM(CONVERT(t.tur_adi USING utf8mb4)),
       TRIM(CONVERT(t.is_emri_sonucu USING utf8mb4))
FROM tanimlamalar t
WHERE t.grup = 'is_turu'
  AND t.silinme_tarihi IS NULL
  AND COALESCE(t.is_turu_ucret, 0) <= 0
  AND COALESCE(t.aracli_personel_is_turu_ucret, 0) <= 0
  AND COALESCE(t.unvan_ucret, 0) <= 0
  AND NOT EXISTS (
      SELECT 1 FROM personel_is_turu_ucretleri piu
      WHERE piu.is_turu_id = t.id AND piu.aktif = 1 AND piu.silinme_tarihi IS NULL
        AND (piu.ucret > 0 OR piu.aracli_ucret > 0)
  )
  AND NOT EXISTS (
      SELECT 1 FROM tanimlamalar p
      WHERE p.firma_id = t.firma_id AND p.grup = 'is_turu' AND p.silinme_tarihi IS NULL
        AND TRIM(CONVERT(p.tur_adi USING utf8mb4)) = TRIM(CONVERT(t.tur_adi USING utf8mb4)) COLLATE utf8mb4_unicode_ci
        AND TRIM(CONVERT(p.is_emri_sonucu USING utf8mb4)) = TRIM(CONVERT(t.is_emri_sonucu USING utf8mb4)) COLLATE utf8mb4_unicode_ci
        AND (COALESCE(p.is_turu_ucret, 0) > 0 OR COALESCE(p.aracli_personel_is_turu_ucret, 0) > 0 OR COALESCE(p.unvan_ucret, 0) > 0
             OR EXISTS (
                 SELECT 1 FROM personel_is_turu_ucretleri pp
                 WHERE pp.is_turu_id = p.id AND pp.aktif = 1 AND pp.silinme_tarihi IS NULL
                   AND (pp.ucret > 0 OR pp.aracli_ucret > 0)
             ))
  );

UPDATE puantaj_mutabakat_fark f
INNER JOIN puantaj_mutabakat_donem d ON d.id = f.donem_id
INNER JOIN tmp_mutabakat_ucretsiz_sonuc u
        ON u.firma_id = d.firma_id
       AND u.tur_adi = TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(f.yeni_payload_json, '$.is_emri_tipi')),
                                     JSON_UNQUOTE(JSON_EXTRACT(f.eski_payload_json, '$.is_emri_tipi'))))
       AND u.is_emri_sonucu = TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(f.yeni_payload_json, '$.is_emri_sonucu')),
                                            JSON_UNQUOTE(JSON_EXTRACT(f.eski_payload_json, '$.is_emri_sonucu'))))
SET f.is_active = 0,
    f.deleted_at = NOW()
WHERE f.kaynak IN ('kesme', 'muhurleme')
  AND f.durum = 'acik'
  AND f.is_active = 1;

UPDATE puantaj_mutabakat_donem d
SET d.acik_fark_sayisi = (
    SELECT COUNT(*) FROM puantaj_mutabakat_fark f
    WHERE f.donem_id = d.id AND f.durum = 'acik' AND f.is_active = 1
      AND f.kontrol_gunu = (
          SELECT MAX(c.kontrol_gunu) FROM puantaj_mutabakat_calisma c
          WHERE c.donem_id = d.id AND c.durum = 'basarili'
      )
)
WHERE d.snapshot_hash IS NOT NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_mutabakat_ucretsiz_sonuc;

COMMIT;
