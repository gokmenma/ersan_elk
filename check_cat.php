<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
$dbObj = new Db();
$db = $dbObj->getConnection();
$stmt = $db->prepare("SELECT d.seri_no, k.tur_adi FROM demirbas d LEFT JOIN tanimlamalar k ON d.kategori_id = k.id WHERE d.seri_no = ?");
$stmt->execute(['SN351829101191556']);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
