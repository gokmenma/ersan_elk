-- Personel Tipi Kolonu: Standart şirket personeli veya KASKI/Saha Kaçak Bildirim personeli ayrımı
ALTER TABLE personel 
ADD COLUMN personel_tipi ENUM('standart', 'kaski_kacak') NOT NULL DEFAULT 'standart' AFTER firma_id,
ADD INDEX idx_personel_tipi (personel_tipi);
