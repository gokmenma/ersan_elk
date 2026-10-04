-- Cari tablosuna e-Fatura / e-Arşiv ve detaylı fatura/vergi alanları ekleme
ALTER TABLE `cari`
    ADD COLUMN `vkn_tckn` VARCHAR(20) NULL DEFAULT NULL AFTER `firma`,
    ADD COLUMN `vergi_dairesi` VARCHAR(100) NULL DEFAULT NULL AFTER `vkn_tckn`,
    ADD COLUMN `alici_turu` ENUM('KURUMSAL', 'BIREYSEL') NOT NULL DEFAULT 'KURUMSAL' AFTER `vergi_dairesi`,
    ADD COLUMN `belge_turu` ENUM('OTOMATIK', 'EFATURA', 'EARSIV') NOT NULL DEFAULT 'OTOMATIK' AFTER `alici_turu`,
    ADD COLUMN `posta_kutusu` VARCHAR(255) NULL DEFAULT NULL AFTER `belge_turu`,
    ADD COLUMN `ulke` VARCHAR(100) NULL DEFAULT 'Türkiye' AFTER `posta_kutusu`,
    ADD COLUMN `il` VARCHAR(100) NULL DEFAULT NULL AFTER `ulke`,
    ADD COLUMN `ilce` VARCHAR(100) NULL DEFAULT NULL AFTER `il`,
    ADD COLUMN `posta_kodu` VARCHAR(20) NULL DEFAULT NULL AFTER `ilce`,
    ADD COLUMN `web_sitesi` VARCHAR(255) NULL DEFAULT NULL AFTER `Email`,
    ADD COLUMN `ticaret_sicil_no` VARCHAR(50) NULL DEFAULT NULL AFTER `web_sitesi`,
    ADD COLUMN `mersis_no` VARCHAR(50) NULL DEFAULT NULL AFTER `ticaret_sicil_no`;
