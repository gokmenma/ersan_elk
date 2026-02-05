<?php
/**
 * Nöbet Hatırlatma Cron Job
 * 
 * Bu dosya cron job ile düzenli aralıklarla çalıştırılmalıdır.
 * Yaklaşan nöbetler için personellere PWA push bildirimi gönderir.
 * 
 * Cron Örneği (her saat başı):
 * 0 * * * * php /path/to/ersan_elk/cron/nobet_hatirlatma.php
 * 
 * Cron Örneği (her 6 saatte bir):
 * 0 0,6,12,18 * * * php /path/to/ersan_elk/cron/nobet_hatirlatma.php
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session simülasyonu (cron için)
$_SESSION = [];

// Autoloader
require_once dirname(__DIR__) . '/Autoloader.php';

use App\Model\NobetModel;

// Firma ID'leri için veritabanından tüm firmaları al
try {
    $db = new \App\Core\Db();
    $pdo = $db->db;

    // Tüm aktif firmaları al
    $stmt = $pdo->query("SELECT id FROM firmalar WHERE silinme_tarihi IS NULL");
    $firmalar = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $toplamGonderilen = 0;

    foreach ($firmalar as $firma_id) {
        // Her firma için session simüle et
        $_SESSION['firma_id'] = $firma_id;

        $Nobet = new NobetModel();

        // 24 saat içindeki nöbetler için hatırlatma gönder
        $gonderilen = $Nobet->sendNobetHatirlatmaBildirimleri(24);
        $toplamGonderilen += $gonderilen;

        echo "[" . date('Y-m-d H:i:s') . "] Firma #$firma_id: $gonderilen bildirim gönderildi.\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Toplam: $toplamGonderilen bildirim gönderildi.\n";

} catch (Exception $e) {
    echo "[HATA] " . $e->getMessage() . "\n";
    error_log("Nöbet hatırlatma cron hatası: " . $e->getMessage());
}
