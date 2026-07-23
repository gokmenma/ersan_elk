-- Modül Bazlı Yapay Zeka İş Ajanı (AI Agent) Yetki Ekleme Betiği

-- 1. Genel Yapay Zeka Yetkisini Güncelle
UPDATE permissions 
SET name = 'Yapay Zeka İş Ajanı (Tüm Modüller)', 
    description = 'Tüm ERP modüllerinde Yapay Zeka İş Ajanı kullanma genel yetkisi' 
WHERE auth_name = 'ai_is_ajani';

-- 2. Araç Takip Modülü Özel Yapay Zeka Yetkisini Ekle
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active, superadmin)
SELECT 'Araç Takip Yapay Zeka İş Ajanı', 'Araç Takip & Filo modülünde Yapay Zeka İş Ajanı kullanma yetkisi', 'ai_is_ajani_arac_takip', 'Yapay Zeka & Analiz', 0, 0, 1, 0
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE auth_name = 'ai_is_ajani_arac_takip'
);

-- 3. Yetkileri Varsayılan Olarak Yönetici / İdari / Araç Takip Rollerıne Tanımla
INSERT IGNORE INTO user_role_permissions (role_id, permission_id)
SELECT ur.id, p.id
FROM user_roles ur
CROSS JOIN permissions p
WHERE p.auth_name IN ('ai_is_ajani', 'ai_is_ajani_arac_takip')
  AND (ur.superadmin = 1 OR ur.role_type IN ('superadmin', 'admin') OR ur.id IN (1, 2, 11, 12));
