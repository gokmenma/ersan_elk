UPDATE aparat_hareket h
INNER JOIN aparat_otomatik_kirma k ON k.id = h.referans_id AND k.firma_id = h.firma_id
INNER JOIN (
    SELECT y.firma_id, y.personel_id, y.tarih, y.is_emri_sonucu_id, MAX(y.created_at) AS kayit_zamani
    FROM yapilan_isler y
    GROUP BY y.firma_id, y.personel_id, y.tarih, y.is_emri_sonucu_id
) z ON z.firma_id = k.firma_id AND z.personel_id = k.personel_id
   AND z.tarih = k.tarih AND z.is_emri_sonucu_id = k.is_emri_sonucu_id
SET h.tarih = z.kayit_zamani,
    h.aciklama = CONCAT('Aparat kırma: abonedeki aparat hurdaya ayrıldı (iş tarihi ', DATE_FORMAT(k.tarih, '%d.%m.%Y'), ', puantajdan).')
WHERE h.referans_tipi = 'otomatik_kirma'
  AND h.adet <> 0
  AND TIME(h.tarih) = '12:00:00'
  AND h.aciklama LIKE 'Aparat kırma (puantaj)%'
  AND z.kayit_zamani IS NOT NULL;
