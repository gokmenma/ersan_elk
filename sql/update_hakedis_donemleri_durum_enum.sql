-- Hakediş dönemleri durum alanına 'hazirlandi' değerini ekleme
ALTER TABLE hakedis_donemleri 
MODIFY COLUMN durum ENUM('taslak', 'hazirlandi', 'onaylandi', 'tamamlandi') DEFAULT 'taslak';
