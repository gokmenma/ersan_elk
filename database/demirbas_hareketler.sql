-- Demirbaş Hareket Tablosu
-- Her zimmet verme, iade alma, sarf ve kayıp işlemi ayrı satır olarak kaydedilir
-- Bu sayede tam audit trail sağlanır ve negatif bakiye sorunu ortadan kalkar

CREATE TABLE IF NOT EXISTS `demirbas_hareketler` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `demirbas_id` INT(11) NOT NULL COMMENT 'Hangi demirbaş',
    `personel_id` INT(11) NOT NULL COMMENT 'Hangi personel',
    `hareket_tipi` ENUM('zimmet', 'iade', 'sarf', 'kayip', 'duzelme') NOT NULL COMMENT 'İşlem türü',
    `miktar` INT(11) NOT NULL DEFAULT 1 COMMENT 'İşlem miktarı (her zaman pozitif)',
    `tarih` DATE NOT NULL COMMENT 'İşlem tarihi',
    `islem_id` VARCHAR(100) NULL COMMENT 'Puantaj veya diğer kaynak referansı',
    `is_emri_sonucu` VARCHAR(255) NULL COMMENT 'Hangi iş emri sonucundan geldi',
    `aciklama` TEXT NULL COMMENT 'Açıklama/Not',
    `islem_yapan_id` INT(11) NULL COMMENT 'İşlemi yapan kullanıcı',
    `kaynak` ENUM('manuel', 'puantaj_excel', 'puantaj_online', 'sistem') DEFAULT 'manuel' COMMENT 'İşlem kaynağı',
    `kayit_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_demirbas` (`demirbas_id`),
    INDEX `idx_personel` (`personel_id`),
    INDEX `idx_tarih` (`tarih`),
    INDEX `idx_hareket_tipi` (`hareket_tipi`),
    INDEX `idx_islem_id` (`islem_id`),
    FOREIGN KEY (`demirbas_id`) REFERENCES `demirbas`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`personel_id`) REFERENCES `personel`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Demirbaş hareket geçmişi (zimmet, iade, sarf, kayıp)';

-- Mevcut zimmet kayıtlarını hareket tablosuna aktarma (migration)
-- Bu sorguyu bir kez çalıştırın
INSERT INTO `demirbas_hareketler` (`demirbas_id`, `personel_id`, `hareket_tipi`, `miktar`, `tarih`, `aciklama`, `kaynak`, `kayit_tarihi`)
SELECT 
    `demirbas_id`,
    `personel_id`,
    'zimmet' as hareket_tipi,
    `teslim_miktar` as miktar,
    `teslim_tarihi` as tarih,
    CONCAT('Migration: ', COALESCE(`aciklama`, '')) as aciklama,
    'sistem' as kaynak,
    `kayit_tarihi`
FROM `demirbas_zimmet`
WHERE `silinme_tarihi` IS NULL AND `teslim_miktar` > 0;

-- İade kayıtlarını da aktar
INSERT INTO `demirbas_hareketler` (`demirbas_id`, `personel_id`, `hareket_tipi`, `miktar`, `tarih`, `aciklama`, `kaynak`, `kayit_tarihi`)
SELECT 
    `demirbas_id`,
    `personel_id`,
    'iade' as hareket_tipi,
    `iade_miktar` as miktar,
    COALESCE(`iade_tarihi`, `kayit_tarihi`) as tarih,
    CONCAT('Migration İade: ', COALESCE(`aciklama`, '')) as aciklama,
    'sistem' as kaynak,
    COALESCE(`iade_tarihi`, `kayit_tarihi`)
FROM `demirbas_zimmet`
WHERE `silinme_tarihi` IS NULL AND `iade_miktar` > 0;

-- Personel bazlı bakiye görüntüleme (VIEW)
CREATE OR REPLACE VIEW `v_personel_demirbas_bakiye` AS
SELECT 
    h.personel_id,
    h.demirbas_id,
    d.demirbas_adi,
    d.demirbas_no,
    p.adi_soyadi as personel_adi,
    SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END) as toplam_zimmet,
    SUM(CASE WHEN h.hareket_tipi IN ('iade', 'sarf', 'kayip') THEN h.miktar ELSE 0 END) as toplam_cikis,
    SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END) - 
    SUM(CASE WHEN h.hareket_tipi IN ('iade', 'sarf', 'kayip') THEN h.miktar ELSE 0 END) as bakiye
FROM demirbas_hareketler h
INNER JOIN demirbas d ON h.demirbas_id = d.id
INNER JOIN personel p ON h.personel_id = p.id
WHERE h.silinme_tarihi IS NULL
GROUP BY h.personel_id, h.demirbas_id, d.demirbas_adi, d.demirbas_no, p.adi_soyadi
HAVING bakiye != 0;
