UPDATE personel_kesintileri pk
INNER JOIN personel_avanslari pa
    ON pa.id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(pk.aciklama, '#', -1), '/', 1) AS UNSIGNED)
   AND pa.personel_id = pk.personel_id
SET pk.kayit_yapan = pa.onaylayan_id
WHERE pk.tur = 'avans'
  AND pk.aciklama LIKE '[Avans] #%/%-%'
  AND pa.onaylayan_id IS NOT NULL
  AND (pk.kayit_yapan IS NULL OR pk.kayit_yapan <> pa.onaylayan_id);
