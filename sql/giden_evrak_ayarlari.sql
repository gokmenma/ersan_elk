-- Evrak Bilgileri Sekmesi ve Ayarları SQL Scripti

-- 1. settings tablosunda set_value alanını TEXT yaparak uzun antet/adres metinlerinin kaybolmasını önle
ALTER TABLE settings MODIFY COLUMN set_value TEXT NULL;

-- 2. Permisyon kaydı ekle
INSERT INTO permissions (name, auth_name, description, group_name, permission_level, is_active, superadmin, is_required)
SELECT 'Evrak Bilgileri Sekmesi', 'evrak_bilgileri_sekmesi', 'Evrak (Antet, Logo, Alt Bilgi) ayarlarını yönetme yetkisi', 'Ayarlar', 0, 1, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'evrak_bilgileri_sekmesi');

-- 3. Yetkileri rollere ata (1: Süper Admin, 2: Firma Sahibi, 11: İdari İşler vb.)
INSERT INTO user_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM user_roles r
CROSS JOIN permissions p
WHERE p.auth_name = 'evrak_bilgileri_sekmesi'
AND r.id IN (1, 2, 11)
AND NOT EXISTS (
    SELECT 1 FROM user_role_permissions urp 
    WHERE urp.role_id = r.id AND urp.permission_id = p.id
);
