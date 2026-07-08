-- Add 'elden_tutardan' to bordro_parametreleri.hesaplama_tipi
ALTER TABLE bordro_parametreleri MODIFY COLUMN hesaplama_tipi enum(
    'brut', 'net', 'kismi_muaf', 'oran_bazli', 'netten', 'brutten', 'sgk_matrahindan',
    'oran_bazli_vergi', 'oran_bazli_sgk', 'oran_bazli_net', 'gunluk_brut', 'gunluk_net',
    'gunluk_kismi_muaf', 'aylik_gun_brut', 'aylik_gun_net', 'gunluk_kesinti', 'aylik_gun_kesinti',
    'aylik_fiili_gun_net', 'elden_tutardan'
) NOT NULL DEFAULT 'net';

-- Add 'elden_tutardan' to personel_kesintileri.hesaplama_tipi
ALTER TABLE personel_kesintileri MODIFY COLUMN hesaplama_tipi enum(
    'sabit', 'oran_net', 'oran_brut', 'asgari_oran_net', 'aylik_gun_kesinti',
    'elden_tutardan'
) DEFAULT 'sabit';
