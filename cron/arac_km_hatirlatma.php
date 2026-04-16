<?php
/**
 * Araç KM Bildirim Hatırlatma Cron Job
 * 
 * Bu dosya cron job ile sabah ve akşam saatlerinde çalıştırılmalıdır.
 * KM bildirimi yapmayan personellere sistem ve push bildirimi gönderir.
 * 
 * Cron Örneği (sabah ve akşam):
 * 0 8,17 * * * php /xampp/htdocs/ersan_elk/cron/arac_km_hatirlatma.php
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CLI ortamı kontrolü (Sadece CLI'dan çalışması için opsiyonel ama güvenli)
// if (PHP_SAPI !== 'cli') {
//     die("Bu dosya sadece komut satırından çalıştırılabilir.");
// }

// Autoloader
require_once dirname(__DIR__) . '/Autoloader.php';

use App\Model\AracKmBildirimModel;

try {
    $db = new \App\Core\Db();
    $pdo = $db->db;

    // Tüm aktif firmaları al
    $stmt = $pdo->query("SELECT id FROM firmalar WHERE silinme_tarihi IS NULL");
    $firmalar = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($firmalar)) {
        echo "[" . date('Y-m-d H:i:s') . "] Aktif firma bulunamadı.\n";
        exit;
    }

    $now = date('H:i');
    $tur = '';
    
    // Türü belirle (Cron saati geçse bile manuel çalıştırılabilir veya pencere içinde çalışır)
    // 08:00 - 11:00 arası sabah, 17:00 - 20:00 arası akşam kabul edilir
    if ($now >= '08:00' && $now < '11:00') {
        $tur = 'sabah';
    } elseif ($now >= '17:00' && $now < '20:00') {
        $tur = 'aksam';
    }

    // CLI argümanı varsa ez (Örn: php arac_km_hatirlatma.php --tur=sabah)
    if (PHP_SAPI === 'cli') {
        $options = getopt("", ["tur:"]);
        if (isset($options['tur'])) $tur = $options['tur'];
    }

    if (empty($tur)) {
        echo "[" . date('Y-m-d H:i:s') . "] Şu an bildirim gönderim saati değil (08:00-11:00 veya 17:00-20:00). Atlanıyor.\n";
        exit;
    }

    $toplamGonderilen = 0;
    $tarih = date('Y-m-d');

    echo "[" . date('Y-m-d H:i:s') . "] $tur bildirimi için hatırlatma süreci başlatıldı.\n";

    foreach ($firmalar as $firma_id) {
        // Her firma için session simüle et (Model içindeki getUnreported ve diğerleri $_SESSION['firma_id'] kullanıyor)
        $_SESSION['firma_id'] = $firma_id;
        
        $Model = new AracKmBildirimModel();
        
        // Hatırlatmaları gönder
        $gonderilen = $Model->sendBatchKmHatirlatma($tarih, $tur);
        $toplamGonderilen += $gonderilen;
        
        if ($gonderilen > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Firma #$firma_id: $gonderilen hatırlatma gönderildi.\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] İşlem tamamlandı. Toplam gonderilen: $toplamGonderilen\n";

} catch (Exception $e) {
    echo "[HATA] " . $e->getMessage() . "\n";
    error_log("Araç KM hatırlatma cron hatası: " . $e->getMessage());
}
