<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$bolge = 'Beldeler';
$firma_id = 1; // Assuming firma_id is 1, need to verify

$db = (new Model('tanimlamalar'))->getDb();

$sql = "SELECT 
            t.id, 
            t.tur_adi as defter_kodu, 
            t.defter_mahalle as mahalle,
            t.baslangic_tarihi,
            t.defter_bolge,
            (SELECT MAX(eo.tarih) 
             FROM endeks_okuma eo 
             WHERE eo.defter = t.tur_adi 
             AND eo.firma_id = :firma_id 
             AND eo.silinme_tarihi IS NULL) as son_okuma_tarihi
        FROM tanimlamalar t
        WHERE t.grup = 'defter_kodu' 
        AND t.defter_bolge = :bolge 
        AND t.firma_id = :firma_id 
        AND t.silinme_tarihi IS NULL";

$stmt = $db->prepare($sql);
$stmt->execute(['bolge' => $bolge, 'firma_id' => $firma_id]);
$defterler = $stmt->fetchAll(PDO::FETCH_OBJ);

echo "Total defterler in $bolge: " . count($defterler) . "\n";

foreach ($defterler as $d) {
    $sonOkuma = $d->son_okuma_tarihi ?: $d->baslangic_tarihi;
    if ($sonOkuma) {
        $sonTarih = new DateTime($sonOkuma);
        $bugun = new DateTime();
        $interval = $bugun->diff($sonTarih);
        $gunFarki = $interval->days;
        
        if ($gunFarki > 35) {
            echo "DELAYED: {$d->defter_kodu} - {$d->mahalle} - $gunFarki days (Last: $sonOkuma)\n";
        } else {
            // echo "OK: {$d->defter_kodu} - $gunFarki days\n";
        }
    } else {
        echo "NO DATE: {$d->defter_kodu}\n";
    }
}
