<?php
require_once 'app/Model/Model.php';
require_once 'app/Model/PuantajModel.php';
require_once 'app/Model/TanimlamalarModel.php';

use App\Model\PuantajModel;
use App\Model\TanimlamalarModel;

// Mock session
session_start();
$_SESSION['firma_id'] = 1; // Adjust if needed

$PM = new PuantajModel();
$TM = new TanimlamalarModel();

echo "Checking yapilan_isler vs tanimlamalar...\n";

$sql = "SELECT DISTINCT ekip_kodu_id, ekip_kodu FROM yapilan_isler WHERE silinme_tarihi IS NULL LIMIT 20";
$stmt = $PM->db->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    if (!$row['ekip_kodu_id']) continue;
    $t = $TM->find($row['ekip_kodu_id']);
    $expected = $t ? $t->tur_adi : 'NOT FOUND';
    echo "ID: {$row['ekip_kodu_id']} | DB ekip_kodu: '{$row['ekip_kodu']}' | Tanimlamalar tur_adi: '{$expected}'\n";
}
