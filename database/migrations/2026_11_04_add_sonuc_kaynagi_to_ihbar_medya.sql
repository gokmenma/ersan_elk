ALTER TABLE `ihbar_fotograflari`
    ADD COLUMN `kaynak` ENUM('bildirim','sonuc') NOT NULL DEFAULT 'bildirim' AFTER `medya_tipi`,
    ADD COLUMN `ekleyen_personel_id` INT UNSIGNED NULL DEFAULT NULL AFTER `kaynak`,
    ADD INDEX `idx_ihbar_kaynak` (`ihbar_id`, `kaynak`, `medya_tipi`);
