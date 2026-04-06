<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

// 1. Get teams in "Beldeler"
$sql = "SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'ekip_kodu' AND ekip_bolge = 'Beldeler' AND silinme_tarihi IS NULL";
$teams = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "TEAMS IN BELDELER:\n";
print_r($teams);

if (!empty($teams)) {
    $ids = array_column($teams, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // 2. Get defters read by these teams
    $sql = "SELECT DISTINCT eo.defter 
            FROM endeks_okuma eo 
            WHERE eo.ekip_kodu_id IN ($placeholders) 
            AND eo.silinme_tarihi IS NULL 
            LIMIT 50";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($ids);
    $defters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "DEFTERS READ BY THESE TEAMS:\n";
    print_r($defters);
    
    if (!empty($defters)) {
        $defterCodes = array_column($defters, 'defter');
        $placeholders2 = implode(',', array_fill(0, count($defterCodes), '?'));
        
        // 3. Check defter_bolge for these defters
        $sql = "SELECT tur_adi, defter_bolge FROM tanimlamalar WHERE grup = 'defter_kodu' AND tur_adi IN ($placeholders2) AND silinme_tarihi IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute($defterCodes);
        $defterDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "DEFTER DETAILS (Regions):\n";
        print_r($defterDetails);
    }
}
