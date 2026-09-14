-- Silinmiş personellere ait bekleyen avans, izin ve genel talepleri soft-delete ile temizleme scripti

-- 1. Silinmiş personele ait bekleyen avans taleplerini temizle
UPDATE personel_avanslari pa
JOIN personel p ON pa.personel_id = p.id
SET pa.silinme_tarihi = NOW(),
    pa.silinme_aciklama = 'Personel silindiği için otomatik iptal edildi'
WHERE pa.silinme_tarihi IS NULL 
  AND p.silinme_tarihi IS NOT NULL
  AND pa.durum = 'beklemede';

-- 2. Silinmiş personele ait bekleyen izin taleplerini temizle
UPDATE personel_izinleri pi
JOIN personel p ON pi.personel_id = p.id
SET pi.silinme_tarihi = NOW(),
    pi.silinme_aciklama = 'Personel silindiği için otomatik iptal edildi'
WHERE pi.silinme_tarihi IS NULL 
  AND p.silinme_tarihi IS NOT NULL
  AND pi.onay_durumu = 'beklemede';

-- 3. Silinmiş personele ait bekleyen genel talepleri temizle
UPDATE personel_talepleri pt
JOIN personel p ON pt.personel_id = p.id
SET pt.silinme_tarihi = NOW(),
    pt.silinme_aciklama = 'Personel silindiği için otomatik iptal edildi'
WHERE pt.silinme_tarihi IS NULL 
  AND p.silinme_tarihi IS NOT NULL
  AND pt.durum = 'beklemede';
