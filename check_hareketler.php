<?php
/**
 * Veritabanı kontrol scripti
 */

require_once __DIR__ . '/Autoloader.php';

use App\Core\Db;

$dbInstance = new Db();
$db = $dbInstance->db;

echo "=== Personel Hareketleri Tablosu Kontrolü ===\n\n";

try {
    // Tablo var mı kontrol
    $stmt = $db->query("SHOW TABLES LIKE 'personel_hareketleri'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "✅ Tablo mevcut\n\n";

        // Kayıt sayısı
        $stmt = $db->query("SELECT COUNT(*) as toplam FROM personel_hareketleri");
        $toplam = $stmt->fetch(PDO::FETCH_OBJ)->toplam;
        echo "📊 Toplam kayıt: {$toplam}\n\n";

        // Son 5 kayıt
        $stmt = $db->query("SELECT ph.*, p.adi_soyadi 
                           FROM personel_hareketleri ph 
                           LEFT JOIN personel p ON ph.personel_id = p.id 
                           ORDER BY ph.zaman DESC LIMIT 5");
        $kayitlar = $stmt->fetchAll(PDO::FETCH_OBJ);

        if (!empty($kayitlar)) {
            echo "📋 Son 5 kayıt:\n";
            foreach ($kayitlar as $k) {
                echo "  - {$k->adi_soyadi} | {$k->islem_tipi} | {$k->zaman} | ({$k->konum_enlem}, {$k->konum_boylam})\n";
            }
        }
    } else {
        echo "❌ Tablo bulunamadı. Oluşturuluyor...\n";

        $sql = "CREATE TABLE IF NOT EXISTS `personel_hareketleri` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `personel_id` INT NOT NULL,
            `islem_tipi` ENUM('BASLA', 'BITIR') NOT NULL,
            `zaman` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `konum_enlem` DECIMAL(10, 7) NOT NULL,
            `konum_boylam` DECIMAL(10, 7) NOT NULL,
            `konum_hassasiyeti` DECIMAL(10, 2) NULL,
            `cihaz_bilgisi` VARCHAR(500) NULL,
            `ip_adresi` VARCHAR(45) NULL,
            `firma_id` INT NULL,
            `silinme_tarihi` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_personel_tarih` (`personel_id`, `zaman`),
            INDEX `idx_islem_tipi` (`islem_tipi`),
            INDEX `idx_firma_tarih` (`firma_id`, `zaman`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($sql);
        echo "✅ Tablo oluşturuldu!\n";
    }

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>