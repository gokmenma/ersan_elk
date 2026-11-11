-- Personel > Sözleşme Şablonları: şablon sürüm tablosu, yetkiler, menü ve politikalar.
-- Betik tekrar çalıştırılabilir (idempotent). Sabit permission ID kullanılmaz.

CREATE TABLE IF NOT EXISTS personel_sozlesme_sablonlari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT UNSIGNED NOT NULL,
    sablon_turu VARCHAR(50) NOT NULL DEFAULT 'hizmet_sozlesmesi',
    versiyon INT UNSIGNED NOT NULL,
    dosya_adi VARCHAR(100) NOT NULL,
    orijinal_dosya_adi VARCHAR(255) NOT NULL,
    dosya_boyutu INT UNSIGNED NOT NULL DEFAULT 0,
    sha256 CHAR(64) NOT NULL,
    aciklama VARCHAR(255) NULL,
    kullanimda TINYINT(1) NOT NULL DEFAULT 0,
    yukleyen_id INT UNSIGNED NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sablon_versiyon (firma_id, sablon_turu, versiyon),
    KEY idx_sablon_kullanim (firma_id, sablon_turu, kullanimda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO permissions (name, auth_name, permission_key, description, group_name, permission_level, is_active, superadmin, is_required)
SELECT 'Sözleşme Şablonları', 'personel/sozlesme-sablonlari', 'personel/sozlesme-sablonlari',
       'Hizmet sözleşmesi şablon sürümlerini ve yer tutucu listesini görüntüleme, şablon indirme', 'Personel Yönetim', 1, 1, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_key = 'personel/sozlesme-sablonlari');

INSERT INTO permissions (name, auth_name, permission_key, description, group_name, permission_level, is_active, superadmin, is_required)
SELECT 'Sözleşme Şablonu Yönetimi', 'personel/sozlesme-sablonlari-duzenle', 'personel/sozlesme-sablonlari-duzenle',
       'Hizmet sözleşmesi şablonunun yeni sürümünü yükleme ve kullanılan sürümü değiştirme', 'Personel Yönetim', 1, 1, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_key = 'personel/sozlesme-sablonlari-duzenle');

-- Formlar ve Tutanaklar üst menüsü alt menü kazanır: mevcut sayfası "Form Listesi" olarak korunur.
INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Form Listesi', 'Form ve tutanak şablonları', parent.id, parent.group_name, parent.group_order, parent.menu_link, parent.permission_id, 'file-text', 1, 1, 1, 1, 0
FROM menus parent
WHERE parent.menu_link = 'formlar/list' AND parent.parent_id = 0 AND parent.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM menus c WHERE c.parent_id = parent.id AND c.menu_link = 'formlar/list' AND c.deleted_at IS NULL
  );

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized, created_by)
SELECT 'Sözleşme Şablonları',
       'Hizmet sözleşmesi şablonu sürümleri ve yer tutucular',
       parent.id, parent.group_name, parent.group_order, 'personel/sozlesme-sablonlari', p.id, 'file-text', 2,
       1, 1, 1, 0
FROM permissions p
INNER JOIN menus parent ON parent.menu_link = 'formlar/list' AND parent.parent_id = 0 AND parent.deleted_at IS NULL
WHERE p.permission_key = 'personel/sozlesme-sablonlari'
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'personel/sozlesme-sablonlari' AND deleted_at IS NULL);

UPDATE menus m
INNER JOIN menus parent ON parent.menu_link = 'formlar/list' AND parent.parent_id = 0 AND parent.deleted_at IS NULL
SET m.parent_id = parent.id, m.group_name = parent.group_name, m.group_order = parent.group_order, m.menu_order = 2
WHERE m.menu_link = 'personel/sozlesme-sablonlari' AND m.deleted_at IS NULL AND m.parent_id <> parent.id;

UPDATE menus m
INNER JOIN permissions p ON p.permission_key = 'personel/sozlesme-sablonlari'
SET m.permission_id = p.id
WHERE m.menu_link = 'personel/sozlesme-sablonlari' AND m.deleted_at IS NULL AND (m.permission_id IS NULL OR m.permission_id <> p.id);

INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'page', 'personel/sozlesme-sablonlari', '', '*', 'read', p.id, 0, 0, 1
FROM permissions p
WHERE p.is_active = 1 AND p.permission_key = 'personel/sozlesme-sablonlari'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'read',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'personel/api/sozlesme-sablonlari', policies.action, '*', 'read', p.id, 0, 0, 1
FROM (
    SELECT 'sablon-indir' AS action
    UNION ALL SELECT 'sablon-yer-tutucu-listesi'
) policies
INNER JOIN permissions p ON p.is_active = 1 AND p.permission_key = 'personel/sozlesme-sablonlari'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'read',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'personel/api/sozlesme-sablonlari', policies.action, '*', 'write', p.id, 0, 0, 1
FROM (
    SELECT 'sablon-yukle' AS action
    UNION ALL SELECT 'sablon-aktif-et'
    UNION ALL SELECT 'sablon-varsayilana-don'
) policies
INNER JOIN permissions p ON p.is_active = 1 AND p.permission_key = 'personel/sozlesme-sablonlari-duzenle'
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), access_mode = 'write',
    superadmin_only = 0, authenticated_only = 0, is_active = 1;

INSERT INTO user_role_permissions (role_id, permission_id, created_at, created_by)
SELECT ur.id, p.id, NOW(), 1
FROM user_roles ur
CROSS JOIN permissions p
WHERE (ur.role_type = 'superadmin' OR ur.superadmin = 1)
  AND p.permission_key IN ('personel/sozlesme-sablonlari', 'personel/sozlesme-sablonlari-duzenle')
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions urp
      WHERE urp.role_id = ur.id AND urp.permission_id = p.id
  );
