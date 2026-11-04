ALTER TABLE personel_icralari
    ADD COLUMN kayit_yapan INT NULL DEFAULT NULL AFTER aciklama;

UPDATE personel_kesintileri pk
INNER JOIN personel_icralari pi ON pi.id = pk.icra_id
SET pk.kayit_yapan = pi.kayit_yapan
WHERE pk.tur = 'icra'
  AND pi.kayit_yapan IS NOT NULL
  AND (pk.kayit_yapan IS NULL OR pk.kayit_yapan <> pi.kayit_yapan);
