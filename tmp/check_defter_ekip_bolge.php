<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$stmt = $db->query("SELECT id, tur_adi, defter_bolge, ekip_bolge FROM tanimlamalar WHERE grup = 'defter_kodu' AND ekip_bolge = 'Beldeler'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['tur_adi'] . ' - ' . ROW['defter_bolge'] . ' - ' . ROW['ekip_bolge'] . "\n";
}
echo "Count: " . $stmt->rowCount();
