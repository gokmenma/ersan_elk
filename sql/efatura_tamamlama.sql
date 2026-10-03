-- e-Fatura tamamlama — MariaDB 10.4+ (ADD COLUMN IF NOT EXISTS).
-- Önce aşağıdaki raporları inceleyin. Mükerrerler otomatik silinmez.
SELECT firm_id, ettn, COUNT(*) adet FROM faturalar GROUP BY firm_id, ettn HAVING COUNT(*) > 1;
SELECT firm_id, fatura_no, COUNT(*) adet FROM faturalar WHERE yon = 'GIDEN' AND fatura_no IS NOT NULL GROUP BY firm_id, fatura_no HAVING COUNT(*) > 1;

ALTER TABLE efatura_ayarlar ADD COLUMN IF NOT EXISTS kontor_esik INT UNSIGNED NOT NULL DEFAULT 100;
ALTER TABLE faturalar MODIFY fatura_profili VARCHAR(40) NOT NULL DEFAULT 'TICARIFATURA', MODIFY fatura_tipi VARCHAR(40) NOT NULL DEFAULT 'SATIS';
ALTER TABLE faturalar
    ADD COLUMN IF NOT EXISTS edm_durum VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS zarf_id VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS earsiv_rapor_durum VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS earsiv_rapor_aciklama TEXT NULL,
    ADD COLUMN IF NOT EXISTS earsiv_iptal_rapor_durum VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS earsiv_iptal_rapor_aciklama TEXT NULL,
    ADD COLUMN IF NOT EXISTS islem_belirsiz VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS iade_fatura_no VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS iade_fatura_tarihi DATE NULL,
    ADD COLUMN IF NOT EXISTS kaynak_xml MEDIUMTEXT NULL,
    ADD COLUMN IF NOT EXISTS giden_fatura_no VARCHAR(16) GENERATED ALWAYS AS (CASE WHEN yon = 'GIDEN' THEN fatura_no ELSE NULL END) STORED;
ALTER TABLE fatura_satirlari
    ADD COLUMN IF NOT EXISTS istisna_aciklama TEXT NULL,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS efatura_islem_gecmisi (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firm_id INT NOT NULL, fatura_id INT NOT NULL,
    islem VARCHAR(60) NOT NULL, sonuc VARCHAR(30) NOT NULL,
    aciklama TEXT NULL, user_id INT NULL, olay_tarihi DATETIME NOT NULL,
    INDEX idx_efatura_gecmis (firm_id, fatura_id, olay_tarihi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS efatura_senkronizasyon (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firm_id INT NOT NULL, yon VARCHAR(10) NOT NULL,
    baslangic DATE NOT NULL, bitis DATE NOT NULL, tamamlandi TINYINT(1) NOT NULL,
    sonuc MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL,
    INDEX idx_efatura_sync (firm_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abort constraint installation if duplicates exist; leave records intact.
SET @efatura_duplicates =
    (SELECT COUNT(*) FROM (SELECT firm_id, ettn FROM faturalar GROUP BY firm_id, ettn HAVING COUNT(*) > 1) d)
    + (SELECT COUNT(*) FROM (SELECT firm_id, fatura_no FROM faturalar WHERE yon = 'GIDEN' AND fatura_no IS NOT NULL GROUP BY firm_id, fatura_no HAVING COUNT(*) > 1) d);
CREATE TEMPORARY TABLE efatura_migration_guard (duplicates INT CHECK (duplicates = 0));
-- A duplicate fails this CHECK and stops a normal SQL client before index changes.
INSERT INTO efatura_migration_guard VALUES (@efatura_duplicates);
DROP TEMPORARY TABLE efatura_migration_guard;
SET @efatura_ddl = IF(@efatura_duplicates = 0 AND EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'faturalar' AND index_name = 'ettn' AND non_unique = 0), 'ALTER TABLE faturalar DROP INDEX ettn', 'SELECT 1');
PREPARE efatura_stmt FROM @efatura_ddl;
EXECUTE efatura_stmt;
DEALLOCATE PREPARE efatura_stmt;
SET @efatura_ddl = IF(@efatura_duplicates = 0 AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'faturalar' AND index_name = 'uk_firm_ettn'), 'ALTER TABLE faturalar ADD UNIQUE KEY uk_firm_ettn (firm_id, ettn)', 'SELECT 1');
PREPARE efatura_stmt FROM @efatura_ddl;
EXECUTE efatura_stmt;
DEALLOCATE PREPARE efatura_stmt;
SET @efatura_ddl = IF(@efatura_duplicates = 0 AND NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'faturalar' AND index_name = 'uk_firm_giden_no'), 'ALTER TABLE faturalar ADD UNIQUE KEY uk_firm_giden_no (firm_id, giden_fatura_no)', 'SELECT 1');
PREPARE efatura_stmt FROM @efatura_ddl;
EXECUTE efatura_stmt;
DEALLOCATE PREPARE efatura_stmt;

INSERT INTO permissions (name, description, auth_name, group_name, permission_level, is_required, is_active)
SELECT names.name, names.name, names.auth_name, 'Finans & Muhasebe', 1, 0, 1
FROM (
    SELECT 'E-Fatura Gönderme' name, 'efatura/gonder' auth_name
    UNION ALL SELECT 'E-Fatura Senkronizasyon', 'efatura/senkronize'
    UNION ALL SELECT 'E-Fatura İptal', 'efatura/iptal'
    UNION ALL SELECT 'E-Fatura Ticari Yanıt', 'efatura/yanit'
) names WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.auth_name = names.auth_name);
INSERT INTO user_role_permissions (role_id, permission_id, created_by)
SELECT r.id, p.id, 0 FROM user_roles r
JOIN permissions p ON p.auth_name IN ('efatura/gonder','efatura/senkronize','efatura/iptal','efatura/yanit')
WHERE r.role_name IN ('Süper Admin','Firma Sahibi','Muhasebe Sorumlusu','Yönetici')
AND NOT EXISTS (SELECT 1 FROM user_role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);
