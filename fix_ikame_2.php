<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);
// 81 AAK 474 (id = 78) şu an aktif bir serviste (id=12) ikame olarak kullanılıyor ama aktif_mi=0 kalmış.
$stmt = $pdo->prepare("UPDATE araclar SET aktif_mi = 1 WHERE id = 78 AND firma_id = 1");
$stmt->execute();
echo "Arac tekrar aktif edildi.";
