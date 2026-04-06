<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$stmt = $db->query("SELECT id, tur_adi, defter_bolge, defter_mahalle FROM tanimlamalar WHERE grup = 'defter_kodu' AND (defter_bolge LIKE '%Belde%' OR defter_mahalle LIKE '%Belde%')");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['tur_adi'] . ' - ' . $row['defter_bolge'] . ' - ' . $row['defter_mahalle'] . "\n";
}
