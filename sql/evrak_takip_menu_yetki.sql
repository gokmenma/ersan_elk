-- Evrak Takip Menü ve Yetkilendirme Güncellemesi SQL Scripti

-- 1. Permisyon kayıtlarını güncelle (auth_name ve is_active)
UPDATE permissions SET auth_name = 'evrak-takip/list', is_active = 1, superadmin = 0 WHERE id = 7;
UPDATE permissions SET auth_name = 'evrak-takip/giden-evrak', is_active = 1, superadmin = 0 WHERE id = 17;
UPDATE permissions SET auth_name = 'evrak-takip/gelen-evrak', is_active = 1, superadmin = 0 WHERE id = 18;

-- 2. Evrak Takip ana menü kaydını güncelle ve İş Takip Yönetim grubuna bağla
UPDATE menus SET 
    group_name = 'İş Takip Yönetim',
    group_order = 2,
    menu_order = 3,
    menu_icon = 'folder',
    is_active = 1,
    is_menu = 1,
    is_authorized = 1
WHERE id = 7;

-- Alt menülerin (giden-evrak, gelen-evrak vb.) üst menü bağlantısını Puantaj (15) yerine Evrak Takip (7) yap
UPDATE menus SET 
    parent_id = 7,
    group_name = 'İş Takip Yönetim'
WHERE id IN (16, 17, 18);

-- 3. Yetkileri Ekle (Roller: 1 = Süper Admin, 2 = Firma Sahibi / Yetkilisi, 11 = İdari ve Mali İşler Müdürü)
INSERT INTO user_role_permissions (role_id, permission_id)
SELECT p.role_id, p.permission_id
FROM (
    SELECT 1 AS role_id, 7 AS permission_id UNION ALL
    SELECT 1, 17 UNION ALL
    SELECT 1, 18 UNION ALL
    SELECT 2, 7 UNION ALL
    SELECT 2, 17 UNION ALL
    SELECT 2, 18 UNION ALL
    SELECT 11, 7 UNION ALL
    SELECT 11, 17 UNION ALL
    SELECT 11, 18
) AS p
WHERE NOT EXISTS (
    SELECT 1 FROM user_role_permissions urp 
    WHERE urp.role_id = p.role_id AND urp.permission_id = p.permission_id
);
