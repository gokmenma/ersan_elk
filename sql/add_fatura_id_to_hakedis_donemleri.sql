-- Hakediş dönemleri tablosuna fatura_id alanının eklenmesi
ALTER TABLE hakedis_donemleri 
ADD COLUMN fatura_id INT NULL DEFAULT NULL AFTER durum,
ADD CONSTRAINT fk_hakedis_donem_fatura FOREIGN KEY (fatura_id) REFERENCES faturalar(id) ON DELETE SET NULL;
