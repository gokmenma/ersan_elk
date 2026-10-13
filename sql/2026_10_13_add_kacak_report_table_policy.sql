-- Kaçak İşlemleri > Ekip Özeti raporu için bağımsız API politikası.
-- Genel puantaj raporu yetkisi olmayan kacak_islemleri kullanıcılarının
-- yalnızca kaçak kontrol raporunu okuyabilmesini sağlar.
SET @kacak_islemleri_permission_id := (
    SELECT id
    FROM permissions
    WHERE permission_key = 'kacak_islemleri'
      AND is_active = 1
    LIMIT 1
);

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id,
     superadmin_only, authenticated_only, is_active, created_at, updated_at)
SELECT
    'api', 'puantaj/api', 'get-kacak-report-table', 'GET', 'read',
    @kacak_islemleri_permission_id, 0, 0, 1, NOW(), NOW()
WHERE @kacak_islemleri_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM permission_policies
      WHERE scope = 'api'
        AND resource = 'puantaj/api'
        AND action = 'get-kacak-report-table'
        AND http_method IN ('GET', '*')
  );
