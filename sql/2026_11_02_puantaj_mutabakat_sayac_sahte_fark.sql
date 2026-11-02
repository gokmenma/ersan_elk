START TRANSACTION;

UPDATE puantaj_mutabakat_fark f
SET f.is_active = 0,
    f.deleted_at = NOW()
WHERE f.kaynak = 'sayac'
  AND f.fark_tipi = 'alan_degismis'
  AND f.durum = 'acik'
  AND f.is_active = 1
  AND JSON_REMOVE(f.eski_payload_json, '$.sonuc_aciklama') = JSON_REMOVE(f.yeni_payload_json, '$.sonuc_aciklama')
  AND TRIM(COALESCE(JSON_VALUE(f.eski_payload_json, '$.sonuc_aciklama'), '')) = TRIM(COALESCE(JSON_VALUE(f.yeni_payload_json, '$.sonuc_aciklama'), ''));

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

COMMIT;
