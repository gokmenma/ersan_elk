<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$db = new \PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);

$stmt = $db->query("SELECT id, baslik, durum, ana_sayfada_goster, firma_id FROM duyurular WHERE silinme_tarihi IS NULL");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- Current Duyurular in DB ---\n";
foreach ($results as $row) {
    echo "ID: {$row['id']} | Firma: {$row['firma_id']} | Başlık: {$row['baslik']} | Durum: [{$row['durum']}] | Ana Sayfa: {$row['ana_sayfada_goster']}\n";
}
echo "-----------------------------\n";
