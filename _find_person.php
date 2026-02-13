<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

$stmt = $pdo->query("SELECT id, ad, soyad FROM personel WHERE ad LIKE 'ABDULKADİR%' AND soyad LIKE 'PURDAŞ%'");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
