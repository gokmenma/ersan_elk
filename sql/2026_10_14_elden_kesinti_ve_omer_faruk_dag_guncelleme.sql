-- Elden kesintilerin elden tutarı aşan kısmının bankadan karşılanması kuralı gereğince,
-- Ömer Faruk Dağ (Personel ID: 236, Dönem ID: 26) için girilen kesintinin kanalının elden kesinti (banka_matrahina_ekle = 0) olarak güncellenmesi:

UPDATE personel_kesintileri
SET banka_matrahina_ekle = 0
WHERE id = 7851 AND personel_id = 236 AND donem_id = 26;

-- Not: Kesinti kanalı 'elden' olduğunda (banka_matrahina_ekle = 0),
-- kesinti tutarı öncelikle personelin kesinti öncesi elden hakedişinden düşülür.
-- Elden hakedişi aşan bakiye bankadan karşılanır ve muhasebe Excel çıktısında
-- yeşil resmi hakediş sütunlarından (asgari ücret / yemek) düşülmeyip
-- kırmızı kesinti sütununda (Diğer Kesintiler) gösterilir.
