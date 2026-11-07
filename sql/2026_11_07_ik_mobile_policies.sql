-- Mobil SPA: İK İşe Alım Modülü API Yetki Politikaları
-- Sabit ID kullanılmaz; permission_key üzerinden bağlanır. Tekrar çalıştırılabilir.

START TRANSACTION;

-- stats -> ik_ise_alim_dashboard
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'stats', 'GET', 'read', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_ise_alim_dashboard'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- applications -> ik_basvuru_listesi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'applications', 'GET', 'read', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_listesi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- application-detail -> ik_basvuru_listesi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'application-detail', 'GET', 'read', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_listesi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- update-status -> ik_basvuru_listesi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'update-status', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_listesi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- save-note -> ik_basvuru_listesi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'save-note', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_listesi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- rerun-ai -> ik_basvuru_listesi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'rerun-ai', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_listesi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- cv-view -> ik_basvuru_listesi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'cv-view', 'GET', 'read', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_listesi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- photo-view -> ik_basvuru_listesi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'photo-view', 'GET', 'read', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_listesi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- jobs -> ik_ilan_yonetimi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'jobs', 'GET', 'read', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_ilan_yonetimi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- job-status -> ik_ilan_yonetimi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'job-status', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_ilan_yonetimi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- job-link -> ik_ilan_yonetimi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'job-link', 'GET', 'read', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_ilan_yonetimi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- job-detail -> ik_ilan_yonetimi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'job-detail', 'GET', 'read', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_ilan_yonetimi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- save-job -> ik_ilan_yonetimi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'save-job', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_ilan_yonetimi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- delete-job -> ik_ilan_yonetimi
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'delete-job', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_ilan_yonetimi'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- save-interview -> ik_basvuru_mulakat
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'save-interview', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_mulakat'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- delete-interview -> ik_basvuru_mulakat
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'delete-interview', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_mulakat'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- prepare-notification -> ik_basvuru_aday_bildirim
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'prepare-notification', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_aday_bildirim'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- send-notification -> ik_basvuru_aday_bildirim
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'send-notification', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_aday_bildirim'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

-- convert-to-personnel -> ik_basvuru_personele_donustur
INSERT INTO permission_policies (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT 'api', 'mobile-spa/ik-ise-alim', 'convert-to-personnel', 'POST', 'write', p.id, 0, 0, 1
FROM permissions p WHERE p.permission_key = 'ik_basvuru_personele_donustur'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id), is_active = 1;

COMMIT;
