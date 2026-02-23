<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
try {
    $db->exec("ALTER TABLE duyurular ADD COLUMN ana_sayfada_goster TINYINT(1) DEFAULT 0 AFTER hedef_sayfa");
    echo "Added ana_sayfada_goster\n";
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
try {
    $db->exec("ALTER TABLE duyurular ADD COLUMN pwa_goster TINYINT(1) DEFAULT 0 AFTER ana_sayfada_goster");
    echo "Added pwa_goster\n";
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
