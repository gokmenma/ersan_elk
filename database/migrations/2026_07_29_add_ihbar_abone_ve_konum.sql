ALTER TABLE `ihbarlar`
  ADD COLUMN `komsu_abone_no` varchar(50) DEFAULT NULL AFTER `telefon`,
  ADD COLUMN `konum_lat` decimal(10,7) DEFAULT NULL AFTER `aciklama`,
  ADD COLUMN `konum_lng` decimal(10,7) DEFAULT NULL AFTER `konum_lat`,
  ADD COLUMN `konum_dogruluk` decimal(10,2) DEFAULT NULL AFTER `konum_lng`;
