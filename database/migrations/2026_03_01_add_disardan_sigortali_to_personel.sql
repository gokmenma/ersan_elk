-- Dışarıdan Sigortalı: Sadece belirli modüllerde görünen personeller
-- disardan_sigortali = 1 olduğunda, gorunum_modulleri alanındaki modüllerde görünür
-- disardan_sigortali = 0 (varsayılan) olduğunda, her yerde görünür

ALTER TABLE personel ADD COLUMN disardan_sigortali TINYINT(1) NOT NULL DEFAULT 0 
  COMMENT 'Dışarıdan sigortalı: 1 ise sadece belirli modüllerde görünür';

ALTER TABLE personel ADD COLUMN gorunum_modulleri TEXT DEFAULT NULL 
  COMMENT 'Virgülle ayrılmış modül kodları: bordro,puantaj,nobet,demirbas,arac,evrak,mail,takip,personel,dashboard';
