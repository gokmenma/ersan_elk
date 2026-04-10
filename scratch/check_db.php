<?php
require_once __DIR__ . '/../Autoloader.php';
use App\Model\AracKmModel;

// Manually set session if needed for CLI - but let's assume it's set in the app
// Actually, I'll just query the table directly

$pdo = (new AracKmModel())->getDb();
$stmt = $pdo->query("SELECT * FROM arac_km_kayitlari ORDER BY id DESC LIMIT 10");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Last 10 KM Records:\n";
foreach($results as $row) {
    echo "ID: {$row['id']} | Arac: {$row['arac_id']} | Tarih: {$row['tarih']} | Baslangic: {$row['baslangic_km']} | Bitis: {$row['bitis_km']} | Firma: {$row['firma_id']}\n";
}
