<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$stmt = $db->query("SELECT COUNT(*) FROM tanimlamalar WHERE grup = 'defter_kodu' AND CAST(tur_adi AS UNSIGNED) >= 600");
echo "Defters >= 600: " . $stmt->fetchColumn() . "\n";
$stmt = $db->query("SELECT id, tur_adi, defter_bolge, defter_mahalle FROM tanimlamalar WHERE grup = 'defter_kodu' AND CAST(tur_adi AS UNSIGNED) >= 600 LIMIT 10");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "- [{$row['tur_adi']}] Region: {$row['defter_bolge']} - Mahalle: {$row['defter_mahalle']}\n";
}
