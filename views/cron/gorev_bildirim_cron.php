<?php
/**
 * Görev Bildirim Cron Job
 * 
 * Bu dosya her 15 dakikada bir çalıştırılmalıdır.
 * Bugün tarihli ve saati gelmiş görevler için bildirim gönderir.
 * 
 * Push bildirim başarısız olursa otomatik email fallback çalışır.
 * 
 * Cron kurulumu (Windows Görev Zamanlayıcı):
 * "C:\xampp\php\php.exe" "C:\xampp\htdocs\ersan_elk\views\cron\gorev_bildirim_cron.php"
 */

// Gerekli dosyaları yükle
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Saat dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

use App\Model\GorevModel;
use App\Model\BildirimModel;
use App\Service\PushNotificationService;

// CLI modunda mı çalışıyor?
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo "Bu dosya sadece komut satırı (CLI) üzerinden çalıştırılabilir.";
    exit;
}

// Başlangıç zamanı
$startTime = microtime(true);
$logDate = date('Y-m-d H:i:s');

// Log dizinini oluştur
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log fonksiyonu
function cronLog($message)
{
    global $logDate;
    $logFile = __DIR__ . '/logs/gorev_bildirim_cron_' . date('Y-m-d') . '.log';
    $logMessage = "[$logDate] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

cronLog("=== Görev Bildirim Cron başlatıldı ===");

try {
    // Session simülasyonu (cron'da session olmaz)
    if (!isset($_SESSION)) {
        $_SESSION = [];
    }

    $gorevModel = new GorevModel();
    $bildirimModel = new BildirimModel();

    // Bildirim bekleyen görevleri al
    $bekleyenGorevler = $gorevModel->getBildirimBekleyenGorevler();

    if (empty($bekleyenGorevler)) {
        cronLog("Bildirim bekleyen görev yok.");
    } else {
        cronLog(count($bekleyenGorevler) . " görev için bildirim gönderilecek.");

        // Push notification servisini bir kez oluştur
        $pushService = new PushNotificationService();
        $basarili = 0;
        $basarisiz = 0;

        foreach ($bekleyenGorevler as $gorev) {
            try {
                // Bildirim mesajını hazırla
                $saatStr = $gorev->saat ? ' (Saat: ' . substr($gorev->saat, 0, 5) . ')' : '';
                $listeStr = $gorev->liste_adi ? ' [' . $gorev->liste_adi . ']' : '';

                $title = '📋 Görev Hatırlatması';
                $body = $gorev->baslik . $saatStr . $listeStr;
                $link = 'index.php?p=gorevler/list';

                // 1. Bilirimm gidecek kullanıcıları belirle
                $targetUserIds = [];
                
                // Görevi oluşturan veya liste sahibini ekle
                if ($gorev->olusturan_id) $targetUserIds[] = (int)$gorev->olusturan_id;
                else if ($gorev->liste_olusturan_id) $targetUserIds[] = (int)$gorev->liste_olusturan_id;
                
                // Göreve atanan kullanıcılar varsa onları da ekle
                if (!empty($gorev->gorev_kullanicilari)) {
                    $atamaIds = explode(',', $gorev->gorev_kullanicilari);
                    foreach ($atamaIds as $aid) {
                        $aid = (int)trim($aid);
                        if ($aid > 0) $targetUserIds[] = $aid;
                    }
                }
                
                $targetUserIds = array_unique($targetUserIds);

                if (empty($targetUserIds)) {
                    cronLog("  ⚠ Görev #{$gorev->id} '{$gorev->baslik}' → Kullanıcı ID bulunamadı, atlanıyor.");
                    $gorevModel->markBildirimGonderildi($gorev->id);
                    continue;
                }

                foreach ($targetUserIds as $userId) {
                    // In-app bildirim oluştur
                    $bildirimModel->createNotification(
                        $userId,
                        $title,
                        $body,
                        $link,
                        'task',
                        'warning'
                    );

                    // Push bildirim gönder
                    $payload = [
                        'title' => $title,
                        'body' => $body,
                        'url' => $link,
                        'icon' => '/assets/images/logo-sm.png',
                        'badge' => '/assets/images/logo-sm.png'
                    ];

                    $pushService->sendToUser($userId, $payload);
                    cronLog("  ✓ Görev #{$gorev->id} '{$gorev->baslik}' → Kullanıcı #{$userId} bildirim gönderildi.");
                }

                // 3. Flag'i güncelle (kullanıcı bulunamasa bile tekrar denememek için)
                $gorevModel->markBildirimGonderildi($gorev->id);
                $basarili++;

            } catch (Exception $e) {
                cronLog("  ✗ Görev #{$gorev->id} hatası: " . $e->getMessage());
                $basarisiz++;

                // Hata olsa bile flag'i güncelle (sonsuz döngüye girmesin)
                try {
                    $gorevModel->markBildirimGonderildi($gorev->id);
                } catch (Exception $e2) {
                    cronLog("    Flag güncelleme hatası: " . $e2->getMessage());
                }
            }
        }

        cronLog("Sonuç: $basarili başarılı, $basarisiz başarısız.");
    }

} catch (Exception $e) {
    cronLog("HATA: " . $e->getMessage());
    cronLog("Stack trace: " . $e->getTraceAsString());
}

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2);
cronLog("Görev Bildirim Cron tamamlandı. Süre: {$executionTime}ms");
cronLog("========================================\n");
