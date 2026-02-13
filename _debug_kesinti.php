<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

$stmt = $pdo->query("SELECT * FROM personel_kesintileri WHERE id = 188");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
echo "--- KESINTI 188 DETAIL ---\n";
print_r($res);

$stmt = $pdo->query("SELECT * FROM personel_icralari WHERE id = 2");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\n--- ICRA 2 DETAIL ---\n";
print_r($res);
