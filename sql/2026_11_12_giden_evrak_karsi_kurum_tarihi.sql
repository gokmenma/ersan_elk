-- Giden evrak: karşı kurumun verdiği evrak numarasının tarihi. Tekrar çalıştırılabilir.
ALTER TABLE `evrak_takip`
    ADD COLUMN IF NOT EXISTS `karsi_evrak_tarihi` DATE NULL DEFAULT NULL AFTER `karsi_evrak_no`;
