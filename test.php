<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);
$stmt = $pdo->query("SELECT id, arac_id, servis_tarihi, iade_tarihi, ikame_arac_id, ikame_plaka, ikame_alis_tarihi, ikame_iade_tarihi FROM arac_servis_kayitlari WHERE ikame_arac_id IN (77, 78) ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
