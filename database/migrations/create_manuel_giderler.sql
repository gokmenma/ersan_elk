-- =============================================
-- MANUEL GİDERLER TABLOSU
-- Maliyet raporu için elle girilen gider kayıtları
-- Oluşturulma: 2026-03-09
-- =============================================
CREATE TABLE IF NOT EXISTS `manuel_giderler` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `kategori` VARCHAR(50) NOT NULL COMMENT 'Araç, Personel, Demirbaş, Operasyonel, Diğer',
    `alt_kategori` VARCHAR(100) DEFAULT NULL COMMENT 'Alt kategori açıklaması',
    `tutar` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tarih` DATE NOT NULL,
    `aciklama` TEXT DEFAULT NULL,
    `belge_no` VARCHAR(50) DEFAULT NULL COMMENT 'Fatura/Belge numarası',
    `olusturan_kullanici_id` INT(11) DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_firma` (`firma_id`),
    KEY `idx_tarih` (`tarih`),
    KEY `idx_kategori` (`kategori`),
    KEY `idx_firma_tarih` (`firma_id`, `tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
