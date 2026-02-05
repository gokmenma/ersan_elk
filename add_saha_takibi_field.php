<?php
/**
 * Personel tablosuna saha_takibi alanı ekle
 */
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;

$db = (new Db())->db;

echo "=== Saha Takibi Alanı Ekleme ===\n\n";

try {
    // Sütun var mı kontrol et
    $stmt = $db->query("SHOW COLUMNS FROM personel LIKE 'saha_takibi'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Sütun zaten mevcut.\n";
    } else {
        // Sütun ekle
        $db->exec("ALTER TABLE personel ADD COLUMN saha_takibi TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Saha takibi yapılacak mı?' AFTER aktif_mi");
        echo "✅ saha_takibi sütunu eklendi!\n";
    }

    // İlk 20 personeli saha takibine ekle (test için)
    $db->exec("UPDATE personel SET saha_takibi = 1 WHERE aktif_mi = 1 LIMIT 20");
    echo "✅ 20 personel saha takibine eklendi.\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
