<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

$stmt = $pdo->query("DESCRIBE personel");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
for ($i = 0; $i < min(16, count($cols)); $i++) {
    print_r($cols[$i]);
}
