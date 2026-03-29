-- Destek mesajlarında geçmişte yanlış kaydedilmiş gonderen_tip değerlerini düzeltir.
-- Kural:
-- 1) personel tipi mesajın gonderen_id'si bilet sahibine eşit olmalı.
-- 2) Eşit değilse bu mesaj yönetici cevabı kabul edilir.
-- 3) yonetici tipi mesajın gonderen_id'si bilet sahibine eşitse bu mesaj personel mesajıdır.

START TRANSACTION;

-- A) Yanlışlıkla 'personel' kaydedilen (ama ticket owner olmayan) kayıtları 'yonetici' yap
UPDATE destek_bilet_mesajlari m
INNER JOIN destek_biletleri b ON b.id = m.bilet_id
SET m.gonderen_tip = 'yonetici'
WHERE m.gonderen_tip = 'personel'
  AND b.personel_id IS NOT NULL
  AND m.gonderen_id <> b.personel_id;

-- B) Yanlışlıkla 'yonetici' kaydedilen (ama ticket owner olan) kayıtları 'personel' yap
UPDATE destek_bilet_mesajlari m
INNER JOIN destek_biletleri b ON b.id = m.bilet_id
SET m.gonderen_tip = 'personel'
WHERE m.gonderen_tip = 'yonetici'
  AND b.personel_id IS NOT NULL
  AND m.gonderen_id = b.personel_id;

COMMIT;

-- Kontrol sorguları
SELECT
  SUM(CASE WHEN m.gonderen_tip='personel' AND m.gonderen_id <> b.personel_id THEN 1 ELSE 0 END) AS personel_tip_not_owner,
  SUM(CASE WHEN m.gonderen_tip='yonetici' AND m.gonderen_id = b.personel_id THEN 1 ELSE 0 END) AS admin_tip_equals_owner,
  COUNT(*) AS total_messages
FROM destek_bilet_mesajlari m
INNER JOIN destek_biletleri b ON b.id = m.bilet_id;
