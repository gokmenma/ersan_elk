-- Kaçak kayıtlarında fotoğrafı silindiği hâlde beklenen sayı düşmediği için
-- listede kalıcı "N bekleniyor" rozeti kalıyor. Yalnızca silinmiş veya
-- arşivlenmiş fotoğrafı bulunan kayıtlarda beklenen sayı mevcut adede çekilir;
-- yüklemesi hâlâ süren kayıtlara dokunulmaz.
--
-- Önce kontrol (uygulamadan önce çalıştırıp etkilenecek kayıtları görün):
-- SELECT k.id, k.tutanak_no, k.beklenen_foto_sayisi,
--        (SELECT COUNT(*) FROM kacak_kontrol_fotograflari f
--          WHERE f.kacak_id = k.id AND f.medya_tipi = 'foto'
--            AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0) AS mevcut,
--        (SELECT COUNT(*) FROM kacak_kontrol_fotograflari f
--          WHERE f.kacak_id = k.id AND f.medya_tipi = 'foto'
--            AND (f.silinme_tarihi IS NOT NULL OR f.arsivlendi = 1)) AS silinmis
--   FROM kacak_kontrol k
--  WHERE k.silinme_tarihi IS NULL
-- HAVING silinmis > 0 AND k.beklenen_foto_sayisi > mevcut;

UPDATE kacak_kontrol k
   SET k.beklenen_foto_sayisi = (
        SELECT COUNT(*) FROM kacak_kontrol_fotograflari f
         WHERE f.kacak_id = k.id AND f.medya_tipi = 'foto'
           AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0
   )
 WHERE k.silinme_tarihi IS NULL
   AND k.beklenen_foto_sayisi > (
        SELECT COUNT(*) FROM kacak_kontrol_fotograflari f
         WHERE f.kacak_id = k.id AND f.medya_tipi = 'foto'
           AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0
   )
   AND EXISTS (
        SELECT 1 FROM kacak_kontrol_fotograflari f
         WHERE f.kacak_id = k.id AND f.medya_tipi = 'foto'
           AND (f.silinme_tarihi IS NOT NULL OR f.arsivlendi = 1)
   );
