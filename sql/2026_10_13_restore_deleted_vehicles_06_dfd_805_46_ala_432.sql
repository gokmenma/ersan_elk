START TRANSACTION;

SET @firma_id := 1;
SET @arac_06_id := 159;
SET @arac_46_id := 164;
SET @silinme_06 := '2026-10-12 10:42:31';
SET @silinme_46 := '2026-10-12 16:52:19';

UPDATE araclar
SET silinme_tarihi = NULL,
    aktif_mi = 1
WHERE firma_id = @firma_id
  AND ((id = @arac_06_id AND plaka = '06 DFD 805' AND silinme_tarihi = @silinme_06)
    OR (id = @arac_46_id AND plaka = '46 ALA 432' AND silinme_tarihi = @silinme_46));

UPDATE arac_yakit_kayitlari
SET silinme_tarihi = NULL,
    silen_kullanici = NULL
WHERE firma_id = @firma_id
  AND ((arac_id = @arac_06_id AND silinme_tarihi = @silinme_06)
    OR (arac_id = @arac_46_id AND silinme_tarihi = @silinme_46));

UPDATE arac_km_kayitlari
SET silinme_tarihi = NULL,
    silen_kullanici = NULL
WHERE firma_id = @firma_id
  AND ((arac_id = @arac_06_id AND silinme_tarihi = @silinme_06)
    OR (arac_id = @arac_46_id AND silinme_tarihi = @silinme_46));

UPDATE arac_zimmetleri
SET silinme_tarihi = NULL,
    silen_kullanici = NULL
WHERE firma_id = @firma_id
  AND ((arac_id = @arac_06_id AND silinme_tarihi = @silinme_06)
    OR (arac_id = @arac_46_id AND silinme_tarihi = @silinme_46));

UPDATE arac_servis_kayitlari
SET silinme_tarihi = NULL,
    silen_kullanici = NULL
WHERE firma_id = @firma_id
  AND ((arac_id = @arac_06_id AND silinme_tarihi = @silinme_06)
    OR (arac_id = @arac_46_id AND silinme_tarihi = @silinme_46));

UPDATE arac_sigorta_kayitlari
SET silinme_tarihi = NULL,
    silen_kullanici = NULL
WHERE firma_id = @firma_id
  AND ((arac_id = @arac_06_id AND silinme_tarihi = @silinme_06)
    OR (arac_id = @arac_46_id AND silinme_tarihi = @silinme_46));

UPDATE arac_bakim_kayitlari
SET silinme_tarihi = NULL,
    silen_kullanici = NULL
WHERE firma_id = @firma_id
  AND ((arac_id = @arac_06_id AND silinme_tarihi = @silinme_06)
    OR (arac_id = @arac_46_id AND silinme_tarihi = @silinme_46));

COMMIT;

SELECT id, plaka, aktif_mi, ikame_mi, silinme_tarihi
FROM araclar
WHERE firma_id = @firma_id AND id IN (@arac_06_id, @arac_46_id);

SELECT arac_id, COUNT(*) AS aktif_yakit_kaydi
FROM arac_yakit_kayitlari
WHERE firma_id = @firma_id
  AND arac_id IN (@arac_06_id, @arac_46_id)
  AND silinme_tarihi IS NULL
GROUP BY arac_id;

SELECT arac_id, COUNT(*) AS aktif_km_kaydi
FROM arac_km_kayitlari
WHERE firma_id = @firma_id
  AND arac_id IN (@arac_06_id, @arac_46_id)
  AND silinme_tarihi IS NULL
GROUP BY arac_id;
