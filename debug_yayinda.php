<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$db = new \PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);

$stmt = $db->query("SELECT * FROM duyurular WHERE durum = 'Yayında' AND silinme_tarihi IS NULL");
$yayinda = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- Yayında Status Count: " . count($yayinda) . " ---\n";
foreach ($yayinda as $row) {
    echo "ID: {$row['id']} | Başlık: {$row['baslik']}\n";
}

$stmt = $db->query("SELECT * FROM duyurular WHERE (durum IS NULL OR durum = '') AND silinme_tarihi IS NULL");
$no_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "--- No Status Count: " . count($no_status) . " ---\n";
foreach ($no_status as $row) {
    echo "ID: {$row['id']} | Başlık: {$row['baslik']}\n";
}
