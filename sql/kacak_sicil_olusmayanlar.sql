-- =====================================================
-- KAÇAK İŞLEMLERİ - SİCİL OLUŞMAYANLAR MODÜLÜ
-- Kurumun (KASKİ) sicil oluşturamadığı tutanakları geri bildirmesi,
-- tutanağı tutan ekibe düşürülmesi, düzeltilen bilginin kuruma dönmesi.
--
-- Script idempotenttir, birden fazla kez çalıştırılabilir.
-- =====================================================

-- -----------------------------------------------------
-- 1. kacak_kontrol yeni alanlar
--    abone_tc / abone_dogum_tarihi: sicil oluşmama sebebi olan iki alan
--    sistemde hiç tutulmuyordu; düzeltilen bilgi buraya işlenecek.
--    sicil_durumu: yalnızca rozet/filtre için denormalize bayrak.
--    NOT: durum ve onay_durumu alanlarına DOKUNULMUYOR - onlar hakediş
--    hesabına giriyor (KacakKontrolModel::hakedisKosulu).
-- -----------------------------------------------------
ALTER TABLE `kacak_kontrol`
    ADD COLUMN IF NOT EXISTS `abone_tc` VARCHAR(11) DEFAULT NULL AFTER `abone_adi`,
    ADD COLUMN IF NOT EXISTS `abone_dogum_tarihi` DATE DEFAULT NULL AFTER `abone_tc`,
    ADD COLUMN IF NOT EXISTS `abone_adres` VARCHAR(500) DEFAULT NULL AFTER `abone_dogum_tarihi`,
    ADD COLUMN IF NOT EXISTS `sicil_durumu` ENUM('normal','eksik','yanitlandi','cozuldu') NOT NULL DEFAULT 'normal' AFTER `hakedisten_dus`;

ALTER TABLE `kacak_kontrol`
    ADD INDEX IF NOT EXISTS `idx_sicil_durumu` (`firma_id`, `sicil_durumu`);

-- -----------------------------------------------------
-- 2. kacak_sicil_eksik tablosu
--    Her gidiş-geliş turu ayrı satır (tur_sira) - aynı tutanak birden
--    fazla kez geri dönebilir, geçmiş kaybolmamalı.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `kacak_sicil_eksik` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `kacak_id` INT(11) DEFAULT NULL,
    `tutanak_no` VARCHAR(100) NOT NULL,
    `tutanak_tarihi` DATE DEFAULT NULL,
    `tur_sira` TINYINT(3) NOT NULL DEFAULT 1,

    `neden` ENUM('tc_hatali','dogum_tarihi_hatali','ad_soyad_hatali','adres_hatali',
                 'sayac_no_hatali','abone_bulunamadi','tutanak_okunmuyor','diger') NOT NULL DEFAULT 'diger',
    `aciklama` TEXT DEFAULT NULL,

    `durum` ENUM('beklemede','yanitlandi','cozuldu','iptal') NOT NULL DEFAULT 'beklemede',

    `bildiren_user_id` INT(11) NOT NULL,
    `bildirim_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
    `atanan_personel_ids` VARCHAR(255) DEFAULT NULL,

    `yanit_veren_personel_id` INT(11) DEFAULT NULL,
    `yanit_veren_user_id` INT(11) DEFAULT NULL,
    `yanit_tarihi` DATETIME DEFAULT NULL,
    `yanit_aciklama` TEXT DEFAULT NULL,
    `duzeltilen_veri` LONGTEXT DEFAULT NULL,
    `onceki_veri` LONGTEXT DEFAULT NULL,

    `kapatan_user_id` INT(11) DEFAULT NULL,
    `kapatma_tarihi` DATETIME DEFAULT NULL,
    `kapatma_aciklama` TEXT DEFAULT NULL,

    `olusturma_tarihi` DATETIME NOT NULL DEFAULT current_timestamp(),
    `guncelleme_tarihi` DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
    `silinme_tarihi` DATETIME DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_firma_durum` (`firma_id`, `durum`),
    KEY `idx_kacak` (`kacak_id`),
    KEY `idx_tutanak` (`firma_id`, `tutanak_no`),
    KEY `idx_bildiren` (`bildiren_user_id`),
    KEY `idx_yanit_veren` (`yanit_veren_personel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Yetki kayıtları
--    kacak_sicil_bildir  -> kurum kullanıcısı: eksik bildirimi açar/kapatır
--    kacak_sicil_yanitla -> ofis/ekip: düzeltilmiş bilgiyi girer
--    Bu iki yetki kacak_duzenle'den bağımsızdır; kurum kullanıcısı
--    tutanağın kendisine dokunamaz.
-- -----------------------------------------------------
INSERT INTO `permissions` (`name`, `auth_name`, `description`, `group_name`, `permission_level`, `is_active`, `superadmin`, `is_required`)
SELECT * FROM (
    SELECT 'Kaçak Sicil Eksik Bildirimi' AS n, 'kacak_sicil_bildir' AS a,
           'Sicil oluşmayan tutanağı bildirme ve çözüldü olarak kapatma' AS d,
           'İş Takip' AS g, 0 AS l, 1 AS ia, 0 AS sa, 0 AS ir
    UNION ALL SELECT 'Kaçak Sicil Düzeltme Yanıtı', 'kacak_sicil_yanitla',
           'Sicil oluşmayan tutanak için düzeltilmiş abone bilgisi girme',
           'İş Takip', 0, 1, 0, 0
) t
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`auth_name` = t.a);

-- -----------------------------------------------------
-- 4. Rol atamaları
-- -----------------------------------------------------

-- 4a. Kaçak Kontrol Sorumlusu (kurum tarafı) -> sadece bildirme yetkisi
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_by`)
SELECT ur.`id`, p.`id`, 0
FROM `user_roles` ur
CROSS JOIN `permissions` p
WHERE ur.`role_name` = 'Kaçak Kontrol Sorumlusu'
  AND p.`auth_name` = 'kacak_sicil_bildir'
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp
      WHERE urp.`role_id` = ur.`id` AND urp.`permission_id` = p.`id`
  );

-- 4b. Süper Admin ve Firma Sahibi -> her iki yetki
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_by`)
SELECT ur.`id`, p.`id`, 0
FROM `user_roles` ur
CROSS JOIN `permissions` p
WHERE ur.`role_name` IN ('Süper Admin', 'Firma Sahibi')
  AND p.`auth_name` IN ('kacak_sicil_bildir', 'kacak_sicil_yanitla')
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp
      WHERE urp.`role_id` = ur.`id` AND urp.`permission_id` = p.`id`
  );

-- 4c. kacak_duzenle yetkisi olan roller -> yanıtlama yetkisi
INSERT INTO `user_role_permissions` (`role_id`, `permission_id`, `created_by`)
SELECT DISTINCT urp.`role_id`, py.`id`, 0
FROM `user_role_permissions` urp
JOIN `permissions` pd ON pd.`id` = urp.`permission_id` AND pd.`auth_name` = 'kacak_duzenle'
CROSS JOIN `permissions` py
WHERE py.`auth_name` = 'kacak_sicil_yanitla'
  AND NOT EXISTS (
      SELECT 1 FROM `user_role_permissions` urp2
      WHERE urp2.`role_id` = urp.`role_id` AND urp2.`permission_id` = py.`id`
  );
