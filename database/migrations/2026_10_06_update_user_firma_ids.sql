-- Kullanıcıların boş veya NULL olan firma yetkilerini varsayılan ana firma (1) olarak günceller
UPDATE `users` 
SET `firma_ids` = '1' 
WHERE (`firma_ids` IS NULL OR TRIM(`firma_ids`) = '') 
  AND (`role` != 'superadmin' OR `role` IS NULL);
