<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
$dbObj = new Db();
$db = $dbObj->getConnection();
$stmt = $db->query("SELECT DISTINCT grup FROM tanimlamalar WHERE grup LIKE 'demirbas%'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
