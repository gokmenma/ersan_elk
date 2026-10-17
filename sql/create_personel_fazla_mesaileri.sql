-- ============================================================================
-- Fazla Mesai (FM) Puantaj Takip Tablosu
-- Tarih: 2026-10-17
-- Amaç: Puantaj ekranında RTÇ/HTÇ/X gibi çalışma türlerini ezmeden
--        bağımsız değişken saatli fazla mesai (Overtime) kayıtlarının tutulması.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `personel_fazla_mesaileri` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL DEFAULT 0,
    `personel_id` INT(11) NOT NULL,
    `tarih` DATE NOT NULL,
    `saat` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `aciklama` VARCHAR(255) NULL DEFAULT NULL,
    `onay_durumu` VARCHAR(50) NOT NULL DEFAULT 'Onaylandı',
    `kayit_yapan` INT(11) NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `silinme_tarihi` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_personel_tarih` (`personel_id`, `tarih`, `silinme_tarihi`),
    KEY `idx_firma_tarih` (`firma_id`, `tarih`),
    KEY `idx_personel_silinme` (`personel_id`, `silinme_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
