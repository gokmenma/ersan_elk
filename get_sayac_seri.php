<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
$dbObj = new Db();
$db = $dbObj->getConnection();
$stmt = $db->query("SELECT d.seri_no FROM demirbas d LEFT JOIN tanimlamalar k ON d.kategori_id = k.id WHERE (LOWER(k.tur_adi) LIKE '%sayaç%' OR LOWER(k.tur_adi) LIKE '%sayac%') AND d.seri_no IS NOT NULL AND d.seri_no != '' LIMIT 1");
echo $stmt->fetchColumn();
