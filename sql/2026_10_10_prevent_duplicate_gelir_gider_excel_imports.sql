-- Gelir-gider Excel aktarımlarında aynı dosya ve aynı satırın tekrar yüklenmesini engeller.

CREATE TABLE IF NOT EXISTS `gelir_gider_excel_imports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `file_hash` CHAR(64) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL DEFAULT '',
    `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `imported_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `duplicate_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `uploaded_by` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_gelir_gider_excel_file_hash` (`file_hash`),
    KEY `idx_gelir_gider_excel_uploaded_by` (`uploaded_by`),
    KEY `idx_gelir_gider_excel_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

ALTER TABLE `gelir_gider`
    ADD COLUMN IF NOT EXISTS `duplicate_hash` CHAR(64) NULL AFTER `kayit_yapan`,
    ADD COLUMN IF NOT EXISTS `excel_import_id` BIGINT UNSIGNED NULL AFTER `duplicate_hash`;

ALTER TABLE `gelir_gider`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_gelir_gider_active_duplicate_hash` (`duplicate_hash`),
    ADD INDEX IF NOT EXISTS `idx_gelir_gider_excel_import_id` (`excel_import_id`);
