-- ============================================================================
-- E-Fatura & E-Arşiv Menü ve Yetki Tanımlamaları
-- ============================================================================

-- 1. Permissions (Yetkiler)
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura & E-Arşiv Yönetimi', 'Giden ve gelen e-faturaları görüntüleme ve yönetme yetkisi', 'efatura/giden-list', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/giden-list');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'Yeni Fatura Kesme', 'E-Fatura ve e-arşiv faturası düzenleme yetkisi', 'efatura/olustur', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/olustur');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura Ayarları', 'EDM Bilişim API ve seri yapılandırma yetkisi', 'efatura/ayarlar', 'Finans & Muhasebe', 2, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/ayarlar');

-- 2. Menüler
INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'E-Fatura & E-Arşiv', 0, 'Finans & Muhasebe', 3, 'efatura/giden-list', 'file-text', 10, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/giden-list');

-- 3. Yetkileri Süper Admin ve Firma Sahibi rollerine bağla
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT ur.id, p.id, 0
FROM user_roles ur
JOIN permissions p ON p.auth_name IN ('efatura/giden-list', 'efatura/olustur', 'efatura/ayarlar')
WHERE ur.role_name IN ('Süper Admin', 'Firma Sahibi', 'Muhasebe Sorumlusu', 'Yönetici')
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp2 WHERE urp2.role_id = ur.id AND urp2.permission_id = p.id
  );
