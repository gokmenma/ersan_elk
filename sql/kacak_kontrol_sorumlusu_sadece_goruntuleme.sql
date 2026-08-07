-- Kaçak Kontrol Sorumlusu Rolünün Yetkilerini Sadece Görüntüleme (Read-Only) Olarak Güncelleme Scripti

-- 1. İhbar Düzenleme yetkisi (ihbar_duzenle) yoksa permissions tablosuna ekle
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'İhbar Kayıt Düzenleme', 'İhbar ekleme, güncelleme, silme, yönlendirme ve sonuçlandırma yetkisi', 'ihbar_duzenle', 'İş Takip Yönetim', 1, 0, 1
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE auth_name = 'ihbar_duzenle'
);

-- 2. Süper Admin ve Firma Sahibi rollerine ihbar_duzenle yetkisini ver
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT ur.id, p.id, 0
FROM user_roles ur
JOIN permissions p ON p.auth_name = 'ihbar_duzenle'
WHERE ur.role_name IN ('Süper Admin', 'Firma Sahibi')
  AND ur.owner_id = '1'
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp2 WHERE urp2.role_id = ur.id AND urp2.permission_id = p.id
  );

-- 3. 'Kaçak Kontrol Sorumlusu' rolünden değiştirme/düzenleme/silme/onaylama yetkilerini kaldır
DELETE urp FROM user_role_permissions urp
JOIN user_roles ur ON ur.id = urp.role_id
JOIN permissions p ON p.id = urp.permission_id
WHERE ur.role_name = 'Kaçak Kontrol Sorumlusu'
  AND p.auth_name IN ('kacak_duzenle', 'kacak_onay', 'kacak_iptal', 'kacak_arsiv', 'ihbar_duzenle');

-- 4. 'Kaçak Kontrol Sorumlusu' rolüne sadece görüntüleme (ihbar/list ve kacak_islemleri) yetkilerinin bağlı olduğundan emin ol
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT ur.id, p.id, 0
FROM user_roles ur
JOIN permissions p ON p.auth_name IN ('ihbar/list', 'kacak_islemleri')
WHERE ur.role_name = 'Kaçak Kontrol Sorumlusu'
  AND ur.owner_id = '1'
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp2 WHERE urp2.role_id = ur.id AND urp2.permission_id = p.id
  );
