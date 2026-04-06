<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$stmt = $db->query("SELECT id, tur_adi, defter_bolge, defter_mahalle FROM tanimlamalar WHERE grup = 'defter_kodu' AND (defter_bolge IS NULL OR defter_bolge = '') LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "ID: {$row['id']} - Code: {$row['tur_adi']} - Mahalle: {$row['defter_mahalle']}\n";
}
echo "Total empty regions: " . count($rows);
