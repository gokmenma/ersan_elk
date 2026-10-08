-- Soft-delete edilmiş personellerin açık bordro dönemlerinde kalan aktif satırlarını temizler.
-- Kapalı dönemler tarihsel bordro kayıtlarını korumak için kapsam dışındadır.

START TRANSACTION;

UPDATE bordro_personel bp
INNER JOIN personel p ON p.id = bp.personel_id
INNER JOIN bordro_donemi bd ON bd.id = bp.donem_id
SET bp.silinme_tarihi = NOW()
WHERE bp.silinme_tarihi IS NULL
  AND p.silinme_tarihi IS NOT NULL
  AND bd.silinme_tarihi IS NULL
  AND bd.kapali_mi = 0;

COMMIT;
