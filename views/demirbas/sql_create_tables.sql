-- Demirbaş/Zimmet Modülü Veritabanı Tabloları
-- Oluşturma Tarihi: 2026-01-16

-- Demirbaş Kategorileri Tablosu
CREATE TABLE IF NOT EXISTS `demirbas_kategorileri` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `kategori_adi` VARCHAR(100) NOT NULL,
    `kategori_aciklama` TEXT,
    `aktif` TINYINT(1) DEFAULT 1,
    `kayit_tarihi` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan Kategorileri Ekle
INSERT INTO `demirbas_kategorileri` (`kategori_adi`, `kategori_aciklama`) VALUES
('Araç', 'Şirket araçları'),
('Laptop', 'Dizüstü bilgisayarlar'),
('Bilgisayar', 'Masaüstü bilgisayarlar'),
('SIM Kart', 'Telefon SIM kartları'),
('Tablet', 'Tablet cihazlar'),
('Sayaç', 'Elektrik/Su sayaçları'),
('Telefon', 'Cep telefonları'),
('Monitör', 'Bilgisayar monitörleri'),
('Yazıcı', 'Yazıcı ve tarayıcılar'),
('Diğer', 'Diğer demirbaşlar');

-- Demirbas tablosuna yeni alanlar ekle (mevcut tabloya ALTER)
ALTER TABLE `demirbas` 
ADD COLUMN IF NOT EXISTS `kategori_id` INT(11) DEFAULT NULL AFTER `id`,
ADD COLUMN IF NOT EXISTS `miktar` INT DEFAULT 1 AFTER `aciklama`,
ADD COLUMN IF NOT EXISTS `kalan_miktar` INT DEFAULT 1 AFTER `miktar`,
ADD COLUMN IF NOT EXISTS `seri_no` VARCHAR(100) DEFAULT NULL AFTER `model`,
ADD COLUMN IF NOT EXISTS `durum` ENUM('aktif', 'pasif', 'arizali', 'hurda') DEFAULT 'aktif' AFTER `kalan_miktar`,
ADD FOREIGN KEY (`kategori_id`) REFERENCES `demirbas_kategorileri`(`id`) ON DELETE SET NULL;

-- Zimmet (Envanter Atama) Tablosu
CREATE TABLE IF NOT EXISTS `demirbas_zimmet` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `demirbas_id` INT(11) NOT NULL,
    `personel_id` INT(11) NOT NULL,
    `teslim_tarihi` DATE NOT NULL,
    `teslim_miktar` INT DEFAULT 1,
    `iade_tarihi` DATE DEFAULT NULL,
    `iade_miktar` INT DEFAULT NULL,
    `durum` ENUM('teslim', 'iade', 'kayip', 'arizali') DEFAULT 'teslim',
    `aciklama` TEXT,
    `teslim_eden_id` INT(11) DEFAULT NULL,
    `kayit_tarihi` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_demirbas` (`demirbas_id`),
    KEY `idx_personel` (`personel_id`),
    KEY `idx_durum` (`durum`),
    FOREIGN KEY (`demirbas_id`) REFERENCES `demirbas`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`personel_id`) REFERENCES `personel`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zimmet Geçmişi için View
CREATE OR REPLACE VIEW `v_zimmet_detay` AS
SELECT 
    z.id AS zimmet_id,
    z.demirbas_id,
    z.personel_id,
    z.teslim_tarihi,
    z.teslim_miktar,
    z.iade_tarihi,
    z.iade_miktar,
    z.durum AS zimmet_durum,
    z.aciklama AS zimmet_aciklama,
    d.demirbas_no,
    d.demirbas_adi,
    d.marka,
    d.model,
    d.seri_no,
    k.kategori_adi,
    p.adi_soyadi AS personel_adi,
    p.cep_telefonu AS personel_telefon
FROM demirbas_zimmet z
LEFT JOIN demirbas d ON z.demirbas_id = d.id
LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
LEFT JOIN personel p ON z.personel_id = p.id;
