-- Ömer Faruk Dağ (Personel ID: 236, Dönem ID: 26, Bordro Personel ID: 1818)
-- Kural 5 gereği yemek tavanını (7.200 TL) aşan 496,20 TL'lik kaçak ihbar primi banka matrahına eklenmeyip
-- doğrudan elden ödemeye aktarılır:
-- Banka Net: 28.779,80 TL (33.403,80 TL resmî alacak - 4.624,00 TL kesinti)
-- Elden Net: 496,20 TL
-- Toplam Net: 29.276,00 TL

UPDATE bordro_personel 
SET banka_odemesi = 28779.80,
    elden_odeme = 496.20,
    hesaplama_detay = JSON_SET(
        hesaplama_detay, 
        '$.odeme_dagilimi.banka_net', 28779.80,
        '$.odeme_dagilimi.elden', 496.20
    )
WHERE id = 1818;
