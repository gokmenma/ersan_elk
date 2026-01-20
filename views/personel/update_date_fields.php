<?php
/**
 * Migration Script - Tarih Alanlarını DATE Tipine Güncelle
 */

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Core\Db;

$db = new Db();
$pdo = $db->db;

$results = [];

try {
    // personel_kesintileri tablosu için
    $pdo->exec("ALTER TABLE personel_kesintileri MODIFY COLUMN baslangic_donemi DATE NULL");
    $results[] = "✅ personel_kesintileri.baslangic_donemi DATE olarak güncellendi";

    $pdo->exec("ALTER TABLE personel_kesintileri MODIFY COLUMN bitis_donemi DATE NULL");
    $results[] = "✅ personel_kesintileri.bitis_donemi DATE olarak güncellendi";

    // personel_ek_odemeler tablosu için
    $pdo->exec("ALTER TABLE personel_ek_odemeler MODIFY COLUMN baslangic_donemi DATE NULL");
    $results[] = "✅ personel_ek_odemeler.baslangic_donemi DATE olarak güncellendi";

    $pdo->exec("ALTER TABLE personel_ek_odemeler MODIFY COLUMN bitis_donemi DATE NULL");
    $results[] = "✅ personel_ek_odemeler.bitis_donemi DATE olarak güncellendi";

    $status = 'success';
    $message = 'Tarih alanları başarıyla güncellendi!';

} catch (Exception $e) {
    $status = 'error';
    $message = 'Hata: ' . $e->getMessage();
    $results[] = "❌ " . $e->getMessage();
}

// Çıktı
echo "=== TARIH ALANLARI GÜNCELLEMESİ ===\n";
echo "Durum: $status\n";
echo "Mesaj: $message\n\n";
foreach ($results as $result) {
    echo $result . "\n";
}
