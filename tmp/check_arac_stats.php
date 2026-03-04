<?php
require_once __DIR__ . '/../Autoloader.php';
session_start();
$_SESSION['firma_id'] = 1;

$pdo = (new \App\Core\Db())->getConnection();

$stmt = $pdo->query("
    SELECT 
        a.id, a.plaka,
        (CASE WHEN az.id IS NOT NULL THEN 1 ELSE 0 END) as is_zimmetli,
        (CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END) as is_serviste
    FROM araclar a
    LEFT JOIN arac_zimmetleri az ON a.id = az.arac_id AND az.durum = 'aktif'
    LEFT JOIN (SELECT DISTINCT arac_id, MIN(id) as id FROM arac_servis_kayitlari WHERE iade_tarihi IS NULL AND silinme_tarihi IS NULL GROUP BY arac_id) s ON a.id = s.arac_id
    WHERE a.firma_id = 1 AND a.aktif_mi = 1 AND a.silinme_tarihi IS NULL
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
$zimmetli = 0;
$serviste = 0;
$bosta = 0;
$zimmetli_ve_serviste = 0;

foreach ($results as $row) {
    $total++;
    if ($row['is_zimmetli'])
        $zimmetli++;
    if ($row['is_serviste'])
        $serviste++;
    if (!$row['is_zimmetli'] && !$row['is_serviste'])
        $bosta++;
    if ($row['is_zimmetli'] && $row['is_serviste']) {
        $zimmetli_ve_serviste++;
        echo "Plaka: {$row['plaka']} is both zimmetli and in service.\n";
    }
}

echo "Total: $total\n";
echo "Zimmetli: $zimmetli\n";
echo "Serviste: $serviste\n";
echo "Bosta: $bosta\n";
echo "Zimmetli + Serviste overlap: $zimmetli_ve_serviste\n";
