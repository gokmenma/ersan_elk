-- arac_tipi enum değerine ikame ekle
ALTER TABLE `araclar` 
MODIFY COLUMN `arac_tipi` ENUM('binek', 'kamyonet', 'kamyon', 'minibus', 'otobus', 'motosiklet', 'diger', 'ikame') DEFAULT 'binek';
