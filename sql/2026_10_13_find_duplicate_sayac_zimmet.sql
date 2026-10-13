-- Demirbaş > Zimmet ekranındaki çift kayıtları salt-okunur olarak tespit eder.
-- Hiçbir kayıt değiştirmez veya silmez.
-- Çift gönderimler normalde birkaç saniye içinde oluşur; gerekirse pencereyi artırın.
SET @duplicate_window_seconds := 120;

SELECT
    first_zimmet.id AS ilk_zimmet_id,
    duplicate_zimmet.id AS mukerrer_zimmet_id,
    first_zimmet.demirbas_id AS ilk_demirbas_id,
    duplicate_zimmet.demirbas_id AS mukerrer_demirbas_id,
    duplicate_asset.firma_id,
    duplicate_asset.seri_no,
    duplicate_asset.demirbas_adi,
    duplicate_asset.marka,
    duplicate_asset.model,
    personel.adi_soyadi AS personel_adi,
    duplicate_zimmet.teslim_tarihi,
    first_zimmet.kayit_tarihi AS ilk_kayit_tarihi,
    duplicate_zimmet.kayit_tarihi AS mukerrer_kayit_tarihi,
    TIMESTAMPDIFF(
        SECOND,
        first_zimmet.kayit_tarihi,
        duplicate_zimmet.kayit_tarihi
    ) AS kayit_araligi_saniye
FROM `demirbas_zimmet` AS duplicate_zimmet
INNER JOIN `demirbas` AS duplicate_asset
    ON duplicate_asset.id = duplicate_zimmet.demirbas_id
   AND duplicate_asset.silinme_tarihi IS NULL
INNER JOIN `tanimlamalar` AS kategori
    ON kategori.id = duplicate_asset.kategori_id
   AND kategori.grup = 'demirbas_kategorisi'
   AND kategori.silinme_tarihi IS NULL
   AND (
       LOWER(kategori.tur_adi) LIKE '%sayaç%'
       OR LOWER(kategori.tur_adi) LIKE '%sayac%'
   )
INNER JOIN `demirbas_zimmet` AS first_zimmet
    ON first_zimmet.personel_id = duplicate_zimmet.personel_id
   AND first_zimmet.teslim_tarihi = duplicate_zimmet.teslim_tarihi
   AND first_zimmet.durum = 'teslim'
   AND first_zimmet.silinme_tarihi IS NULL
   AND first_zimmet.id < duplicate_zimmet.id
   AND TIMESTAMPDIFF(
       SECOND,
       first_zimmet.kayit_tarihi,
       duplicate_zimmet.kayit_tarihi
   ) BETWEEN 0 AND @duplicate_window_seconds
INNER JOIN `demirbas` AS first_asset
    ON first_asset.id = first_zimmet.demirbas_id
   AND first_asset.firma_id = duplicate_asset.firma_id
   AND first_asset.silinme_tarihi IS NULL
   AND LOWER(TRIM(first_asset.seri_no)) = LOWER(TRIM(duplicate_asset.seri_no))
INNER JOIN `personel` AS personel
    ON personel.id = duplicate_zimmet.personel_id
WHERE duplicate_zimmet.durum = 'teslim'
  AND duplicate_zimmet.silinme_tarihi IS NULL
  AND NULLIF(TRIM(duplicate_asset.seri_no), '') IS NOT NULL
  AND LOWER(TRIM(duplicate_asset.seri_no)) NOT IN (
      '0', '-', '--', 'yok', 'yoktur', 'belirsiz', 'n/a', 'na'
  )
ORDER BY duplicate_asset.seri_no, duplicate_zimmet.kayit_tarihi, duplicate_zimmet.id;
