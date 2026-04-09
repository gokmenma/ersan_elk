<?php
require_once 'bootstrap.php';
$dbClass = new \App\Core\Db();
$db = $dbClass->getConnection();

$sql = "CREATE TABLE IF NOT EXISTS `arac_km_bildirimleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `arac_id` int(11) NOT NULL,
  `personel_id` int(11) NOT NULL,
  `tarih` date NOT NULL,
  `bitis_km` int(11) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `resim_yolu` varchar(255) NOT NULL,
  `durum` enum('beklemede','onaylandi','reddedildi') DEFAULT 'beklemede',
  `red_nedeni` text DEFAULT NULL,
  `onaylayan_id` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `firma_id` (`firma_id`),
  KEY `arac_id` (`arac_id`),
  KEY `personel_id` (`personel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $db->exec($sql);
    echo "Table arac_km_bildirimleri created successfully or already exists.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
