-- Migration: Allow NULL for calisilan_firma and calisilan_proje columns in personel table
ALTER TABLE `personel` 
  MODIFY COLUMN `calisilan_firma` varchar(150) NULL DEFAULT NULL,
  MODIFY COLUMN `calisilan_proje` varchar(150) NULL DEFAULT NULL;
