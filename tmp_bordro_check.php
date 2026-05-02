<?php
require 'Autoloader.php';
$m = new App\Model\BordroPersonelModel();
$m->hesaplaMaas(1338, 1, 'Codex');
$pdo = (new App\Core\Db())->getConnection();
$stmt = $pdo->prepare('SELECT brut_maas, net_maas, banka_odemesi, elden_odeme, prim_tutar FROM bordro_personel WHERE id = ?');
$stmt->execute([1338]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'NET=' . $row['net_maas'] . "\n";
echo 'PRIM=' . $row['prim_tutar'] . "\n";
echo 'BANKA=' . $row['banka_odemesi'] . "\n";
echo 'ELDEN=' . $row['elden_odeme'] . "\n";
?>
