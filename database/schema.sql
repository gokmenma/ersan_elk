-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ersantrc_personel
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `arac_bakim_kayitlari`
--

DROP TABLE IF EXISTS `arac_bakim_kayitlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arac_bakim_kayitlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `arac_id` int(11) NOT NULL,
  `tarih` date NOT NULL,
  `km` int(11) DEFAULT NULL,
  `bakim_tipi` enum('periyodik_bakim','yag_degisimi','lastik','fren','motor','elektrik','kaporta','diger') DEFAULT 'periyodik_bakim',
  `aciklama` text NOT NULL,
  `servis_adi` varchar(150) DEFAULT NULL,
  `tutar` decimal(10,2) DEFAULT 0.00,
  `fatura_no` varchar(50) DEFAULT NULL,
  `sonraki_bakim_km` int(11) DEFAULT NULL,
  `sonraki_bakim_tarihi` date DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_arac` (`arac_id`),
  KEY `idx_firma` (`firma_id`),
  KEY `idx_tarih` (`tarih`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `arac_km_kayitlari`
--

DROP TABLE IF EXISTS `arac_km_kayitlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arac_km_kayitlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `arac_id` int(11) NOT NULL,
  `tarih` date NOT NULL,
  `baslangic_km` int(11) NOT NULL,
  `bitis_km` int(11) NOT NULL,
  `yapilan_km` int(11) GENERATED ALWAYS AS (`bitis_km` - `baslangic_km`) STORED,
  `notlar` text DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_arac_tarih` (`arac_id`,`tarih`),
  KEY `idx_arac` (`arac_id`),
  KEY `idx_firma` (`firma_id`),
  KEY `idx_tarih` (`tarih`)
) ENGINE=InnoDB AUTO_INCREMENT=2719 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `arac_servis_kayitlari`
--

DROP TABLE IF EXISTS `arac_servis_kayitlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arac_servis_kayitlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) DEFAULT NULL,
  `arac_id` int(11) DEFAULT NULL,
  `servis_tarihi` date DEFAULT NULL,
  `iade_tarihi` date DEFAULT NULL,
  `giris_km` int(11) DEFAULT NULL,
  `cikis_km` int(11) DEFAULT NULL,
  `servis_adi` varchar(255) DEFAULT NULL,
  `servis_nedeni` text DEFAULT NULL,
  `yapilan_islemler` text DEFAULT NULL,
  `tutar` decimal(10,2) DEFAULT NULL,
  `fatura_no` varchar(100) DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  `ikame_arac_id` int(11) DEFAULT NULL,
  `ikame_plaka` varchar(20) DEFAULT NULL,
  `ikame_marka` varchar(100) DEFAULT NULL,
  `ikame_model` varchar(100) DEFAULT NULL,
  `ikame_alis_tarihi` date DEFAULT NULL,
  `ikame_teslim_km` int(11) DEFAULT NULL,
  `ikame_iade_km` int(11) DEFAULT NULL,
  `ikame_iade_tarihi` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `firma_id` (`firma_id`),
  KEY `arac_id` (`arac_id`),
  KEY `idx_ikame_arac` (`ikame_arac_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `arac_sigorta_kayitlari`
--

DROP TABLE IF EXISTS `arac_sigorta_kayitlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arac_sigorta_kayitlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `arac_id` int(11) NOT NULL,
  `sigorta_tipi` enum('trafik','kasko','diger') DEFAULT 'trafik',
  `sigorta_sirketi` varchar(150) DEFAULT NULL,
  `police_no` varchar(100) DEFAULT NULL,
  `baslangic_tarihi` date NOT NULL,
  `bitis_tarihi` date NOT NULL,
  `prim_tutari` decimal(10,2) DEFAULT 0.00,
  `acente` varchar(150) DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_arac` (`arac_id`),
  KEY `idx_firma` (`firma_id`),
  KEY `idx_bitis` (`bitis_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `arac_yakit_kayitlari`
--

DROP TABLE IF EXISTS `arac_yakit_kayitlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arac_yakit_kayitlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `arac_id` int(11) NOT NULL,
  `tarih` date NOT NULL,
  `km` int(11) NOT NULL COMMENT 'Yakıt alım anındaki kilometre',
  `onceki_km` int(11) DEFAULT NULL COMMENT 'Önceki kayıttaki kilometre',
  `yakit_miktari` decimal(10,2) NOT NULL COMMENT 'Litre',
  `birim_fiyat` decimal(10,2) DEFAULT NULL COMMENT 'TL/Litre',
  `toplam_tutar` decimal(10,2) NOT NULL COMMENT 'TL',
  `yakit_tipi` enum('benzin','dizel','lpg','elektrik') DEFAULT 'dizel',
  `tam_depo_mu` tinyint(1) DEFAULT 0,
  `istasyon` varchar(150) DEFAULT NULL,
  `fatura_no` varchar(50) DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  `external_id` varchar(50) DEFAULT NULL,
  `cihaz_numarasi` varchar(100) DEFAULT NULL,
  `kart_numarasi` varchar(100) DEFAULT NULL,
  `brut_tutar` decimal(10,2) DEFAULT NULL,
  `fatura_tarihi` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `external_id` (`external_id`),
  KEY `idx_arac` (`arac_id`),
  KEY `idx_firma` (`firma_id`),
  KEY `idx_tarih` (`tarih`),
  KEY `idx_arac_tarih` (`arac_id`,`tarih`)
) ENGINE=InnoDB AUTO_INCREMENT=4694 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `arac_zimmetleri`
--

