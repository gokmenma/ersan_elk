-- Evrak Takip tablosuna gelen evrak termin/son tarih takibi için son_tarih sütunu eklenmesi
ALTER TABLE `evrak_takip` 
ADD COLUMN `son_tarih` DATE NULL DEFAULT NULL AFTER `tarih`;
