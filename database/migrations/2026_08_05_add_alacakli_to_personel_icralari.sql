ALTER TABLE `personel_icralari`
    ADD COLUMN `alacakli` VARCHAR(255) NULL COMMENT 'Alacaklı kurum veya kişi adı' AFTER `icra_dairesi`;
