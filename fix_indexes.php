<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;

try {
    $db = new Db();
    $pdo = $db->db;

    echo "Running data repair and index creation...\n";

    // 1. Delete records with empty external_id that appear to be duplicates
    // We'll keep the ones with external_id (from Excel) and remove the ones without but having same details
    // Actually, to be safe, let's just create the unique index and handle existing duplicates

    // Find duplicates first
    $duplicates = $pdo->query("
        SELECT external_id, COUNT(*) as c 
        FROM arac_yakit_kayitlari 
        WHERE external_id IS NOT NULL AND external_id != ''
        GROUP BY external_id 
        HAVING c > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($duplicates) . " duplicate External IDs.\n";

    foreach ($duplicates as $dup) {
        $extId = $dup['external_id'];
        // Keep the one with lowest ID, delete others
        $pdo->exec("DELETE FROM arac_yakit_kayitlari WHERE external_id = '$extId' AND id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM arac_yakit_kayitlari WHERE external_id = '$extId') as t)");
    }

    // 2. Add Unique Index if it doesn't exist
    $indexCheck = $pdo->query("SHOW INDEX FROM arac_yakit_kayitlari WHERE Key_name = 'idx_external_id_unique'");
    if ($indexCheck->rowCount() == 0) {
        // Drop non-unique if exists
        $pdo->exec("ALTER TABLE arac_yakit_kayitlari DROP INDEX IF EXISTS idx_external_id");
        $pdo->exec("CREATE UNIQUE INDEX idx_external_id_unique ON arac_yakit_kayitlari (external_id)");
        echo "Unique index created.\n";
    } else {
        echo "Unique index already exists.\n";
    }

    echo "Migration finished.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
