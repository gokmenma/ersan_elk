-- Destek talepleri için yönetici onay akışı alanları
ALTER TABLE destek_biletleri
  ADD COLUMN onay_durumu ENUM('beklemede','onaylandi','reddedildi') NOT NULL DEFAULT 'onaylandi' AFTER durum,
  ADD COLUMN onaylayan_user_id INT(11) NULL DEFAULT NULL AFTER onay_durumu,
  ADD COLUMN onay_tarihi DATETIME NULL DEFAULT NULL AFTER onaylayan_user_id,
  ADD COLUMN onay_notu VARCHAR(500) NULL DEFAULT NULL AFTER onay_tarihi;

CREATE INDEX idx_destek_onay_durumu ON destek_biletleri (onay_durumu, guncelleme_tarihi);

-- Mevcut kayıtlar doğrudan onaylı kabul edilir
UPDATE destek_biletleri
SET onay_durumu = 'onaylandi'
WHERE onay_durumu IS NULL;
