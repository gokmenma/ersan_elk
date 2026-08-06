-- Kaçak kontrol tutanaklarındaki ters sıralı ekip isimlerini ve personel ID'lerini standartlaştırma SQL scripti

UPDATE kacak_kontrol 
SET ekip_adi = 'ALİ AKKURT, BÜNYAMİN ATEŞ',
    personel_ids = '1,2' -- Uygun ID'ler
WHERE ekip_adi = 'BÜNYAMİN ATEŞ, ALİ AKKURT';

UPDATE kacak_kontrol 
SET ekip_adi = 'SÜLEYMAN GÜZEL, SÜLEYMAN YEŞİL'
WHERE ekip_adi = 'SÜLEYMAN YEŞİL, SÜLEYMAN GÜZEL';
