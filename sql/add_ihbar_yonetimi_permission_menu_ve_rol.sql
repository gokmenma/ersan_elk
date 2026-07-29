-- İhbar Sistemi - Yetki, Menü ve Rol Tanımlamaları
-- Bu betiği database/migrations/2026_07_29_create_ihbar_tables.sql çalıştırıldıktan sonra çalıştırın.

-- 1) Yetki (permission) kaydı - menu_link ile aynı auth_name kullanılır (MenuModel eşleşmesi için)
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'İhbar Yönetimi', 'Kaçak Su ihbarlarını görüntüleme ve yönetme yetkisi', 'ihbar/list', 'İş Takip Yönetim', 1, 0, 1
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE auth_name = 'ihbar/list'
);

-- 2) Menü kaydı - "İş Takip" grubunun (id=5) altına eklenir
INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'İhbar Yönetimi', 5, 'İş Takip Yönetim', 1, 'ihbar/list', 'alert-triangle', 5, 1, 1, 1, 0
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM menus WHERE menu_link = 'ihbar/list'
);

-- 3) Yeni yetki grubu (rol): Kaçak Kontrol Sorumlusu
INSERT INTO user_roles (owner_id, superadmin, role_type, role_name, description, role_color)
SELECT '1', 0, 'user', 'Kaçak Kontrol Sorumlusu', 'Gelen ihbarları görüntüler, ekibe yönlendirir ve sonuçlandırır.', 'danger'
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM user_roles WHERE role_name = 'Kaçak Kontrol Sorumlusu' AND owner_id = '1'
);

-- 4) Yeni role ve mevcut Süper Admin / Firma Sahibi rollerine "İhbar Yönetimi" yetkisini bağla
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT ur.id, p.id, 0
FROM user_roles ur
JOIN permissions p ON p.auth_name = 'ihbar/list'
WHERE ur.role_name IN ('Kaçak Kontrol Sorumlusu', 'Süper Admin', 'Firma Sahibi')
  AND ur.owner_id = '1'
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp2 WHERE urp2.role_id = ur.id AND urp2.permission_id = p.id
  );
