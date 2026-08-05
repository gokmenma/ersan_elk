-- Evrak Takip Tablosuna Kimin Adına İmza Alanı (JSON) Ekleme Scripti
ALTER TABLE `evrak_takip` 
ADD COLUMN `imza_kimin_adina_json` text DEFAULT NULL AFTER `imza_kullanici_ids`;
