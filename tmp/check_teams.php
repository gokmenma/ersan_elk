<?php
require_once __DIR__ . '/../bootstrap.php';
use App\Model\Model;

$model = new Model('tanimlamalar');
$db = $model->getDb();
$stmt = $db->query("SELECT tur_adi FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL LIMIT 10");
$teams = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($teams);
