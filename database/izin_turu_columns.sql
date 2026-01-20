-- İzin Türü için tanimlamalar tablosuna ek kolonlar
-- Bu kolonlar zaten mevcut değilse ekle

ALTER TABLE `tanimlamalar` 
ADD COLUMN IF NOT EXISTS `ucretli_mi` TINYINT(1) DEFAULT 1 COMMENT 'Ücretli izin mi? (1=Evet, 0=Hayır)',
ADD COLUMN IF NOT EXISTS `personel_gorebilir` TINYINT(1) DEFAULT 1 COMMENT 'Personel izin talebinde görebilir mi? (1=Evet, 0=Hayır)';
