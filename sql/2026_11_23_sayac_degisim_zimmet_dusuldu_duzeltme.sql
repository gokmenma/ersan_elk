UPDATE sayac_degisim sd
JOIN demirbas_hareketler h
    ON h.islem_id = sd.islem_id
   AND h.hareket_tipi IN ('sarf', 'iade')
   AND h.silinme_tarihi IS NULL
SET sd.zimmet_dusuldu = 1
WHERE sd.zimmet_dusuldu = 0
  AND sd.silinme_tarihi IS NULL
  AND sd.takilan_sayacno IS NOT NULL
  AND sd.takilan_sayacno <> '';
