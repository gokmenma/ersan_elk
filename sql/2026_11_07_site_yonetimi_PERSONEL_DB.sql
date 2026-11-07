-- Hedef veritabanı: PERSONEL veritabanı (yerel: ersan_elk, canlı: ersantrc_personel). Site veritabanlarında çalıştırmayın.
-- ===== 2026_11_07_site_dashboard_yetki.sql =====
START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Site Yönetimi Dashboard', 'Kurumsal site yönetim özetini ve son işlemleri görüntüleme yetkisi', 'site-yonetimi/dashboard', 'site_dashboard', 'Site Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'site_dashboard'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @site_dash_perm = (SELECT id FROM permissions WHERE permission_key = 'site_dashboard' LIMIT 1);
SET @site_parent_menu = (SELECT id FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Dashboard', 'Site yönetimi özeti ve son işlemler', @site_parent_menu, 'Site Yönetimi', 7, 'site-yonetimi/dashboard', @site_dash_perm, 'pie-chart', 1, 1, 1, 1
FROM DUAL
WHERE @site_parent_menu IS NOT NULL
  AND @site_dash_perm IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/dashboard' AND parent_id = @site_parent_menu);

UPDATE menus SET permission_id = @site_dash_perm, is_active = 1 WHERE menu_link = 'site-yonetimi/dashboard' AND @site_dash_perm IS NOT NULL;
UPDATE menus SET menu_order = 2 WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = @site_parent_menu AND @site_parent_menu IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'site-yonetimi/dashboard' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @site_dash_perm AS permission_id
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'dashboard-data', '*', 'read', @site_dash_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;

-- ===== 2026_11_07_site_hizmetler_yetki.sql =====
START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Site Hizmet Yönetimi', 'Kurumsal sitedeki hizmetleri listeleme, ekleme, düzenleme, yayınlama ve silme yetkisi', 'site-yonetimi/hizmetler', 'site_hizmet_yonetimi', 'Site Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'site_hizmet_yonetimi'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @site_hizmet_perm = (SELECT id FROM permissions WHERE permission_key = 'site_hizmet_yonetimi' LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Site Yönetimi', 'Kurumsal web sitesi içerik yönetimi', 0, 'Site Yönetimi', 7, 'site-yonetimi/hizmetler', @site_hizmet_perm, 'globe', 70, 1, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0);

SET @site_parent_menu = (SELECT id FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Hizmetler', 'Sitede yayınlanan hizmetlerin yönetimi', @site_parent_menu, 'Site Yönetimi', 7, 'site-yonetimi/hizmetler', @site_hizmet_perm, 'tool', 1, 1, 1, 1
FROM DUAL
WHERE @site_parent_menu IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = @site_parent_menu);

UPDATE menus SET permission_id = @site_hizmet_perm, is_active = 1 WHERE menu_link = 'site-yonetimi/hizmetler' AND @site_hizmet_perm IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'site-yonetimi/hizmetler' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @site_hizmet_perm AS permission_id
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'hizmet-ajax-list', '*', 'read', @site_hizmet_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'hizmet-getir', '*', 'read', @site_hizmet_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'hizmet-kaydet', '*', 'write', @site_hizmet_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'hizmet-durum', '*', 'write', @site_hizmet_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'hizmet-sil', '*', 'write', @site_hizmet_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;

-- ===== 2026_11_07_site_projeler_yetki.sql =====
START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Site Proje Yönetimi', 'Kurumsal sitedeki projeleri listeleme, ekleme, düzenleme, yayınlama ve silme yetkisi', 'site-yonetimi/projeler', 'site_proje_yonetimi', 'Site Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'site_proje_yonetimi'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @site_proje_perm = (SELECT id FROM permissions WHERE permission_key = 'site_proje_yonetimi' LIMIT 1);
SET @site_parent_menu = (SELECT id FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Projeler', 'Sitede yayınlanan projelerin yönetimi', @site_parent_menu, 'Site Yönetimi', 7, 'site-yonetimi/projeler', @site_proje_perm, 'briefcase', 3, 1, 1, 1
FROM DUAL
WHERE @site_parent_menu IS NOT NULL
  AND @site_proje_perm IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/projeler' AND parent_id = @site_parent_menu);

UPDATE menus SET permission_id = @site_proje_perm, is_active = 1 WHERE menu_link = 'site-yonetimi/projeler' AND @site_proje_perm IS NOT NULL;
UPDATE menus SET menu_order = 4 WHERE menu_link = 'site-yonetimi/mesajlar' AND parent_id = @site_parent_menu AND @site_parent_menu IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'site-yonetimi/projeler' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @site_proje_perm AS permission_id
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'proje-ajax-list', '*', 'read', @site_proje_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'proje-getir', '*', 'read', @site_proje_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'proje-kaydet', '*', 'write', @site_proje_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'proje-durum', '*', 'write', @site_proje_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'proje-sil', '*', 'write', @site_proje_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;

-- ===== 2026_11_07_site_referanslar_yetki.sql =====
START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Site Referans Yönetimi', 'Kurumsal sitedeki müşteri referanslarını listeleme, ekleme, düzenleme, yayınlama ve silme yetkisi', 'site-yonetimi/referanslar', 'site_referans_yonetimi', 'Site Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'site_referans_yonetimi'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @site_referans_perm = (SELECT id FROM permissions WHERE permission_key = 'site_referans_yonetimi' LIMIT 1);
SET @site_parent_menu = (SELECT id FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Referanslar', 'Sitede yayınlanan müşteri referansları', @site_parent_menu, 'Site Yönetimi', 7, 'site-yonetimi/referanslar', @site_referans_perm, 'award', 5, 1, 1, 1
FROM DUAL
WHERE @site_parent_menu IS NOT NULL
  AND @site_referans_perm IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/referanslar' AND parent_id = @site_parent_menu);

UPDATE menus SET permission_id = @site_referans_perm, is_active = 1 WHERE menu_link = 'site-yonetimi/referanslar' AND @site_referans_perm IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'site-yonetimi/referanslar' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @site_referans_perm AS permission_id
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'referans-ajax-list', '*', 'read', @site_referans_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'referans-getir', '*', 'read', @site_referans_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'referans-kaydet', '*', 'write', @site_referans_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'referans-durum', '*', 'write', @site_referans_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'referans-sil', '*', 'write', @site_referans_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;

-- ===== 2026_11_07_site_sss_yetki.sql =====
START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Site SSS Yönetimi', 'Kurumsal sitedeki sık sorulan soruları listeleme, ekleme, düzenleme, yayınlama ve silme yetkisi', 'site-yonetimi/sss', 'site_sss_yonetimi', 'Site Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'site_sss_yonetimi'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @site_sss_perm = (SELECT id FROM permissions WHERE permission_key = 'site_sss_yonetimi' LIMIT 1);
SET @site_parent_menu = (SELECT id FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Sık Sorulanlar', 'Sitede yayınlanan sık sorulan sorular', @site_parent_menu, 'Site Yönetimi', 7, 'site-yonetimi/sss', @site_sss_perm, 'help-circle', 6, 1, 1, 1
FROM DUAL
WHERE @site_parent_menu IS NOT NULL
  AND @site_sss_perm IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/sss' AND parent_id = @site_parent_menu);

UPDATE menus SET permission_id = @site_sss_perm, is_active = 1 WHERE menu_link = 'site-yonetimi/sss' AND @site_sss_perm IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'site-yonetimi/sss' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @site_sss_perm AS permission_id
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'sss-ajax-list', '*', 'read', @site_sss_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'sss-getir', '*', 'read', @site_sss_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'sss-kaydet', '*', 'write', @site_sss_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'sss-durum', '*', 'write', @site_sss_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'sss-sil', '*', 'write', @site_sss_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;

-- ===== 2026_11_07_site_istatistikler_yetki.sql =====
START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Site İstatistik Yönetimi', 'Kurumsal sitedeki sayaçlı istatistikleri listeleme, ekleme, düzenleme, yayınlama ve silme yetkisi', 'site-yonetimi/istatistikler', 'site_istatistik_yonetimi', 'Site Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'site_istatistik_yonetimi'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @site_istatistik_perm = (SELECT id FROM permissions WHERE permission_key = 'site_istatistik_yonetimi' LIMIT 1);
SET @site_parent_menu = (SELECT id FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'İstatistikler', 'Sitede gösterilen sayaçlı istatistikler', @site_parent_menu, 'Site Yönetimi', 7, 'site-yonetimi/istatistikler', @site_istatistik_perm, 'bar-chart-2', 7, 1, 1, 1
FROM DUAL
WHERE @site_parent_menu IS NOT NULL
  AND @site_istatistik_perm IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/istatistikler' AND parent_id = @site_parent_menu);

UPDATE menus SET permission_id = @site_istatistik_perm, is_active = 1 WHERE menu_link = 'site-yonetimi/istatistikler' AND @site_istatistik_perm IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'site-yonetimi/istatistikler' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @site_istatistik_perm AS permission_id
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'istatistik-ajax-list', '*', 'read', @site_istatistik_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'istatistik-getir', '*', 'read', @site_istatistik_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'istatistik-kaydet', '*', 'write', @site_istatistik_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'istatistik-durum', '*', 'write', @site_istatistik_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'istatistik-sil', '*', 'write', @site_istatistik_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;

-- ===== 2026_11_07_site_mesajlar_yetki.sql =====
START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Site Mesaj Yönetimi', 'Kurumsal siteden gelen iletişim mesajlarını görüntüleme, durum güncelleme ve silme yetkisi', 'site-yonetimi/mesajlar', 'site_mesaj_yonetimi', 'Site Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'site_mesaj_yonetimi'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @site_mesaj_perm = (SELECT id FROM permissions WHERE permission_key = 'site_mesaj_yonetimi' LIMIT 1);
SET @site_parent_menu = (SELECT id FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Mesajlar', 'Siteden gelen iletişim mesajları', @site_parent_menu, 'Site Yönetimi', 7, 'site-yonetimi/mesajlar', @site_mesaj_perm, 'mail', 3, 1, 1, 1
FROM DUAL
WHERE @site_parent_menu IS NOT NULL
  AND @site_mesaj_perm IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/mesajlar' AND parent_id = @site_parent_menu);

UPDATE menus SET permission_id = @site_mesaj_perm, is_active = 1 WHERE menu_link = 'site-yonetimi/mesajlar' AND @site_mesaj_perm IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'site-yonetimi/mesajlar' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @site_mesaj_perm AS permission_id
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'mesaj-ajax-list', '*', 'read', @site_mesaj_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'mesaj-getir', '*', 'read', @site_mesaj_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'mesaj-durum', '*', 'write', @site_mesaj_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'mesaj-sil', '*', 'write', @site_mesaj_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;

-- ===== 2026_11_07_site_ayarlar_yetki.sql =====
START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Site Ayarları', 'Kurumsal sitenin iletişim bilgileri, hakkımızda, çalışma süreci, slayt metinleri ve sosyal medya ayarlarını düzenleme yetkisi', 'site-yonetimi/ayarlar', 'site_ayar_yonetimi', 'Site Yönetimi', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    auth_name = VALUES(auth_name),
    group_name = VALUES(group_name),
    permission_level = VALUES(permission_level),
    is_active = 1,
    superadmin = 0;

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT roles.id, perm.id, NULL
FROM user_roles roles
JOIN permissions perm ON perm.permission_key = 'site_ayar_yonetimi'
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @site_ayar_perm = (SELECT id FROM permissions WHERE permission_key = 'site_ayar_yonetimi' LIMIT 1);
SET @site_parent_menu = (SELECT id FROM menus WHERE menu_link = 'site-yonetimi/hizmetler' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Site Ayarları', 'İletişim bilgileri, hakkımızda ve genel site metinleri', @site_parent_menu, 'Site Yönetimi', 7, 'site-yonetimi/ayarlar', @site_ayar_perm, 'settings', 8, 1, 1, 1
FROM DUAL
WHERE @site_parent_menu IS NOT NULL
  AND @site_ayar_perm IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'site-yonetimi/ayarlar' AND parent_id = @site_parent_menu);

UPDATE menus SET permission_id = @site_ayar_perm, is_active = 1 WHERE menu_link = 'site-yonetimi/ayarlar' AND @site_ayar_perm IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'site-yonetimi/ayarlar' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @site_ayar_perm AS permission_id
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'ayar-getir', '*', 'read', @site_ayar_perm
    UNION ALL SELECT 'api', 'site-yonetimi/api', 'ayar-kaydet', '*', 'write', @site_ayar_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;
