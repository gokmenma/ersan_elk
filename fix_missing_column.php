<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
     $pdo = new PDO($dsn, $user, $pass);
     $pdo->exec("ALTER TABLE arac_servis_kayitlari ADD COLUMN ikame_alis_tarihi DATE DEFAULT NULL AFTER ikame_model");
     echo "Column ikame_alis_tarihi added successfully.\n";
} catch (\Exception $e) {
     echo "Error: " . $e->getMessage() . "\n";
}
