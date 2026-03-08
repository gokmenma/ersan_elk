<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);
// Any ikame vehicle that is currently NOT active in any service should be deactivated.
// Actually, specifically our returned ikame vehicles.
$stmt = $pdo->prepare("UPDATE araclar SET aktif_mi = 0 WHERE id = 77 AND firma_id = 1");
$stmt->execute();
echo "Updated stuck vehicle.";
