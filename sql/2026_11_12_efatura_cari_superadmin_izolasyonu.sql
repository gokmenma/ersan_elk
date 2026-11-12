-- E-Fatura ve Cari modüllerini yalnızca Süper Admin / Firma Sahibi rollerine izole eder.
-- Tekrar çalıştırılabilir (idempotent). Sabit permission ID kullanılmaz.
-- Etki: Diğer grupların bu modüllere ait atamaları silinir, yetkiler non-superadmin için
-- Yetki Grubu düzenleme ekranlarında görünmez (permissions.superadmin = 1) ve
-- ilgili politikalar superadmin_only = 1 olur.

DROP TEMPORARY TABLE IF EXISTS tmp_izole_yetkiler;
CREATE TEMPORARY TABLE tmp_izole_yetkiler (id INT NOT NULL PRIMARY KEY);

INSERT INTO tmp_izole_yetkiler (id)
SELECT DISTINCT p.id
FROM permissions p
WHERE p.auth_name LIKE 'efatura/%'
   OR p.permission_key LIKE 'efatura/%'
   OR p.auth_name LIKE 'cari/%'
   OR p.permission_key LIKE 'cari/%'
   OR p.auth_name IN ('cari', 'cari_takibi', 'cari_hesap_hareketleri')
   OR p.permission_key IN ('cari', 'cari_takibi', 'cari_hesap_hareketleri');

UPDATE permissions p
INNER JOIN tmp_izole_yetkiler t ON t.id = p.id
SET p.superadmin = 1
WHERE p.superadmin <> 1;

DELETE urp
FROM user_role_permissions urp
INNER JOIN tmp_izole_yetkiler t ON t.id = urp.permission_id
INNER JOIN user_roles ur ON ur.id = urp.role_id
WHERE NOT (ur.role_type = 'superadmin' OR ur.superadmin = 1);

INSERT INTO user_role_permissions (role_id, permission_id, created_at, created_by)
SELECT ur.id, t.id, NOW(), 1
FROM user_roles ur
CROSS JOIN tmp_izole_yetkiler t
WHERE (ur.role_type = 'superadmin' OR ur.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp
      WHERE urp.role_id = ur.id AND urp.permission_id = t.id
  );

UPDATE permission_policies pp
SET pp.superadmin_only = 1, pp.authenticated_only = 0
WHERE pp.superadmin_only <> 1
  AND (
      pp.permission_id IN (SELECT id FROM tmp_izole_yetkiler)
      OR pp.resource LIKE 'efatura/%'
      OR pp.resource LIKE 'cari/%'
  );

DROP TEMPORARY TABLE IF EXISTS tmp_izole_yetkiler;
