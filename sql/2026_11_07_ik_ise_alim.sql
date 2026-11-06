-- İK İşe Alım ve Analiz modülü: tablolar, yetkiler, menüler ve erişim politikaları.
-- Sabit ID kullanılmaz; bütün bağlantılar permission_key üzerinden çözülür. Tekrar çalıştırılabilir.

CREATE TABLE IF NOT EXISTS ik_ilanlar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    baslik VARCHAR(255) NOT NULL,
    departman VARCHAR(150) NULL,
    gorev VARCHAR(150) NULL,
    calisma_yeri VARCHAR(150) NULL,
    aciklama TEXT NULL,
    aranan_nitelikler TEXT NULL,
    ai_kriterleri TEXT NULL,
    sorular TEXT NULL,
    token_hash CHAR(64) NOT NULL,
    token_sifreli TEXT NOT NULL,
    durum ENUM('taslak','yayinda','kapali') NOT NULL DEFAULT 'taslak',
    gecerlilik_tarihi DATETIME NULL,
    olusturan_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ik_ilan_token (token_hash),
    KEY idx_ik_ilan_firma (firma_id, is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS ik_basvurular (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    ilan_id INT UNSIGNED NOT NULL,
    ad_soyad VARCHAR(150) NOT NULL,
    telefon VARCHAR(20) NOT NULL,
    email VARCHAR(190) NULL,
    dogum_tarihi DATE NULL,
    sehir VARCHAR(100) NULL,
    egitim VARCHAR(150) NULL,
    deneyim_yili DECIMAL(4,1) NULL,
    deneyim_ozet TEXT NULL,
    ehliyet VARCHAR(50) NULL,
    maas_beklentisi DECIMAL(12,2) NULL,
    ise_baslama VARCHAR(100) NULL,
    cevaplar TEXT NULL,
    cv_dosya VARCHAR(80) NULL,
    cv_mime VARCHAR(100) NULL,
    cv_metin MEDIUMTEXT NULL,
    kvkk_onay_tarihi DATETIME NOT NULL,
    ip_adresi VARCHAR(45) NULL,
    durum ENUM('yeni','degerlendirildi','gorusme','olumlu','olumsuz','personele_donustu') NOT NULL DEFAULT 'yeni',
    ai_durum ENUM('bekliyor','tamam','hata') NOT NULL DEFAULT 'bekliyor',
    ai_puan TINYINT UNSIGNED NULL,
    ai_etiket ENUM('olumlu','belirsiz','olumsuz') NULL,
    ai_ozet TEXT NULL,
    ai_guclu TEXT NULL,
    ai_zayif TEXT NULL,
    ai_riskler TEXT NULL,
    ai_mulakat_sorulari TEXT NULL,
    ai_tarihi DATETIME NULL,
    ik_notu TEXT NULL,
    personel_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ik_basvuru_ilan_tel (ilan_id, telefon),
    KEY idx_ik_basvuru_firma (firma_id, is_active, deleted_at),
    KEY idx_ik_basvuru_durum (firma_id, durum),
    KEY idx_ik_basvuru_ip (ip_adresi, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS ik_basvuru_olaylari (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    basvuru_id INT UNSIGNED NOT NULL,
    kullanici_id INT NULL,
    olay VARCHAR(60) NOT NULL,
    aciklama VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ik_olay_basvuru (basvuru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS ik_basvuru_denemeleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ilan_id INT UNSIGNED NULL,
    ip_adresi VARCHAR(45) NOT NULL,
    sonuc VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ik_deneme_ip (ip_adresi, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

START TRANSACTION;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('İşe Alım Dashboard', 'İşe alım başvuru ve yapay zekâ değerlendirme göstergelerini görüntüleme yetkisi', 'ik-ise-alim/dashboard', 'ik_ise_alim_dashboard', 'İşe Alım', 1, 0, 1, 0),
    ('İşe Alım İlan Yönetimi', 'İş ilanı oluşturma, düzenleme, yayınlama ve başvuru linkini paylaşma yetkisi', 'ik-ise-alim/ilanlar', 'ik_ilan_yonetimi', 'İşe Alım', 1, 0, 1, 0),
    ('İşe Alım Başvuru Listesi', 'Aday başvurularını, yapay zekâ analizlerini görüntüleme ve değerlendirme yetkisi', 'ik-ise-alim/basvurular', 'ik_basvuru_listesi', 'İşe Alım', 1, 0, 1, 0),
    ('Adayı Personele Dönüştür', 'Olumlu adayı personel olarak kaydetme yetkisi', 'ik-ise-alim/personele-donustur', 'ik_basvuru_personele_donustur', 'İşe Alım', 1, 0, 1, 0)
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
JOIN permissions perm ON perm.permission_key IN ('ik_ise_alim_dashboard', 'ik_ilan_yonetimi', 'ik_basvuru_listesi', 'ik_basvuru_personele_donustur')
WHERE (roles.role_type = 'superadmin' OR roles.superadmin = 1)
  AND NOT EXISTS (
      SELECT 1 FROM user_role_permissions existing_grant
      WHERE existing_grant.role_id = roles.id AND existing_grant.permission_id = perm.id
  );

SET @ik_dashboard_perm = (SELECT id FROM permissions WHERE permission_key = 'ik_ise_alim_dashboard' LIMIT 1);
SET @ik_ilan_perm = (SELECT id FROM permissions WHERE permission_key = 'ik_ilan_yonetimi' LIMIT 1);
SET @ik_basvuru_perm = (SELECT id FROM permissions WHERE permission_key = 'ik_basvuru_listesi' LIMIT 1);
SET @ik_donustur_perm = (SELECT id FROM permissions WHERE permission_key = 'ik_basvuru_personele_donustur' LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'İşe Alım', 'Aday başvuruları ve yapay zekâ destekli ön değerlendirme', 0, 'İnsan Kaynakları', 2, 'ik-ise-alim/dashboard', @ik_dashboard_perm, 'user-plus', 60, 1, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link = 'ik-ise-alim/dashboard' AND parent_id = 0);

SET @ik_parent_menu = (SELECT id FROM menus WHERE menu_link = 'ik-ise-alim/dashboard' AND parent_id = 0 LIMIT 1);

INSERT INTO menus (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT d.menu_name, d.page_description, @ik_parent_menu, 'İnsan Kaynakları', 2, d.menu_link, d.permission_id, d.menu_icon, d.menu_order, 1, d.is_menu, 1
FROM (
    SELECT 'İşe Alım Dashboard' AS menu_name, 'Başvuru ve yapay zekâ değerlendirme özeti' AS page_description, 'ik-ise-alim/dashboard' AS menu_link, @ik_dashboard_perm AS permission_id, 'pie-chart' AS menu_icon, 1 AS menu_order, 1 AS is_menu
    UNION ALL
    SELECT 'İş İlanları', 'İlan oluşturma ve başvuru linki paylaşımı', 'ik-ise-alim/ilanlar', @ik_ilan_perm, 'briefcase', 2, 1
    UNION ALL
    SELECT 'Başvurular', 'Aday başvuruları ve yapay zekâ analizleri', 'ik-ise-alim/basvurular', @ik_basvuru_perm, 'users', 3, 1
    UNION ALL
    SELECT 'Başvuru Detayı', 'Aday başvuru detayı', 'ik-ise-alim/basvuru-detay', @ik_basvuru_perm, 'user', 4, 0
) d
WHERE NOT EXISTS (SELECT 1 FROM menus m WHERE m.menu_link = d.menu_link AND m.parent_id = @ik_parent_menu);

UPDATE menus SET permission_id = @ik_dashboard_perm, is_active = 1 WHERE menu_link = 'ik-ise-alim/dashboard' AND @ik_dashboard_perm IS NOT NULL;
UPDATE menus SET permission_id = @ik_ilan_perm, is_active = 1 WHERE menu_link = 'ik-ise-alim/ilanlar' AND @ik_ilan_perm IS NOT NULL;
UPDATE menus SET permission_id = @ik_basvuru_perm, is_active = 1 WHERE menu_link IN ('ik-ise-alim/basvurular', 'ik-ise-alim/basvuru-detay') AND @ik_basvuru_perm IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT d.scope, d.resource, d.action, d.http_method, d.access_mode, d.permission_id, 0, 0, 1
FROM (
    SELECT 'page' AS scope, 'ik-ise-alim/dashboard' AS resource, '' AS action, 'GET' AS http_method, 'read' AS access_mode, @ik_dashboard_perm AS permission_id
    UNION ALL SELECT 'page', 'ik-ise-alim/ilanlar', '', 'GET', 'read', @ik_ilan_perm
    UNION ALL SELECT 'page', 'ik-ise-alim/basvurular', '', 'GET', 'read', @ik_basvuru_perm
    UNION ALL SELECT 'page', 'ik-ise-alim/basvuru-detay', '', 'GET', 'read', @ik_basvuru_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'dashboard-data', '*', 'read', @ik_dashboard_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'ilan-ajax-list', '*', 'read', @ik_ilan_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'ilan-getir', '*', 'read', @ik_ilan_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'ilan-kaydet', '*', 'write', @ik_ilan_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'ilan-durum', '*', 'write', @ik_ilan_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'ilan-sil', '*', 'write', @ik_ilan_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'ilan-link', '*', 'read', @ik_ilan_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'basvuru-ajax-list', '*', 'read', @ik_basvuru_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'basvuru-getir', '*', 'read', @ik_basvuru_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'basvuru-durum', '*', 'write', @ik_basvuru_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'basvuru-not', '*', 'write', @ik_basvuru_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'basvuru-ai-yeniden', '*', 'write', @ik_basvuru_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'basvuru-cv', '*', 'read', @ik_basvuru_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'basvuru-sil', '*', 'write', @ik_basvuru_perm
    UNION ALL SELECT 'api', 'ik-ise-alim/api', 'personele-donustur', '*', 'write', @ik_donustur_perm
) d
WHERE d.permission_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM permission_policies p
      WHERE p.scope = d.scope AND p.resource = d.resource AND p.action = d.action
        AND p.http_method = d.http_method AND p.permission_id = d.permission_id
  );

COMMIT;
