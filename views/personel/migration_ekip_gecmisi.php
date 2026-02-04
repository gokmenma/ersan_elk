<?php
/**
 * Migration Script - Personel Ekip Geçmişi
 */

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Core\Db;

$db = new Db();
$pdo = $db->db;

$results = [];

try {
    // =====================================================================
    // personel_ekip_gecmisi tablosunu oluştur
    // =====================================================================
    
    $sql = "CREATE TABLE IF NOT EXISTS personel_ekip_gecmisi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        personel_id INT NOT NULL,
        ekip_kodu_id INT NOT NULL,
        baslangic_tarihi DATE NOT NULL,
        bitis_tarihi DATE NULL,
        firma_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_personel_firma (personel_id, firma_id),
        INDEX idx_tarih (baslangic_tarihi, bitis_tarihi)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    $results[] = "✅ personel_ekip_gecmisi tablosu oluşturuldu veya zaten mevcut";

    // Mevcut personel verilerini başlangıç olarak geçmişe aktar (opsiyonel ama mantıklı)
    // Eğer geçmiş tablosu boşsa, personellerin mevcut ekip kodlarını işe giriş tarihleriyle beraber aktaralım
    
    $checkSql = "SELECT COUNT(*) FROM personel_ekip_gecmisi";
    $count = $pdo->query($checkSql)->fetchColumn();
    
    if ($count == 0) {
        $insertSql = "INSERT INTO personel_ekip_gecmisi (personel_id, ekip_kodu_id, baslangic_tarihi, firma_id)
                      SELECT id, ekip_no, COALESCE(ise_giris_tarihi, CURDATE()), firma_id 
                      FROM personel 
                      WHERE ekip_no IS NOT NULL AND ekip_no > 0";
        $affected = $pdo->exec($insertSql);
        $results[] = "✅ $affected personelin mevcut ekip kodu geçmiş tablosuna başlangıç olarak eklendi.";
    }

    $status = 'success';
    $message = 'Migration başarıyla tamamlandı!';

} catch (Exception $e) {
    $status = 'error';
    $message = 'Hata: ' . $e->getMessage();
    $results[] = "❌ " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Migration - Ekip Geçmişi</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status.success { color: green; }
        .status.error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Migration Script</h1>
        <div class="status <?= $status ?>">
            <strong><?= $message ?></strong>
        </div>
        <ul>
            <?php foreach ($results as $result): ?>
                <li><?= $result ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="/index.php">← Ana Sayfa</a>
    </div>
</body>
</html>
