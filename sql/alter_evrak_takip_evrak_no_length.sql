-- evrak_takip tablosundaki evrak_no sütununun uzunluğunu artırma
-- Uzun evrak numaraları ve referans kodlarının sorunsuz kaydedilmesi için
ALTER TABLE `evrak_takip` MODIFY COLUMN `evrak_no` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Evrak Numarası / Takip No';
