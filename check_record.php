<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
$dbObj = new Db();
$db = $dbObj->getConnection();
$stmt = $db->prepare("SELECT d.id, d.seri_no, k.tur_adi 
                      FROM demirbas d 
                      LEFT JOIN tanimlamalar k ON d.kategori_id = k.id 
                      WHERE d.seri_no = ?");
$stmt->execute(['26141461']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
