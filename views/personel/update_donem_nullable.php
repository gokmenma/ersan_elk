<?php
/**
 * donem_id kolonlarını NULL olabilir yapan migration
 */

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Core\Db;

$db = new Db();
$pdo = $db->db;

try {
    // personel_kesintileri tablosunda donem_id NULL olabilir yap
    $pdo->exec('ALTER TABLE personel_kesintileri MODIFY COLUMN donem_id INT NULL');
    echo "✅ personel_kesintileri.donem_id NULL olabilir yapıldı\n";

    // personel_ek_odemeler tablosunda donem_id NULL olabilir yap
    $pdo->exec('ALTER TABLE personel_ek_odemeler MODIFY COLUMN donem_id INT NULL');
    echo "✅ personel_ek_odemeler.donem_id NULL olabilir yapıldı\n";

    echo "\n=== Tamamlandı! ===\n";
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
