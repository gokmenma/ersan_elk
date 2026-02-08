-- Demirbaş Otomatik Zimmet Ayarları Migration
-- Oluşturulma Tarihi: 2026-02-07
-- Açıklama: Demirbaş tablosuna otomatik zimmet/iade için iş emri eşleştirme alanları eklendi

ALTER TABLE `demirbas` 
ADD COLUMN `otomatik_zimmet_is_emri` VARCHAR(255) NULL 
    COMMENT 'Bu iş emri sonucu geldiğinde personele otomatik zimmetlenir' AFTER `durum`,
ADD COLUMN `otomatik_iade_is_emri` VARCHAR(255) NULL 
    COMMENT 'Bu iş emri sonucu geldiğinde personelden otomatik iade alınır' AFTER `otomatik_zimmet_is_emri`;

-- İndeks ekle (iş emri sonuçlarına göre hızlı arama için)
ALTER TABLE `demirbas` 
ADD INDEX `idx_otomatik_zimmet` (`otomatik_zimmet_is_emri`),
ADD INDEX `idx_otomatik_iade` (`otomatik_iade_is_emri`);
