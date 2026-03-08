<?php
require_once __DIR__ . '/../Autoloader.php';

$db = (new App\Core\Db())->db;

$queries = [
    "ALTER TABLE `araclar` ADD COLUMN `ikame_mi` TINYINT(1) NOT NULL DEFAULT 0 AFTER `aktif_mi`",
    "ALTER TABLE `arac_servis_kayitlari` ADD COLUMN `ikame_arac_id` INT(11) DEFAULT NULL AFTER `fatura_no`",
    "ALTER TABLE `arac_servis_kayitlari` ADD COLUMN `ikame_plaka` VARCHAR(20) DEFAULT NULL AFTER `ikame_arac_id`",
    "ALTER TABLE `arac_servis_kayitlari` ADD COLUMN `ikame_marka` VARCHAR(100) DEFAULT NULL AFTER `ikame_plaka`",
    "ALTER TABLE `arac_servis_kayitlari` ADD COLUMN `ikame_model` VARCHAR(100) DEFAULT NULL AFTER `ikame_marka`",
    "ALTER TABLE `arac_servis_kayitlari` ADD COLUMN `ikame_teslim_km` INT(11) DEFAULT NULL AFTER `ikame_model`",
    "ALTER TABLE `arac_servis_kayitlari` ADD COLUMN `ikame_iade_km` INT(11) DEFAULT NULL AFTER `ikame_teslim_km`",
    "ALTER TABLE `arac_servis_kayitlari` ADD COLUMN `ikame_iade_tarihi` DATETIME DEFAULT NULL AFTER `ikame_iade_km`",
    "ALTER TABLE `arac_servis_kayitlari` ADD KEY `idx_ikame_arac` (`ikame_arac_id`)"
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
        echo "OK: " . substr($q, 0, 70) . "\n";
    } catch (Exception $e) {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration completed!\n";
