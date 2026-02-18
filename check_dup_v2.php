<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;

$db = new Db();
$stmt = $db->db->query("SELECT id, external_id, arac_id, tarih, toplam_tutar FROM arac_yakit_kayitlari WHERE external_id IS NOT NULL AND external_id != '' ORDER BY id DESC LIMIT 10");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "No records found with external_id.\n";
} else {
    foreach ($results as $row) {
        echo "ID: {$row['id']} | ExternalID: '{$row['external_id']}' | Date: {$row['tarih']} | Amount: {$row['toplam_tutar']}\n";
    }
}
