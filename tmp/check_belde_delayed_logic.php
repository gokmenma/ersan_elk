<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

// 1. Get teams in "Beldeler"
$sql = "SELECT id FROM tanimlamalar WHERE grup = 'ekip_kodu' AND ekip_bolge = 'Beldeler' AND silinme_tarihi IS NULL";
$teams = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);

if (empty($teams)) {
    die("No teams in Beldeler\n");
}

$placeholders = implode(',', array_fill(0, count($teams), '?'));

// 2. Get defters that these teams HAVE READ at least once
$sql = "SELECT DISTINCT eo.defter 
        FROM endeks_okuma eo 
        WHERE eo.ekip_kodu_id IN ($placeholders) 
        AND eo.silinme_tarihi IS NULL";

$stmt = $db->prepare($sql);
$stmt->execute($teams);
$defters = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Total defters touched by Beldeler teams: " . count($defters) . "\n";

$delayed = [];
$bugun = new DateTime();

foreach ($defters as $defterCode) {
    // Check last reading of this defter
    $sql = "SELECT MAX(tarih) FROM endeks_okuma WHERE defter = ? AND silinme_tarihi IS NULL";
    $stmt = $db->prepare($sql);
    $stmt->execute([$defterCode]);
    $sonOkuma = $stmt->fetchColumn();
    
    if ($sonOkuma) {
        $sonTarih = new DateTime($sonOkuma);
        $interval = $bugun->diff($sonTarih);
        $gunFarki = $interval->days;
        
        if ($gunFarki > 35) {
            $delayed[] = [
                'defter_kodu' => $defterCode,
                'gun' => $gunFarki,
                'son_okuma' => $sonOkuma
            ];
        }
    }
}

echo "Total Delayed Defters touched by Beldeler teams: " . count($delayed) . "\n";
print_r($delayed);
