-- Kaçak ihbar primini genel primden ayırarak tamamen parametre bazlı yönetir.
-- Bankadan ödeme sistemde resmî ödeme olduğundan varsayılan parametre resmî ve banka kanallıdır.

INSERT INTO bordro_parametreleri (
    firma_id, kod, etiket, kategori, hesaplama_tipi, odeme_yontemi,
    gunluk_muaf_limit, aylik_muaf_limit, muaf_limit_tipi,
    sgk_matrahi_dahil, resmi_alacagina_dahil, gelir_vergisi_dahil,
    damga_vergisi_dahil, icra_pirim_dahil, gecerlilik_baslangic,
    varsayilan_tutar, gunluk_tutar, gun_sayisi_otomatik,
    varsayilan_gun_sayisi, oran, aciklama, sira, aktif, created_at, updated_at
)
SELECT
    1, 'kacak_ihbar_primi', 'Olumlu Kaçak İhbar Primi', 'gelir', 'net', 'banka',
    0, 0, 'yok', 0, 1, 0, 0, 0, '2026-01-01',
    100, 0, 0, 26, 0,
    'Bildirdiği kaçak ihbarı olumlu sonuçlanan personele ödenen birim prim tutarı',
    6, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM bordro_parametreleri WHERE kod = 'kacak_ihbar_primi'
);

UPDATE bordro_parametreleri
SET odeme_yontemi = 'banka',
    resmi_alacagina_dahil = 1,
    updated_at = NOW()
WHERE kod = 'kacak_ihbar_primi';

UPDATE personel_ek_odemeler
SET tur = 'kacak_ihbar_primi',
    banka_matrahina_ekle = 1,
    updated_at = NOW()
WHERE tur = 'prim'
  AND aciklama LIKE '[Kaçak İhbar Primi]%'
  AND silinme_tarihi IS NULL;
