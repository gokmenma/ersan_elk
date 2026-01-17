-- Bordro Dönemi Tablosu
CREATE TABLE IF NOT EXISTS `bordro_donemi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `donem_adi` VARCHAR(100) NOT NULL COMMENT 'Örn: Ocak 2026, Şubat 2026',
    `baslangic_tarihi` DATE NOT NULL,
    `bitis_tarihi` DATE NOT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bordro Personel Tablosu (Dönem bazlı personel ve hesaplama verileri)
CREATE TABLE IF NOT EXISTS `bordro_personel` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `donem_id` INT NOT NULL,
    `personel_id` INT NOT NULL,
    
    -- Bordro Hesaplama Verileri
    `brut_maas` DECIMAL(12,2) DEFAULT NULL,
    `sgk_isci` DECIMAL(12,2) DEFAULT NULL COMMENT 'SGK İşçi Payı (%14)',
    `issizlik_isci` DECIMAL(12,2) DEFAULT NULL COMMENT 'İşsizlik İşçi Payı (%1)',
    `gelir_vergisi` DECIMAL(12,2) DEFAULT NULL,
    `damga_vergisi` DECIMAL(12,2) DEFAULT NULL COMMENT 'Damga Vergisi (%0.759)',
    `net_maas` DECIMAL(12,2) DEFAULT NULL,
    
    -- İşveren Maliyetleri
    `sgk_isveren` DECIMAL(12,2) DEFAULT NULL COMMENT 'SGK İşveren Payı (%20.5)',
    `issizlik_isveren` DECIMAL(12,2) DEFAULT NULL COMMENT 'İşsizlik İşveren Payı (%2)',
    `toplam_maliyet` DECIMAL(12,2) DEFAULT NULL COMMENT 'Brüt + İşveren Payları',
    
    -- Ek bilgiler
    `calisan_gun` INT DEFAULT 30,
    `fazla_mesai_saat` DECIMAL(5,2) DEFAULT 0,
    `fazla_mesai_tutar` DECIMAL(12,2) DEFAULT 0,
    `kesinti_tutar` DECIMAL(12,2) DEFAULT 0,
    `prim_tutar` DECIMAL(12,2) DEFAULT 0,
    `aciklama` TEXT DEFAULT NULL,
    
    `hesaplama_tarihi` DATETIME DEFAULT NULL,
    `olusturma_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME DEFAULT NULL,
    
    FOREIGN KEY (`donem_id`) REFERENCES `bordro_donemi`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`personel_id`) REFERENCES `personel`(`id`) ON DELETE CASCADE,
    
    UNIQUE KEY `unique_donem_personel` (`donem_id`, `personel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İndeksler
CREATE INDEX idx_bordro_personel_donem ON bordro_personel(donem_id);
CREATE INDEX idx_bordro_personel_personel ON bordro_personel(personel_id);
CREATE INDEX idx_bordro_donemi_tarih ON bordro_donemi(baslangic_tarihi, bitis_tarihi);
