-- Kaçak kontrol personeli olmayan personellere yapılmış hatalı ihbar atamalarını soft-delete yap
UPDATE ihbar_atamalar a
JOIN personel p ON p.id = a.personel_id
SET a.silinme_tarihi = NOW()
WHERE a.silinme_tarihi IS NULL
  AND NOT (
      p.departman LIKE '%Kaçak%'
      OR p.departman LIKE '%kacak%'
      OR p.departman LIKE '%KACAK%'
      OR p.personel_tipi = 'kaski_kacak'
      OR FIND_IN_SET('kacak', COALESCE(p.gorunum_modulleri, '')) > 0
  );

-- Aktif ve geçerli ataması kalmayan ancak durumu 'yonlendirildi' kalmış ihbarları 'yeni' durumuna geri al
UPDATE ihbarlar i
SET i.durum = 'yeni'
WHERE i.durum = 'yonlendirildi'
  AND i.silinme_tarihi IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM ihbar_atamalar a
      WHERE a.ihbar_id = i.id
        AND a.silinme_tarihi IS NULL
  );