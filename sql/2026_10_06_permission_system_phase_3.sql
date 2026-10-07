-- Yetki Sistemi Dönüşümü / Aşama 3
-- Sayfa ve API aksiyonlarını kanonik bir yetkiye açıkça bağlayan politika tablosu.
-- Ön koşul: 2026_10_06_permission_system_phase_1.sql

CREATE TABLE IF NOT EXISTS permission_policies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope VARCHAR(20) NOT NULL COMMENT 'page veya api',
    resource VARCHAR(191) NOT NULL COMMENT 'Sayfa rotası veya API dosyası',
    action VARCHAR(191) NOT NULL DEFAULT '' COMMENT 'API action; sayfalarda boş',
    http_method VARCHAR(10) NOT NULL DEFAULT '*' COMMENT 'GET, POST veya *',
    permission_id INT NULL,
    superadmin_only TINYINT(1) NOT NULL DEFAULT 0,
    authenticated_only TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permission_policy (scope, resource, action, http_method),
    KEY idx_permission_policy_permission (permission_id),
    CONSTRAINT fk_permission_policy_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_authenticated_only = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'permission_policies'
      AND COLUMN_NAME = 'authenticated_only'
);
SET @sql = IF(
    @has_authenticated_only = 0,
    'ALTER TABLE permission_policies ADD COLUMN authenticated_only TINYINT(1) NOT NULL DEFAULT 0 AFTER superadmin_only',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Açık menü-yetki ilişkilerini sayfa politikası olarak aktar.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'page', m.menu_link, '', 'GET', m.permission_id, 0
FROM menus m
WHERE m.is_active = 1
  AND m.menu_link IS NOT NULL
  AND TRIM(m.menu_link) <> ''
  AND m.permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- Menüde ayrı kaydı olmayan fakat üst sayfanın yetkisini kullanan bilinen sayfalar.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'page', aliases.resource, '', 'GET', m.permission_id, aliases.superadmin_only
FROM (
    SELECT 'kullanici-gruplari/yetki-matrisi' AS resource, 'kullanici-gruplari/list' AS parent_resource, 0 AS superadmin_only
    UNION ALL SELECT 'kullanici-gruplari/yetki-denetimi', 'kullanici-gruplari/list', 1
    UNION ALL SELECT 'kullanici-gruplari/yetki-katalogu', 'kullanici-gruplari/list', 1
    UNION ALL SELECT 'bordro/ai-analiz', 'bordro/list', 0
    UNION ALL SELECT 'hakedisler/sozlesme-detay', 'hakedisler/index', 0
    UNION ALL SELECT 'hakedisler/hakedis-detay', 'hakedisler/index', 0
) aliases
INNER JOIN menus m ON m.menu_link = aliases.parent_resource AND m.is_active = 1
WHERE m.permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Sözleşme/Hakediş ortak API ve rapor dışa aktarma uçları.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', endpoints.resource, endpoints.action, endpoints.http_method, p.id, 0, 0
FROM (
    SELECT 'hakedisler/online-api' AS resource, '*' AS action, '*' AS http_method
    UNION ALL SELECT 'hakedisler/export-excel', 'export', 'GET'
) endpoints
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'hakedis' OR p.auth_name = 'hakedis')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Kullanıcı grupları API aksiyonları: okuma ve yazma ayrı yetkilere bağlanır.
-- Yönetim yetkisi olan roller salt-okuma yetkisini de alır; mevcut erişim davranışı korunur.
INSERT INTO user_role_permissions (role_id, permission_id)
SELECT manager_grants.role_id, viewer_permission.id
FROM user_role_permissions manager_grants
INNER JOIN permissions manager_permission
    ON manager_permission.id = manager_grants.permission_id
   AND manager_permission.is_active = 1
   AND (manager_permission.permission_key = 'yetki_gruplari' OR manager_permission.auth_name = 'yetki_gruplari')
INNER JOIN permissions viewer_permission
    ON viewer_permission.is_active = 1
   AND (viewer_permission.permission_key = 'yetki_gruplari_izleme' OR viewer_permission.auth_name = 'yetki_gruplari_izleme')
WHERE NOT EXISTS (
    SELECT 1 FROM user_role_permissions existing_grant
    WHERE existing_grant.role_id = manager_grants.role_id
      AND existing_grant.permission_id = viewer_permission.id
);

INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'kullanici-gruplari/api', actions.action, 'POST', p.id, actions.superadmin_only
FROM (
    SELECT 'searchPermissionRoles' AS action, 'yetki_gruplari_izleme' AS permission_key, 0 AS superadmin_only
    UNION ALL SELECT 'getPermissions', 'yetki_gruplari_izleme', 0
    UNION ALL SELECT 'getGroup', 'yetki_gruplari_izleme', 0
    UNION ALL SELECT 'getPermissionsSummary', 'yetki_gruplari_izleme', 0
    UNION ALL SELECT 'getAssignedUsers', 'yetki_gruplari_izleme', 0
    UNION ALL SELECT 'runPermissionAudit', 'yetki_gruplari_izleme', 1
    UNION ALL SELECT 'saveGroup', 'yetki_gruplari', 0
    UNION ALL SELECT 'savePermissions', 'yetki_gruplari', 0
    UNION ALL SELECT 'deleteGroup', 'yetki_gruplari', 0
    UNION ALL SELECT 'copyPermissions', 'yetki_gruplari', 0
    UNION ALL SELECT 'toggleRolePermission', 'yetki_gruplari', 0
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = actions.permission_key OR p.auth_name = actions.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Personel düzenleme yetkisi, personel listesini görüntüleme yetkisini de içerir.
INSERT INTO user_role_permissions (role_id, permission_id)
SELECT editor_grants.role_id, viewer_permission.id
FROM user_role_permissions editor_grants
INNER JOIN permissions editor_permission
    ON editor_permission.id = editor_grants.permission_id
   AND editor_permission.is_active = 1
   AND (editor_permission.permission_key = 'personel_duzenle' OR editor_permission.auth_name = 'personel_duzenle')
INNER JOIN permissions viewer_permission
    ON viewer_permission.is_active = 1
   AND (viewer_permission.permission_key = 'personel_listesi' OR viewer_permission.auth_name = 'personel_listesi')
WHERE NOT EXISTS (
    SELECT 1 FROM user_role_permissions existing_grant
    WHERE existing_grant.role_id = editor_grants.role_id
      AND existing_grant.permission_id = viewer_permission.id
);

-- Personel API aksiyonları.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'personel/api', actions.action, actions.http_method, p.id, 0
FROM (
    SELECT 'get-details' AS action, 'POST' AS http_method, 'personel_listesi' AS permission_key
    UNION ALL SELECT 'get-ekip-kodlari-by-bolge', 'POST', 'personel_listesi'
    UNION ALL SELECT 'get-unique-values', 'POST', 'personel_listesi'
    UNION ALL SELECT 'personel-list', 'POST', 'personel_listesi'
    UNION ALL SELECT 'get-musait-ekipler', 'POST', 'personel_listesi'
    UNION ALL SELECT 'get-ekip-gecmisi', 'POST', 'personel_listesi'
    UNION ALL SELECT 'ekip-gecmisi-get', 'POST', 'personel_listesi'
    UNION ALL SELECT 'get-gorev-gecmisi', 'POST', 'personel_listesi'
    UNION ALL SELECT 'get-aktif-gorev', 'POST', 'personel_listesi'
    UNION ALL SELECT 'gorev-gecmisi-get', 'POST', 'personel_listesi'
    UNION ALL SELECT 'get-ozel-is-turu-ucretleri', 'POST', 'personel_listesi'
    UNION ALL SELECT 'ozel-is-turu-ucreti-getir', 'POST', 'personel_listesi'
    UNION ALL SELECT 'get-calisma-gecmisi', 'POST', 'personel_listesi'
    UNION ALL SELECT 'calisma-gecmisi-get', 'POST', 'personel_listesi'
    UNION ALL SELECT 'export-puantaj', 'GET', 'personel_listesi'
    UNION ALL SELECT 'personel-kaydet', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'personel-sil', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'manual-gelir-ekle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'manual-kesinti-ekle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'kesinti-onayla', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'kesinti-reddet', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'kesinti-sil', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'kesinti-sonlandir', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'excel-upload', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'update-login-info', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'puantaj-manuel-kaydet', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'ekip-gecmisi-ekle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'ekip-gecmisi-sil', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'ekip-gecmisi-guncelle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'gorev-gecmisi-ekle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'gorev-gecmisi-guncelle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'gorev-gecmisi-sil', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'ozel-is-turu-ucreti-ekle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'ozel-is-turu-ucreti-guncelle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'ozel-is-turu-ucreti-sil', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'calisma-gecmisi-ekle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'calisma-gecmisi-guncelle', 'POST', 'personel_duzenle'
    UNION ALL SELECT 'calisma-gecmisi-sil', 'POST', 'personel_duzenle'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = actions.permission_key OR p.auth_name = actions.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Bordro API: dönem yönetimi, parametreler ve raporlar birbirinden ayrılır.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'bordro/api', actions.action, 'POST', p.id, actions.superadmin_only
FROM (
    SELECT 'fix-kumulatif-matrah-2026' AS action, 'bordro/list' AS permission_key, 1 AS superadmin_only
    UNION ALL SELECT 'donem-ekle', 'bordro/list', 0
    UNION ALL SELECT 'donem-guncelle', 'bordro/list', 0
    UNION ALL SELECT 'donem-personel-gorsun-guncelle', 'bordro/list', 0
    UNION ALL SELECT 'get-personel-kesinti-listesi', 'bordro/list', 0
    UNION ALL SELECT 'get-personel-ek-odeme-listesi', 'bordro/list', 0
    UNION ALL SELECT 'personel-kesinti-sil', 'bordro/list', 0
    UNION ALL SELECT 'personel-ek-odeme-sil', 'bordro/list', 0
    UNION ALL SELECT 'personel-guncelle', 'bordro/list', 0
    UNION ALL SELECT 'personel-cikar', 'bordro/list', 0
    UNION ALL SELECT 'maas-hesapla', 'bordro/list', 0
    UNION ALL SELECT 'get-icra-detail', 'bordro/list', 0
    UNION ALL SELECT 'get-detail-old', 'bordro/list', 0
    UNION ALL SELECT 'get-detail', 'bordro/list', 0
    UNION ALL SELECT 'donem-kapat', 'bordro/list', 0
    UNION ALL SELECT 'donem-ac', 'bordro/list', 0
    UNION ALL SELECT 'odeme-dagit', 'bordro/list', 0
    UNION ALL SELECT 'odeme-reset', 'bordro/list', 0
    UNION ALL SELECT 'odeme-reset-all', 'bordro/list', 0
    UNION ALL SELECT 'personel-gelir-ekle', 'bordro/list', 0
    UNION ALL SELECT 'personel-kesinti-ekle', 'bordro/list', 0
    UNION ALL SELECT 'surekli-kayitlari-olustur', 'bordro/list', 0
    UNION ALL SELECT 'gelir-ekle', 'bordro/list', 0
    UNION ALL SELECT 'kesinti-ekle', 'bordro/list', 0
    UNION ALL SELECT 'odeme-dagit-excel', 'bordro/list', 0
    UNION ALL SELECT 'tablo-yenile', 'bordro/list', 0
    UNION ALL SELECT 'yayin-onizle', 'bordro/list', 0
    UNION ALL SELECT 'yayin-yayinla', 'bordro/list', 0
    UNION ALL SELECT 'yayin-test-yayinla', 'bordro/list', 0
    UNION ALL SELECT 'yayin-takip', 'bordro/list', 0
    UNION ALL SELECT 'yayin-detay', 'bordro/list', 0
    UNION ALL SELECT 'yayin-yanitla', 'bordro/list', 0
    UNION ALL SELECT 'add-parametre', 'bordro/parametreler', 0
    UNION ALL SELECT 'update-parametre', 'bordro/parametreler', 0
    UNION ALL SELECT 'add-genel-ayar', 'bordro/parametreler', 0
    UNION ALL SELECT 'get-gelir-turleri', 'bordro/parametreler', 0
    UNION ALL SELECT 'get-kesinti-turleri', 'bordro/parametreler', 0
    UNION ALL SELECT 'delete-parametre', 'bordro/parametreler', 0
    UNION ALL SELECT 'update-genel-ayar', 'bordro/parametreler', 0
    UNION ALL SELECT 'delete-genel-ayar', 'bordro/parametreler', 0
    UNION ALL SELECT 'copy-genel-ayarlar', 'bordro/parametreler', 0
    UNION ALL SELECT 'add-vergi-dilimi', 'bordro/parametreler', 0
    UNION ALL SELECT 'update-vergi-dilimi', 'bordro/parametreler', 0
    UNION ALL SELECT 'delete-vergi-dilimi', 'bordro/parametreler', 0
    UNION ALL SELECT 'donem-sil', 'bordro/list', 0
    UNION ALL SELECT 'get-hatali-islem-raporu', 'bordro/raporlar', 0
    UNION ALL SELECT 'ai-bordro-audit', 'bordro/raporlar', 0
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = actions.permission_key OR p.auth_name = actions.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Finans API politikaları.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', policies.resource, policies.action, 'POST', p.id, 0
FROM (
    SELECT 'cari/api' AS resource, 'get-unique-values' AS action, 'cari_takibi' AS permission_key
    UNION ALL SELECT 'cari/api', 'get_unique_values', 'cari_takibi'
    UNION ALL SELECT 'cari/api', 'cari-ajax-list', 'cari_takibi'
    UNION ALL SELECT 'cari/api', 'cari-kaydet', 'cari_takibi'
    UNION ALL SELECT 'cari/api', 'cari-not-kaydet', 'cari_takibi'
    UNION ALL SELECT 'cari/api', 'cari-getir', 'cari_takibi'
    UNION ALL SELECT 'cari/api', 'vkn-sorgula', 'cari_takibi'
    UNION ALL SELECT 'cari/api', 'cari-sil', 'cari_takibi'
    UNION ALL SELECT 'cari/api', 'hesap-hareketleri-ajax-list', 'cari_hesap_hareketleri'
    UNION ALL SELECT 'cari/api', 'hizli-hareket-kaydet', 'cari_hesap_hareketleri'
    UNION ALL SELECT 'cari/api', 'hareket-getir', 'cari_hesap_hareketleri'
    UNION ALL SELECT 'cari/api', 'hareket-sil', 'cari_hesap_hareketleri'
    UNION ALL SELECT 'cari/api', 'hareket-pdf-analiz', 'cari_hesap_hareketleri'
    UNION ALL SELECT 'cari/api', 'hareket-pdf-kaydet', 'cari_hesap_hareketleri'
    UNION ALL SELECT 'cari/api', 'tum-hareketler-getir', 'cari_hesap_hareketleri'
    UNION ALL SELECT 'gelir-gider/api', 'gelir-gider-kaydet', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'gelir-gider-getir', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'gelir-gider-sil', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'gelir-gider-toplu-sil', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'gelir-gider-turu-getir', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'hesap-adlari-getir', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'plakalari-getir', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'bankalari-getir', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'get-unique-values', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'gelir-gider-ajax-list', 'gelir_gider_takibi'
    UNION ALL SELECT 'gelir-gider/api', 'tum-hareketler-getir', 'gelir_gider_takibi'
    UNION ALL SELECT 'kasa/api', 'kasa_kaydet', 'gelir_gider_takibi'
    UNION ALL SELECT 'kasa/api', 'kasa_sil', 'gelir_gider_takibi'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Demirbaş API: ana kayıt, sayaç, aparat, zimmet ve servis yetkileri ayrıdır.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'demirbas/api', policies.action, '*', p.id, 0
FROM (
    SELECT 'demirbas-kaydet' AS action, 'demirbas/list' AS permission_key
    UNION ALL SELECT 'demirbas-toplu-kaydet', 'demirbas/list'
    UNION ALL SELECT 'demirbas-getir', 'demirbas/list'
    UNION ALL SELECT 'bulk-demirbas-sil', 'demirbas/list'
    UNION ALL SELECT 'demirbas-sil', 'demirbas/list'
    UNION ALL SELECT 'demirbas-listesi', 'demirbas/list'
    UNION ALL SELECT 'excel-upload', 'demirbas/list'
    UNION ALL SELECT 'personel-ara', 'demirbas/list'
    UNION ALL SELECT 'demirbas-ara', 'demirbas/list'
    UNION ALL SELECT 'kategori-listesi', 'demirbas/list'
    UNION ALL SELECT 'is-emri-sonuclari', 'demirbas/list'
    UNION ALL SELECT 'hareket-gecmisi', 'demirbas/list'
    UNION ALL SELECT 'get-unique-values', 'demirbas/list'
    UNION ALL SELECT 'kasiye-teslim', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'toplu-kasiye-teslim', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'get-filtered-sayac-ids', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'get-filtered-hareket-ids', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'kaskiden-depoya-cek', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-montaj-yap', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-depo-hareketleri', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-depo-hareketleri-grouped', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-depo-hareketleri-detay', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'bulk-hareket-sil', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'hareket-sil', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'bulk-kasiye-teslim', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-kaski-date-details', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-global-summary', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-kaski-ozet', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-kaski-tarih-list', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-personel-all-summary', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-personel-list', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-personel-daily-details', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-personel-summary', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-personel-history', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'sayac-hareketler-list', 'demirbas/sayac-deposu'
    UNION ALL SELECT 'zimmet-ver', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-listesi', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-kaydet', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-iade', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-depoya-iade', 'demirbas/zimmet'
    UNION ALL SELECT 'hurda-zimmet-listesi', 'demirbas/zimmet'
    UNION ALL SELECT 'hurda-sayac-iade', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-sil', 'demirbas/zimmet'
    UNION ALL SELECT 'bulk-zimmet-sil', 'demirbas/zimmet'
    UNION ALL SELECT 'bulk-zimmet-iade', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-hareket-sil', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-hareket-toplu-sil', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-detay', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-foto-sil', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-duzenle-get', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-duzenle-save', 'demirbas/zimmet'
    UNION ALL SELECT 'personel-bakiye', 'demirbas/zimmet'
    UNION ALL SELECT 'zimmet-stats-chart', 'demirbas/zimmet'
    UNION ALL SELECT 'toplu-aparat-zimmet-kaydet', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'koli-kontrol', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'zimmet-koli-kaydet-coklu', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'aparat-zimmet-kayitlari', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'aparat-global-summary', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'aparat-personel-list', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'aparat-personel-summary', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'aparat-personel-history', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'aparat-hareketler-list', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'aparat-personel-ozet', 'demirbas/aparat-deposu'
    UNION ALL SELECT 'servis-listesi', 'demirbas/servis'
    UNION ALL SELECT 'servis-detay', 'demirbas/servis'
    UNION ALL SELECT 'servis-kaydet', 'demirbas/servis'
    UNION ALL SELECT 'servis-sil', 'demirbas/servis'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Araç takip API: yönetim, puantaj, performans, KM onayı ve AI yetkileri ayrıdır.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'arac-takip/api', policies.action, '*', p.id, 0
FROM (
    SELECT 'arac-kaydet' AS action, 'arac_takip_yonetim' AS permission_key
    UNION ALL SELECT 'arac-sil', 'arac_takip_yonetim'
    UNION ALL SELECT 'arac-detay', 'arac_takip_yonetim'
    UNION ALL SELECT 'arac-ruhsat-yukle', 'arac_takip_yonetim'
    UNION ALL SELECT 'arac-ruhsat-sil', 'arac_takip_yonetim'
    UNION ALL SELECT 'arac-listesi', 'arac_takip_yonetim'
    UNION ALL SELECT 'arac-excel-aktar', 'arac_takip_yonetim'
    UNION ALL SELECT 'arac-excel-yukle', 'arac_takip_yonetim'
    UNION ALL SELECT 'zimmet-ver', 'arac_takip_yonetim'
    UNION ALL SELECT 'zimmet-iade', 'arac_takip_yonetim'
    UNION ALL SELECT 'zimmet-foto-listele', 'arac_takip_yonetim'
    UNION ALL SELECT 'zimmet-foto-sil', 'arac_takip_yonetim'
    UNION ALL SELECT 'zimmet-listesi', 'arac_takip_yonetim'
    UNION ALL SELECT 'zimmet-gecmisi', 'arac_takip_yonetim'
    UNION ALL SELECT 'arac-zimmet-detay-get', 'arac_takip_yonetim'
    UNION ALL SELECT 'arac-zimmet-guncelle', 'arac_takip_yonetim'
    UNION ALL SELECT 'zimmet-gecmisi-excel', 'arac_takip_yonetim'
    UNION ALL SELECT 'yakit-kaydet', 'arac_takip_yonetim'
    UNION ALL SELECT 'yakit-sil', 'arac_takip_yonetim'
    UNION ALL SELECT 'yakit-toplu-sil', 'arac_takip_yonetim'
    UNION ALL SELECT 'yakit-listesi', 'arac_takip_yonetim'
    UNION ALL SELECT 'get-yakit-personelleri', 'arac_takip_yonetim'
    UNION ALL SELECT 'yakit-detay', 'arac_takip_yonetim'
    UNION ALL SELECT 'km-kaydet', 'arac_takip_yonetim'
    UNION ALL SELECT 'km-sil', 'arac_takip_yonetim'
    UNION ALL SELECT 'km-listesi', 'arac_takip_yonetim'
    UNION ALL SELECT 'km-detay', 'arac_takip_yonetim'
    UNION ALL SELECT 'km-excel-yukle', 'arac_takip_yonetim'
    UNION ALL SELECT 'km-kaydet-inline', 'arac_takip_yonetim'
    UNION ALL SELECT 'yakit-excel-yukle', 'arac_takip_yonetim'
    UNION ALL SELECT 'servis-kaydet', 'arac_takip_yonetim'
    UNION ALL SELECT 'servis-sil', 'arac_takip_yonetim'
    UNION ALL SELECT 'servis-listesi', 'arac_takip_yonetim'
    UNION ALL SELECT 'servis-detay', 'arac_takip_yonetim'
    UNION ALL SELECT 'get-arac-puantaj-table', 'arac_takip_puantaj'
    UNION ALL SELECT 'get-arac-ozel-puantaj', 'arac_takip_puantaj'
    UNION ALL SELECT 'aylik-rapor', 'arac_takip_puantaj'
    UNION ALL SELECT 'gunluk-rapor', 'arac_takip_puantaj'
    UNION ALL SELECT 'yillik-rapor', 'arac_takip_puantaj'
    UNION ALL SELECT 'arac-performans', 'arac_performans'
    UNION ALL SELECT 'get-arac-analiz', 'arac_performans'
    UNION ALL SELECT 'arac-karsilastirma', 'arac_performans'
    UNION ALL SELECT 'get-km-onay-yapmayanlar', 'arac_km_onaylari'
    UNION ALL SELECT 'send-km-bildirim-hatirlatma', 'arac_km_onaylari'
    UNION ALL SELECT 'onayli-km-guncelle', 'arac_km_onaylari'
    UNION ALL SELECT 'get-km-onay-list-server-side', 'arac_km_onaylari'
    UNION ALL SELECT 'get-mobile-km-onaylari', 'arac_km_onaylari'
    UNION ALL SELECT 'get-km-image-cleanup-count', 'arac_km_onaylari'
    UNION ALL SELECT 'start-km-image-cleanup', 'arac_km_onaylari'
    UNION ALL SELECT 'delete-km-archive-zip', 'arac_km_onaylari'
    UNION ALL SELECT 'km-ai-kontrol-onayla', 'arac_km_onaylari'
    UNION ALL SELECT 'km-onay-ver', 'arac_km_onaylari'
    UNION ALL SELECT 'km-onay-duzelt-onayla', 'arac_km_onaylari'
    UNION ALL SELECT 'km-onay-reddet', 'arac_km_onaylari'
    UNION ALL SELECT 'km-onay-toplu-onayla', 'arac_km_onaylari'
    UNION ALL SELECT 'ai_agent_query', 'ai_is_ajani_arac_takip'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Puantaj / İş Takip API: veri yükleme, rapor, defter, sorgu, kaçak ve ayar yetkileri ayrıdır.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'puantaj/api', policies.action, '*', p.id, 0
FROM (
    SELECT 'puantaj-excel-kaydet' AS action, 'puantaj/veri-yukleme' AS permission_key
    UNION ALL SELECT 'endeks-excel-kaydet', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'endeks-sil', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'puantaj-sil', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'endeks-datatable', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'puantaj-datatable', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'sayac-degisim-datatable', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'muhurleme-datatable', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'get-unique-values', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'sayac-degisim-sil', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'get-tab-content', 'puantaj/veri-yukleme'
    UNION ALL SELECT 'kacak-excel-kaydet', 'kacak_islemleri'
    UNION ALL SELECT 'kacak-sil', 'kacak_islemleri'
    UNION ALL SELECT 'kacak-hucre-sil', 'kacak_islemleri'
    UNION ALL SELECT 'kacak-kaydet', 'kacak_islemleri'
    UNION ALL SELECT 'get-kacak-cell-records', 'kacak_islemleri'
    UNION ALL SELECT 'get-kacak-record', 'kacak_islemleri'
    UNION ALL SELECT 'get-kacak-teams', 'kacak_islemleri'
    UNION ALL SELECT 'get-report-table', 'puantaj/raporlar'
    UNION ALL SELECT 'get-mobile-report-cards', 'puantaj/raporlar'
    UNION ALL SELECT 'get-mobile-personel-details', 'puantaj/raporlar'
    UNION ALL SELECT 'get-mobile-personel-is-takip', 'puantaj/raporlar'
    UNION ALL SELECT 'get-mobile-personel-is-takip-summary', 'puantaj/raporlar'
    UNION ALL SELECT 'get-comparison-report', 'puantaj/raporlar'
    UNION ALL SELECT 'online-puantaj-sorgula', 'puantaj/raporlar'
    UNION ALL SELECT 'online-icmal-sorgula', 'puantaj/raporlar'
    UNION ALL SELECT 'okuma-gun-sayilari', 'puantaj/raporlar'
    UNION ALL SELECT 'export-okuma-comparison-excel', 'puantaj/raporlar'
    UNION ALL SELECT 'get-okuma-comparison', 'puantaj/raporlar'
    UNION ALL SELECT 'get-puantaj-comparison', 'puantaj/raporlar'
    UNION ALL SELECT 'get-sayac-comparison', 'puantaj/raporlar'
    UNION ALL SELECT 'get-personel-by-region', 'puantaj/raporlar'
    UNION ALL SELECT 'defter-bazli-rapor', 'defter_bazli_rapor'
    UNION ALL SELECT 'defter-ozet-rapor', 'defter_bazli_rapor'
    UNION ALL SELECT 'save-manuel-dusum', 'defter_bazli_rapor'
    UNION ALL SELECT 'online-sayac-degisim-sorgula', 'sorgulama'
    UNION ALL SELECT 'online-sorgu-kesme-acma', 'sorgulama'
    UNION ALL SELECT 'online-endeks-sorgula', 'sorgulama'
    UNION ALL SELECT 'online-sayac-sorgula', 'sorgulama'
    UNION ALL SELECT 'get-puantaj-sorgu-datatable', 'sorgulama'
    UNION ALL SELECT 'get-endeks-sorgu-datatable', 'sorgulama'
    UNION ALL SELECT 'get-sayac-sorgu-datatable', 'sorgulama'
    UNION ALL SELECT 'sorgu-sil-generic', 'sorgulama'
    UNION ALL SELECT 'sorgu-sil-toplu-generic', 'sorgulama'
    UNION ALL SELECT 'export-excel-sorgu-generic', 'sorgulama'
    UNION ALL SELECT 'save-report-settings', 'is_takip_ayarlar'
    UNION ALL SELECT 'report-settings-kaydet', 'is_takip_ayarlar'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Kaçak API: temel erişim, düzenleme, onay, iptal, arşiv, sicil ve bildirim personeli ayrıdır.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'kacak/api', policies.action, '*', p.id, 0
FROM (
    SELECT 'get-unique-values' AS action, 'kacak_islemleri' AS permission_key
    UNION ALL SELECT 'list', 'kacak_islemleri'
    UNION ALL SELECT 'get-record', 'kacak_islemleri'
    UNION ALL SELECT 'pending-count', 'kacak_islemleri'
    UNION ALL SELECT 'dashboard', 'kacak_islemleri'
    UNION ALL SELECT 'get-photos', 'kacak_islemleri'
    UNION ALL SELECT 'download-zip', 'kacak_islemleri'
    UNION ALL SELECT 'gunluk-rapor', 'kacak_islemleri'
    UNION ALL SELECT 'haftalik-rapor', 'kacak_islemleri'
    UNION ALL SELECT 'teslim-alma-listesi', 'kacak_islemleri'
    UNION ALL SELECT 'save', 'kacak_duzenle'
    UNION ALL SELECT 'excel-yukle', 'kacak_duzenle'
    UNION ALL SELECT 'delete', 'kacak_duzenle'
    UNION ALL SELECT 'upload-photo', 'kacak_duzenle'
    UNION ALL SELECT 'analyze', 'kacak_duzenle'
    UNION ALL SELECT 'teslim-alindi-isaretle', 'kacak_duzenle'
    UNION ALL SELECT 'approve', 'kacak_onay'
    UNION ALL SELECT 'reject', 'kacak_onay'
    UNION ALL SELECT 'cancel-candidates', 'kacak_iptal_ekle'
    UNION ALL SELECT 'cancel', 'kacak_iptal_ekle'
    UNION ALL SELECT 'revert-cancel', 'kacak_iptal'
    UNION ALL SELECT 'delete-photo', 'kacak_arsiv'
    UNION ALL SELECT 'archive-preview', 'kacak_arsiv'
    UNION ALL SELECT 'archive-download', 'kacak_arsiv'
    UNION ALL SELECT 'sicil-list', 'kacak_sicil_bildir'
    UNION ALL SELECT 'sicil-counts', 'kacak_sicil_bildir'
    UNION ALL SELECT 'sicil-detay', 'kacak_sicil_bildir'
    UNION ALL SELECT 'sicil-tutanak-ara', 'kacak_sicil_bildir'
    UNION ALL SELECT 'sicil-create', 'kacak_sicil_bildir'
    UNION ALL SELECT 'sicil-kapat', 'kacak_sicil_bildir'
    UNION ALL SELECT 'sicil-yanitla', 'kacak_sicil_yanitla'
    UNION ALL SELECT 'sicil-eslestir', 'kacak_sicil_yanitla'
    UNION ALL SELECT 'get_bildirim_personelleri', 'kacak_bildirim_personelleri'
    UNION ALL SELECT 'get_bildirim_personeli', 'kacak_bildirim_personelleri'
    UNION ALL SELECT 'save_bildirim_personel', 'kacak_bildirim_personelleri'
    UNION ALL SELECT 'toggle_bildirim_personel_status', 'kacak_bildirim_personelleri'
    UNION ALL SELECT 'reset_bildirim_personel_sifre', 'kacak_bildirim_personelleri'
    UNION ALL SELECT 'delete_bildirim_personel', 'kacak_bildirim_personelleri'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- İhbar API: görüntüleme ve yönetim aksiyonları ayrıdır.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'ihbar/api', policies.action, '*', p.id, 0
FROM (
    SELECT 'foto' AS action, 'ihbar/list' AS permission_key
    UNION ALL SELECT 'detay', 'ihbar/list'
    UNION ALL SELECT 'reassignPreview', 'ihbar/list'
    UNION ALL SELECT 'requestFreshLocations', 'ihbar/list'
    UNION ALL SELECT 'freshLocationStatus', 'ihbar/list'
    UNION ALL SELECT 'create', 'ihbar_duzenle'
    UNION ALL SELECT 'update', 'ihbar_duzenle'
    UNION ALL SELECT 'delete', 'ihbar_duzenle'
    UNION ALL SELECT 'assign', 'ihbar_duzenle'
    UNION ALL SELECT 'bulkReassign', 'ihbar_duzenle'
    UNION ALL SELECT 'bulkAssignSelected', 'ihbar_duzenle'
    UNION ALL SELECT 'saveSettings', 'ihbar_duzenle'
    UNION ALL SELECT 'addNote', 'ihbar_duzenle'
    UNION ALL SELECT 'close', 'ihbar_duzenle'
    UNION ALL SELECT 'cancelResult', 'ihbar_duzenle'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Kesme/Açma API: görüntüleme, mahalle, mesaj, atama, kalan iş, nöbet ve analiz yönetimi ayrıdır.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'kesme-acma/api', policies.action, '*', p.id, 0
FROM (
    SELECT 'ozet' AS action, 'kesme_acma' AS permission_key
    UNION ALL SELECT 'mahalle-listesi', 'kesme_acma'
    UNION ALL SELECT 'ekip-listesi', 'kesme_acma'
    UNION ALL SELECT 'gecmis-listesi', 'kesme_acma'
    UNION ALL SELECT 'nobet-plani', 'kesme_acma'
    UNION ALL SELECT 'matris', 'kesme_acma'
    UNION ALL SELECT 'analiz-dashboard', 'kesme_acma'
    UNION ALL SELECT 'analiz-normal', 'kesme_acma'
    UNION ALL SELECT 'analiz-kurallar', 'kesme_acma'
    UNION ALL SELECT 'analiz-gunluk', 'kesme_acma'
    UNION ALL SELECT 'analiz-kural-kaydet', 'kesme_analiz_yonetim'
    UNION ALL SELECT 'analiz-uyari-kapat', 'kesme_analiz_yonetim'
    UNION ALL SELECT 'analiz-kontrol-isaretle', 'kesme_analiz_yonetim'
    UNION ALL SELECT 'analiz-kontrol-ekle', 'kesme_analiz_yonetim'
    UNION ALL SELECT 'analiz-kontrol-sil', 'kesme_analiz_yonetim'
    UNION ALL SELECT 'mahalle-kaydet', 'kesme_mahalle_tanim'
    UNION ALL SELECT 'mahalle-havuz', 'kesme_mahalle_tanim'
    UNION ALL SELECT 'mesaj-kaydet', 'kesme_mesaj'
    UNION ALL SELECT 'atama-yap', 'kesme_atama'
    UNION ALL SELECT 'atama-kapat', 'kesme_atama'
    UNION ALL SELECT 'atama-sil', 'kesme_atama'
    UNION ALL SELECT 'kalan-is-kaydet', 'kesme_kalan_is'
    UNION ALL SELECT 'nobet-saha-yaz', 'kesme_nobet'
    UNION ALL SELECT 'nobet-ilce-yaz', 'kesme_nobet'
    UNION ALL SELECT 'nobet-telefon-yaz', 'kesme_nobet'
    UNION ALL SELECT 'nobet-uret', 'kesme_nobet'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Aparat takip API: depo, tanım, iptal, transfer ve sayım yetkileri ayrıdır.
INSERT INTO permission_policies (scope, resource, action, http_method, permission_id, superadmin_only)
SELECT 'api', 'aparat-takip/api', policies.action, '*', p.id, 0
FROM (
    SELECT 'stok-matris' AS action, 'aparat_takip' AS permission_key
    UNION ALL SELECT 'tutarlilik', 'aparat_takip'
    UNION ALL SELECT 'tip-listesi', 'aparat_takip'
    UNION ALL SELECT 'islem-listesi', 'aparat_takip'
    UNION ALL SELECT 'islem-detay', 'aparat_takip'
    UNION ALL SELECT 'hareket-listesi', 'aparat_takip'
    UNION ALL SELECT 'transfer-listesi', 'aparat_takip'
    UNION ALL SELECT 'sayim-listesi', 'aparat_takip'
    UNION ALL SELECT 'sayim-detay', 'aparat_takip'
    UNION ALL SELECT 'sahada-takili', 'aparat_takip'
    UNION ALL SELECT 'donemsel-ozet', 'aparat_takip'
    UNION ALL SELECT 'api-karsilastirma', 'aparat_takip'
    UNION ALL SELECT 'ekip-listesi', 'aparat_takip'
    UNION ALL SELECT 'bakiye-yeniden-kur', 'aparat_depo'
    UNION ALL SELECT 'islem-kaydet', 'aparat_depo'
    UNION ALL SELECT 'havuz-hareketi', 'aparat_depo'
    UNION ALL SELECT 'tip-kaydet', 'aparat_tanim'
    UNION ALL SELECT 'tip-sil', 'aparat_tanim'
    UNION ALL SELECT 'islem-iptal', 'aparat_iptal'
    UNION ALL SELECT 'hareket-iptal', 'aparat_iptal'
    UNION ALL SELECT 'transfer-iptal', 'aparat_transfer_yonet'
    UNION ALL SELECT 'sayim-baslat', 'aparat_sayim'
    UNION ALL SELECT 'sayim-gir', 'aparat_sayim'
    UNION ALL SELECT 'sayim-farklari-isle', 'aparat_sayim'
    UNION ALL SELECT 'sayim-kapat', 'aparat_sayim'
    UNION ALL SELECT 'sayim-iptal', 'aparat_sayim'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    is_active = 1;

-- Personel PWA işlemleri personel oturumu ve uç içindeki sahiplik/departman kurallarıyla korunur.
-- Debug oturum çıktısı genel PWA politikasından daha öncelikli, yalnızca Superadmin politikasıdır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
VALUES
    ('api', 'personel-pwa/api', '*', '*', NULL, 0, 1),
    ('api', 'personel-pwa/api', 'debugSession', '*', NULL, 1, 0)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = VALUES(superadmin_only),
    authenticated_only = VALUES(authenticated_only),
    is_active = 1;

-- API istemcisi yalnızca Superadmin tarafından çalıştırılır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
VALUES ('api', 'api-istemcisi/api', 'execute', '*', NULL, 1, 0)
ON DUPLICATE KEY UPDATE
    permission_id = NULL, superadmin_only = 1,
    authenticated_only = 0, is_active = 1;

-- Maliyet raporu ve personel takip API politikaları.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'maliyet-raporu/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'manuel-gider-kaydet' AS action
    UNION ALL SELECT 'manuel-gider-sil'
    UNION ALL SELECT 'manuel-gider-detay'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'maliyet_raporu' OR p.auth_name = 'maliyet_raporu')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'personel-takip/api', policies.action, '*', p.id, 0, 0
FROM (
    SELECT 'getOzet' AS action, 'personel-takip/list' AS permission_key
    UNION ALL SELECT 'getPersonelDurumlari', 'personel-takip/list'
    UNION ALL SELECT 'getHareketGecmisi', 'personel-takip/list'
    UNION ALL SELECT 'getRapor', 'personel-takip/list'
    UNION ALL SELECT 'getHaritaVerileri', 'personel-takip/list'
    UNION ALL SELECT 'istekTumKonum', 'personel-takip/list'
    UNION ALL SELECT 'istekKonum', 'personel-takip/list'
    UNION ALL SELECT 'getCalismaRaporu', 'personel-takip/list'
    UNION ALL SELECT 'getGecKalanlar', 'personel-takip/list'
    UNION ALL SELECT 'saveGecikmeAciklama', 'personel-takip/list'
    UNION ALL SELECT 'getGecikmeGecmisi', 'personel-takip/list'
    UNION ALL SELECT 'getHomeStatsDetail', 'personel-takip/list'
    UNION ALL SELECT 'getHomeAracStatsDetail', 'personel-takip/list'
    UNION ALL SELECT 'getDashboardAnaliz', 'personel-takip/list'
    UNION ALL SELECT 'getDepartmanMesaileri', 'personel_takip_deparmana_gore_ise_baslama_belirleme'
    UNION ALL SELECT 'saveDepartmanMesaileri', 'personel_takip_deparmana_gore_ise_baslama_belirleme'
    UNION ALL SELECT 'exportCalismaRaporu', 'personel-takip/list'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Tanımlamalar API'sindeki her veri grubu kendi modül yetkisine bağlıdır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'tanimlamalar/api', policies.action, '*', p.id, 0, 0
FROM (
    SELECT 'gelir-gider-turu-kaydet' AS action, 'tanimlamalar/gelir-gider-turu' AS permission_key
    UNION ALL SELECT 'ekip-kodu-kaydet', 'tanimlamalar/ekip-kodu'
    UNION ALL SELECT 'ekip-kodu-getir', 'tanimlamalar/ekip-kodu'
    UNION ALL SELECT 'ekip-kodu-gecmis-getir', 'tanimlamalar/ekip-kodu'
    UNION ALL SELECT 'ekip-kodu-sil', 'tanimlamalar/ekip-kodu'
    UNION ALL SELECT 'is-turu-kaydet', 'tanimlamalar/is-turu'
    UNION ALL SELECT 'is-turu-getir', 'tanimlamalar/is-turu'
    UNION ALL SELECT 'is-turu-ucret-gecmisi-getir', 'is_turu_ucret_tanimla'
    UNION ALL SELECT 'is-turu-ucret-gecmisi-kaydet', 'is_turu_ucret_tanimla'
    UNION ALL SELECT 'is-turu-sil', 'tanimlamalar/is-turu'
    UNION ALL SELECT 'is-turu-excel-yukle', 'tanimlamalar/is-turu'
    UNION ALL SELECT 'izin-turu-kaydet', 'tanimlamalar/izin-turu'
    UNION ALL SELECT 'izin-turu-getir', 'tanimlamalar/izin-turu'
    UNION ALL SELECT 'izin-turu-sil', 'tanimlamalar/izin-turu'
    UNION ALL SELECT 'bolge-kurallari-getir', 'ekip_kodu_kurallari'
    UNION ALL SELECT 'bolge-kurallari-kaydet', 'ekip_kodu_kurallari'
    UNION ALL SELECT 'unvan-ucret-kaydet', 'tanimlamalar/unvan-ucret'
    UNION ALL SELECT 'unvan-ucret-getir', 'tanimlamalar/unvan-ucret'
    UNION ALL SELECT 'unvan-ucret-sil', 'tanimlamalar/unvan-ucret'
    UNION ALL SELECT 'unvan-ucretleri-getir', 'tanimlamalar/unvan-ucret'
    UNION ALL SELECT 'demirbas-kategorisi-kaydet', 'tanimlamalar/demirbas-kategorileri'
    UNION ALL SELECT 'demirbas-kategorisi-getir', 'tanimlamalar/demirbas-kategorileri'
    UNION ALL SELECT 'demirbas-kategorisi-sil', 'tanimlamalar/demirbas-kategorileri'
    UNION ALL SELECT 'defter-kodu-kaydet', 'tanimlamalar/defter-kodu'
    UNION ALL SELECT 'defter-kodu-getir', 'tanimlamalar/defter-kodu'
    UNION ALL SELECT 'defter-kodu-sil', 'tanimlamalar/defter-kodu'
    UNION ALL SELECT 'defter-kodu-excel-yukle', 'tanimlamalar/defter-kodu'
    UNION ALL SELECT 'defter-kodu-liste', 'tanimlamalar/defter-kodu'
    UNION ALL SELECT 'defter-kodu-api-sorgula', 'tanimlamalar/defter-kodu'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'tanimlamalar/icra-daireleri/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'getir' AS action
    UNION ALL SELECT 'kaydet'
    UNION ALL SELECT 'sil'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'tanimlamalar/icra-daireleri/list' OR p.auth_name = 'tanimlamalar/icra-daireleri/list')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Yardım taleplerinde kişisel işlemler oturumla, yönetim işlemleri ilgili yönetici izniyle korunur.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'yardim/api', actions.action, '*', NULL, 0, 1
FROM (
    SELECT 'create-ticket' AS action
    UNION ALL SELECT 'get-tickets-pwa'
    UNION ALL SELECT 'get-ticket-details'
    UNION ALL SELECT 'add-message'
) actions
ON DUPLICATE KEY UPDATE
    permission_id = NULL, superadmin_only = 0,
    authenticated_only = 1, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'yardim/api', policies.action, '*', p.id, 0, 0
FROM (
    SELECT 'get-tickets-admin' AS action, 'admin_destek_talebi' AS permission_key
    UNION ALL SELECT 'update-approval', 'destek_talebi_onaylama'
    UNION ALL SELECT 'update-status', 'admin_destek_talebi'
    UNION ALL SELECT 'get-stats', 'admin_destek_talebi'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Ana sayfa ve profil uçları yalnızca oturum sahibinin kendi kapsamındaki verileri işler.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'home/api', actions.action, '*', NULL, 0, 1
FROM (
    SELECT 'save-dashboard-settings' AS action
    UNION ALL SELECT 'batch-load-all'
    UNION ALL SELECT 'get-work-type-stats'
    UNION ALL SELECT 'get-work-result-stats'
    UNION ALL SELECT 'get-endeks-comparison'
    UNION ALL SELECT 'get-kesme-comparison'
    UNION ALL SELECT 'get-dashboard-operational-stats'
    UNION ALL SELECT 'batch-load-widgets'
    UNION ALL SELECT 'get-arac-evrak-stats'
) actions
ON DUPLICATE KEY UPDATE
    permission_id = NULL, superadmin_only = 0,
    authenticated_only = 1, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'profil/api', actions.action, '*', NULL, 0, 1
FROM (
    SELECT 'profil-guncelle' AS action
    UNION ALL SELECT 'ayarlari-guncelle'
    UNION ALL SELECT 'save-mobile-menu-order'
    UNION ALL SELECT 'reset-mobile-menu-order'
) actions
ON DUPLICATE KEY UPDATE
    permission_id = NULL, superadmin_only = 0,
    authenticated_only = 1, is_active = 1;

-- Kullanıcı ve rehber yönetim uçları kendi modül izinlerine bağlıdır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'kullanici/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'kullanici-kaydet' AS action
    UNION ALL SELECT 'kullanici-sil'
    UNION ALL SELECT 'kullanici-durum-degistir'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'kullanici/list' OR p.auth_name = 'kullanici/list')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'rehber/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'kaydet' AS action
    UNION ALL SELECT 'kayitSil'
    UNION ALL SELECT 'kayitGetir'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'rehber/list' OR p.auth_name = 'rehber/list')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Toplu raporlarda silme aksiyonu ayrıca yüksek seviye silme izni ister.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'raporlar/api', policies.action, '*', p.id, 0, 0
FROM (
    SELECT 'get-summary' AS action, 'toplu_raporlar' AS permission_key
    UNION ALL SELECT 'get-rapor', 'toplu_raporlar'
    UNION ALL SELECT 'export-rapor', 'toplu_raporlar'
    UNION ALL SELECT 'delete-rapor-satir', 'toplu_raporlar_satir_silme'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Evrak takip API'si: mevcut evrak modülü erişimi bütün API aksiyonlarında zorunludur.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'evrak-takip/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'evrak-sablon-listele' AS action
    UNION ALL SELECT 'evrak-sablon-kaydet'
    UNION ALL SELECT 'evrak-sablon-sil'
    UNION ALL SELECT 'evrak-kaydet'
    UNION ALL SELECT 'evrak-e-imza-onaya-sun'
    UNION ALL SELECT 'evrak-sil'
    UNION ALL SELECT 'evrak-e-imza-onayla'
    UNION ALL SELECT 'evrak-e-imza-iade'
    UNION ALL SELECT 'evrak-e-imza-geri-al'
    UNION ALL SELECT 'evrak-detay'
    UNION ALL SELECT 'evrak-listesi'
    UNION ALL SELECT 'evrak-istatistik'
    UNION ALL SELECT 'get-next-evrak-no'
    UNION ALL SELECT 'get-konular'
    UNION ALL SELECT 'evrak-bildir'
    UNION ALL SELECT 'arac-zimmet-sorgula'
    UNION ALL SELECT 'evrak-ai-taslak-olustur'
    UNION ALL SELECT 'evrak-ai-metin-duzenle'
    UNION ALL SELECT 'icra-listesi'
    UNION ALL SELECT 'icra-ust-yazi-listesi'
    UNION ALL SELECT 'taslak'
    UNION ALL SELECT 'icra-ust-yazi-olustur'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'evrak-takip/list' OR p.auth_name = 'evrak-takip/list')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Nöbet API'si: planlama, talep ve yönetici onayı birbirinden ayrılır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'nobet/api', policies.action, '*', p.id, 0, 0
FROM (
    SELECT 'save-settings' AS action, 'nobet_onceki_gunlerde_islem_yapabilir' AS permission_key
    UNION ALL SELECT 'send-bulk-notifications', 'nobet/list'
    UNION ALL SELECT 'send-today-nobet-reminder', 'nobet/list'
    UNION ALL SELECT 'get-bildirim-stats', 'nobet/list'
    UNION ALL SELECT 'get-calendar-events', 'nobet/list'
    UNION ALL SELECT 'add-nobet', 'nobet/list'
    UNION ALL SELECT 'update-nobet', 'nobet/list'
    UNION ALL SELECT 'delete-nobet', 'nobet/list'
    UNION ALL SELECT 'move-nobet', 'nobet/list'
    UNION ALL SELECT 'update-nobet-tipi', 'nobet/list'
    UNION ALL SELECT 'drop-personel', 'nobet/list'
    UNION ALL SELECT 'create-degisim-talebi', 'nobet/talepler'
    UNION ALL SELECT 'onayla-personel-talebi', 'nobet/talepler'
    UNION ALL SELECT 'onayla-amir-talebi', 'nobet/talepler'
    UNION ALL SELECT 'reddet-talebi', 'nobet/talepler'
    UNION ALL SELECT 'get-bekleyen-talepler', 'nobet/talepler'
    UNION ALL SELECT 'devir-yap', 'nobet/talepler'
    UNION ALL SELECT 'get-personel-list', 'nobet/list'
    UNION ALL SELECT 'get-nobet-dagilimi', 'nobet/list'
    UNION ALL SELECT 'get-personel-istatistikleri', 'nobet/list'
    UNION ALL SELECT 'get-nobet-detay', 'nobet/list'
    UNION ALL SELECT 'get-degisim-talepleri', 'nobet/talepler'
    UNION ALL SELECT 'get-mazeret-bildirimleri', 'nobet/talepler'
    UNION ALL SELECT 'get-talep-stats', 'nobet/talepler'
    UNION ALL SELECT 'onayla-degisim-talebi', 'yonetici_onayi'
    UNION ALL SELECT 'get-talep-gecmisi', 'nobet/talepler'
    UNION ALL SELECT 'get-mazeret-gecmisi', 'nobet/talepler'
    UNION ALL SELECT 'get-personel-nobet-talepleri', 'nobet/talepler'
    UNION ALL SELECT 'onayla-personel-nobet-talebi', 'yonetici_onayi'
    UNION ALL SELECT 'reddet-personel-nobet-talebi', 'yonetici_onayi'
    UNION ALL SELECT 'reddet-mazeret', 'yonetici_onayi'
    UNION ALL SELECT 'get-onay-bekleyen-nobetler', 'yonetici_onayi'
    UNION ALL SELECT 'get-onay-stats', 'yonetici_onayi'
    UNION ALL SELECT 'onayla-nobet', 'yonetici_onayi'
    UNION ALL SELECT 'onayi-kaldir-nobet', 'yonetici_onayi'
    UNION ALL SELECT 'bulk-onayla-nobet', 'yonetici_onayi'
    UNION ALL SELECT 'bulk-onayi-kaldir-nobet', 'yonetici_onayi'
    UNION ALL SELECT 'bulk-sil-nobet', 'yonetici_onayi'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Talep panosunun birleşik özetleri kendi içlerinde alt yetkilere göre filtrelenir.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'talepler/api', actions.action, '*', NULL, 0, 1
FROM (
    SELECT 'get-all-pending' AS action
    UNION ALL SELECT 'get-all-approved'
    UNION ALL SELECT 'get-dashboard-summary'
) actions
ON DUPLICATE KEY UPDATE
    permission_id = NULL, superadmin_only = 0,
    authenticated_only = 1, is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'talepler/api', policies.action, '*', p.id, 0, 0
FROM (
    SELECT 'get-avans-detay' AS action, 'avans_talepleri' AS permission_key
    UNION ALL SELECT 'avans-onayla', 'avans_talepleri'
    UNION ALL SELECT 'avans-reddet', 'avans_talepleri'
    UNION ALL SELECT 'avans-sil', 'avans_talepleri'
    UNION ALL SELECT 'get-izin-detay', 'izin_talepleri'
    UNION ALL SELECT 'izin-onayla', 'izin_talepleri'
    UNION ALL SELECT 'izin-reddet', 'izin_talepleri'
    UNION ALL SELECT 'izin-sil', 'izin_talepleri'
    UNION ALL SELECT 'get-talep-detay', 'ariza_talepleri'
    UNION ALL SELECT 'talep-cozuldu', 'ariza_talepleri'
    UNION ALL SELECT 'talep-isleme-al', 'ariza_talepleri'
    UNION ALL SELECT 'talep-sil', 'ariza_talepleri'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Canlı destek yönetici API'si yalnızca canlı sohbet yönetim yetkisine açıktır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'destek/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'get-conversations' AS action
    UNION ALL SELECT 'get-all-conversations'
    UNION ALL SELECT 'get-messages'
    UNION ALL SELECT 'send-message'
    UNION ALL SELECT 'send-image'
    UNION ALL SELECT 'update-status'
    UNION ALL SELECT 'get-unread-count'
    UNION ALL SELECT 'check-new-messages'
    UNION ALL SELECT 'poll-messages'
    UNION ALL SELECT 'delete-conversation'
    UNION ALL SELECT 'set-admin-status'
    UNION ALL SELECT 'get-admin-status'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'canli_sohbet_ayarlari_sekmesi' OR p.auth_name = 'canli_sohbet_ayarlari_sekmesi')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;

-- Sistem ayarları API'si: genel ayarlar ile özel sekme yetkileri aksiyon bazında ayrılır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'ayarlar/api', policies.action, '*', p.id, 0, 0
FROM (
    SELECT 'get' AS action, 'ayarlar/duzenle' AS permission_key
    UNION ALL SELECT 'save', 'ayarlar/duzenle'
    UNION ALL SELECT 'test_email_ayarlari', 'ayarlar/duzenle'
    UNION ALL SELECT 'test_sms_ayarlari', 'ayarlar/duzenle'
    UNION ALL SELECT 'remove_logo', 'ayarlar/duzenle'
    UNION ALL SELECT 'save_sgk_ayarlari', 'sgk_vizite_ayarlari_sekmesi'
    UNION ALL SELECT 'test_sgk_baglantisi', 'sgk_vizite_ayarlari_sekmesi'
    UNION ALL SELECT 'save_evrak_ayarlari', 'evrak_bilgileri_sekmesi'
    UNION ALL SELECT 'save_avans_ayarlari', 'avans_ayarlari_sekmesi'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Menü yönetimi API'sinin tüm aksiyonları yalnızca Superadmin tarafından çalıştırılabilir.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'menu-yonetimi/api', actions.action, '*', NULL, 1, 0
FROM (
    SELECT 'fetch_list' AS action
    UNION ALL SELECT 'get_parents'
    UNION ALL SELECT 'get_groups'
    UNION ALL SELECT 'get_detail'
    UNION ALL SELECT 'save'
    UNION ALL SELECT 'soft_delete'
    UNION ALL SELECT 'update_hierarchy'
    UNION ALL SELECT 'reset_defaults'
    UNION ALL SELECT 'restore'
) actions
ON DUPLICATE KEY UPDATE
    permission_id = NULL,
    superadmin_only = 1,
    authenticated_only = 0,
    is_active = 1;

-- Sistem logları API'si: bütün log görünümleri ortak log kayıtları yetkisine bağlıdır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'logs/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'get-unified-logs' AS action
    UNION ALL SELECT 'get-system-logs'
    UNION ALL SELECT 'get-personel-logs'
    UNION ALL SELECT 'get-user-logs'
    UNION ALL SELECT 'get-ai-agent-logs'
    UNION ALL SELECT 'get-page-view-logs'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'log_kayitlari' OR p.auth_name = 'log_kayitlari')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Kişisel notlar API'si: firma ve kullanıcı oturumu yeterlidir; kayıtlar modelde kullanıcıya göre süzülür.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'notlar/api', actions.action, '*', NULL, 0, 1
FROM (
    SELECT 'add-defter' AS action
    UNION ALL SELECT 'add-not'
    UNION ALL SELECT 'delete-defter'
    UNION ALL SELECT 'delete-not'
    UNION ALL SELECT 'get-defterler'
    UNION ALL SELECT 'get-notlar'
    UNION ALL SELECT 'pin-not'
    UNION ALL SELECT 'update-defter'
    UNION ALL SELECT 'update-not'
) actions
ON DUPLICATE KEY UPDATE
    permission_id = NULL,
    superadmin_only = 0,
    authenticated_only = 1,
    is_active = 1;

-- Görev API'si: görev modülüne erişebilen kullanıcılar kendi firma/kullanıcı kapsamındaki işlemleri yapar.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'gorevler/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'add-gorev' AS action
    UNION ALL SELECT 'add-liste'
    UNION ALL SELECT 'check-subscription-status'
    UNION ALL SELECT 'delete-gorev'
    UNION ALL SELECT 'delete-liste'
    UNION ALL SELECT 'geri-al'
    UNION ALL SELECT 'get-gorevler'
    UNION ALL SELECT 'get-listeler'
    UNION ALL SELECT 'get-settings'
    UNION ALL SELECT 'get-settings-for-task'
    UNION ALL SELECT 'get-tum-gorevler'
    UNION ALL SELECT 'get-upcoming-alarms'
    UNION ALL SELECT 'mark-notified'
    UNION ALL SELECT 'remove-subscription'
    UNION ALL SELECT 'save-settings'
    UNION ALL SELECT 'save-subscription'
    UNION ALL SELECT 'tamamla'
    UNION ALL SELECT 'update-gorev'
    UNION ALL SELECT 'update-liste'
    UNION ALL SELECT 'update-liste-sira'
    UNION ALL SELECT 'update-sira'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'gorevler' OR p.auth_name = 'gorevler')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Duyuru API'si: duyuru görüntüleme ve yönetim işlemleri aynı modül yetkisine bağlıdır.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'duyuru/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'delete' AS action
    UNION ALL SELECT 'get'
    UNION ALL SELECT 'save'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'duyuru_etkinlik' OR p.auth_name = 'duyuru_etkinlik')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- Bildirim API'si: kişisel bildirim kutusu oturumla, toplu gönderim ve kayıt listesi yönetim yetkisiyle korunur.
INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'bildirim/api', actions.action, '*', NULL, 0, 1
FROM (
    SELECT 'get-unread' AS action
    UNION ALL SELECT 'mark-all-read'
    UNION ALL SELECT 'mark-read'
    UNION ALL SELECT 'save-subscription'
) actions
ON DUPLICATE KEY UPDATE
    permission_id = NULL,
    superadmin_only = 0,
    authenticated_only = 1,
    is_active = 1;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'bildirim/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'datatable-list' AS action
    UNION ALL SELECT 'send-notification'
    UNION ALL SELECT 'test-notification'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'gorev-bildirimler' OR p.auth_name = 'gorev-bildirimler')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;

-- İnceleme raporu: politika tanımı olmayan aktif ve korumalı menüler.
SELECT m.id, m.menu_name, m.menu_link
FROM menus m
LEFT JOIN permission_policies pp
    ON pp.scope = 'page' AND pp.resource = m.menu_link AND pp.is_active = 1
WHERE m.is_active = 1
  AND m.is_authorized = 1
  AND m.menu_link IS NOT NULL
  AND TRIM(m.menu_link) <> ''
  AND pp.id IS NULL
ORDER BY m.group_order, m.menu_order;
