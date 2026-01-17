-- Personel Evrakları Modülü Veritabanı Tablosu
-- Oluşturma Tarihi: 2026-01-16

-- Personel Evrakları Tablosu
CREATE TABLE IF NOT EXISTS `personel_evraklar` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `personel_id` INT(11) NOT NULL,
    `evrak_adi` VARCHAR(255) NOT NULL COMMENT 'Evrak başlığı/adı',
    `evrak_turu` VARCHAR(50) DEFAULT NULL COMMENT 'Sözleşme, Kimlik, Diploma, CV, vb.',
    `dosya_adi` VARCHAR(255) NOT NULL COMMENT 'Sunucudaki dosya adı',
    `orijinal_dosya_adi` VARCHAR(255) DEFAULT NULL COMMENT 'Yüklenen orijinal dosya adı',
    `dosya_boyutu` INT DEFAULT 0 COMMENT 'Dosya boyutu (byte)',
    `dosya_tipi` VARCHAR(50) DEFAULT NULL COMMENT 'application/pdf, image/jpeg, vb.',
    `aciklama` TEXT DEFAULT NULL COMMENT 'Evrak hakkında notlar',
    `yukleme_tarihi` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `yukleyen_id` INT(11) DEFAULT NULL COMMENT 'Evrakı yükleyen kullanıcı',
    `guncelleme_tarihi` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `aktif` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_personel` (`personel_id`),
    KEY `idx_evrak_turu` (`evrak_turu`),
    KEY `idx_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek evrak türleri için tanımlama (isteğe bağlı)
-- INSERT INTO `tanimlamalar` (`tur`, `baslik`, `deger`) VALUES
-- ('evrak_turu', 'Sözleşme', 'sozlesme'),
-- ('evrak_turu', 'Kimlik', 'kimlik'),
-- ('evrak_turu', 'Diploma', 'diploma'),
-- ('evrak_turu', 'CV', 'cv'),
-- ('evrak_turu', 'Sağlık Raporu', 'saglik_raporu'),
-- ('evrak_turu', 'Sertifika', 'sertifika'),
-- ('evrak_turu', 'Diğer', 'diger');
