-- Eski SOAP kayıtlarında giriş sırları bulunabilir. Kayıtlar silinmez.
-- JSON ve serbest metin biçimlerini kapsamak için giriş/firma payloadları tamamen maskelenir.
UPDATE efatura_loglari SET istek_payload = '[MASKED: eski kimlik doğrulama isteği]', yanit_payload = '[MASKED: eski kimlik doğrulama yanıtı]' WHERE islem_turu IN ('Login', 'Logout', 'GetCompany');
-- Other historic payloads can contain SESSION_ID in nested headers.
UPDATE efatura_loglari SET istek_payload = '[MASKED: eski oturum bilgisi]' WHERE istek_payload LIKE '%SESSION_ID%' OR istek_payload LIKE '%PASSWORD%' OR istek_payload LIKE '%GIB_PASS%';
UPDATE efatura_loglari SET yanit_payload = '[MASKED: eski oturum bilgisi]' WHERE yanit_payload LIKE '%SESSION_ID%' OR yanit_payload LIKE '%PASSWORD%' OR yanit_payload LIKE '%GIB_PASS%';
