-- Mühür ve Conta iş sonucunu 'muhurleme' rapor sekmesine atama
UPDATE tanimlamalar 
SET rapor_sekmesi = 'muhurleme' 
WHERE grup = 'is_turu' 
  AND (is_emri_sonucu = 'MÜHÜR  VE CONTA' OR is_emri_sonucu = 'MÜHÜR VE CONTA');

-- Mühürleme ekip aralığı ayarına 1-40 aralığını ekleme (Mühürlemeyi de Kesme ekipleri yaptığı için)
UPDATE settings 
SET set_value = '1-40,61-70' 
WHERE set_name = 'ekip_aralik_muhurleme' AND (firma_id = 1 OR firma_id IS NULL);
