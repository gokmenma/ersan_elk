<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

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
             AND eo.silinme_tarihi IS NULL) as son_okuma_tarihi
        FROM tanimlamalar t
        WHERE t.grup = 'defter_kodu' 
        AND t.silinme_tarihi IS NULL";

$defterler = $db->query($sql)->fetchAll(PDO::FETCH_OBJ);

echo "Total defterler: " . count($defterler) . "\n";

$delayed_count = 0;
foreach ($defterler as $d) {
    $sonOkuma = $d->son_okuma_tarihi ?: $d->baslangic_tarihi;
    if ($sonOkuma) {
        $sonTarih = new DateTime($sonOkuma);
        $bugun = new DateTime();
        $interval = $bugun->diff($sonTarih);
        $gunFarki = $interval->days;
        
        if ($gunFarki > 35) {
            $delayed_count++;
            echo "DELAYED: {$d->defter_kodu} - Bölge: [{$d->defter_bolge}] - Gun: $gunFarki\n";
        }
    }
}
echo "Total Delayed: $delayed_count\n";
