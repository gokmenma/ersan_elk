-- Zimmet devir hareketleri (personeller arası teslim alma / teslim etme)
-- zimmet_turu: 'arac' (şimdilik), ileride 'demirbas' vb. aynı tabloyu kullanır.
-- devir_turu : 'gecici' asıl zimmet kaydını değiştirmez; 'kalici' ileride asıl zimmetin devri için ayrılmıştır.

CREATE TABLE IF NOT EXISTS `zimmet_devirleri` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `zimmet_turu` VARCHAR(30) NOT NULL,
    `kaynak_zimmet_id` INT(11) NOT NULL,
    `nesne_id` INT(11) NOT NULL,
    `devir_turu` ENUM('gecici','kalici') NOT NULL DEFAULT 'gecici',
    `veren_personel_id` INT(11) NOT NULL,
    `alan_personel_id` INT(11) NOT NULL,
    `teslim_tarihi` DATETIME NOT NULL,
    `planlanan_iade_tarihi` DATE DEFAULT NULL,
    `iade_tarihi` DATETIME DEFAULT NULL,
    `teslim_sayac` INT(11) DEFAULT NULL COMMENT 'Araçta KM',
    `iade_sayac` INT(11) DEFAULT NULL COMMENT 'Araçta KM',
    `durum` ENUM('aktif','iade_edildi','iptal') NOT NULL DEFAULT 'aktif',
    `aciklama` TEXT DEFAULT NULL,
    `iade_notu` TEXT DEFAULT NULL,
    `olusturan_kullanici_id` INT(11) DEFAULT NULL,
    `iade_alan_kullanici_id` INT(11) DEFAULT NULL,
    `olusturma_tarihi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_zd_kaynak` (`firma_id`, `zimmet_turu`, `kaynak_zimmet_id`, `durum`),
    KEY `idx_zd_nesne` (`firma_id`, `zimmet_turu`, `nesne_id`),
    KEY `idx_zd_alan` (`firma_id`, `alan_personel_id`, `durum`),
    KEY `idx_zd_veren` (`firma_id`, `veren_personel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permission_policies`
    (`scope`, `resource`, `action`, `http_method`, `access_mode`, `permission_id`, `superadmin_only`, `authenticated_only`, `is_active`, `created_at`, `updated_at`)
SELECT 'api', 'arac-takip/api', policies.action, '*', policies.access_mode, p.id, 0, 0, 1, NOW(), NOW()
FROM (
    SELECT 'zimmet-gecici-devir' AS action, 'write' AS access_mode
    UNION ALL SELECT 'zimmet-gecici-devir-iade', 'write'
    UNION ALL SELECT 'zimmet-gecici-devir-iptal', 'write'
    UNION ALL SELECT 'zimmet-devir-gecmisi', 'read'
) policies
INNER JOIN `permissions` p ON p.permission_key = 'arac_takip_yonetim'
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`), `access_mode` = VALUES(`access_mode`), `is_active` = 1, `updated_at` = NOW();