DROP TABLE IF EXISTS `arac_zimmetleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arac_zimmetleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `arac_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `zimmet_tarihi` date NOT NULL,
  `iade_tarihi` date DEFAULT NULL,
  `teslim_km` int(11) DEFAULT NULL,
  `iade_km` int(11) DEFAULT NULL,
  `durum` enum('aktif','iade_edildi','iptal') DEFAULT 'aktif',
  `notlar` text DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_arac` (`arac_id`),
  KEY `idx_personel` (`personel_id`),
  KEY `idx_firma` (`firma_id`),
  KEY `idx_durum` (`durum`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `araclar`
--

DROP TABLE IF EXISTS `araclar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `araclar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `plaka` varchar(20) NOT NULL,
  `marka` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `model_yili` year(4) DEFAULT NULL,
  `arac_tipi` enum('binek','kamyonet','kamyon','minibus','otobus','motosiklet','diger','ikame') DEFAULT 'binek',
  `yakit_tipi` enum('benzin','dizel','lpg','elektrik','hibrit') DEFAULT 'dizel',
  `renk` varchar(50) DEFAULT NULL,
  `mulkiyet` varchar(150) DEFAULT NULL,
  `sase_no` varchar(50) DEFAULT NULL,
  `motor_no` varchar(50) DEFAULT NULL,
  `departmani` varchar(50) DEFAULT NULL,
  `ruhsat_sahibi` varchar(150) DEFAULT NULL,
  `muayene_bitis_tarihi` date DEFAULT NULL,
  `sigorta_bitis_tarihi` date DEFAULT NULL,
  `kasko_bitis_tarihi` date DEFAULT NULL,
  `baslangic_km` int(11) DEFAULT 0,
  `guncel_km` int(11) DEFAULT 0,
  `aktif_mi` tinyint(1) DEFAULT 1,
  `ikame_mi` tinyint(1) DEFAULT 0,
  `notlar` text DEFAULT NULL,
  `resim_yolu` varchar(255) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plaka_firma` (`plaka`,`firma_id`),
  KEY `idx_firma` (`firma_id`),
  KEY `idx_aktif` (`aktif_mi`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bildirim_kuyrugu`
--

DROP TABLE IF EXISTS `bildirim_kuyrugu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bildirim_kuyrugu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `baslik` varchar(100) DEFAULT NULL,
  `mesaj` text DEFAULT NULL,
  `okundu_mu` tinyint(1) DEFAULT 0,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bildirim_personel` (`personel_id`),
  CONSTRAINT `fk_bildirim_personel` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bildirimler`
--

DROP TABLE IF EXISTS `bildirimler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bildirimler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Bildirimin gideceği yönetici ID',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'bell',
  `color` varchar(20) DEFAULT 'primary',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bordro_donemi`
--

DROP TABLE IF EXISTS `bordro_donemi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bordro_donemi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL DEFAULT 0,
  `donem_adi` varchar(100) NOT NULL,
  `baslangic_tarihi` date NOT NULL,
  `bitis_tarihi` date NOT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `kapali_mi` tinyint(1) DEFAULT 0 COMMENT '0=Açık, 1=Kapalı',
  `personel_gorsun` tinyint(1) DEFAULT 0,
  `durum` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_bordro_donemi_tarih` (`baslangic_tarihi`,`bitis_tarihi`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bordro_genel_ayarlar`
--

DROP TABLE IF EXISTS `bordro_genel_ayarlar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bordro_genel_ayarlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parametre_kodu` varchar(50) NOT NULL,
  `parametre_adi` varchar(100) NOT NULL,
  `deger` decimal(15,2) NOT NULL,
  `gecerlilik_baslangic` date NOT NULL,
  `gecerlilik_bitis` date DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kod_tarih` (`parametre_kodu`,`gecerlilik_baslangic`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bordro_parametreleri`
--

DROP TABLE IF EXISTS `bordro_parametreleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bordro_parametreleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL DEFAULT 0,
  `kod` varchar(50) NOT NULL,
  `etiket` varchar(100) NOT NULL,
  `kategori` enum('gelir','kesinti') NOT NULL,
  `hesaplama_tipi` enum('brut','net','kismi_muaf','oran_bazli','netten','brutten','sgk_matrahindan','oran_bazli_vergi','oran_bazli_sgk','oran_bazli_net','gunluk_brut','gunluk_net','gunluk_kismi_muaf','aylik_gun_brut','aylik_gun_net','gunluk_kesinti','aylik_gun_kesinti') NOT NULL DEFAULT 'net',
  `gunluk_muaf_limit` decimal(12,2) DEFAULT 0.00,
  `aylik_muaf_limit` decimal(12,2) DEFAULT 0.00,
  `muaf_limit_tipi` enum('gunluk','aylik','yok') DEFAULT 'yok',
  `sgk_matrahi_dahil` tinyint(1) DEFAULT 0,
  `gelir_vergisi_dahil` tinyint(1) DEFAULT 1,
  `damga_vergisi_dahil` tinyint(1) DEFAULT 0,
  `gecerlilik_baslangic` date DEFAULT NULL,
  `gecerlilik_bitis` date DEFAULT NULL,
  `varsayilan_tutar` decimal(12,2) DEFAULT 0.00,
  `gunluk_tutar` decimal(10,2) DEFAULT 0.00 COMMENT 'Günlük birim tutar (örn: yemek yardımı günlük 50 TL)',
  `gun_sayisi_otomatik` tinyint(1) DEFAULT 0 COMMENT '0: Manuel/Sabit gün, 1: Otomatik puantajdan çek',
  `varsayilan_gun_sayisi` int(11) DEFAULT 26 COMMENT 'Manuel hesaplama için varsayılan gün sayısı',
  `oran` decimal(5,2) DEFAULT 0.00,
  `aciklama` text DEFAULT NULL,
  `sira` int(11) DEFAULT 0,
  `aktif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kategori` (`kategori`),
  KEY `idx_aktif` (`aktif`),
  KEY `idx_gecerlilik` (`gecerlilik_baslangic`,`gecerlilik_bitis`),
  KEY `idx_kod_gecerlilik` (`kod`,`gecerlilik_baslangic`,`gecerlilik_bitis`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bordro_personel`
--

DROP TABLE IF EXISTS `bordro_personel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bordro_personel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `donem_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `brut_maas` decimal(12,2) DEFAULT NULL,
  `sgk_isci` decimal(12,2) DEFAULT NULL COMMENT 'SGK Ä°ÅŸÃ§i PayÄ± (%14)',
  `issizlik_isci` decimal(12,2) DEFAULT NULL COMMENT 'Ä°ÅŸsizlik Ä°ÅŸÃ§i PayÄ± (%1)',
  `gelir_vergisi` decimal(12,2) DEFAULT NULL,
  `damga_vergisi` decimal(12,2) DEFAULT NULL COMMENT 'Damga Vergisi (%0.759)',
  `net_maas` decimal(12,2) DEFAULT NULL,
  `sgk_isveren` decimal(12,2) DEFAULT NULL COMMENT 'SGK Ä°ÅŸveren PayÄ± (%20.5)',
  `issizlik_isveren` decimal(12,2) DEFAULT NULL COMMENT 'Ä°ÅŸsizlik Ä°ÅŸveren PayÄ± (%2)',
  `toplam_maliyet` decimal(12,2) DEFAULT NULL COMMENT 'BrÃ¼t + Ä°ÅŸveren PaylarÄ±',
  `kumulatif_matrah` decimal(15,2) DEFAULT 0.00,
  `hesaplama_detay` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hesaplama_detay`)),
  `calisan_gun` int(11) DEFAULT 30,
  `fazla_mesai_saat` decimal(5,2) DEFAULT 0.00,
  `fazla_mesai_tutar` decimal(12,2) DEFAULT 0.00,
  `kesinti_tutar` decimal(12,2) DEFAULT 0.00,
  `prim_tutar` decimal(12,2) DEFAULT 0.00,
  `aciklama` text DEFAULT NULL,
  `hesaplama_tarihi` datetime DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `banka_odemesi` decimal(10,2) DEFAULT 0.00 COMMENT 'Bankadan yapýlan ödeme',
  `sodexo_odemesi` decimal(10,2) DEFAULT 0.00 COMMENT 'Sodexo/Kart ile ödeme',
  `sodexo_manuel` tinyint(1) DEFAULT 0,
  `diger_odeme` decimal(10,2) DEFAULT 0.00 COMMENT 'Diðer ödemeler',
  `elden_odeme` decimal(10,2) DEFAULT 0.00 COMMENT 'Elden ödeme (kalan)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_donem_personel` (`donem_id`,`personel_id`),
  KEY `idx_bordro_personel_donem` (`donem_id`),
  KEY `idx_bordro_personel_personel` (`personel_id`),
  CONSTRAINT `bordro_personel_ibfk_1` FOREIGN KEY (`donem_id`) REFERENCES `bordro_donemi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bordro_personel_ibfk_2` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bordro_vergi_dilimleri`
--

DROP TABLE IF EXISTS `bordro_vergi_dilimleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bordro_vergi_dilimleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `yil` int(11) NOT NULL,
  `dilim_no` int(11) NOT NULL,
  `alt_limit` decimal(15,2) NOT NULL,
  `ust_limit` decimal(15,2) DEFAULT NULL,
  `vergi_orani` decimal(5,2) NOT NULL,
  `aciklama` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_yil_dilim` (`yil`,`dilim_no`),
  KEY `idx_yil` (`yil`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cari`
--

DROP TABLE IF EXISTS `cari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `CariAdi` varchar(200) NOT NULL,
  `Telefon` varchar(20) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Adres` varchar(500) DEFAULT NULL,
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  `Aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cari_hareketleri`
--

DROP TABLE IF EXISTS `cari_hareketleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cari_hareketleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cari_id` int(11) NOT NULL,
  `islem_tarihi` datetime DEFAULT current_timestamp(),
  `belge_no` varchar(50) DEFAULT NULL,
  `aciklama` varchar(500) DEFAULT NULL,
  `borc` decimal(12,2) DEFAULT 0.00,
  `alacak` decimal(12,2) DEFAULT 0.00,
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cari` (`cari_id`),
  CONSTRAINT `fk_cari` FOREIGN KEY (`cari_id`) REFERENCES `cari` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `demirbas`
--

DROP TABLE IF EXISTS `demirbas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demirbas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_id` int(11) DEFAULT NULL,
  `demirbas_no` varchar(20) NOT NULL,
  `firma_id` int(11) NOT NULL DEFAULT 0,
  `demirbas_adi` varchar(255) NOT NULL DEFAULT '0',
  `edinme_tutari` varchar(20) NOT NULL,
  `marka` varchar(100) NOT NULL DEFAULT '0',
  `model` varchar(100) NOT NULL DEFAULT '0',
  `seri_no` varchar(100) DEFAULT NULL,
  `edinme_tarihi` varchar(20) NOT NULL,
  `aciklama` varchar(255) NOT NULL DEFAULT '0',
  `miktar` int(11) DEFAULT 1,
  `kalan_miktar` int(11) DEFAULT 1,
  `durum` enum('aktif','pasif','arizali','hurda','Kaskiye Teslim Edildi','serviste') DEFAULT 'aktif',
  `kayit_tarihi` varchar(20) NOT NULL,
  `kayit_yapan` int(11) NOT NULL DEFAULT 0,
  `otomatik_zimmet_is_emri` varchar(255) DEFAULT NULL COMMENT 'Bu iş emri sonucu geldiğinde personele otomatik zimmetlenir',
  `otomatik_iade_is_emri` varchar(255) DEFAULT NULL COMMENT 'Bu iş emri sonucu geldiğinde personelden otomatik iade alınır',
  `kaskiye_teslim_tarihi` varchar(255) DEFAULT NULL,
  `kaskiye_teslim_eden` int(11) DEFAULT NULL,
  `minimun_stok_uyari_miktari` int(11) DEFAULT 0,
  `otomatik_zimmet_is_emri_ids` varchar(50) DEFAULT NULL,
  `otomatik_iade_is_emri_ids` varchar(50) DEFAULT NULL,
  `otomatik_zimmetten_dus_is_emri_ids` varchar(50) DEFAULT NULL,
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kategori_id` (`kategori_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15557 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `demirbas_hareketler`
--

DROP TABLE IF EXISTS `demirbas_hareketler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demirbas_hareketler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `demirbas_id` int(11) NOT NULL COMMENT 'Hangi demirbaş',
  `personel_id` int(11) NOT NULL COMMENT 'Hangi personel',
  `zimmet_id` int(11) DEFAULT NULL,
  `hareket_tipi` enum('zimmet','iade','sarf','kayip','duzelme') NOT NULL COMMENT 'İşlem türü',
  `miktar` int(11) NOT NULL DEFAULT 1 COMMENT 'İşlem miktarı (her zaman pozitif)',
  `tarih` date NOT NULL COMMENT 'İşlem tarihi',
  `islem_id` varchar(100) DEFAULT NULL COMMENT 'Puantaj veya diğer kaynak referansı',
  `is_emri_sonucu` varchar(255) DEFAULT NULL COMMENT 'Hangi iş emri sonucundan geldi',
  `aciklama` text DEFAULT NULL COMMENT 'Açıklama/Not',
  `islem_yapan_id` int(11) DEFAULT NULL COMMENT 'İşlemi yapan kullanıcı',
  `kaynak` enum('manuel','puantaj_excel','puantaj_online','sistem') DEFAULT 'manuel' COMMENT 'İşlem kaynağı',
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demirbas` (`demirbas_id`),
  KEY `idx_personel` (`personel_id`),
  KEY `idx_tarih` (`tarih`),
  KEY `idx_hareket_tipi` (`hareket_tipi`),
  KEY `idx_islem_id` (`islem_id`),
  KEY `idx_zimmet_id` (`zimmet_id`),
  CONSTRAINT `demirbas_hareketler_ibfk_1` FOREIGN KEY (`demirbas_id`) REFERENCES `demirbas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `demirbas_hareketler_ibfk_2` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5483 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Demirbaş hareket geçmişi (zimmet, iade, sarf, kayıp)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `demirbas_kategorileri`
--

DROP TABLE IF EXISTS `demirbas_kategorileri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demirbas_kategorileri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_adi` varchar(100) NOT NULL,
  `kategori_aciklama` text DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `demirbas_servis_kayitlari`
--

DROP TABLE IF EXISTS `demirbas_servis_kayitlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demirbas_servis_kayitlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) DEFAULT NULL,
  `demirbas_id` int(11) NOT NULL,
  `teslim_eden_personel_id` int(11) DEFAULT NULL,
  `servis_tarihi` date NOT NULL,
  `iade_tarihi` date DEFAULT NULL,
  `giris_sayac` varchar(50) DEFAULT NULL,
  `cikis_sayac` varchar(50) DEFAULT NULL,
  `servis_adi` varchar(255) DEFAULT NULL,
  `servis_nedeni` text DEFAULT NULL,
  `yapilan_islemler` text DEFAULT NULL,
  `tutar` decimal(10,2) DEFAULT 0.00,
  `fatura_no` varchar(100) DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `demirbas_zimmet`
--

DROP TABLE IF EXISTS `demirbas_zimmet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demirbas_zimmet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `demirbas_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `teslim_tarihi` date NOT NULL,
  `teslim_miktar` int(11) DEFAULT 1,
  `iade_tarihi` date DEFAULT NULL,
  `iade_miktar` int(11) DEFAULT NULL,
  `durum` enum('teslim','iade','kayip','arizali') DEFAULT 'teslim',
  `aciklama` text DEFAULT NULL,
  `teslim_eden_id` int(11) DEFAULT NULL,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demirbas` (`demirbas_id`),
  KEY `idx_personel` (`personel_id`),
  KEY `idx_durum` (`durum`),
  CONSTRAINT `demirbas_zimmet_ibfk_1` FOREIGN KEY (`demirbas_id`) REFERENCES `demirbas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `demirbas_zimmet_ibfk_2` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1921 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `destek_konusmalar`
--

DROP TABLE IF EXISTS `destek_konusmalar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `destek_konusmalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `konu` varchar(255) DEFAULT NULL,
  `durum` enum('acik','beklemede','cozuldu','kapali') DEFAULT 'acik',
  `oncelik` enum('dusuk','normal','yuksek','acil') DEFAULT 'normal',
  `atanan_user_id` int(11) DEFAULT NULL COMMENT 'Hangi y??netici ilgileniyor',
  `son_mesaj_zamani` datetime DEFAULT NULL,
  `son_mesaj_onizleme` varchar(255) DEFAULT NULL,
  `okunmamis_personel` int(11) DEFAULT 0 COMMENT 'Personelin okumadigi mesaj sayisi',
  `okunmamis_yonetici` int(11) DEFAULT 0 COMMENT 'Yoneticinin okumadigi mesaj sayisi',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_personel_id` (`personel_id`),
  KEY `idx_durum` (`durum`),
  KEY `idx_atanan_user` (`atanan_user_id`),
  KEY `idx_son_mesaj` (`son_mesaj_zamani`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `destek_mesajlar`
--

DROP TABLE IF EXISTS `destek_mesajlar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `destek_mesajlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `konusma_id` int(11) NOT NULL,
  `gonderen_tip` enum('personel','yonetici','sistem') NOT NULL,
  `gonderen_id` int(11) NOT NULL,
  `mesaj` text NOT NULL,
  `dosya_url` varchar(500) DEFAULT NULL COMMENT 'Resim/dosya eki',
  `dosya_tip` varchar(50) DEFAULT NULL COMMENT 'image/jpeg, image/png vs.',
  `okundu` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_konusma_id` (`konusma_id`),
  KEY `idx_gonderen` (`gonderen_tip`,`gonderen_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `destek_mesajlar_ibfk_1` FOREIGN KEY (`konusma_id`) REFERENCES `destek_konusmalar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `duyurular`
--

DROP TABLE IF EXISTS `duyurular`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `duyurular` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) DEFAULT NULL,
  `baslik` varchar(255) DEFAULT NULL,
  `icerik` text DEFAULT NULL,
  `resim` varchar(500) DEFAULT NULL,
  `hedef_sayfa` varchar(255) DEFAULT NULL,
  `ana_sayfada_goster` tinyint(1) DEFAULT 0,
  `pwa_goster` tinyint(1) DEFAULT 0,
  `alici_tipi` varchar(50) DEFAULT NULL,
  `alici_ids` text DEFAULT NULL,
  `tarih` datetime DEFAULT NULL,
  `etkinlik_tarihi` datetime DEFAULT NULL,
  `silinme_tarihi` datetime DEFAULT NULL,
  `durum` varchar(20) DEFAULT 'Yayında',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `endeks_okuma`
--

DROP TABLE IF EXISTS `endeks_okuma`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `endeks_okuma` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `islem_id` varchar(100) DEFAULT NULL,
  `personel_id` int(11) DEFAULT NULL,
  `ekip_kodu_id` int(11) DEFAULT NULL,
  `firma_id` int(11) DEFAULT NULL,
  `bolge` varchar(255) DEFAULT NULL,
  `defter` varchar(255) DEFAULT NULL,
  `mahalle` varchar(255) DEFAULT NULL,
  `abone_sayisi` int(11) DEFAULT NULL,
  `kullanici_adi` varchar(255) DEFAULT NULL,
  `sarfiyat` decimal(15,2) DEFAULT NULL,
  `sayac_durum` varchar(255) DEFAULT NULL,
  `ort_sarfiyat_gunluk` decimal(15,2) DEFAULT NULL,
  `tahakkuk` decimal(15,2) DEFAULT NULL,
  `ort_tahakkuk_gunluk` decimal(15,2) DEFAULT NULL,
  `okunan_gun_sayisi` int(11) DEFAULT NULL,
  `okunan_abone_sayisi` int(11) DEFAULT NULL,
  `ort_okunan_abone_sayisi_gunluk` decimal(15,2) DEFAULT NULL,
  `okuma_performansi` decimal(15,2) DEFAULT NULL,
  `tarih` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_endeks_okuma_islem_id` (`islem_id`),
  KEY `idx_endeks_okuma_tarih_firma` (`tarih`,`firma_id`),
  KEY `idx_endeks_okuma_silinme` (`silinme_tarihi`)
) ENGINE=InnoDB AUTO_INCREMENT=122010 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evrak_takip`
--

DROP TABLE IF EXISTS `evrak_takip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evrak_takip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `evrak_tipi` enum('gelen','giden') NOT NULL DEFAULT 'gelen',
  `tarih` date NOT NULL,
  `evrak_no` varchar(50) DEFAULT NULL,
  `konu` varchar(255) DEFAULT NULL,
  `kurum_adi` varchar(255) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `personel_id` int(11) DEFAULT NULL COMMENT 'Zimmetlendiği kişi',
  `dosya_yolu` varchar(555) DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_firma` (`firma_id`),
  KEY `idx_personel` (`personel_id`),
  KEY `idx_tarih` (`tarih`),
  KEY `idx_evrak_tipi` (`evrak_tipi`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `firmalar`
--

DROP TABLE IF EXISTS `firmalar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `firmalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_adi` varchar(255) NOT NULL DEFAULT '0',
  `firma_kodu` int(11) NOT NULL DEFAULT 0,
  `vergi_no` varchar(255) NOT NULL DEFAULT '0',
  `vergi_dairesi` varchar(255) NOT NULL DEFAULT '0',
  `telefon` varchar(255) NOT NULL DEFAULT '0',
  `adres` varchar(255) NOT NULL DEFAULT '0',
  `varsayilan_mi` int(11) NOT NULL DEFAULT 0,
  `kayit_tarihi` varchar(20) NOT NULL,
  `kayit_yapan` int(11) NOT NULL DEFAULT 0,
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT 0,
  `firma_unvan` varchar(255) DEFAULT NULL,
  `firma_iban` varchar(26) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gelir_gider`
--

DROP TABLE IF EXISTS `gelir_gider`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gelir_gider` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` int(11) NOT NULL DEFAULT 0,
  `tarih` datetime DEFAULT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `hesap_adi` varchar(255) DEFAULT '0',
  `tutar` varchar(50) NOT NULL DEFAULT '0',
  `aciklama` varchar(255) DEFAULT '0',
  `kayit_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) NOT NULL DEFAULT 0,
  `kayit_yapan` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1522 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gelir_gider_turu`
--

DROP TABLE IF EXISTS `gelir_gider_turu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gelir_gider_turu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipi` int(11) NOT NULL DEFAULT 0,
  `tur_adi` varchar(50) NOT NULL DEFAULT '0',
  `aciklama` varchar(255) NOT NULL DEFAULT '0',
  `kayit_tarihi` varchar(20) NOT NULL,
  `kayit_yapan` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gorev_listeleri`
--

DROP TABLE IF EXISTS `gorev_listeleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gorev_listeleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `baslik` varchar(255) NOT NULL,
  `sira` int(11) DEFAULT 0,
  `renk` varchar(7) DEFAULT NULL,
  `olusturan_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_firma_id` (`firma_id`),
  KEY `idx_sira` (`sira`),
  KEY `idx_olusturan` (`olusturan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gorevler`
--

DROP TABLE IF EXISTS `gorevler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gorevler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `liste_id` int(11) NOT NULL,
  `firma_id` int(11) NOT NULL,
  `baslik` varchar(500) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `tarih` date DEFAULT NULL,
  `saat` time DEFAULT NULL,
  `tamamlandi` tinyint(1) DEFAULT 0,
  `bildirim_gonderildi` tinyint(1) DEFAULT 0,
  `on_bildirim_gonderildi` tinyint(1) DEFAULT 0,
  `tam_vakit_bildirim_gonderildi` tinyint(1) DEFAULT 0,
  `tamamlanma_tarihi` datetime DEFAULT NULL,
  `sira` int(11) DEFAULT 0,
  `yildizli` tinyint(1) DEFAULT 0,
  `yineleme_sikligi` int(11) DEFAULT NULL,
  `yineleme_birimi` enum('gun','hafta','ay','yil') DEFAULT NULL,
  `yineleme_gunleri` varchar(50) DEFAULT NULL,
  `yineleme_baslangic` date DEFAULT NULL,
  `yineleme_bitis_tipi` enum('asla','tarih','adet') DEFAULT NULL,
  `yineleme_bitis_tarihi` date DEFAULT NULL,
  `yineleme_bitis_adet` int(11) DEFAULT NULL,
  `olusturan_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `gorev_kullanicilari` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_liste_id` (`liste_id`),
  KEY `idx_firma_id` (`firma_id`),
  KEY `idx_tamamlandi` (`tamamlandi`),
  KEY `idx_sira` (`sira`),
  KEY `idx_tarih` (`tarih`),
  KEY `idx_gorevler_bildirim` (`tarih`,`tamamlandi`,`bildirim_gonderildi`),
  CONSTRAINT `gorevler_ibfk_1` FOREIGN KEY (`liste_id`) REFERENCES `gorev_listeleri` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hakedis_donemleri`
--

DROP TABLE IF EXISTS `hakedis_donemleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hakedis_donemleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sozlesme_id` int(11) NOT NULL,
  `hakedis_no` int(11) NOT NULL,
  `hakedis_tarihi_ay` int(11) NOT NULL,
  `hakedis_tarihi_yil` int(11) NOT NULL,
  `is_yapilan_ayin_son_gunu` date DEFAULT NULL,
  `tutanak_tasdik_tarihi` date DEFAULT NULL,
  `temel_endeks_ayi` varchar(50) DEFAULT NULL,
  `guncel_endeks_ayi` varchar(50) DEFAULT NULL,
  `a1_katsayisi` decimal(20,6) DEFAULT NULL,
  `asgari_ucret_temel` decimal(20,6) DEFAULT NULL,
  `asgari_ucret_guncel` decimal(20,6) DEFAULT NULL,
  `ekstra_parametreler` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ekstra_parametreler`)),
  `b1_katsayisi` decimal(20,6) DEFAULT NULL,
  `motorin_temel` decimal(20,6) DEFAULT NULL,
  `motorin_guncel` decimal(20,6) DEFAULT NULL,
  `b2_katsayisi` decimal(20,6) DEFAULT NULL,
  `ufe_genel_temel` decimal(20,6) DEFAULT NULL,
  `ufe_genel_guncel` decimal(20,6) DEFAULT NULL,
  `c_katsayisi` decimal(20,6) DEFAULT NULL,
  `makine_ekipman_temel` decimal(20,6) DEFAULT NULL,
  `makine_ekipman_guncel` decimal(20,6) DEFAULT NULL,
  `sabit_katsayi_b` decimal(10,5) DEFAULT 0.90000,
  `avans_mahsubu` decimal(15,2) DEFAULT 0.00,
  `kdv_orani` decimal(5,2) DEFAULT 20.00,
  `tevkifat_orani` varchar(20) DEFAULT '4/10',
  `damga_vergisi_orani` decimal(10,5) DEFAULT 0.00948,
  `sozlesme_karar_pulu_orani` decimal(10,5) DEFAULT 0.00569,
  `durum` enum('taslak','onaylandi','tamamlandi') DEFAULT 'taslak',
  `hakedi_tutari` decimal(20,6) DEFAULT NULL,
  `onceki_hakedis_tutari` decimal(20,6) DEFAULT NULL,
  `asgari_farki_dahil_edilsin` int(11) DEFAULT 0,
  `olusturan_personel_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sozlesme_id` (`sozlesme_id`),
  CONSTRAINT `hakedis_donemleri_ibfk_1` FOREIGN KEY (`sozlesme_id`) REFERENCES `hakedis_sozlesmeler` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hakedis_kalemleri`
--

DROP TABLE IF EXISTS `hakedis_kalemleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hakedis_kalemleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sozlesme_id` int(11) NOT NULL,
  `poz_no` varchar(500) NOT NULL,
  `kalem_adi` varchar(500) NOT NULL,
  `birim` varchar(50) NOT NULL,
  `miktari` decimal(15,2) DEFAULT 0.00,
  `teklif_edilen_birim_fiyat` decimal(15,2) DEFAULT 0.00,
  `agirlik_orani` decimal(10,5) DEFAULT 0.00000,
  `sira` int(11) DEFAULT 0,
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sozlesme_id` (`sozlesme_id`),
  CONSTRAINT `hakedis_kalemleri_ibfk_1` FOREIGN KEY (`sozlesme_id`) REFERENCES `hakedis_sozlesmeler` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hakedis_miktarlari`
--

DROP TABLE IF EXISTS `hakedis_miktarlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hakedis_miktarlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hakedis_donem_id` int(11) NOT NULL,
  `kalem_id` int(11) NOT NULL,
  `bolge_id` int(11) DEFAULT NULL,
  `bolge_adi` varchar(50) DEFAULT NULL,
  `miktar` decimal(15,2) DEFAULT 0.00,
  `onceki_miktar` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `hakedis_donem_id` (`hakedis_donem_id`),
  KEY `kalem_id` (`kalem_id`),
  CONSTRAINT `hakedis_miktarlari_ibfk_1` FOREIGN KEY (`hakedis_donem_id`) REFERENCES `hakedis_donemleri` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hakedis_miktarlari_ibfk_2` FOREIGN KEY (`kalem_id`) REFERENCES `hakedis_kalemleri` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hakedis_sozlesmeler`
--

DROP TABLE IF EXISTS `hakedis_sozlesmeler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hakedis_sozlesmeler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `idare_adi` varchar(255) NOT NULL,
  `idare_baskanlik_adi` varchar(255) NOT NULL,
  `isin_adi` varchar(500) NOT NULL,
  `isin_yuklenicisi` varchar(255) NOT NULL,
  `ihale_kayit_no` varchar(100) DEFAULT NULL,
  `yuklenici_adres` text DEFAULT NULL,
  `yuklenici_tel` varchar(50) DEFAULT NULL,
  `kesif_bedeli` decimal(15,6) DEFAULT NULL,
  `ihale_tenzilati` decimal(10,6) DEFAULT NULL,
  `sozlesme_bedeli` decimal(15,6) DEFAULT NULL,
  `sozlesme_tarihi` date DEFAULT NULL,
  `isin_bitecegi_tarih` date DEFAULT NULL,
  `ihale_tarihi` date DEFAULT NULL,
  `yer_teslim_tarihi` date DEFAULT NULL,
  `isin_suresi` int(11) DEFAULT NULL,
  `kontrol_teskilati` text DEFAULT NULL,
  `idare_onaylayan` varchar(255) DEFAULT NULL,
  `idare_onaylayan_unvan` varchar(255) DEFAULT NULL,
  `tasvip_eden` varchar(255) DEFAULT NULL,
  `tasvip_eden_unvan` varchar(255) DEFAULT NULL,
  `durum` enum('aktif','pasif','tamamlandi') DEFAULT 'aktif',
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `a1_katsayisi` decimal(20,6) DEFAULT NULL,
  `b1_katsayisi` decimal(20,6) DEFAULT NULL,
  `b2_katsayisi` decimal(20,6) DEFAULT NULL,
  `c_katsayisi` decimal(20,6) DEFAULT NULL,
  `asgari_ucret_temel` decimal(20,6) DEFAULT NULL,
  `motorin_temel` decimal(20,6) DEFAULT NULL,
  `ufe_genel_temel` decimal(20,6) DEFAULT NULL,
  `makine_ekipman_temel` decimal(20,6) DEFAULT NULL,
  `kdv_orani` decimal(5,2) DEFAULT 20.00,
  `tevkifat_orani` varchar(20) DEFAULT '4/10',
  `temel_endeks_ay` int(11) DEFAULT NULL,
  `temel_endeks_yil` int(11) DEFAULT NULL,
  `yuzde_yirmi_fazla_is` text DEFAULT NULL,
  `son_sure_uzatimi` text DEFAULT NULL,
  `gecici_kabul_tarihi` date DEFAULT NULL,
  `gecici_kabul_itibar_tarihi` date DEFAULT NULL,
  `gecici_kabul_onanma_tarihi` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `il`
