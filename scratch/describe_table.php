<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$db = (new App\Core\Db())->db;
$stmt = $db->query("DESCRIBE personel_ek_odemeler");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['Field'] . " | " . $col['Type'] . "\n";
}
