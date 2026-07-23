-- Yapay Zeka İş Ajanı (AI Agent) Yetkisi Ekleme Betiği
-- Bu sorgu 'ai_is_ajani' yetkisini permissions tablosuna ekler ve admin/yönetici rollerine varsayılan atamasını yapar.

-- 1. Yapay Zeka İş Ajanı Yetki Tanımını Ekle
INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active, superadmin)
SELECT 'Yapay Zeka İş Ajanı', 'Yapay Zeka İş Ajanı ile veri, filo ve sürücü analizi yapma yetkisi', 'ai_is_ajani', 'Yapay Zeka & Analiz', 0, 0, 1, 0
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE auth_name = 'ai_is_ajani'
);

-- 2. Yetkiyi Varsayılan Olarak Yönetici / Admin Rollerıne (Süper Admin, Firma Sahibi, Araç Takip Sorumlusu) Tanımla
INSERT IGNORE INTO user_role_permissions (role_id, permission_id)
SELECT ur.id, p.id
FROM user_roles ur
CROSS JOIN permissions p
WHERE p.auth_name = 'ai_is_ajani'
  AND (ur.superadmin = 1 OR ur.role_type IN ('superadmin', 'admin') OR ur.id IN (1, 2, 11, 12));
