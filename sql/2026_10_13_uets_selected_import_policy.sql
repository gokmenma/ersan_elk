-- UETS gelen evrak önizleme/seçimli aktarım API politikası
-- Mevcut evrak-takip/uets yetkisini kullanır; yeni bir sayfa/yetki oluşturmaz.
SET @uets_permission_id := (
    SELECT id FROM permissions WHERE permission_key = 'evrak-takip/uets' AND is_active = 1 LIMIT 1
);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT
    'api', 'evrak-takip/api', 'uets-secilenleri-aktar', 'POST', 'write', @uets_permission_id, 0, 0, 1, NOW(), NOW()
WHERE @uets_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies
      WHERE resource = 'evrak-takip/api' AND action = 'uets-secilenleri-aktar'
  );
