-- Migration: Fix arac_kullanim charset / encoding issues
-- Date: 2026-09-14

-- 1. personel_calisma_gecmisi tablosundaki bozuk kayıtları düzelt
UPDATE personel_calisma_gecmisi 
SET arac_kullanim = 'Kendi Aracı' 
WHERE arac_kullanim LIKE 'Kendi Arac%' 
   OR arac_kullanim LIKE '%Kendi%' 
   OR arac_kullanim = 'Kendi Arac?'
   OR HEX(arac_kullanim) = '4B656E646920417261633F'
   OR HEX(arac_kullanim) = '4B656E64692041726163FD';

UPDATE personel_calisma_gecmisi 
SET arac_kullanim = 'Şirket aracı' 
WHERE arac_kullanim LIKE '%irket arac%' 
   OR arac_kullanim = '?irket arac?'
   OR HEX(arac_kullanim) = '3F69726B657420617261633F'
   OR HEX(arac_kullanim) = 'DE69726B65742061726163FD';

UPDATE personel_calisma_gecmisi 
SET arac_kullanim = 'Yok' 
WHERE arac_kullanim IS NULL 
   OR arac_kullanim = '' 
   OR arac_kullanim LIKE '%yok%'
   OR arac_kullanim LIKE '%Yok%';

-- 2. personel tablosundaki bozuk kayıtları düzelt
UPDATE personel 
SET arac_kullanim = 'Kendi Aracı' 
WHERE arac_kullanim LIKE 'Kendi Arac%' 
   OR arac_kullanim LIKE '%Kendi%' 
   OR arac_kullanim = 'Kendi Arac?'
   OR HEX(arac_kullanim) = '4B656E646920417261633F'
   OR HEX(arac_kullanim) = '4B656E64692041726163FD';

UPDATE personel 
SET arac_kullanim = 'Şirket aracı' 
WHERE arac_kullanim LIKE '%irket arac%' 
   OR arac_kullanim = '?irket arac?'
   OR HEX(arac_kullanim) = '3F69726B657420617261633F'
   OR HEX(arac_kullanim) = 'DE69726B65742061726163FD';

UPDATE personel 
SET arac_kullanim = 'Yok' 
WHERE arac_kullanim IS NULL 
   OR arac_kullanim = '' 
   OR arac_kullanim LIKE '%yok%'
   OR arac_kullanim LIKE '%Yok%';