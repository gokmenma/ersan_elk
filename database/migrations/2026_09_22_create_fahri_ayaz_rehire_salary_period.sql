-- Fahri Ayaz'ın 21.09.2026 tarihli yeniden işe giriş dönemi için eksik olan
-- görev/maaş dönemini son geçmiş kaydından üretir. Tekrar çalıştırılabilir.
INSERT INTO personel_gorev_gecmisi
    (personel_id, departman, gorev, maas_durumu, maas_tutari,
     baslangic_tarihi, bitis_tarihi, aciklama, olusturan_id)
SELECT
    p.id, onceki.departman, onceki.gorev, onceki.maas_durumu,
    onceki.maas_tutari, '2026-09-21', NULL,
    'Yeniden işe girişte önceki maaş bilgilerinden otomatik oluşturuldu.', 0
FROM personel AS p
INNER JOIN firmalar AS f ON f.id = p.firma_id
INNER JOIN personel_gorev_gecmisi AS onceki
    ON onceki.id = (
        SELECT pg.id
        FROM personel_gorev_gecmisi AS pg
        WHERE pg.personel_id = p.id
          AND pg.baslangic_tarihi < '2026-09-21'
        ORDER BY pg.baslangic_tarihi DESC, pg.id DESC
        LIMIT 1
    )
WHERE f.firma_kodu = 17
  AND p.adi_soyadi = 'FAHRİ AYAZ'
  AND p.silinme_tarihi IS NULL
  AND EXISTS (
      SELECT 1 FROM personel_calisma_gecmisi AS pcg
      WHERE pcg.personel_id = p.id
        AND pcg.ise_giris_tarihi = '2026-09-21'
  )
  AND NOT EXISTS (
      SELECT 1 FROM personel_gorev_gecmisi AS mevcut
      WHERE mevcut.personel_id = p.id
        AND mevcut.baslangic_tarihi <= '2026-09-21'
        AND (mevcut.bitis_tarihi IS NULL OR mevcut.bitis_tarihi >= '2026-09-21')
  );
