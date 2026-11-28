-- Tuncay Dal isimli personelin aktif ekip atamasında ekip_sefi_mi alanının 1 olarak güncellenmesi
-- Tarih: 2026-11-28

UPDATE personel_ekip_gecmisi peg
INNER JOIN personel p ON p.id = peg.personel_id
SET peg.ekip_sefi_mi = 1,
    peg.updated_at = NOW()
WHERE p.adi_soyadi = 'TUNCAY DAL'
  AND p.silinme_tarihi IS NULL
  AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())
  AND peg.ekip_sefi_mi = 0;
