<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

$id = 75;
echo "\n--- BORDRO PERSONEL CHECK ---\n";
// bordro_personel tablosunda icra kesintisi kolonu varsa ona bakalım
$stmt = $pdo->query("SELECT * FROM bordro_personel WHERE personel_id = $id AND donem_id = 7");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
if ($res) {
    print_r($res);
} else {
    echo "No bordro record found for donem 7.\n";
}
