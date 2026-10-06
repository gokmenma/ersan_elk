-- ============================================================================
-- E-Fatura & E-Arşiv Dashboard Menü ve Yetki Tanımlaması
-- ============================================================================

-- 1. Permission (Yetki)
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura Dashboard', 'E-Fatura ve finansal göstergeler dashboard sayfasını görüntüleme yetkisi', 'efatura/dashboard', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/dashboard');

-- 2. Ana E-Fatura Menü ID'sini Tespit Et
-- Bağımsız SQL scriptlerinde IF/THEN kullanılamadığı için fallback COALESCE ile verilir.
SET @parent_id = COALESCE(
    (SELECT id FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = 0 LIMIT 1),
    975
);

-- 3. Alt Menü Olarak E-Fatura Dashboard Ekle (En Üst Sıraya: menu_order = 1)
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'Fatura Dashboard', 'E-Fatura, KDV Dengesi ve Finansal Göstergeler', @parent_id, 'Finans & Muhasebe', 4, 'efatura/dashboard', 'grid', 1, 1, 1, 1, NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/dashboard');

-- 4. Yetkiyi İlgili Rollere Bağla
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT ur.id, p.id, 0
FROM user_roles ur
JOIN permissions p ON p.auth_name = 'efatura/dashboard'
WHERE ur.role_name IN ('Süper Admin', 'Firma Sahibi', 'Muhasebe Sorumlusu', 'Yönetici')
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp2 WHERE urp2.role_id = ur.id AND urp2.permission_id = p.id
  );
