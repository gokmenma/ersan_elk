-- KASKİ salt okunur portalı için rol ve yetki kurulumu.
-- Script idempotenttir; birden fazla kez güvenle çalıştırılabilir.

INSERT INTO permissions
    (name, auth_name, description, group_name, permission_level, is_required, is_active)
SELECT
    'KASKİ Portal Girişi',
    'kaski_portal_giris',
    'KASKİ personelinin izole salt okunur portala giriş yetkisi',
    'Kaçak İşlemleri',
    1,
    0,
    1
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE auth_name = 'kaski_portal_giris'
);

INSERT INTO user_roles
    (owner_id, superadmin, role_name, description, role_color, kayit_yapan)
SELECT
    '1',
    0,
    'KASKİ Görüntüleme',
    'Yalnızca izole KASKİ portalında kaçak kayıtlarını görüntüleyebilir.',
    'info',
    0
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM user_roles
    WHERE owner_id = '1' AND role_name = 'KASKİ Görüntüleme'
);

-- Portal girişi ve kaçak kayıtlarını okuma izinleri.
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT ur.id, p.id, 0
FROM user_roles ur
INNER JOIN permissions p
    ON p.auth_name IN ('kaski_portal_giris', 'kacak_islemleri')
WHERE ur.owner_id = '1'
  AND ur.role_name = 'KASKİ Görüntüleme'
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions urp
      WHERE urp.role_id = ur.id AND urp.permission_id = p.id
  );

-- Rol daha önce oluşturulmuşsa dahi yazma yetkilerini kesin olarak kaldır.
DELETE urp
FROM user_role_permissions urp
INNER JOIN user_roles ur ON ur.id = urp.role_id
INNER JOIN permissions p ON p.id = urp.permission_id
WHERE ur.owner_id = '1'
  AND ur.role_name = 'KASKİ Görüntüleme'
  AND p.auth_name IN (
      'kacak_duzenle',
      'kacak_onay',
      'kacak_iptal',
      'kacak_iptal_ekle',
      'kacak_arsiv',
      'kacak_sicil_bildir',
      'kacak_sicil_yanitla',
      'kacak_bildirim_personelleri'
  );
