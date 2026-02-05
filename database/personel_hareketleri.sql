-- Personel Hareketleri Tablosu
-- Saha personelinin giriş/çıkış konum takibi için
-- Oluşturulma Tarihi: 2026-02-05

CREATE TABLE IF NOT EXISTS `personel_hareketleri` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `personel_id` INT NOT NULL COMMENT 'İşlemi yapan personel',
    `islem_tipi` ENUM('BASLA', 'BITIR') NOT NULL COMMENT 'İşlem türü: Göreve Başla veya Bitir',
    `zaman` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Sunucu saati (manipülasyona karşı)',
    `konum_enlem` DECIMAL(10, 7) NOT NULL COMMENT 'GPS Latitude',
    `konum_boylam` DECIMAL(10, 7) NOT NULL COMMENT 'GPS Longitude',
    `konum_hassasiyeti` DECIMAL(10, 2) NULL COMMENT 'GPS doğruluk (metre cinsinden)',
    `cihaz_bilgisi` VARCHAR(500) NULL COMMENT 'Tarayıcı/cihaz user agent',
    `ip_adresi` VARCHAR(45) NULL COMMENT 'İstemci IP adresi',
    `firma_id` INT NULL COMMENT 'Firma ID (çoklu firma desteği)',
    `silinme_tarihi` DATETIME NULL DEFAULT NULL COMMENT 'Soft delete',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_personel_tarih` (`personel_id`, `zaman`),
    INDEX `idx_islem_tipi` (`islem_tipi`),
    INDEX `idx_firma_tarih` (`firma_id`, `zaman`),
    
    CONSTRAINT `fk_personel_hareketleri_personel` 
        FOREIGN KEY (`personel_id`) REFERENCES `personel`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Saha personelinin görev giriş-çıkış konum takibi';
