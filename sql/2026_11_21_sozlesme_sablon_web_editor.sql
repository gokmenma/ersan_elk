-- Personel > Sözleşme Şablonları: Word yükleme yerine web editörde (HTML) şablon düzenleme.
-- Betik tekrar çalıştırılabilir (idempotent). Yeni yetki oluşturulmaz; mevcut yetki anahtarları kullanılır.

ALTER TABLE personel_sozlesme_sablonlari
    MODIFY dosya_adi VARCHAR(100) NULL,
    MODIFY orijinal_dosya_adi VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS icerik_turu VARCHAR(10) NOT NULL DEFAULT 'docx' AFTER sablon_turu,
    ADD COLUMN IF NOT EXISTS html_icerik LONGTEXT NULL AFTER aciklama,
    ADD COLUMN IF NOT EXISTS sayfa_cercevesi TINYINT(1) NOT NULL DEFAULT 0 AFTER html_icerik;

INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'personel/api/sozlesme-sablonlari', 'sablon-kaydet', '*', 'write', p.id, 0, 0, 1
FROM permissions p
WHERE p.is_active = 1 AND p.permission_key = 'personel/sozlesme-sablonlari-duzenle'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'write',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

UPDATE permission_policies
SET is_active = 0
WHERE scope = 'api'
  AND resource = 'personel/api/sozlesme-sablonlari'
  AND action IN ('sablon-yukle', 'sablon-aktif-et', 'sablon-varsayilana-don');

UPDATE permissions
SET description = 'Hizmet sözleşmesi şablonunu web editöründe düzenleyip kaydetme'
WHERE permission_key = 'personel/sozlesme-sablonlari-duzenle';

UPDATE permissions
SET description = 'Hizmet sözleşmesi şablonunu web editöründe görüntüleme ve önizleme'
WHERE permission_key = 'personel/sozlesme-sablonlari';
