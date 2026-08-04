-- Masaüstü ihbar formunda harita bağlantısı ve sistem tarafından yapılan otomatik atamalar.
ALTER TABLE `ihbarlar`
  ADD COLUMN `konum_link` varchar(1000) DEFAULT NULL AFTER `aciklama`;

ALTER TABLE `ihbar_atamalar`
  MODIFY COLUMN `atayan_user_id` int(11) DEFAULT NULL;
