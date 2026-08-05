ALTER TABLE `evrak_takip`
    ADD COLUMN `muhatap_alt_birim` VARCHAR(255) NULL AFTER `kurum_adi`,
    ADD COLUMN `muhatap_adres` VARCHAR(500) NULL AFTER `muhatap_alt_birim`,
    ADD COLUMN `yazi_tipi` ENUM('times_new_roman', 'arial') NOT NULL DEFAULT 'times_new_roman' AFTER `aciklama`,
    ADD COLUMN `imza_kullanici_ids` TEXT NULL COMMENT 'JSON kullanıcı ID listesi' AFTER `yazi_tipi`,
    ADD COLUMN `ilgiler` TEXT NULL COMMENT 'Her satır bir ilgi olacak şekilde düz metin' AFTER `imza_kullanici_ids`,
    ADD COLUMN `ekler` TEXT NULL COMMENT 'Her satır bir ek olacak şekilde düz metin' AFTER `ilgiler`;
