-- Okuma ve kesme/acma ay sonu snapshot + ayin 1-3'u mutabakat altyapisi.
-- Tekrarlanabilir kurulum; sabit permission/role ID kullanmaz.
START TRANSACTION;

CREATE TABLE IF NOT EXISTS puantaj_mutabakat_donem (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT NOT NULL,
    donem CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    durum ENUM('snapshot_bekliyor','on_kontrol','fark_var','mutabik','hata') NOT NULL DEFAULT 'snapshot_bekliyor',
    snapshot_hash CHAR(64) NULL,
    snapshot_tarihi DATETIME NULL,
    son_kontrol_tarihi DATETIME NULL,
    final_kontrol_tarihi DATETIME NULL,
    acik_fark_sayisi INT NOT NULL DEFAULT 0,
    eksik_gun_sayisi INT NOT NULL DEFAULT 0,
    yeniden_hesaplama_gerekli TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_puantaj_mutabakat_donem (firma_id, donem),
    KEY idx_pmd_durum (firma_id, durum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS puantaj_mutabakat_snapshot (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    donem_id BIGINT UNSIGNED NOT NULL,
    kaynak ENUM('okuma','kesme','sayac','muhurleme') NOT NULL,
    islem_tarihi DATE NOT NULL,
    kaynak_anahtar CHAR(32) NOT NULL,
    miktar DECIMAL(18,4) NOT NULL DEFAULT 0,
    acik_miktar DECIMAL(18,4) NOT NULL DEFAULT 0,
    veri_hash CHAR(64) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pms_item (donem_id, kaynak, islem_tarihi, kaynak_anahtar),
    KEY idx_pms_lookup (donem_id, kaynak, islem_tarihi),
    CONSTRAINT fk_pms_donem FOREIGN KEY (donem_id) REFERENCES puantaj_mutabakat_donem(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS puantaj_mutabakat_calisma (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    donem_id BIGINT UNSIGNED NOT NULL,
    kontrol_gunu TINYINT UNSIGNED NOT NULL,
    kaynak ENUM('okuma','kesme','sayac','muhurleme') NOT NULL,
    islem_tarihi DATE NOT NULL,
    durum ENUM('bekliyor','calisiyor','basarili','hata') NOT NULL DEFAULT 'bekliyor',
    son_offset INT NOT NULL DEFAULT 0,
    kayit_sayisi INT NOT NULL DEFAULT 0,
    hata_mesaji TEXT NULL,
    baslama_tarihi DATETIME NULL,
    bitis_tarihi DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pmc_run (donem_id, kontrol_gunu, kaynak, islem_tarihi),
    KEY idx_pmc_status (donem_id, kontrol_gunu, durum),
    CONSTRAINT fk_pmc_donem FOREIGN KEY (donem_id) REFERENCES puantaj_mutabakat_donem(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS puantaj_mutabakat_guncel (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    calisma_id BIGINT UNSIGNED NOT NULL,
    kaynak_anahtar CHAR(32) NOT NULL,
    miktar DECIMAL(18,4) NOT NULL DEFAULT 0,
    acik_miktar DECIMAL(18,4) NOT NULL DEFAULT 0,
    veri_hash CHAR(64) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pmg_item (calisma_id, kaynak_anahtar),
    CONSTRAINT fk_pmg_calisma FOREIGN KEY (calisma_id) REFERENCES puantaj_mutabakat_calisma(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS puantaj_mutabakat_fark (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    donem_id BIGINT UNSIGNED NOT NULL,
    kontrol_gunu TINYINT UNSIGNED NOT NULL,
    kaynak ENUM('okuma','kesme','sayac','muhurleme') NOT NULL,
    islem_tarihi DATE NOT NULL,
    kaynak_anahtar CHAR(32) NOT NULL,
    fark_tipi ENUM('silinmis','eklenmis','miktar_azalmis','miktar_artmis','alan_degismis') NOT NULL,
    eski_miktar DECIMAL(18,4) NOT NULL DEFAULT 0,
    yeni_miktar DECIMAL(18,4) NOT NULL DEFAULT 0,
    eski_payload_json LONGTEXT NULL,
    yeni_payload_json LONGTEXT NULL,
    durum ENUM('acik','uygulandi') NOT NULL DEFAULT 'acik',
    cozen_kullanici_id INT NULL,
    cozum_gerekcesi VARCHAR(1000) NULL,
    cozum_tarihi DATETIME NULL,
    bildirim_tarihi DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pmf_item (donem_id, kontrol_gunu, kaynak, islem_tarihi, kaynak_anahtar),
    KEY idx_pmf_open (donem_id, durum),
    CONSTRAINT fk_pmf_donem FOREIGN KEY (donem_id) REFERENCES puantaj_mutabakat_donem(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE puantaj_mutabakat_snapshot MODIFY kaynak ENUM('okuma','kesme','sayac','muhurleme') NOT NULL;
ALTER TABLE puantaj_mutabakat_calisma MODIFY kaynak ENUM('okuma','kesme','sayac','muhurleme') NOT NULL;
ALTER TABLE puantaj_mutabakat_fark MODIFY kaynak ENUM('okuma','kesme','sayac','muhurleme') NOT NULL;

INSERT INTO permissions
    (name, description, auth_name, permission_key, group_name, permission_level, is_required, is_active, superadmin)
VALUES
    ('Puantaj Mutabakatı', 'Okuma ve kesme/acma ay sonu snapshot ve fark yönetimi', 'puantaj/mutabakat', 'puantaj/mutabakat', 'Puantaj Yönetimi', 2, 0, 1, 0)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), auth_name=VALUES(auth_name), is_active=1;

SET @mutabakat_permission_id = (SELECT id FROM permissions WHERE permission_key='puantaj/mutabakat' LIMIT 1);

INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT r.id, @mutabakat_permission_id, NULL FROM user_roles r
WHERE (r.role_type='superadmin' OR r.superadmin=1)
  AND @mutabakat_permission_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM user_role_permissions urp WHERE urp.role_id=r.id AND urp.permission_id=@mutabakat_permission_id);

INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT 'Puantaj Mutabakatı', 'Okuma ve kesme/acma ay sonu farklarının yönetimi', 0, 'Puantaj Yönetimi', 6,
       'puantaj/mutabakat', @mutabakat_permission_id, 'git-compare', 95, 1, 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM menus WHERE menu_link='puantaj/mutabakat');

UPDATE menus SET permission_id=@mutabakat_permission_id, is_active=1, is_authorized=1
WHERE menu_link='puantaj/mutabakat' AND @mutabakat_permission_id IS NOT NULL;

INSERT INTO permission_policies
    (scope, resource, action, http_method, access_mode, permission_id, superadmin_only, authenticated_only, is_active)
SELECT x.scope, x.resource, x.action, x.http_method, x.access_mode, @mutabakat_permission_id, 0, 0, 1
FROM (
    SELECT 'page' scope, 'puantaj/mutabakat' resource, '' action, 'GET' http_method, 'read' access_mode
    UNION ALL SELECT 'api','puantaj/mutabakat-api','list','POST','read'
    UNION ALL SELECT 'api','puantaj/mutabakat-api','summary','POST','read'
    UNION ALL SELECT 'api','puantaj/mutabakat-api','apply','POST','write'
    UNION ALL SELECT 'api','puantaj/mutabakat-api','retry','POST','write'
) x
WHERE @mutabakat_permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE permission_id=VALUES(permission_id), access_mode=VALUES(access_mode), is_active=1;

COMMIT;
