INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT ur.id, p.id, 0
FROM user_roles ur
INNER JOIN permissions p ON p.permission_key = 'kacak_bildirim_personelleri' AND p.is_active = 1
WHERE ur.role_name = 'KASKİ Görüntüleme'
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp
      WHERE urp.role_id = ur.id AND urp.permission_id = p.id
  );
