<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

echo "=== personel_icralari structure ===\n";
$stmt = $pdo->query("DESCRIBE personel_icralari");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
