ALTER TABLE `uets_ayarlar`
  ADD COLUMN IF NOT EXISTS `liste_onbellek` LONGTEXT NULL DEFAULT NULL AFTER `son_giden_sync_zamani`,
  ADD COLUMN IF NOT EXISTS `liste_onbellek_zamani` DATETIME NULL DEFAULT NULL AFTER `liste_onbellek`,
  ADD COLUMN IF NOT EXISTS `liste_engel_bitis` DATETIME NULL DEFAULT NULL AFTER `liste_onbellek_zamani`;
