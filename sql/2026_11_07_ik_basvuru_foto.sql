-- İşe alım başvurusuna vesikalık fotoğraf alanı ve fotoğraf görüntüleme API politikası. Tekrar çalıştırılabilir.

ALTER TABLE ik_basvurular
    ADD COLUMN IF NOT EXISTS foto_dosya VARCHAR(80) NULL AFTER cv_metin;

START TRANSACTION;

SET @ik_basvuru_perm = (SELECT id FROM permissions WHERE permission_key = 'ik_basvuru_listesi' LIMIT 1);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'ik-ise-alim/api', 'basvuru-foto', '*', 'read', @ik_basvuru_perm, 0, 0, 1
FROM DUAL
WHERE @ik_basvuru_perm IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = 'api' AND p.resource = 'ik-ise-alim/api' AND p.action = 'basvuru-foto'
        AND p.http_method = '*' AND p.permission_id = @ik_basvuru_perm
  );

COMMIT;
