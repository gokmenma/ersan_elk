<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

// Let's find defter regions
$sql = "SELECT DISTINCT defter_bolge FROM tanimlamalar WHERE grup = 'defter_kodu' AND silinme_tarihi IS NULL";
$stmt = $db->query($sql);
$bolgeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "DEFER_BOLGELER:\n";
print_r($bolgeler);

// Let's find regions in general
$sql = "SELECT DISTINCT ekip_bolge FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL";
$stmt = $db->query($sql);
$ekipBolgeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "EKIP_BOLGELER:\n";
print_r($ekipBolgeler);
