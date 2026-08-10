-- Demirbaş zimmet fotoğraflarında hareket_id alanını isteğe bağlı (NULL) yap
ALTER TABLE `demirbas_zimmet_fotograflari` MODIFY COLUMN `hareket_id` INT(11) NULL DEFAULT NULL;
