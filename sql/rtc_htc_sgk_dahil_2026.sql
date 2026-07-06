-- RTÇ (Resmi Tatil Çalışması) ve HTÇ (Hafta Tatili Çalışması) ödemelerinde artık
-- SGK işçi payı + İşsizlik işçi payı da gross-up hesabına dahil edilecek.
-- Bu script ilgili parametrelerin "sgk_matrahi_dahil" bayrağını açar.

UPDATE bordro_parametreleri
SET sgk_matrahi_dahil = 1
WHERE kod IN ('resmi_tatil_calisma', 'hafta_tatili_calisma')
  AND aktif = 1;
