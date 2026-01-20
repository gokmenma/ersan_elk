<?php
/**
 * Migration Script - Sürekli Kesinti ve Ek Ödeme Alanları
 * Bu dosyayı tarayıcıda çalıştırın: /views/personel/run_migration.php
 */

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Core\Db;

$db = new Db();
$pdo = $db->db;

$results = [];

try {
    // =====================================================================
    // personel_kesintileri tablosuna yeni alanlar ekle
    // =====================================================================
    
    $columns_to_add_kesinti = [
        ['name' => 'tekrar_tipi', 'definition' => "ENUM('tek_sefer', 'surekli') NOT NULL DEFAULT 'tek_sefer'", 'after' => 'tur'],
        ['name' => 'baslangic_donemi', 'definition' => 'VARCHAR(7) NULL', 'after' => 'tekrar_tipi'],
        ['name' => 'bitis_donemi', 'definition' => 'VARCHAR(7) NULL', 'after' => 'baslangic_donemi'],
        ['name' => 'hesaplama_tipi', 'definition' => "ENUM('sabit', 'oran_net', 'oran_brut') NOT NULL DEFAULT 'sabit'", 'after' => 'bitis_donemi'],
        ['name' => 'oran', 'definition' => 'DECIMAL(5,2) NULL', 'after' => 'hesaplama_tipi'],
        ['name' => 'parametre_id', 'definition' => 'INT NULL', 'after' => 'oran'],
        ['name' => 'ana_kesinti_id', 'definition' => 'INT NULL', 'after' => 'parametre_id'],
        ['name' => 'aktif', 'definition' => 'TINYINT(1) DEFAULT 1', 'after' => 'ana_kesinti_id'],
        ['name' => 'updated_at', 'definition' => 'TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP', 'after' => null],
    ];
    
    foreach ($columns_to_add_kesinti as $col) {
        if (!columnExists($pdo, 'personel_kesintileri', $col['name'])) {
            $afterClause = $col['after'] ? " AFTER {$col['after']}" : '';
            $sql = "ALTER TABLE personel_kesintileri ADD COLUMN {$col['name']} {$col['definition']}{$afterClause}";
            $pdo->exec($sql);
            $results[] = "✅ personel_kesintileri.{$col['name']} eklendi";
        } else {
            $results[] = "⏭️ personel_kesintileri.{$col['name']} zaten mevcut";
        }
    }

    // =====================================================================
    // personel_ek_odemeler tablosuna yeni alanlar ekle
    // =====================================================================
    
    $columns_to_add_ek_odeme = [
        ['name' => 'tekrar_tipi', 'definition' => "ENUM('tek_sefer', 'surekli') NOT NULL DEFAULT 'tek_sefer'", 'after' => 'tur'],
        ['name' => 'baslangic_donemi', 'definition' => 'VARCHAR(7) NULL', 'after' => 'tekrar_tipi'],
        ['name' => 'bitis_donemi', 'definition' => 'VARCHAR(7) NULL', 'after' => 'baslangic_donemi'],
        ['name' => 'hesaplama_tipi', 'definition' => "ENUM('sabit', 'oran_net', 'oran_brut') NOT NULL DEFAULT 'sabit'", 'after' => 'bitis_donemi'],
        ['name' => 'oran', 'definition' => 'DECIMAL(5,2) NULL', 'after' => 'hesaplama_tipi'],
        ['name' => 'parametre_id', 'definition' => 'INT NULL', 'after' => 'oran'],
        ['name' => 'ana_odeme_id', 'definition' => 'INT NULL', 'after' => 'parametre_id'],
        ['name' => 'aktif', 'definition' => 'TINYINT(1) DEFAULT 1', 'after' => 'ana_odeme_id'],
        ['name' => 'updated_at', 'definition' => 'TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP', 'after' => null],
    ];
    
    foreach ($columns_to_add_ek_odeme as $col) {
        if (!columnExists($pdo, 'personel_ek_odemeler', $col['name'])) {
            $afterClause = $col['after'] ? " AFTER {$col['after']}" : '';
            $sql = "ALTER TABLE personel_ek_odemeler ADD COLUMN {$col['name']} {$col['definition']}{$afterClause}";
            $pdo->exec($sql);
            $results[] = "✅ personel_ek_odemeler.{$col['name']} eklendi";
        } else {
            $results[] = "⏭️ personel_ek_odemeler.{$col['name']} zaten mevcut";
        }
    }

    // =====================================================================
    // bordro_parametreleri tablosuna oran alanı ekle
    // =====================================================================
    
    if (!columnExists($pdo, 'bordro_parametreleri', 'oran')) {
        $pdo->exec("ALTER TABLE bordro_parametreleri ADD COLUMN oran DECIMAL(5,2) NULL AFTER varsayilan_tutar");
        $results[] = "✅ bordro_parametreleri.oran eklendi";
    } else {
        $results[] = "⏭️ bordro_parametreleri.oran zaten mevcut";
    }

    // =====================================================================
    // BES parametresi ekle (eğer yoksa)
    // =====================================================================
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bordro_parametreleri WHERE kod = 'bes'");
    $stmt->execute();
    $besExists = $stmt->fetchColumn() > 0;
    
    if (!$besExists) {
        $pdo->exec("INSERT INTO bordro_parametreleri (kod, etiket, kategori, hesaplama_tipi, oran, varsayilan_tutar, sira, aktif, gecerlilik_baslangic) 
                    VALUES ('bes', 'Bireysel Emeklilik (BES)', 'kesinti', 'net', 3.00, 0, 5, 1, '2026-01-01')");
        $results[] = "✅ BES parametresi eklendi";
    } else {
        $results[] = "⏭️ BES parametresi zaten mevcut";
    }

    $status = 'success';
    $message = 'Migration başarıyla tamamlandı!';

} catch (Exception $e) {
    $status = 'error';
    $message = 'Hata: ' . $e->getMessage();
    $results[] = "❌ " . $e->getMessage();
}

/**
 * Kolonun var olup olmadığını kontrol eder
 */
function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() > 0;
}

// HTML Çıktı
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration Script - Sürekli Kesinti</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .status { padding: 15px; border-radius: 5px; margin: 20px 0; }
        .status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .results { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .results ul { list-style: none; padding: 0; margin: 0; }
        .results li { padding: 8px 0; border-bottom: 1px solid #eee; }
        .results li:last-child { border-bottom: none; }
        .back-link { margin-top: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Migration Script</h1>
        <h2>Sürekli Kesinti ve Ek Ödeme Desteği</h2>
        
        <div class="status <?= $status ?>">
            <strong><?= $status === 'success' ? '✅' : '❌' ?> <?= $message ?></strong>
        </div>
        
        <div class="results">
            <h3>İşlem Detayları:</h3>
            <ul>
                <?php foreach ($results as $result): ?>
                    <li><?= $result ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="back-link">
            <a href="/index.php">← Ana Sayfaya Dön</a>
        </div>
    </div>
</body>
</html>
