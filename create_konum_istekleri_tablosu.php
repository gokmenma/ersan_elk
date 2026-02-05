<?php
/**
 * Konum İstekleri Tablosu Oluşturma
 */
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;

$db = (new Db())->db;

echo "=== Konum İstekleri Tablosu Kurulumu ===\n\n";

try {
    $sql = "CREATE TABLE IF NOT EXISTS `personel_konum_istekleri` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `personel_id` INT NOT NULL,
        `istek_zamani` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `yanit_zamani` DATETIME NULL,
        `durum` ENUM('BEKLIYOR', 'TAMAMLANDI', 'HATA') DEFAULT 'BEKLIYOR',
        `enlem` DECIMAL(10, 7) NULL,
        `boylam` DECIMAL(10, 7) NULL,
        `hata_mesaji` VARCHAR(255) NULL,
        INDEX `idx_personel_durum` (`personel_id`, `durum`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "✅ personel_konum_istekleri tablosu oluşturuldu!\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>