-- Ekip Kodu Bölge Kuralları Yetkisi Ekleme
-- Bu sorguyu veritabanınızda çalıştırarak 'ekip_kodu_kurallari' yetkisini ekleyin

-- Önce yetkinin var olup olmadığını kontrol et
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT 'Ekip Kodu Kuralları', 'Ekip kodu bölge kurallarını yönetme yetkisi', 'ekip_kodu_kurallari', 'Tanımlamalar', 1, 0, 1
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE auth_name = 'ekip_kodu_kurallari'
);

-- Not: Bu yetkiyi admin rolüne atamak için aşağıdaki sorguyu da çalıştırın:
-- (role_id değerini kendi admin rol ID'niz ile değiştirin)
-- 
-- INSERT INTO user_role_permissions (role_id, permission_id)
-- SELECT 1, id FROM permissions WHERE auth_name = 'ekip_kodu_kurallari';
