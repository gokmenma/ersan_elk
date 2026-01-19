-- Mail bildirim ayarları alanlarını users tablosuna ekle
-- Bu alanlar personelin taleplerinde kullanıcıya mail gönderilip gönderilmeyeceğini belirler

ALTER TABLE `users` 
ADD COLUMN `mail_avans_talep` ENUM('Evet', 'Hayır') DEFAULT 'Hayır' COMMENT 'Avans talebi bildirimlerini al',
ADD COLUMN `mail_izin_talep` ENUM('Evet', 'Hayır') DEFAULT 'Hayır' COMMENT 'İzin talebi bildirimlerini al',
ADD COLUMN `mail_genel_talep` ENUM('Evet', 'Hayır') DEFAULT 'Hayır' COMMENT 'Genel talep bildirimlerini al',
ADD COLUMN `mail_ariza_talep` ENUM('Evet', 'Hayır') DEFAULT 'Hayır' COMMENT 'Arıza talebi bildirimlerini al';
