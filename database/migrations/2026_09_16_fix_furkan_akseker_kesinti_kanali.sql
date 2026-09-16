-- Furkan Akşeker / Ağustos 2026: arayüzde Elden / Harici olarak tanımlanan
-- iki kesintinin ödeme kanalını sunucu verisinde de elden olarak düzeltir.
UPDATE personel_kesintileri pk
JOIN personel p ON p.id = pk.personel_id
JOIN bordro_donemi bd ON bd.id = pk.donem_id
SET pk.banka_matrahina_ekle = 0,
    pk.updated_at = NOW()
WHERE pk.id IN (7709, 7802)
  AND p.adi_soyadi = 'FURKAN AKŞEKER'
  AND bd.baslangic_tarihi = '2026-08-01'
  AND pk.silinme_tarihi IS NULL;
