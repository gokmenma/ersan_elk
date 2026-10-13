-- Kaçak ihbar primi vergi ve SGK'ya tabi olmayan gayriresmî primdir, banka resmî alacağına dahil edilemez.
-- Maaşa dahil yemek yardımı mantığında primler yemek tavanını tamamlar, tavanı aşan bakiye ise elden ödenir (Kural 5).

UPDATE bordro_parametreleri 
SET resmi_alacagina_dahil = 0,
    updated_at = NOW()
WHERE kod = 'kacak_ihbar_primi';
