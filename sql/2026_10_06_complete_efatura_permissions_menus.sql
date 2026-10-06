-- ============================================================================
-- E-Fatura & E-Arşiv Modülü Tüm Yetki ve Menü Tanımlamaları
-- Tarih: 2026-10-06
-- Açıklama: Sunucu ve yerel ortamda eksik olan E-Fatura yetkilerini (Gelen, Giden,
-- Taslak, Oluştur, Cari, Mal/Hizmet, Dashboard, Ayarlar) ve menülerini güvenli
-- (idempotent) şekilde ekler ve ilgili rollere tanımlar.
-- ============================================================================

-- 1. E-Fatura İzinleri (permissions)
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura Dashboard', 'E-Fatura özet ve grafik dashboard görüntüleme', 'efatura/dashboard', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/dashboard');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura & E-Arşiv Yönetimi', 'Giden ve genel e-faturaları görüntüleme ve yönetme', 'efatura/giden-list', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/giden-list');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'Gelen Faturalar', 'Gelen e-faturaları ve ticari yanıtları görüntüleme', 'efatura/gelen-list', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/gelen-list');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'Taslak Faturalar', 'Taslak faturaları listeleme ve onaylama', 'efatura/taslak-list', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/taslak-list');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'Yeni Fatura Kesme', 'Yeni e-fatura ve e-arşiv faturası oluşturma', 'efatura/olustur', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/olustur');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura Cari Listesi', 'E-Fatura mükellef cari listesini yönetme', 'efatura/cari-list', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/cari-list');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura Mal/Hizmet Tanımları', 'Fatura kalem ve hizmet tanımlarını yönetme', 'efatura/mal-hizmet-list', 'Finans & Muhasebe', 1, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/mal-hizmet-list');

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'E-Fatura Ayarları', 'EDM Bilişim entegratör ve seri ayarları', 'efatura/ayarlar', 'Finans & Muhasebe', 2, 0, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'efatura/ayarlar');


-- 2. Ana Menü ve Alt Menüler (menus)
-- Ana Menü
INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'E-Fatura & E-Arşiv', 0, 'Finans & Muhasebe', 3, 'efatura/giden-list', 'file-text', 10, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = 0);

-- Parent ID Al
SET @efatura_parent_id = (SELECT id FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = 0 LIMIT 1);

-- Alt Menüler
INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Fatura Dashboard', @efatura_parent_id, 'Finans & Muhasebe', 3, 'efatura/dashboard', 'file-text', 1, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/dashboard');

INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Giden Faturalar', @efatura_parent_id, 'Finans & Muhasebe', 3, 'efatura/giden-list', 'file-text', 2, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = @efatura_parent_id);

INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Gelen Faturalar', @efatura_parent_id, 'Finans & Muhasebe', 3, 'efatura/gelen-list', 'file-text', 3, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/gelen-list');

INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Taslak Faturalar', @efatura_parent_id, 'Finans & Muhasebe', 3, 'efatura/taslak-list', 'file-text', 4, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/taslak-list');

INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Yeni Fatura Kes', @efatura_parent_id, 'Finans & Muhasebe', 3, 'efatura/olustur', 'file-text', 5, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/olustur');

INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Cari Listesi', @efatura_parent_id, 'Finans & Muhasebe', 3, 'efatura/cari-list', 'file-text', 6, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/cari-list');

INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Mal/Hizmet Tanımları', @efatura_parent_id, 'Finans & Muhasebe', 3, 'efatura/mal-hizmet-list', 'file-text', 7, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/mal-hizmet-list');

INSERT INTO menus (menu_name, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Entegratör Ayarları', @efatura_parent_id, 'Finans & Muhasebe', 3, 'efatura/ayarlar', 'file-text', 8, 1, 1, 1, 0
FROM dual WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/ayarlar');


-- 3. Yetkilerin Sadece Süper Admin ve Firma Sahibi Rollerine Tanımlanması
-- Önce diğer rollerdeki efatura yetkilerini temizle
DELETE urp FROM user_role_permissions urp 
JOIN permissions p ON urp.permission_id = p.id 
WHERE urp.role_id NOT IN (1, 2) AND p.auth_name LIKE 'efatura/%';

-- Süper Admin (1) ve Firma Sahibi (2) rollerine tanımla
INSERT IGNORE INTO user_role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM user_roles r
CROSS JOIN permissions p
WHERE r.id IN (1, 2)
  AND p.auth_name LIKE 'efatura/%'
  AND NOT EXISTS (
      SELECT 1
      FROM user_role_permissions urp2
      WHERE urp2.role_id = r.id
        AND urp2.permission_id = p.id
  );
