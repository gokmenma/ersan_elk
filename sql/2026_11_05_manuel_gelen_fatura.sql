-- Manuel gelen fatura / makbuz ekleme (MariaDB 10.4+, tekrar çalıştırılabilir).

ALTER TABLE faturalar
    MODIFY belge_turu ENUM('EFATURA','EARSIV','KAGIT','SMM','GIDER_PUSULASI','MAKBUZ') NOT NULL;

ALTER TABLE faturalar
    ADD COLUMN IF NOT EXISTS kaynak VARCHAR(20) NOT NULL DEFAULT 'ENTEGRATOR' AFTER yon,
    ADD COLUMN IF NOT EXISTS stopaj_orani DECIMAL(5,2) NULL AFTER tevkifat_tutari,
    ADD COLUMN IF NOT EXISTS stopaj_tutari DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER stopaj_orani;

ALTER TABLE faturalar
    ADD INDEX IF NOT EXISTS idx_faturalar_firm_kaynak (firm_id, kaynak);

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
SELECT 'Manuel Gelen Fatura/Makbuz Ekleme',
       'Entegratör dışında gelen fatura, serbest meslek makbuzu, gider pusulası ve makbuzları belge yükleyerek Gelen Faturalara ekleme, düzenleme ve silme yetkisi',
       'efatura/manuel-fatura', 'efatura/manuel-fatura', 'Finans & Muhasebe', 2, 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE p.permission_key = 'efatura/manuel-fatura' OR p.auth_name = 'efatura/manuel-fatura'
);

INSERT INTO user_role_permissions (role_id, permission_id, created_at, created_by)
SELECT ur.id, p.id, NOW(), 1
FROM user_roles ur
CROSS JOIN permissions p
WHERE (ur.role_type = 'superadmin' OR ur.superadmin = 1)
  AND p.permission_key = 'efatura/manuel-fatura'
  AND p.is_active = 1
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp
      WHERE urp.role_id = ur.id AND urp.permission_id = p.id
  );
