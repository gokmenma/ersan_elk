-- Olumlu Kaçak İhbar Primi Parametresi
-- Kaçak ihbarı olumlu sonuçlanan personellere verilecek birim prim (100 TL)

INSERT INTO `bordro_parametreleri` (
    `firma_id`,
    `kod`,
    `etiket`,
    `kategori`,
    `hesaplama_tipi`,
    `gunluk_muaf_limit`,
    `odeme_yontemi`,
    `aylik_muaf_limit`,
    `muaf_limit_tipi`,
    `sgk_matrahi_dahil`,
    `resmi_alacagina_dahil`,
    `gelir_vergisi_dahil`,
    `damga_vergisi_dahil`,
    `icra_pirim_dahil`,
    `gecerlilik_baslangic`,
    `gecerlilik_bitis`,
    `varsayilan_tutar`,
    `gunluk_tutar`,
    `gun_sayisi_otomatik`,
    `varsayilan_gun_sayisi`,
    `oran`,
    `aciklama`,
    `sira`,
    `aktif`,
    `created_at`
) 
SELECT 
    1,
    "kacak_ihbar_primi",
    "Olumlu Kaçak İhbar Primi",
    "gelir",
    "net",
    0.00,
    "diger",
    0.00,
    "yok",
    0,
    0,
    0,
    0,
    0,
    "2026-01-01",
    NULL,
    100.00,
    0.00,
    0,
    26,
    0.00,
    "Bildirdiği kaçak ihbarı olumlu sonuçlanan personele ödenen birim prim tutarı",
    6,
    1,
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `bordro_parametreleri` WHERE `kod` = "kacak_ihbar_primi" AND `firma_id` = 1
);
