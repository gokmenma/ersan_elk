<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$bugun = date('Y-m-d');
$firma_id = 1;

$sql = "SELECT 
            t.defter_bolge,
            t.tur_adi,
            (SELECT MAX(eo.tarih) 
             FROM endeks_okuma eo 
             WHERE eo.defter = t.tur_adi 
             AND eo.firma_id = :firma_id 
             AND eo.silinme_tarihi IS NULL) as son_okuma_tarihi
        FROM tanimlamalar t
        WHERE t.grup = 'defter_kodu' 
        AND t.firma_id = :firma_id 
        AND t.silinme_tarihi IS NULL";

$stmt = $db->prepare($sql);
$stmt->execute(['firma_id' => $firma_id]);
$defterler = $stmt->fetchAll(PDO::FETCH_OBJ);

$delayed_total = 0;
foreach ($defterler as $d) {
    $sonOkuma = $d->son_okuma_tarihi;
    if ($sonOkuma) {
        $diff = (time() - strtotime($sonOkuma)) / (60 * 60 * 24);
        if ($diff > 35) $delayed_total++;
    }
}

echo "TOTAL DELAYED IN SYSTEM: $delayed_total\n";
