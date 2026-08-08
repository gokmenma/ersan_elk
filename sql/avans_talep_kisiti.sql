-- Avans Talep Kısıtı ve Ayarlar Sekmesi SQL Scripti

-- 1. Varsayılan ayar: kısıt aktif (0 = sadece hiç maaş almamış personel avans talebi yapabilir)
INSERT INTO settings (user_id, firma_id, set_name, set_value)
SELECT NULL, NULL, 'avans_talep_serbest', '0'
WHERE NOT EXISTS (
    SELECT 1 FROM settings
    WHERE set_name = 'avans_talep_serbest'
      AND user_id IS NULL
      AND firma_id IS NULL
);

-- 2. Permisyon kaydı ekle
INSERT INTO permissions (name, auth_name, description, group_name, permission_level, is_active, is_required)
SELECT 'Avans Ayarları Sekmesi', 'avans_ayarlari_sekmesi', 'Avans talep kısıtı ayarını yönetme yetkisi', 'Ayarlar', 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE auth_name = 'avans_ayarlari_sekmesi');

-- 3. Yetkiyi rollere ata (1: Süper Admin, 2: Firma Sahibi, 11: İdari İşler)
INSERT INTO user_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM user_roles r
CROSS JOIN permissions p
WHERE p.auth_name = 'avans_ayarlari_sekmesi'
AND r.id IN (1, 2, 11)
AND NOT EXISTS (
    SELECT 1 FROM user_role_permissions urp
    WHERE urp.role_id = r.id AND urp.permission_id = p.id
);
