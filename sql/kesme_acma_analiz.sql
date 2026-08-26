-- Kesme/Açma analiz ve takip ekranı (Teknik doküman v1.0)
-- Uygulamadan önce yedek alınarak ilgili firma veritabanında çalıştırılmalıdır.

CREATE TABLE IF NOT EXISTS `kesme_acma_kural_degeri` (
    `firma_id` INT(11) NOT NULL,
    `kural_kodu` VARCHAR(40) NOT NULL,
    `deger` TEXT NOT NULL,
    `guncelleyen_id` INT(11) DEFAULT NULL,
    `guncelleme_ts` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`firma_id`, `kural_kodu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kesme_acma_uyari` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `personel_id` INT(11) NOT NULL,
    `ay` CHAR(7) NOT NULL,
    `tur` ENUM('odeme','kapali','toplu','ani','sonucsuz','eksik') NOT NULL,
    `puan` DECIMAL(6,2) NOT NULL DEFAULT 0,
    `ozet` VARCHAR(255) NOT NULL,
    `detay` TEXT DEFAULT NULL,
    `dogum_ts` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `durum` ENUM('acik','kapali') NOT NULL DEFAULT 'acik',
    `yukseltildi_ts` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_firma_personel_ay_tur` (`firma_id`,`personel_id`,`ay`,`tur`),
    KEY `idx_uyari_liste` (`firma_id`,`ay`,`durum`,`is_active`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kesme_acma_uyari_gerekce` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `uyari_id` INT(11) NOT NULL,
    `yazan_id` INT(11) NOT NULL,
    `ts` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `gerekce` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_uyari` (`firma_id`,`uyari_id`,`is_active`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kesme_acma_kontrol_madde` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `ad` VARCHAR(120) NOT NULL,
    `sira` INT(11) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_kontrol_madde` (`firma_id`,`is_active`,`deleted_at`,`sira`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kesme_acma_kontrol_durum` (
    `firma_id` INT(11) NOT NULL,
    `ay` CHAR(7) NOT NULL,
    `madde_id` INT(11) NOT NULL,
    `isaretli` TINYINT(1) NOT NULL DEFAULT 0,
    `isaretleyen_id` INT(11) DEFAULT NULL,
    `ts` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`firma_id`,`ay`,`madde_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kesme_acma_islem_gunlugu` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `firma_id` INT(11) NOT NULL,
    `ts` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `kullanici_id` INT(11) NOT NULL,
    `kategori` ENUM('kural','uyari','kontrol','nobet') NOT NULL,
    `aciklama` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_gunluk` (`firma_id`,`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions`
    (`name`,`auth_name`,`description`,`group_name`,`permission_level`,`is_active`,`superadmin`,`is_required`)
SELECT 'Kesme/Açma Analiz Yönetimi','kesme_analiz_yonetim',
       'Kesme/açma kuralları, uyarı gerekçeleri ve aylık kontrol listesini yönetme',
       'İş Takip',0,1,0,0
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `auth_name` = 'kesme_analiz_yonetim');

INSERT INTO `user_role_permissions` (`role_id`,`permission_id`)
SELECT r.id,p.id FROM `user_roles` r
CROSS JOIN `permissions` p
WHERE p.auth_name='kesme_analiz_yonetim'
  AND (r.superadmin=1 OR r.role_name LIKE '%Şef%' OR r.role_name LIKE '%Müdür%' OR r.role_name LIKE '%Yönetici%')
  AND NOT EXISTS (SELECT 1 FROM `user_role_permissions` x WHERE x.role_id=r.id AND x.permission_id=p.id);

INSERT INTO `kesme_acma_kural_degeri` (`firma_id`,`kural_kodu`,`deger`)
SELECT f.id,k.kod,k.deger FROM `firmalar` f
CROSS JOIN (
    SELECT 'nobet_havuz_kodlari' kod,'["AKY"]' deger
    UNION ALL SELECT 'nobet_yeni_bekleme','14'
    UNION ALL SELECT 'nobet_ay_basi_min','9'
    UNION ALL SELECT 'nobet_telefon_personelleri','["MERYEM ETYEMEZ","EDA KÖSE","MERYEM BERFİN AKSOY"]'
    UNION ALL SELECT 'nobet_telefon_sabit','{"Pzt":"MERYEM ETYEMEZ","Sal":"MERYEM BERFİN AKSOY","Çar":"EDA KÖSE"}'
    UNION ALL SELECT 'nobet_telefon_dongu','3'
    UNION ALL SELECT 'nobet_ilce_secim','"en_uzun"'
    UNION ALL SELECT 'nobet_arac_kisitli_ekipler','["ER-SAN ELEKTRİK EKİP-8","ER-SAN ELEKTRİK EKİP-9"]'
    UNION ALL SELECT 'nobet_ilce_merkez','"yazilmaz"'
    UNION ALL SELECT 'mahalle_mesaj_bekleme','5'
    UNION ALL SELECT 'mahalle_ilce_dongu','"2_1"'
    UNION ALL SELECT 'mahalle_ust_uste','"verilmez"'
    UNION ALL SELECT 'odeme_gecerlilik_gun','1'
    UNION ALL SELECT 'odeme_agir_suphe_dk','15'
    UNION ALL SELECT 'odeme_acan_ayrimi','"uygulanir"'
    UNION ALL SELECT 'odeme_ust','1.25' UNION ALL SELECT 'odeme_alt','0.75'
    UNION ALL SELECT 'odeme_min_temas','40' UNION ALL SELECT 'kapali_ust','1.4'
    UNION ALL SELECT 'kapali_alt','0.7' UNION ALL SELECT 'kapali_min_beklenen','15'
    UNION ALL SELECT 'toplu_kat','3' UNION ALL SELECT 'toplu_min','40'
    UNION ALL SELECT 'pazar_esik','150' UNION ALL SELECT 'ani_yuzde','50'
    UNION ALL SELECT 'ani_min','30' UNION ALL SELECT 'sonucsuz_yuzde','35'
    UNION ALL SELECT 'eksik_gun','3' UNION ALL SELECT 'uyari_gun','7'
    UNION ALL SELECT 'mahalle_min_is','200' UNION ALL SELECT 'mahalle_min_temas','150'
) k
WHERE NOT EXISTS (SELECT 1 FROM `kesme_acma_kural_degeri` d WHERE d.firma_id=f.id AND d.kural_kodu=k.kod);

INSERT INTO `kesme_acma_kontrol_madde` (`firma_id`,`ad`,`sira`)
SELECT f.id, m.ad, m.sira
FROM firmalar f
CROSS JOIN (
    SELECT 'Nöbet planı üretildi ve kontrol edildi' ad, 10 sira
    UNION ALL SELECT 'Mahalle atamaları günlük olarak girildi',20
    UNION ALL SELECT 'Ödeme kayıtlarından örneklem kontrolü yapıldı',30
    UNION ALL SELECT 'Veri kalitesi uyarıları temizlendi',40
    UNION ALL SELECT 'Dikkat listesindeki tüm uyarılar gerekçelendirildi',50
    UNION ALL SELECT 'Aylık rapor KASKİ''ye gönderildi',60
) m
WHERE NOT EXISTS (
    SELECT 1 FROM `kesme_acma_kontrol_madde` k
    WHERE k.firma_id = f.id AND k.ad = m.ad AND k.deleted_at IS NULL
);
