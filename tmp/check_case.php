<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$bolge = 'Pazarcık'; // Ekip region
$firma_id = 1;

// 1. Exact match (Current logic)
$sql1 = "SELECT COUNT(*) FROM tanimlamalar WHERE grup = 'defter_kodu' AND defter_bolge = :bolge AND silinme_tarihi IS NULL";
$stmt1 = $db->prepare($sql1);
$stmt1->execute(['bolge' => $bolge]);
$count1 = $stmt1->fetchColumn();

// 2. Case-insensitive match
$sql2 = "SELECT COUNT(*) FROM tanimlamalar WHERE grup = 'defter_kodu' AND UPPER(defter_bolge) = :bolge AND silinme_tarihi IS NULL";
$stmt2 = $db->prepare($sql2);
$stmt2->execute(['bolge' => mb_strtoupper($bolge, 'UTF-8')]);
$count2 = $stmt2->fetchColumn();

echo "Pazarcık Exact: $count1\n";
echo "Pazarcık UPPER: $count2\n";

$bolge2 = 'Andırın';
$stmt1->execute(['bolge' => $bolge2]);
$count1b = $stmt1->fetchColumn();
$stmt2->execute(['bolge' => mb_strtoupper($bolge2, 'UTF-8')]);
$count2b = $stmt2->fetchColumn();

echo "Andırın Exact: $count1b\n";
echo "Andırın UPPER: $count2b\n";
