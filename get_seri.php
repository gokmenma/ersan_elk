<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
$dbObj = new Db();
$db = $dbObj->getConnection();
$stmt = $db->query("SELECT seri_no FROM demirbas WHERE seri_no IS NOT NULL AND seri_no != '' LIMIT 1");
echo $stmt->fetchColumn();
