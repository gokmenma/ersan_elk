-- Puantaj özel gün otomasyonu: resmi tatil tanımları, RTÇ / arife fazla mesaisi altyapısı ve yetkisi.
-- Tekrar çalıştırılabilir. Sabit permission ID kullanılmaz; bağlantılar permission_key = 'resmi_tatil_tanimlari' üzerinden kurulur.
-- Dini bayram tarihleri Diyanet takvimine göre girilmiştir; yıllık kontrol Resmi Tatil Tanımları ekranından yapılır.

START TRANSACTION;

ALTER TABLE resmi_tatiller
    ADD COLUMN IF NOT EXISTS firma_id INT NOT NULL DEFAULT 0 AFTER id,
    ADD COLUMN IF NOT EXISTS tur ENUM('tam','yarim') NOT NULL DEFAULT 'tam' AFTER aciklama,
    ADD COLUMN IF NOT EXISTS yarim_gun_saati TIME NOT NULL DEFAULT '13:00:00' AFTER tur,
    ADD COLUMN IF NOT EXISTS fazla_mesai_saat DECIMAL(4,2) NOT NULL DEFAULT 4.00 AFTER yarim_gun_saati,
    ADD COLUMN IF NOT EXISTS olusturan_kullanici INT NULL AFTER fazla_mesai_saat,
    ADD COLUMN IF NOT EXISTS olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER olusturan_kullanici,
    ADD COLUMN IF NOT EXISTS guncelleme_tarihi DATETIME NULL ON UPDATE CURRENT_TIMESTAMP AFTER olusturma_tarihi,
    ADD COLUMN IF NOT EXISTS silinme_tarihi DATETIME NULL AFTER guncelleme_tarihi,
    ADD COLUMN IF NOT EXISTS silen_kullanici INT NULL AFTER silinme_tarihi;

ALTER TABLE resmi_tatiller
    ADD INDEX IF NOT EXISTS idx_resmi_tatil_firma_tarih (firma_id, tatil_tarihi, silinme_tarihi);

ALTER TABLE personel_fazla_mesaileri
    ADD COLUMN IF NOT EXISTS kaynak VARCHAR(20) NOT NULL DEFAULT 'manuel' AFTER aciklama,
    ADD COLUMN IF NOT EXISTS otomatik_kural VARCHAR(50) NULL AFTER kaynak;

INSERT INTO resmi_tatiller (firma_id, tatil_tarihi, aciklama, tur, yarim_gun_saati, fazla_mesai_saat)
SELECT 0, d.tarih, d.aciklama, d.tur, '13:00:00', 4.00
FROM (
    SELECT DATE('2026-01-01') AS tarih, 'Yılbaşı' AS aciklama, 'tam' AS tur
    UNION ALL SELECT '2026-03-19', 'Ramazan Bayramı Arifesi', 'yarim'
    UNION ALL SELECT '2026-03-20', 'Ramazan Bayramı 1. Gün', 'tam'
    UNION ALL SELECT '2026-03-21', 'Ramazan Bayramı 2. Gün', 'tam'
    UNION ALL SELECT '2026-03-22', 'Ramazan Bayramı 3. Gün', 'tam'
    UNION ALL SELECT '2026-04-23', 'Ulusal Egemenlik ve Çocuk Bayramı', 'tam'
    UNION ALL SELECT '2026-05-01', 'Emek ve Dayanışma Günü', 'tam'
    UNION ALL SELECT '2026-05-19', 'Atatürk''ü Anma, Gençlik ve Spor Bayramı', 'tam'
    UNION ALL SELECT '2026-05-26', 'Kurban Bayramı Arifesi', 'yarim'
    UNION ALL SELECT '2026-05-27', 'Kurban Bayramı 1. Gün', 'tam'
    UNION ALL SELECT '2026-05-28', 'Kurban Bayramı 2. Gün', 'tam'
    UNION ALL SELECT '2026-05-29', 'Kurban Bayramı 3. Gün', 'tam'
    UNION ALL SELECT '2026-05-30', 'Kurban Bayramı 4. Gün', 'tam'
    UNION ALL SELECT '2026-07-15', 'Demokrasi ve Millî Birlik Günü', 'tam'
    UNION ALL SELECT '2026-08-30', 'Zafer Bayramı', 'tam'
    UNION ALL SELECT '2026-10-28', 'Cumhuriyet Bayramı Arifesi', 'yarim'
    UNION ALL SELECT '2026-10-29', 'Cumhuriyet Bayramı', 'tam'
    UNION ALL SELECT '2027-01-01', 'Yılbaşı', 'tam'
    UNION ALL SELECT '2027-03-08', 'Ramazan Bayramı Arifesi', 'yarim'
    UNION ALL SELECT '2027-03-09', 'Ramazan Bayramı 1. Gün', 'tam'
    UNION ALL SELECT '2027-03-10', 'Ramazan Bayramı 2. Gün', 'tam'
    UNION ALL SELECT '2027-03-11', 'Ramazan Bayramı 3. Gün', 'tam'
    UNION ALL SELECT '2027-04-23', 'Ulusal Egemenlik ve Çocuk Bayramı', 'tam'
    UNION ALL SELECT '2027-05-01', 'Emek ve Dayanışma Günü', 'tam'
    UNION ALL SELECT '2027-05-15', 'Kurban Bayramı Arifesi', 'yarim'
    UNION ALL SELECT '2027-05-16', 'Kurban Bayramı 1. Gün', 'tam'
    UNION ALL SELECT '2027-05-17', 'Kurban Bayramı 2. Gün', 'tam'
    UNION ALL SELECT '2027-05-18', 'Kurban Bayramı 3. Gün', 'tam'
    UNION ALL SELECT '2027-05-19', 'Kurban Bayramı 4. Gün / Atatürk''ü Anma, Gençlik ve Spor Bayramı', 'tam'
    UNION ALL SELECT '2027-07-15', 'Demokrasi ve Millî Birlik Günü', 'tam'
    UNION ALL SELECT '2027-08-30', 'Zafer Bayramı', 'tam'
    UNION ALL SELECT '2027-10-28', 'Cumhuriyet Bayramı Arifesi', 'yarim'
    UNION ALL SELECT '2027-10-29', 'Cumhuriyet Bayramı', 'tam'
) d
WHERE NOT EXISTS (
    SELECT 1 FROM resmi_tatiller rt
    WHERE rt.firma_id = 0 AND rt.tatil_tarihi = d.tarih AND rt.silinme_tarihi IS NULL
);

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    (
        'Resmi Tatil Tanımları',
        'Resmi tatil ve arife günlerini tanımlama; puantajda otomatik RTÇ ve arife fazla mesaisi bu takvimden üretilir',
        'resmi_tatil_tanimlari',
        'resmi_tatil_tanimlari',
        'Tanımlamalar',
        0,
        0,
        1,
        0
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    is_active = 1;

SET @resmi_tatil_permission_id = (
    SELECT id FROM permissions WHERE permission_key = 'resmi_tatil_tanimlari' LIMIT 1
);

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, @resmi_tatil_permission_id, NULL
FROM user_roles roles
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND @resmi_tatil_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id
        AND existing_grant.permission_id = @resmi_tatil_permission_id
  );

