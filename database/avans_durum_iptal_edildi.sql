ALTER TABLE personel_avanslari
    MODIFY COLUMN durum ENUM('beklemede','onaylandi','reddedildi','odendi','iptal edildi') NOT NULL DEFAULT 'beklemede';

UPDATE personel_avanslari
SET durum = 'iptal edildi'
WHERE (durum = '' OR durum IS NULL)
  AND aciklama LIKE '%Personel tarafından iptal edildi%';
