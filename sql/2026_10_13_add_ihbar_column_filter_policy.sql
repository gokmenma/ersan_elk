-- İhbar listesindeki server-side kolon seçim filtresinin seçeneklerini okuma politikası.
-- Mevcut ihbar/list yetkisini kullanır; yeni bir yetki oluşturmaz.
SET @ihbar_list_permission_id := (
    SELECT id
    FROM permissions
    WHERE permission_key = 'ihbar/list'
      AND is_active = 1
    LIMIT 1
);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id,
     superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT
    'api', 'ihbar/api', 'get-unique-values', 'GET', 'read',
    @ihbar_list_permission_id, 0, 0, 1, NOW(), NOW()
WHERE @ihbar_list_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM permission_policies
      WHERE scope = 'api'
        AND resource = 'ihbar/api'
        AND action = 'get-unique-values'
        AND http_method IN ('GET', '*')
  );
