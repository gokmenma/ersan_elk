-- E-Fatura Menü Yapısı Güncellemesi
-- Taslak, Giden, Gelen, Oluştur, Ayarlar Hiyerarşisi

-- Varsa eski tekil e-fatura menüsünü güncelle veya parent yap
UPDATE menus SET 
    menu_name = 'E-Fatura & E-Arşiv',
    menu_link = 'efatura/giden-list',
    menu_icon = 'file-text',
    group_name = 'Finans & Muhasebe',
    parent_id = 0,
    is_active = 1,
    is_menu = 1
WHERE menu_link = 'efatura/giden-list' AND parent_id = 0;

-- Ana menü ID'sini alıp alt menüleri oluşturalım
SET @parent_id = (SELECT id FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = 0 LIMIT 1);

-- Eğer parent_id bulunamazsa ekleyelim
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'E-Fatura & E-Arşiv', 'E-Fatura, E-Arşiv ve Taslak Yönetimi', 0, 'Finans & Muhasebe', 4, 'efatura/giden-list', 'file-text', 1, 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = 0);

SET @parent_id = (SELECT id FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = 0 LIMIT 1);

-- 1. Taslak Faturalar
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'Taslak Faturalar', 'Hazırlanan ve Henüz Gönderilmemiş Taslak Faturalar', @parent_id, 'Finans & Muhasebe', 4, 'efatura/taslak-list', 'edit-3', 1, 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/taslak-list');

-- 2. Giden Faturalar
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'Giden Faturalar', 'EDM / GİB Sistemine İletilen Giden Faturalar', @parent_id, 'Finans & Muhasebe', 4, 'efatura/giden-list', 'upload-cloud', 2, 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/giden-list' AND parent_id = @parent_id);

-- 3. Gelen Faturalar
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'Gelen Faturalar', 'Tedarikçilerden Firmamıza Kesilen Gelen Faturalar', @parent_id, 'Finans & Muhasebe', 4, 'efatura/gelen-list', 'download-cloud', 3, 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/gelen-list');

-- 4. Fatura Oluştur
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'Yeni Fatura Kes', 'E-Fatura veya E-Arşiv Faturası Düzenle', @parent_id, 'Finans & Muhasebe', 4, 'efatura/olustur', 'plus-circle', 4, 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/olustur');

-- 5. Entegratör Ayarları
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, menu_icon, menu_order, is_active, is_menu, is_authorized, created_at)
SELECT 'Entegratör Ayarları', 'EDM Bilişim Entegrasyon ve Seri No Ayarları', @parent_id, 'Finans & Muhasebe', 4, 'efatura/ayarlar', 'settings', 5, 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'efatura/ayarlar');
