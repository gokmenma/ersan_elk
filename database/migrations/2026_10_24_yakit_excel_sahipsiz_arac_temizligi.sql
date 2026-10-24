-- Yakıt Excel yüklemesinde otomatik açılıp yakıt kaydı oluşmayan boş araçların temizliği
-- Ölçüt: marka ve model boş, aktif listede, hiçbir yakıt/KM/zimmet/servis/sigorta/bakım/KM bildirimi kaydı yok (silinmişler dahil).
-- ÖNCE 1. adımı çalıştırıp listeyi kontrol edin; uygunsa 2. adımı çalıştırın.

-- 1. ÖNİZLEME
SELECT a.id, a.firma_id, a.plaka, a.aktif_mi, a.olusturma_tarihi
FROM araclar a
WHERE a.silinme_tarihi IS NULL
  AND COALESCE(TRIM(a.marka), '') = ''
  AND COALESCE(TRIM(a.model), '') = ''
  AND NOT EXISTS (SELECT 1 FROM arac_yakit_kayitlari x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_km_kayitlari x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_km_bildirimleri x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_zimmetleri x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_servis_kayitlari x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_sigorta_kayitlari x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_bakim_kayitlari x WHERE x.arac_id = a.id)
ORDER BY a.firma_id, a.plaka;

-- 2. SOFT DELETE
CREATE TEMPORARY TABLE tmp_sahipsiz_araclar AS
SELECT a.id, a.firma_id, a.plaka
FROM araclar a
WHERE a.silinme_tarihi IS NULL
  AND COALESCE(TRIM(a.marka), '') = ''
  AND COALESCE(TRIM(a.model), '') = ''
  AND NOT EXISTS (SELECT 1 FROM arac_yakit_kayitlari x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_km_kayitlari x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_km_bildirimleri x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_zimmetleri x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_servis_kayitlari x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_sigorta_kayitlari x WHERE x.arac_id = a.id)
  AND NOT EXISTS (SELECT 1 FROM arac_bakim_kayitlari x WHERE x.arac_id = a.id);

UPDATE araclar a
INNER JOIN tmp_sahipsiz_araclar t ON t.id = a.id
SET a.silinme_tarihi = NOW(), a.aktif_mi = 0;

INSERT INTO arac_islem_loglari (firma_id, arac_id, user_id, islem_turu, aciklama)
SELECT t.firma_id, t.id, 0, 'Silme', 'Yakıt Excel yüklemesinde otomatik açılan, kaydı olmayan boş araç temizlik scripti ile silindi.'
FROM tmp_sahipsiz_araclar t;

INSERT INTO system_logs (firma_id, user_id, action_type, description, level, created_at)
SELECT t.firma_id, 0, 'Araç Silme (Temizlik)',
       CONCAT('Sahipsiz araç temizliği: ', COUNT(*), ' araç silindi. Plakalar: ', GROUP_CONCAT(t.plaka ORDER BY t.plaka SEPARATOR ', ')),
       1, NOW()
FROM tmp_sahipsiz_araclar t
GROUP BY t.firma_id;

DROP TEMPORARY TABLE tmp_sahipsiz_araclar;
