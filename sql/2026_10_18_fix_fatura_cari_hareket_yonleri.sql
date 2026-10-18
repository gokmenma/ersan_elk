-- 2026-10-18: Fatura Cari Hareketleri Borç/Alacak Yönü Düzeltmesi
-- Gelen e-Fatura (Alış): Bizim borcumuzu artırır -> borc sütununa (GİRİŞ/ALD.) kaydedilmelidir.
-- Giden e-Fatura (Satış): Bizim alacağımızı artırır -> alacak sütununa (ÇIKIŞ/VERD.) kaydedilmelidir.

-- 1. Belge numarası bilinen ve fatura tablosunda ID eşleşmesi bulunan kayıtların fatura_id ve yönlerinin düzeltilmesi
UPDATE cari_hareketleri ch
JOIN faturalar f ON f.fatura_no = ch.belge_no
SET 
    ch.fatura_id = f.id,
    ch.borc = CASE WHEN UPPER(TRIM(f.yon)) = 'GELEN' THEN f.odenecek_tutar ELSE 0.00 END,
    ch.alacak = CASE WHEN UPPER(TRIM(f.yon)) = 'GIDEN' THEN f.odenecek_tutar ELSE 0.00 END
WHERE (ch.fatura_id IS NOT NULL OR ch.aciklama LIKE '%e-Fatura%')
  AND ch.silinme_tarihi IS NULL;

-- 2. Halihazırda fatura_id ile bağlı tüm cari hareketlerinin borc/alacak alanlarının fatura yönüne göre güncellenmesi
UPDATE cari_hareketleri ch
JOIN faturalar f ON f.id = ch.fatura_id
SET 
    ch.borc = CASE WHEN UPPER(TRIM(f.yon)) = 'GELEN' THEN f.odenecek_tutar ELSE 0.00 END,
    ch.alacak = CASE WHEN UPPER(TRIM(f.yon)) = 'GIDEN' THEN f.odenecek_tutar ELSE 0.00 END
WHERE ch.fatura_id IS NOT NULL
  AND ch.silinme_tarihi IS NULL;
