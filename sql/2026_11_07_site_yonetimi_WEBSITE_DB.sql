-- Hedef veritabanı: kurumsal site 'settings' veritabanı (yerel: ersan_website, canlı: ersantrc_website).
-- ===== 2026_11_07_site_kariyer_personel_ayarlari.sql =====
-- 1449b47336b71a6ea6c50e4a76266c5286fef3e4a96e71b6c7a2503b34a19495 alanları: personel sistemi .env içindeki KARIYER_API_KEY ile aynı anahtar,
-- personel_url ise personel sisteminin kök adresi olmalıdır (sonunda / olmadan).
INSERT INTO settings (setting_key, setting_value) VALUES
    ('personel_url', 'https://PERSONEL_ALAN_ADI'),
    ('personel_api_key', 'DEGER_BURAYA')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

