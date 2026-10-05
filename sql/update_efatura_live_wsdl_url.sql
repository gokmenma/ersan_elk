-- EDM Canlı Ortam WSDL URL Güncellemesi
-- Tarih: 2026-10-05
UPDATE efatura_ayarlar 
SET live_wsdl_url = 'https://portal2.edmbilisim.com.tr/EFaturaEDM/EFaturaEDM.svc?wsdl' 
WHERE live_wsdl_url LIKE '%efatura.edmbilisim.com.tr%' OR live_wsdl_url IS NULL OR live_wsdl_url = '';
