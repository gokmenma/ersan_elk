<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

$stmt = $pdo->query("DESCRIBE personel");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
