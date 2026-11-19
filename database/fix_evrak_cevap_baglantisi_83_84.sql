UPDATE evrak_takip
SET ilgili_evrak_id = 112
WHERE id = 119
  AND evrak_tipi = 'giden'
  AND ilgili_evrak_id = 113;

UPDATE evrak_takip
SET cevap_verildi_mi = 1,
    cevap_tarihi = COALESCE(cevap_tarihi, '2026-11-17')
WHERE id = 112
  AND evrak_tipi = 'gelen';
