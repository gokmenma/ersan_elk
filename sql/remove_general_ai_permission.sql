-- Genel 'ai_is_ajani' Yetkisini Kaldırıp Sadece Modül Bazlı Yetkileri Bırakma Betiği

-- 1. Genel 'ai_is_ajani' Yetkisini user_role_permissions Tablosundan Sil
DELETE urp FROM user_role_permissions urp
JOIN permissions p ON urp.permission_id = p.id
WHERE p.auth_name = 'ai_is_ajani';

-- 2. Genel 'ai_is_ajani' Yetkisini permissions Tablosundan Sil
DELETE FROM permissions WHERE auth_name = 'ai_is_ajani';

-- 3. 'ai_is_ajani_arac_takip' Yetkisinin Var Olduğundan Emin Ol ve Yönetici Rollerıne Atamayı Koru
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active, superadmin)
SELECT 'Araç Takip Yapay Zeka İş Ajanı', 'Araç Takip & Filo modülünde Yapay Zeka İş Ajanı kullanma yetkisi', 'ai_is_ajani_arac_takip', 'Yapay Zeka & Analiz', 0, 0, 1, 0
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE auth_name = 'ai_is_ajani_arac_takip'
);

INSERT IGNORE INTO user_role_permissions (role_id, permission_id)
SELECT ur.id, p.id
FROM user_roles ur
CROSS JOIN permissions p
WHERE p.auth_name = 'ai_is_ajani_arac_takip'
  AND (ur.superadmin = 1 OR ur.role_type IN ('superadmin', 'admin') OR ur.id IN (1, 2, 11, 12));
