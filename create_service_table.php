<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
try {
    $db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE TABLE IF NOT EXISTS `arac_servis_kayitlari` (
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
        `olusturma_tarihi` datetime DEFAULT CURRENT_TIMESTAMP,
        `guncelleme_tarihi` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `silinme_tarihi` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `firma_id` (`firma_id`),
        KEY `arac_id` (`arac_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sql);
    echo "Table 'arac_servis_kayitlari' created successfully.";
} catch (Exception $e) {
    echo $e->getMessage();
}