SET @tanimlamalar_menu_id = (
    SELECT id FROM menus WHERE menu_name = 'Tanımlamalar' AND parent_id = 0 AND deleted_at IS NULL LIMIT 1
);

INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT
    'Resmi Tatil Tanımları',
    'Resmi tatil ve arife günleri; otomatik RTÇ ve arife fazla mesaisi takvimi',
    COALESCE(@tanimlamalar_menu_id, 0),
    'Yönetim',
    5,
    'tanimlamalar/resmi-tatiller/list',
    @resmi_tatil_permission_id,
    'calendar',
    72,
    1,
    1,
    1
FROM DUAL
WHERE @resmi_tatil_permission_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'tanimlamalar/resmi-tatiller/list');

UPDATE menus
SET permission_id = @resmi_tatil_permission_id,
    is_active = 1,
    deleted_at = NULL
WHERE menu_link = 'tanimlamalar/resmi-tatiller/list'
  AND @resmi_tatil_permission_id IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT definitions.scope, definitions.resource, definitions.action, definitions.http_method,
       definitions.access_mode, @resmi_tatil_permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'tanimlamalar/resmi-tatiller/list' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode
    UNION ALL SELECT 'api', 'tanimlamalar/resmi-tatiller/api', 'liste', '*', 'read'
    UNION ALL SELECT 'api', 'tanimlamalar/resmi-tatiller/api', 'getir', '*', 'read'
    UNION ALL SELECT 'api', 'tanimlamalar/resmi-tatiller/api', 'kaydet', '*', 'write'
    UNION ALL SELECT 'api', 'tanimlamalar/resmi-tatiller/api', 'sil', '*', 'write'
    UNION ALL SELECT 'api', 'tanimlamalar/resmi-tatiller/api', 'excel-sablon', '*', 'read'
    UNION ALL SELECT 'api', 'tanimlamalar/resmi-tatiller/api', 'excel-yukle', '*', 'write'
) definitions
WHERE @resmi_tatil_permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies pp
      WHERE pp.scope = definitions.scope
        AND pp.resource = definitions.resource
        AND pp.action = definitions.action
  );

UPDATE permission_policies
SET permission_id = @resmi_tatil_permission_id,
    is_active = 1
WHERE resource IN ('tanimlamalar/resmi-tatiller/list', 'tanimlamalar/resmi-tatiller/api')
  AND @resmi_tatil_permission_id IS NOT NULL;

COMMIT;
