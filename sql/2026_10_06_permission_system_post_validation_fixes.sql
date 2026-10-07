-- Yetki geçişi sonrası denetimde bulunan açık menü/politika düzeltmeleri.

-- E-Fatura işlem yetkileri: sayfayı görmek işlem yapma yetkisi anlamına gelmez.
INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active)
SELECT definitions.name, definitions.description, definitions.permission_key,
       definitions.permission_key, 'Finans & Muhasebe', 2, 0, 1
FROM (
    SELECT 'E-Fatura Gönderme' AS name, 'Faturayı EDM/GİB sistemine gönderme yetkisi' AS description, 'efatura/gonder' AS permission_key
    UNION ALL SELECT 'E-Fatura Senkronizasyon', 'Fatura durumlarını entegratör ile senkronize etme yetkisi', 'efatura/senkronize'
    UNION ALL SELECT 'E-Fatura İptal', 'Gönderilmiş faturayı iptal etme yetkisi', 'efatura/iptal'
    UNION ALL SELECT 'E-Fatura Ticari Yanıt', 'Gelen ticari faturaya kabul veya ret yanıtı verme yetkisi', 'efatura/yanit'
) definitions
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE p.permission_key = definitions.permission_key OR p.auth_name = definitions.permission_key
);

-- Önceki etkin davranışı koruyarak sayfa yetkilerinden işlem yetkilerine bir defalık geçiş.
INSERT INTO user_role_permissions (role_id, permission_id)
SELECT DISTINCT source_grant.role_id, operation_permission.id
FROM user_role_permissions source_grant
INNER JOIN permissions source_permission ON source_permission.id = source_grant.permission_id
INNER JOIN (
    SELECT 'efatura/gonder' AS operation_key, 'efatura/taslak-list' AS source_key
    UNION ALL SELECT 'efatura/gonder', 'efatura/giden-list'
    UNION ALL SELECT 'efatura/iptal', 'efatura/giden-list'
    UNION ALL SELECT 'efatura/senkronize', 'efatura/giden-list'
    UNION ALL SELECT 'efatura/senkronize', 'efatura/taslak-list'
    UNION ALL SELECT 'efatura/senkronize', 'efatura/gelen-list'
    UNION ALL SELECT 'efatura/yanit', 'efatura/gelen-list'
) migration_map
    ON source_permission.permission_key = migration_map.source_key
    OR source_permission.auth_name = migration_map.source_key
INNER JOIN permissions operation_permission
    ON operation_permission.is_active = 1
   AND (operation_permission.permission_key = migration_map.operation_key
        OR operation_permission.auth_name = migration_map.operation_key)
WHERE NOT EXISTS (
    SELECT 1 FROM user_role_permissions existing_grant
    WHERE existing_grant.role_id = source_grant.role_id
      AND existing_grant.permission_id = operation_permission.id
);

-- Rota taşımayan üst menüler yetki değildir. Görünürlükleri erişilebilir alt sayfalardan türetilir.
UPDATE menus
SET permission_id = NULL
WHERE parent_id = 0
  AND (menu_link IS NULL OR TRIM(menu_link) = '');

-- Online Hesap Hareketleri, aktif Gelir-Gider Takibi yetkisini kullanır.
UPDATE menus m
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'gelir_gider_takibi' OR p.auth_name = 'gelir_gider_takibi')
SET m.permission_id = p.id,
    m.is_authorized = 1
WHERE m.menu_link = 'gelir-gider/online-hesap-hareketleri';

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'page', 'gelir-gider/online-hesap-hareketleri', '', 'GET', p.id, 0, 0
FROM permissions p
WHERE p.is_active = 1
  AND (p.permission_key = 'gelir_gider_takibi' OR p.auth_name = 'gelir_gider_takibi')
LIMIT 1
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Bordro alt raporlarının tamamı üst "Bordro / Raporlar" yetkisini devralır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'page', routes.resource, '', 'GET', p.id, 0, 0
FROM permissions p
CROSS JOIN (
    SELECT 'bordro/raporlar/bordro' AS resource
    UNION ALL SELECT 'bordro/raporlar/ek-odemeler-raporu'
    UNION ALL SELECT 'bordro/raporlar/icmal'
    UNION ALL SELECT 'bordro/raporlar/maliyet-raporu'
    UNION ALL SELECT 'bordro/raporlar/sgk-bildirge'
    UNION ALL SELECT 'bordro/raporlar/sodexo-raporu'
    UNION ALL SELECT 'bordro/raporlar/vergi-raporu'
) routes
WHERE p.is_active = 1
  AND (p.permission_key = 'bordro/raporlar' OR p.auth_name = 'bordro/raporlar')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Alt/düzenleme ekranları bağlı oldukları işlev yetkisini kullanır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'page', aliases.resource, '', 'GET', p.id, 0, 0
FROM (
    SELECT 'evrak-takip/giden-evrak' AS resource, 'evrak-takip/giden-evrak' AS permission_key
    UNION ALL SELECT 'kasa/duzenle', 'gelir_gider_takibi'
    UNION ALL SELECT 'kullanici-gruplari/duzenle', 'yetki_gruplari'
    UNION ALL SELECT 'personel/manage', 'personel_duzenle'
) aliases
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = aliases.permission_key OR p.auth_name = aliases.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Destek detayında nihai nesne sahipliği view/model katmanında ayrıca denetlenir.
-- Bu rota yönetici kadar kendi talebini görüntüleyen oturum kullanıcısına da açıktır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
VALUES ('page', 'yardim/view', '', 'GET', NULL, 0, 1)
ON DUPLICATE KEY UPDATE
    permission_id = NULL,
    superadmin_only = 0,
    authenticated_only = 1,
    is_active = 1;

-- Log ekranı menü tablosunda bulunmasa da doğrudan rota olarak kullanılır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'page', 'logs/list', '', 'GET', p.id, 0, 0
FROM permissions p
WHERE p.is_active = 1
  AND (p.permission_key = 'log_kayitlari' OR p.auth_name = 'log_kayitlari')
LIMIT 1
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;