--

DROP TABLE IF EXISTS `il`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `il` (
  `id` tinyint(4) NOT NULL DEFAULT 0,
  `city_name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `ad` (`city_name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin5 COLLATE=latin5_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ilce`
--

DROP TABLE IF EXISTS `ilce`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ilce` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `il_id` tinyint(4) NOT NULL,
  `ilce_adi` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=959 DEFAULT CHARSET=latin5 COLLATE=latin5_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `izin_onay_seviyeleri`
--

DROP TABLE IF EXISTS `izin_onay_seviyeleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `izin_onay_seviyeleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seviye_no` int(11) NOT NULL,
  `rol_adi` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seviye_no` (`seviye_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `izin_onaylari`
--

DROP TABLE IF EXISTS `izin_onaylari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `izin_onaylari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `izin_id` int(11) NOT NULL,
  `onaylayan_id` int(11) NOT NULL,
  `seviye_no` int(11) DEFAULT 1,
  `onay_durumu` enum('Beklemede','Onaylandı','Reddedildi') DEFAULT 'Beklemede',
  `onay_tarihi` datetime DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `izin_id` (`izin_id`,`seviye_no`),
  KEY `fk_onay_user` (`onaylayan_id`),
  CONSTRAINT `fk_onay_izin` FOREIGN KEY (`izin_id`) REFERENCES `personel_izinleri` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_onay_user` FOREIGN KEY (`onaylayan_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=310 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_onay_sonuc` AFTER UPDATE ON `izin_onaylari` FOR EACH ROW UPDATE personel_izinleri p
SET p.onay_durumu =
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM izin_onaylari io
            WHERE io.izin_id = NEW.izin_id
              AND io.onay_durumu = 'Reddedildi'
        ) THEN 'Reddedildi'

        WHEN NOT EXISTS (
            SELECT 1
            FROM izin_onaylari io
            WHERE io.izin_id = NEW.izin_id
              AND io.onay_durumu = 'Beklemede'
        ) THEN 'Onaylandı'

        ELSE p.onay_durumu
    END
WHERE p.id = NEW.izin_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `kacak_kontrol`
--

DROP TABLE IF EXISTS `kacak_kontrol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kacak_kontrol` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `personel_ids` varchar(50) DEFAULT NULL,
  `tarih` date NOT NULL,
  `ekip_adi` varchar(255) DEFAULT NULL,
  `sayi` int(11) DEFAULT 0,
  `aciklama` text DEFAULT NULL,
  `islem_id` varchar(100) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_islem` (`islem_id`),
  KEY `idx_firma` (`firma_id`),
  KEY `idx_tarih` (`tarih`)
) ENGINE=InnoDB AUTO_INCREMENT=194 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kasa_hareketleri`
--

DROP TABLE IF EXISTS `kasa_hareketleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kasa_hareketleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hareket_tipi` enum('gelir','gider') NOT NULL COMMENT 'Hareketin gelir mi gider mi olduğunu belirtir',
  `kasa_id` bigint(20) NOT NULL DEFAULT 0,
  `kategori_id` int(11) DEFAULT NULL COMMENT 'Gelir/gider kategorisi (isteğe bağlı, ayrı bir kategoriler tablosuna bağlanabilir)',
  `aciklama` varchar(255) NOT NULL COMMENT 'Hareketin detayı, açıklaması',
  `tutar` decimal(15,2) NOT NULL COMMENT 'Hareketin parasal değeri (pozitif olmalı, hareket_tipi belirler)',
  `para_birimi` varchar(3) NOT NULL DEFAULT 'TRY' COMMENT 'Kullanılan para birimi (örn: TRY, USD, EUR)',
  `islem_tarihi` date NOT NULL COMMENT 'Hareketin gerçekleştiği tarih',
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Bu kaydın veritabanına eklendiği zaman damgası',
  `odeme_yontemi_id` int(11) DEFAULT NULL COMMENT 'Ödeme yöntemi (nakit, kredi kartı vb. için ayrı bir tabloya bağlanabilir)',
  `cari_id` int(11) DEFAULT NULL COMMENT 'İlişkili cari (müşteri, tedarikçi vb. için ayrı bir cari tablosuna bağlanabilir)',
  `kullanici_id` int(11) DEFAULT NULL COMMENT 'Bu kaydı giren kullanıcı (ayrı bir kullanıcılar tablosuna bağlanabilir)',
  `referans_no` varchar(100) DEFAULT NULL COMMENT 'Fatura no, makbuz no gibi bir referans numarası (isteğe bağlı)',
  `ek_notlar` text DEFAULT NULL COMMENT 'Ekstra notlar veya detaylar için alan',
  `aktif` tinyint(1) DEFAULT 1 COMMENT 'Kaydın aktif olup olmadığını belirtir (1: Aktif, 0: Silinmiş/Pasif)',
  PRIMARY KEY (`id`),
  KEY `fk_kullanici` (`kullanici_id`),
  KEY `idx_hareket_tipi` (`hareket_tipi`),
  KEY `idx_islem_tarihi` (`islem_tarihi`),
  CONSTRAINT `fk_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gelir ve gider hareketlerini takip eden kasa tablosu';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kasalar`
--

DROP TABLE IF EXISTS `kasalar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kasalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` bigint(20) DEFAULT NULL,
  `sube_id` bigint(20) DEFAULT NULL,
  `kasa_kodu` varchar(20) DEFAULT NULL COMMENT 'Kasa için benzersiz bir kod (isteğe bağlı)',
  `kasa_adi` varchar(100) NOT NULL COMMENT 'Kasanın adı (örn: Merkez Kasa, USD Kasası, Banka X Hesabı)',
  `kasa_tipi` enum('nakit','banka','kredi_karti','sanal_pos','diger') NOT NULL DEFAULT 'nakit' COMMENT 'Kasanın türü',
  `para_birimi` varchar(3) NOT NULL DEFAULT 'TRY' COMMENT 'Bu kasanın ana para birimi',
  `hesap_no` varchar(55) DEFAULT NULL,
  `varsayilan_mi` int(11) NOT NULL DEFAULT 0,
  `aciklama` text DEFAULT NULL COMMENT 'Kasa ile ilgili ek açıklamalar',
  `baslangic_bakiyesi` decimal(15,2) DEFAULT 0.00 COMMENT 'Kasa ilk oluşturulduğundaki bakiye (opsiyonel)',
  `aktif` tinyint(1) DEFAULT 1 COMMENT 'Kasanın aktif olup olmadığını belirtir (1: Aktif, 0: Pasif)',
  `olusturulma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `silinme_tarihi` timestamp NULL DEFAULT NULL,
  `guncellenme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kasa_kodu` (`kasa_kodu`,`owner_id`) USING BTREE,
  KEY `idx_kasa_adi` (`kasa_adi`),
  KEY `idx_kasa_tipi` (`kasa_tipi`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Farklı fiziksel veya mantıksal kasaları tanımlar';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maliyetler`
--

DROP TABLE IF EXISTS `maliyetler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maliyetler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `yil` int(4) NOT NULL,
  `ay` int(2) NOT NULL,
  `departman` varchar(255) NOT NULL,
  `personel_maas_gideri` decimal(15,2) DEFAULT 0.00,
  `personel_ssk_vergi_gideri` decimal(15,2) DEFAULT 0.00,
  `arac_kira_gideri` decimal(15,2) DEFAULT 0.00,
  `arac_yakit_gideri` decimal(15,2) DEFAULT 0.00,
  `diger_giderler` decimal(15,2) DEFAULT 0.00,
  `olusturan_id` int(11) DEFAULT NULL,
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  `guncellenme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `firma_yil_ay_departman` (`firma_id`,`yil`,`ay`,`departman`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `manuel_giderler`
--

DROP TABLE IF EXISTS `manuel_giderler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manuel_giderler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `gider_tarihi` date NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `alt_kategori` varchar(100) DEFAULT NULL,
  `tutar` decimal(12,2) NOT NULL,
  `aciklama` varchar(255) DEFAULT NULL,
  `belge_no` varchar(100) DEFAULT NULL,
  `olusturan_kullanici_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_firma_tarih` (`firma_id`,`gider_tarihi`),
  KEY `idx_firma_kategori` (`firma_id`,`kategori`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_name` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT 0,
  `group_name` varchar(50) DEFAULT NULL,
  `group_order` int(11) NOT NULL DEFAULT 1,
  `menu_link` varchar(50) DEFAULT NULL,
  `menu_icon` varchar(50) DEFAULT NULL,
  `menu_order` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `is_menu` tinyint(4) NOT NULL DEFAULT 1,
  `is_authorized` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=920 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mesaj_log`
--

DROP TABLE IF EXISTS `mesaj_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mesaj_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) DEFAULT 0,
  `type` enum('email','sms','push') NOT NULL,
  `sender` varchar(255) DEFAULT NULL,
  `recipients` text NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `attachments` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_firma_id` (`firma_id`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nobet_bildirim_loglari`
--

DROP TABLE IF EXISTS `nobet_bildirim_loglari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nobet_bildirim_loglari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nobet_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `bildirim_tipi` enum('push','sms','email') NOT NULL,
  `bildirim_turu` varchar(50) DEFAULT NULL,
  `baslik` varchar(255) DEFAULT NULL,
  `mesaj` text DEFAULT NULL,
  `gonderim_durumu` enum('bekliyor','gonderildi','basarisiz') DEFAULT 'gonderildi',
  `gonderim_tarihi` datetime DEFAULT NULL,
  `bildirim_zamani` datetime NOT NULL,
  `durum` enum('gonderildi','basarisiz') DEFAULT 'gonderildi',
  `hata_mesaji` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nobet_id` (`nobet_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nobet_degisim_talepleri`
--

DROP TABLE IF EXISTS `nobet_degisim_talepleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nobet_degisim_talepleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nobet_id` int(11) NOT NULL COMMENT 'DeÄŸiÅŸtirilmek istenen nÃ¶bet',
  `talep_eden_id` int(11) NOT NULL COMMENT 'Talebi oluÅŸturan personel',
  `talep_edilen_id` int(11) NOT NULL COMMENT 'NÃ¶beti devralmasÄ± istenen personel',
  `aciklama` text DEFAULT NULL,
  `durum` enum('beklemede','personel_onayladi','onaylandi','reddedildi','iptal') DEFAULT 'beklemede',
  `talep_tarihi` datetime NOT NULL,
  `personel_onay_tarihi` datetime DEFAULT NULL,
  `amir_onaylayan_id` int(11) DEFAULT NULL,
  `amir_onay_tarihi` datetime DEFAULT NULL,
  `red_nedeni` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nobet_id` (`nobet_id`),
  KEY `idx_talep_eden_id` (`talep_eden_id`),
  KEY `idx_talep_edilen_id` (`talep_edilen_id`),
  KEY `idx_durum` (`durum`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nobet_devir_kayitlari`
--

DROP TABLE IF EXISTS `nobet_devir_kayitlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nobet_devir_kayitlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nobet_id` int(11) NOT NULL,
  `devralan_personel_id` int(11) NOT NULL,
  `devir_zamani` datetime NOT NULL,
  `konum_lat` decimal(10,8) DEFAULT NULL,
  `konum_lng` decimal(11,8) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nobet_id` (`nobet_id`),
  KEY `idx_devralan_personel_id` (`devralan_personel_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nobetler`
--

DROP TABLE IF EXISTS `nobetler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nobetler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `nobet_tarihi` date NOT NULL,
  `baslangic_saati` time DEFAULT '18:00:00',
  `bitis_saati` time DEFAULT '08:00:00',
  `nobet_tipi` enum('standart','hafta_sonu','resmi_tatil','ozel') DEFAULT 'standart',
  `durum` enum('planli','devir_alindi','tamamlandi','iptal','mazeret_bildirildi','talep_edildi','reddedildi','onaylandi') DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `mazeret_aciklama` text DEFAULT NULL,
  `mazeret_tarihi` datetime DEFAULT NULL,
  `olusturan_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT NULL,
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `bildirim_gonderildi` tinyint(1) DEFAULT 0,
  `bildirim_tarihi` datetime DEFAULT NULL,
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_firma_id` (`firma_id`),
  KEY `idx_personel_id` (`personel_id`),
  KEY `idx_nobet_tarihi` (`nobet_tarihi`),
  KEY `idx_durum` (`durum`),
  KEY `idx_bildirim_gonderildi` (`bildirim_gonderildi`)
) ENGINE=InnoDB AUTO_INCREMENT=280 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `auth_name` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `group_name` varchar(50) DEFAULT NULL,
  `permission_level` int(11) NOT NULL,
  `is_active` int(11) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=920 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel`
--

DROP TABLE IF EXISTS `personel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL DEFAULT 0,
  `tc_kimlik_no` char(11) NOT NULL,
  `adi_soyadi` varchar(150) NOT NULL,
  `anne_adi` varchar(100) DEFAULT NULL,
  `baba_adi` varchar(100) DEFAULT NULL,
  `dogum_tarihi` date DEFAULT NULL,
  `dogum_yeri_il` varchar(50) DEFAULT NULL,
  `dogum_yeri_ilce` varchar(50) DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `cinsiyet` enum('Erkek','Kadın') DEFAULT NULL,
  `medeni_durum` enum('Evli','Bekar') DEFAULT NULL,
  `esi_calisiyor_mu` enum('Evet','Hayır') DEFAULT NULL,
  `seyahat_engeli` enum('Var','Yok') DEFAULT NULL,
  `ehliyet_sinifi` varchar(10) DEFAULT NULL,
  `kan_grubu` varchar(5) DEFAULT NULL,
  `cep_telefonu` varchar(15) DEFAULT NULL,
  `sifre` varchar(255) DEFAULT NULL,
  `kaski_kullanici_adi` varchar(255) DEFAULT NULL,
  `kaski_sifre` varchar(255) DEFAULT NULL,
  `cep_telefonu_2` varchar(15) DEFAULT NULL,
  `email_adresi` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `ayakkabi_numarasi` varchar(5) DEFAULT NULL,
  `ust_beden_no` varchar(5) DEFAULT NULL,
  `alt_beden_no` varchar(5) DEFAULT NULL,
  `referans_adi_soyadi` varchar(150) DEFAULT NULL,
  `referans_telefonu` varchar(15) DEFAULT NULL,
  `referans_firma` varchar(150) DEFAULT NULL,
  `acil_kisi_adi_soyadi` varchar(150) DEFAULT NULL,
  `acil_kisi_yakinlik` varchar(50) DEFAULT NULL,
  `acil_kisi_telefonu` varchar(15) DEFAULT NULL,
  `aktif_mi` tinyint(1) DEFAULT 1,
  `saha_takibi` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Saha takibi yapılacak mı?',
  `bes_kesintisi_varmi` tinyint(1) DEFAULT 1,
  `resim_yolu` varchar(255) DEFAULT NULL,
  `ise_giris_tarihi` date NOT NULL,
  `isten_cikis_tarihi` date DEFAULT NULL,
  `isten_ayrilis_belge_yolu` varchar(1000) DEFAULT NULL,
  `sgk_no` varchar(20) DEFAULT NULL,
  `sgk_yapilan_firma` varchar(150) DEFAULT NULL,
  `calisilan_firma` varchar(150) NOT NULL,
  `calisilan_proje` varchar(150) NOT NULL,
  `personel_sinifi` enum('Beyaz Yaka','Mavi Yaka') DEFAULT NULL,
  `departman` varchar(255) DEFAULT NULL,
  `gorev` varchar(100) DEFAULT NULL,
  `arac_kullanim` varchar(100) DEFAULT NULL,
  `ekip_no` int(11) DEFAULT NULL,
  `ekip_bolge` varchar(100) DEFAULT NULL,
  `dss_sinifi_alt` varchar(50) DEFAULT NULL,
  `banka` varchar(255) DEFAULT NULL,
  `iban_numarasi` varchar(34) DEFAULT NULL,
  `ek_odeme_iban_numarasi` varchar(34) DEFAULT NULL,
  `gorunum_modulleri` text DEFAULT NULL,
  `disardan_sigortali` tinyint(4) DEFAULT 0,
  `maas_durumu` enum('Brüt','Net','Prim Usülü','Maaş Hesaplanmayan') DEFAULT NULL,
  `maas_tutari` decimal(10,2) DEFAULT NULL,
  `gunluk_ucret` decimal(10,2) DEFAULT NULL,
  `sodexo` decimal(10,2) DEFAULT NULL,
  `sodexo_kart_no` varchar(50) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `tc_kimlik_no` (`tc_kimlik_no`),
  KEY `fk_personel_ekip` (`ekip_no`),
  CONSTRAINT `fk_personel_ekip` FOREIGN KEY (`ekip_no`) REFERENCES `tanimlamalar` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=174 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_avanslari`
--

DROP TABLE IF EXISTS `personel_avanslari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_avanslari` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `tutar` decimal(10,2) NOT NULL COMMENT 'Avans tutarı',
  `odeme_sekli` enum('tek','taksit') NOT NULL DEFAULT 'tek',
  `aciklama` text DEFAULT NULL,
  `onay_aciklama` text DEFAULT NULL,
  `onaylayan_id` int(11) DEFAULT NULL,
  `kayit_yapan` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  `durum` enum('beklemede','onaylandi','reddedildi','odendi') NOT NULL DEFAULT 'beklemede',
  `talep_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `olusturma_tarihi` datetime DEFAULT NULL,
  `silinme_tarihi` datetime DEFAULT NULL COMMENT 'Soft delete',
  `silen_kullanici` int(11) DEFAULT NULL,
  `silinme_aciklama` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_personel_id` (`personel_id`),
  KEY `idx_durum` (`durum`),
  CONSTRAINT `fk_avans_personel` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_bildirim_durumu`
--

DROP TABLE IF EXISTS `personel_bildirim_durumu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_bildirim_durumu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `mesaj_log_id` int(11) NOT NULL,
  `okundu` tinyint(1) DEFAULT 0,
  `okunma_tarihi` datetime DEFAULT NULL,
  `silindi` tinyint(1) DEFAULT 0,
  `silme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_personel_mesaj` (`personel_id`,`mesaj_log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_ek_odemeler`
--

DROP TABLE IF EXISTS `personel_ek_odemeler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_ek_odemeler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `donem_id` int(11) DEFAULT NULL,
  `tur` varchar(50) NOT NULL,
  `tekrar_tipi` enum('tek_sefer','surekli') NOT NULL DEFAULT 'tek_sefer',
  `baslangic_donemi` date DEFAULT NULL,
  `bitis_donemi` date DEFAULT NULL,
  `hesaplama_tipi` enum('sabit','oran_net','oran_brut') NOT NULL DEFAULT 'sabit',
  `oran` decimal(5,2) DEFAULT NULL,
  `parametre_id` int(11) DEFAULT NULL,
  `ana_odeme_id` int(11) DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `durum` enum('beklemede','onaylandi','reddedildi') DEFAULT 'onaylandi',
  `tutar` decimal(10,2) NOT NULL,
  `tarih` varchar(50) NOT NULL DEFAULT '',
  `aciklama` text DEFAULT NULL,
  `kayit_yapan` int(11) DEFAULT NULL,
  `silinme_tarihi` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `personel_id` (`personel_id`),
  KEY `donem` (`donem_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=19561 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_ekip_gecmisi`
--

DROP TABLE IF EXISTS `personel_ekip_gecmisi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_ekip_gecmisi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `ekip_kodu_id` int(11) NOT NULL,
  `baslangic_tarihi` date NOT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `firma_id` int(11) NOT NULL,
  `ekip_sefi_mi` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_personel_firma` (`personel_id`,`firma_id`),
  KEY `idx_tarih` (`baslangic_tarihi`,`bitis_tarihi`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_evraklar`
--

DROP TABLE IF EXISTS `personel_evraklar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_evraklar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `evrak_adi` varchar(255) NOT NULL COMMENT 'Evrak baÅŸlÄ±ÄŸÄ±/adÄ±',
  `evrak_turu` varchar(50) DEFAULT NULL COMMENT 'SÃ¶zleÅŸme, Kimlik, Diploma, CV, vb.',
  `dosya_adi` varchar(255) NOT NULL COMMENT 'Sunucudaki dosya adÄ±',
  `orijinal_dosya_adi` varchar(255) DEFAULT NULL COMMENT 'YÃ¼klenen orijinal dosya adÄ±',
  `dosya_boyutu` int(11) DEFAULT 0 COMMENT 'Dosya boyutu (byte)',
  `dosya_tipi` varchar(50) DEFAULT NULL COMMENT 'application/pdf, image/jpeg, vb.',
  `aciklama` text DEFAULT NULL COMMENT 'Evrak hakkÄ±nda notlar',
  `yukleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `yukleyen_id` int(11) DEFAULT NULL COMMENT 'EvrakÄ± yÃ¼kleyen kullanÄ±cÄ±',
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_personel` (`personel_id`),
  KEY `idx_evrak_turu` (`evrak_turu`),
  KEY `idx_aktif` (`aktif`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_giris_loglari`
--

DROP TABLE IF EXISTS `personel_giris_loglari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_giris_loglari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) DEFAULT 0,
  `personel_id` int(11) NOT NULL,
  `ip_adresi` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `cihaz` varchar(100) DEFAULT NULL,
  `tarayici` varchar(100) DEFAULT NULL,
  `isletim_sistemi` varchar(100) DEFAULT NULL,
  `giris_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_gorev_gecmisi`
--

DROP TABLE IF EXISTS `personel_gorev_gecmisi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_gorev_gecmisi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `departman` varchar(255) DEFAULT NULL,
  `gorev` varchar(255) DEFAULT NULL,
  `maas_durumu` varchar(50) NOT NULL,
  `maas_tutari` decimal(12,2) DEFAULT 0.00,
  `baslangic_tarihi` date NOT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `olusturan_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_hareketleri`
--

DROP TABLE IF EXISTS `personel_hareketleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_hareketleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL COMMENT 'İşlemi yapan personel',
  `islem_tipi` enum('BASLA','BITIR') NOT NULL COMMENT 'İşlem türü',
  `zaman` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Sunucu saati',
  `konum_enlem` decimal(10,7) NOT NULL COMMENT 'GPS Latitude',
  `konum_boylam` decimal(10,7) NOT NULL COMMENT 'GPS Longitude',
  `konum_hassasiyeti` decimal(10,2) DEFAULT NULL COMMENT 'GPS doğruluk (metre)',
  `cihaz_bilgisi` varchar(500) DEFAULT NULL COMMENT 'User agent',
  `ip_adresi` varchar(45) DEFAULT NULL COMMENT 'IP adresi',
  `aciklama` varchar(255) DEFAULT NULL COMMENT 'İşlem açıklaması (otomatik sonlandırma vb)',
  `firma_id` int(11) DEFAULT NULL COMMENT 'Firma ID',
  `silinme_tarihi` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_personel_tarih` (`personel_id`,`zaman`),
  KEY `idx_islem_tipi` (`islem_tipi`),
  KEY `idx_firma_tarih` (`firma_id`,`zaman`)
) ENGINE=InnoDB AUTO_INCREMENT=233 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_icralari`
--

DROP TABLE IF EXISTS `personel_icralari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_icralari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `sira` int(11) DEFAULT 1,
  `dosya_no` varchar(100) NOT NULL,
  `icra_dairesi` varchar(255) DEFAULT NULL,
  `toplam_borc` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aylik_kesinti_tutari` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kesinti_tipi` enum('tutar','net_yuzde','asgari_yuzde') DEFAULT 'tutar',
  `kesinti_orani` decimal(10,2) DEFAULT 0.00,
  `aciklama` text DEFAULT NULL,
  `baslangic_tarihi` date DEFAULT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `durum` enum('bekliyor','devam_ediyor','fekki_geldi','kesinti_bitti','bitti','durduruldu') DEFAULT 'bekliyor',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `silinme_tarihi` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personel_id` (`personel_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_izin_bakiyeleri`
--

DROP TABLE IF EXISTS `personel_izin_bakiyeleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_izin_bakiyeleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `yil` int(11) NOT NULL,
  `toplam_hak` decimal(5,2) DEFAULT 14.00,
  `kullanilan_gun` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personel_id` (`personel_id`,`yil`),
  CONSTRAINT `fk_bakiye_personel` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_izinleri`
--

DROP TABLE IF EXISTS `personel_izinleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_izinleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `izin_tipi_id` int(11) DEFAULT NULL,
  `baslangic_tarihi` date NOT NULL,
  `bitis_tarihi` date NOT NULL,
  `toplam_gun` int(11) NOT NULL DEFAULT 0,
  `yillik_izne_etki` int(11) NOT NULL DEFAULT 1,
  `aciklama` text DEFAULT NULL,
  `talep_tarihi` datetime DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `onay_durumu` varchar(50) DEFAULT 'Beklemede',
  `onaylayan_id` varchar(50) DEFAULT 'Beklemede',
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `silen_kullanici` int(11) DEFAULT NULL,
  `silinme_aciklama` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_izin_personel` (`personel_id`) USING BTREE,
  KEY `fk_izin_tanimalamalar` (`izin_tipi_id`),
  CONSTRAINT `fk_izin_personel` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_izin_tanimalamalar` FOREIGN KEY (`izin_tipi_id`) REFERENCES `tanimlamalar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=347 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_kesintileri`
--

DROP TABLE IF EXISTS `personel_kesintileri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_kesintileri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `donem_id` int(11) DEFAULT NULL,
  `tur` varchar(50) NOT NULL,
  `tekrar_tipi` enum('tek_sefer','surekli') NOT NULL DEFAULT 'tek_sefer',
  `baslangic_donemi` date DEFAULT NULL,
  `bitis_donemi` date DEFAULT NULL,
  `hesaplama_tipi` enum('sabit','oran_net','oran_brut','asgari_oran_net','aylik_gun_kesinti') DEFAULT 'sabit',
  `oran` decimal(5,2) DEFAULT NULL,
  `parametre_id` int(11) DEFAULT NULL,
  `ana_kesinti_id` int(11) DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `durum` enum('beklemede','onaylandi','reddedildi') DEFAULT 'onaylandi',
  `tutar` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aciklama` text DEFAULT NULL,
  `kayit_yapan` int(11) DEFAULT NULL,
  `tarih` varchar(50) DEFAULT NULL,
  `icra_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `silinme_tarihi` timestamp NULL DEFAULT NULL,
  `onaylayan_id` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personel_id` (`personel_id`),
  KEY `icra_id` (`icra_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2901 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_konum_istekleri`
--

DROP TABLE IF EXISTS `personel_konum_istekleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_konum_istekleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `istek_zamani` datetime NOT NULL DEFAULT current_timestamp(),
  `yanit_zamani` datetime DEFAULT NULL,
  `durum` enum('BEKLIYOR','TAMAMLANDI','HATA') DEFAULT 'BEKLIYOR',
  `enlem` decimal(10,7) DEFAULT NULL,
  `boylam` decimal(10,7) DEFAULT NULL,
  `hata_mesaji` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_personel_durum` (`personel_id`,`durum`)
) ENGINE=InnoDB AUTO_INCREMENT=285 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personel_talepleri`
--

DROP TABLE IF EXISTS `personel_talepleri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personel_talepleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `ref_no` varchar(50) NOT NULL,
  `konum` varchar(255) NOT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `kategori` varchar(50) NOT NULL,
  `oncelik` varchar(20) DEFAULT 'orta',
  `baslik` varchar(255) DEFAULT NULL,
  `aciklama` text NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `durum` varchar(20) DEFAULT 'beklemede',
  `onay_aciklama` text DEFAULT NULL,
  `cozum_aciklama` text DEFAULT NULL,
  `cozum_tarihi` datetime DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT NULL,
  `silme_aciklama` int(11) DEFAULT NULL,
  `silinme_aciklama` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personel_id` (`personel_id`),
  KEY `durum` (`durum`),
  KEY `ref_no` (`ref_no`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personel_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `public_key` text DEFAULT NULL,
  `auth_token` varchar(255) DEFAULT NULL,
  `content_encoding` varchar(50) DEFAULT 'aes128gcm',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `personel_id` (`personel_id`),
  CONSTRAINT `fk_push_personel` FOREIGN KEY (`personel_id`) REFERENCES `personel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rehber`
--

DROP TABLE IF EXISTS `rehber`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rehber` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `adi_soyadi` varchar(50) NOT NULL,
  `kurum_adi` varchar(250) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `aciklama` varchar(255) DEFAULT NULL,
  `kayit_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resmi_tatiller`
--

DROP TABLE IF EXISTS `resmi_tatiller`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resmi_tatiller` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tatil_tarihi` date NOT NULL,
  `aciklama` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sayac_degisim`
--

DROP TABLE IF EXISTS `sayac_degisim`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sayac_degisim` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `islem_id` varchar(255) NOT NULL COMMENT 'Unique hash for idempotency',
  `firma_id` int(11) DEFAULT NULL,
  `personel_id` int(11) DEFAULT NULL,
  `is_sayisi` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `ekip_kodu_id` int(11) DEFAULT NULL,
  `isemri_no` varchar(50) DEFAULT NULL,
  `abone_no` varchar(50) DEFAULT NULL,
  `isemri_sebep` varchar(255) DEFAULT NULL,
  `ekip` varchar(255) DEFAULT NULL COMMENT 'API den gelen ekip adi',
  `memur` varchar(255) DEFAULT NULL,
  `sonuclandiran_kullanici` varchar(255) DEFAULT NULL,
  `bolge` varchar(255) DEFAULT NULL,
  `isemri_sonucu` text DEFAULT NULL,
  `sonuc_aciklama` text DEFAULT NULL,
  `takilan_sayacno` varchar(100) DEFAULT NULL,
  `zimmet_dusuldu` tinyint(1) DEFAULT 0,
  `kayit_tarihi` datetime DEFAULT NULL,
  `tarih` date DEFAULT NULL COMMENT 'Islem tarihi (Y-m-d)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL,
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_islem_id` (`islem_id`),
  KEY `idx_tarih` (`tarih`),
  KEY `idx_firma_id` (`firma_id`),
  KEY `idx_personel_id` (`personel_id`),
  KEY `idx_ekip_kodu_id` (`ekip_kodu_id`),
  KEY `idx_isemri_no` (`isemri_no`)
) ENGINE=InnoDB AUTO_INCREMENT=856 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `firma_id` int(11) DEFAULT NULL,
  `set_name` varchar(50) DEFAULT NULL,
  `set_value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=447 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sms_sablonlari`
--

DROP TABLE IF EXISTS `sms_sablonlari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_sablonlari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `baslik` text NOT NULL,
  `icerik` text NOT NULL,
  `aktif_mi` int(11) DEFAULT 0,
  `kayit_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `sql_gelir_gider`
--

DROP TABLE IF EXISTS `sql_gelir_gider`;
/*!50001 DROP VIEW IF EXISTS `sql_gelir_gider`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `sql_gelir_gider` AS SELECT
 1 AS `id`,
  1 AS `tarih`,
  1 AS `TYPE`,
  1 AS `kategori`,
  1 AS `hesap_adi`,
  1 AS `tutar`,
  1 AS `aciklama`,
  1 AS `kayit_tarihi`,
  1 AS `kayit_yapan`,
  1 AS `bakiye` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `level` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2066 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tanimlamalar`
--

DROP TABLE IF EXISTS `tanimlamalar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tanimlamalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 0,
  `grup` varchar(100) NOT NULL DEFAULT '0',
  `tur_adi` varchar(90) NOT NULL DEFAULT '0',
  `is_emri_sonucu` varchar(255) NOT NULL DEFAULT '0',
  `ekip_bolge` varchar(90) DEFAULT '0',
  `rapor_sekmesi` varchar(90) DEFAULT '0',
  `is_turu_ucret` float DEFAULT 0,
  `aracli_personel_is_turu_ucret` float DEFAULT 0,
  `unvan_departman` varchar(90) DEFAULT '0',
  `unvan_ucret` float DEFAULT 0,
  `personel_gorsun` int(11) DEFAULT 0 COMMENT 'İzin Talebinde personelin görüp görmeyeceği',
  `aciklama` varchar(255) NOT NULL DEFAULT '0',
  `silinme_tarihi` datetime DEFAULT NULL,
  `defter_bolge` varchar(50) DEFAULT NULL,
  `defter_abone_sayisi` int(11) DEFAULT NULL,
  `defter_mahalle` varchar(50) DEFAULT NULL,
  `baslangic_tarihi` date DEFAULT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `silen_kullanici` int(11) DEFAULT 0,
  `kayit_tarihi` varchar(20) NOT NULL,
  `kayit_yapan` int(11) NOT NULL DEFAULT 0,
  `ucretli_mi` tinyint(1) DEFAULT 1 COMMENT 'Ücretli izin mi? (1=Evet, 0=Hayır)',
  `personel_gorebilir` tinyint(1) DEFAULT 1 COMMENT 'Personel izin talebinde görebilir mi? (1=Evet, 0=Hayır)',
  `yetkili_onayina_tabi` tinyint(1) DEFAULT 1,
  `renk` varchar(100) DEFAULT 'bg-primary/10 text-primary' COMMENT 'İzin türü rengi (Tailwind class)',
  `ikon` varchar(50) DEFAULT 'event' COMMENT 'İzin türü ikonu (Material Icons)',
  `kisa_kod` varchar(50) DEFAULT NULL COMMENT 'izin türü için kısa kod tanımlaması',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2686 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_role_permissions`
--

DROP TABLE IF EXISTS `user_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `permission_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=461 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` varchar(55) NOT NULL COMMENT 'Veri Sahibinin İd''si',
  `superadmin` int(11) DEFAULT 0,
  `role_name` varchar(55) NOT NULL COMMENT 'Yetki Grup Adı',
  `description` varchar(255) DEFAULT NULL COMMENT 'Yetki grubu açıklaması',
  `role_color` varchar(20) DEFAULT 'secondary',
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  `kayit_yapan` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `adi_soyadi` varchar(255) DEFAULT NULL,
  `unvani` varchar(255) DEFAULT NULL,
  `gorevi` varchar(255) DEFAULT NULL,
  `telefon` varchar(255) DEFAULT NULL,
  `email_adresi` varchar(255) DEFAULT NULL,
  `roles` varchar(255) DEFAULT NULL,
  `firma_ids` varchar(255) DEFAULT NULL,
  `izin_onayi_yapacakmi` enum('Evet','Hayır') DEFAULT NULL,
  `izin_onay_sirasi` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL COMMENT 'Verinin Sahibini İfade Eder',
  `owner_type` int(11) DEFAULT NULL,
  `aciklama` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `silinme_tarihi` datetime DEFAULT NULL,
  `menuType` varchar(255) DEFAULT NULL,
  `mail_avans_talep` enum('Evet','Hayır') DEFAULT 'Hayır' COMMENT 'Avans talebi bildirimlerini al',
  `mail_izin_talep` enum('Evet','Hayır') DEFAULT 'Hayır' COMMENT 'İzin talebi bildirimlerini al',
  `mail_genel_talep` enum('Evet','Hayır') DEFAULT 'Hayır' COMMENT 'Genel talep bildirimlerini al',
  `mail_ariza_talep` enum('Evet','Hayır') DEFAULT 'Hayır' COMMENT 'Arıza talebi bildirimlerini al',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `email_adresi` (`email_adresi`),
  UNIQUE KEY `user_name` (`user_name`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_personel_demirbas_bakiye`
--

DROP TABLE IF EXISTS `v_personel_demirbas_bakiye`;
/*!50001 DROP VIEW IF EXISTS `v_personel_demirbas_bakiye`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_personel_demirbas_bakiye` AS SELECT
 1 AS `personel_id`,
  1 AS `demirbas_id`,
  1 AS `demirbas_adi`,
  1 AS `demirbas_no`,
  1 AS `personel_adi`,
  1 AS `toplam_zimmet`,
  1 AS `toplam_cikis`,
  1 AS `bakiye` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_zimmet_detay`
--

DROP TABLE IF EXISTS `v_zimmet_detay`;
/*!50001 DROP VIEW IF EXISTS `v_zimmet_detay`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_zimmet_detay` AS SELECT
 1 AS `zimmet_id`,
  1 AS `demirbas_id`,
  1 AS `personel_id`,
  1 AS `teslim_tarihi`,
  1 AS `teslim_miktar`,
  1 AS `iade_tarihi`,
  1 AS `iade_miktar`,
  1 AS `zimmet_durum`,
  1 AS `zimmet_aciklama`,
  1 AS `demirbas_no`,
  1 AS `demirbas_adi`,
  1 AS `marka`,
  1 AS `model`,
  1 AS `seri_no`,
  1 AS `kategori_adi`,
  1 AS `personel_adi`,
  1 AS `personel_telefon` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `yapilan_isler`
--

DROP TABLE IF EXISTS `yapilan_isler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `yapilan_isler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `islem_id` varchar(255) NOT NULL,
  `firma_id` int(11) DEFAULT 0,
  `personel_id` int(11) DEFAULT 0,
  `ekip_kodu_id` int(11) DEFAULT NULL,
  `is_emri_sonucu_id` int(11) DEFAULT NULL,
  `is_emri_tipi` varchar(255) DEFAULT NULL,
  `ekip_kodu` varchar(255) DEFAULT NULL,
  `is_emri_sonucu` text DEFAULT NULL,
  `aciklama` varchar(255) DEFAULT NULL,
  `sonuclanmis` int(11) DEFAULT 0,
  `acik_olanlar` int(11) DEFAULT 0,
  `tarih` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `islem_id` (`islem_id`,`silinme_tarihi`) USING BTREE,
  KEY `idx_yapilan_isler_tarih_firma` (`tarih`,`firma_id`),
  KEY `idx_yapilan_isler_silinme` (`silinme_tarihi`)
) ENGINE=InnoDB AUTO_INCREMENT=61767 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `sql_gelir_gider`
--

/*!50001 DROP VIEW IF EXISTS `sql_gelir_gider`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `sql_gelir_gider` AS select `gelir_gider`.`id` AS `id`,`gelir_gider`.`tarih` AS `tarih`,`gelir_gider`.`type` AS `TYPE`,`gelir_gider`.`kategori` AS `kategori`,`gelir_gider`.`hesap_adi` AS `hesap_adi`,`gelir_gider`.`tutar` AS `tutar`,`gelir_gider`.`aciklama` AS `aciklama`,`gelir_gider`.`kayit_tarihi` AS `kayit_tarihi`,`gelir_gider`.`kayit_yapan` AS `kayit_yapan`,round(sum(case when `gelir_gider`.`type` = 1 then `gelir_gider`.`tutar` when `gelir_gider`.`type` = 2 then -`gelir_gider`.`tutar` else 0 end) over ( order by `gelir_gider`.`tarih`,`gelir_gider`.`id`),2) AS `bakiye` from `gelir_gider` order by `gelir_gider`.`tarih` desc,`gelir_gider`.`id` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_personel_demirbas_bakiye`
--

/*!50001 DROP VIEW IF EXISTS `v_personel_demirbas_bakiye`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_personel_demirbas_bakiye` AS select `h`.`personel_id` AS `personel_id`,`h`.`demirbas_id` AS `demirbas_id`,`d`.`demirbas_adi` AS `demirbas_adi`,`d`.`demirbas_no` AS `demirbas_no`,`p`.`adi_soyadi` AS `personel_adi`,sum(case when `h`.`hareket_tipi` = 'zimmet' then `h`.`miktar` else 0 end) AS `toplam_zimmet`,sum(case when `h`.`hareket_tipi` in ('iade','sarf','kayip') then `h`.`miktar` else 0 end) AS `toplam_cikis`,sum(case when `h`.`hareket_tipi` = 'zimmet' then `h`.`miktar` else 0 end) - sum(case when `h`.`hareket_tipi` in ('iade','sarf','kayip') then `h`.`miktar` else 0 end) AS `bakiye` from ((`demirbas_hareketler` `h` join `demirbas` `d` on(`h`.`demirbas_id` = `d`.`id`)) join `personel` `p` on(`h`.`personel_id` = `p`.`id`)) where `h`.`silinme_tarihi` is null group by `h`.`personel_id`,`h`.`demirbas_id`,`d`.`demirbas_adi`,`d`.`demirbas_no`,`p`.`adi_soyadi` having `bakiye` <> 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_zimmet_detay`
--

/*!50001 DROP VIEW IF EXISTS `v_zimmet_detay`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_zimmet_detay` AS select `z`.`id` AS `zimmet_id`,`z`.`demirbas_id` AS `demirbas_id`,`z`.`personel_id` AS `personel_id`,`z`.`teslim_tarihi` AS `teslim_tarihi`,`z`.`teslim_miktar` AS `teslim_miktar`,`z`.`iade_tarihi` AS `iade_tarihi`,`z`.`iade_miktar` AS `iade_miktar`,`z`.`durum` AS `zimmet_durum`,`z`.`aciklama` AS `zimmet_aciklama`,`d`.`demirbas_no` AS `demirbas_no`,`d`.`demirbas_adi` AS `demirbas_adi`,`d`.`marka` AS `marka`,`d`.`model` AS `model`,`d`.`seri_no` AS `seri_no`,`k`.`kategori_adi` AS `kategori_adi`,`p`.`adi_soyadi` AS `personel_adi`,`p`.`cep_telefonu` AS `personel_telefon` from (((`demirbas_zimmet` `z` left join `demirbas` `d` on(`z`.`demirbas_id` = `d`.`id`)) left join `demirbas_kategorileri` `k` on(`d`.`kategori_id` = `k`.`id`)) left join `personel` `p` on(`z`.`personel_id` = `p`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-12 14:05:11
