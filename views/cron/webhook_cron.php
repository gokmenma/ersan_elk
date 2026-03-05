<?php
/**
 * Tam Zamanlı Görev Bildirim Webhook'u
 * 
 * Bu dosya dışarıdaki bir Uptime veya WebCron servisi (örn: cron-job.org)
 * tarafından HER 1 DAKİKADA BİR çağrılmak üzere tasarlanmıştır.
 * Paylaşımlı hostinglerdeki 15 dakikalık cron sınırını aşmamızı sağlar.
 * 
 * URL Formatı: https://siteniz.com/views/cron/webhook_cron.php?token=GIZLI_SIFRE
 */

// Gerekli dosyaları yükle
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Saat dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

use App\Model\GorevModel;
use App\Model\BildirimModel;
use App\Service\PushNotificationService;

// =====================================================
// 1. GÜVENLİK KONTROLÜ (TOKEN)
// =====================================================
// Bu şifreyi kimseyle paylaşmayın. Sadece cron-job.org'a vereceğiniz URL'de yer alacak.
$secretToken = "ErsanElk_Cron_987654321";

if (!isset($_GET['token']) || $_GET['token'] !== $secretToken) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz Erişim. Geçersiz Token.']);
    exit;
}

// =====================================================
// 2. İŞ MANTIĞI & BİLDİRİM GÖNDERİMİ
// =====================================================
$startTime = microtime(true);
$logDate = date('Y-m-d H:i:s');

// Log dizinini oluştur
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function webhookLog($message)
{
    global $logDate;
    $logFile = __DIR__ . '/logs/webhook_bildirim_' . date('Y-m-d') . '.log';
    $logMessage = "[$logDate] [WEBHOOK] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

header('Content-Type: application/json');
webhookLog("=== Webhook Bildirim Tetiklendi ===");

try {
    // Session simülasyonu
    if (!isset($_SESSION)) {
        $_SESSION = [];
    }

    $gorevModel = new GorevModel();
    $bildirimModel = new BildirimModel();

    // Bildirim bekleyen (Saati gelmiş) görevleri al
    $bekleyenGorevler = $gorevModel->getBildirimBekleyenGorevler();

    if (empty($bekleyenGorevler)) {
        webhookLog("Bildirim bekleyen görev yok.");
        echo json_encode(['success' => true, 'message' => 'Bekleyen görev yok.']);
    } else {
        webhookLog(count($bekleyenGorevler) . " görev için bildirim gönderilecek.");

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

                $userId = $gorev->olusturan_id ?? $gorev->liste_olusturan_id;

                if ($userId) {
                    // In-app bildirim oluştur
                    $bildirimModel->createNotification($userId, $title, $body, $link, 'task', 'warning');

                    // Push bildirim gönder (başarısız olursa email fallback servisi otomatik çalışır)
                    $payload = [
                        'title' => $title,
                        'body' => $body,
                        'url' => $link,
                        'icon' => '/assets/images/logo-sm.png',
                        'badge' => '/assets/images/logo-sm.png'
                    ];
                    $pushService->sendToPersonel($userId, $payload);

                    webhookLog("  ✓ Görev #{$gorev->id} → Personel #{$userId} bildirim gönderildi.");
                } else {
                    webhookLog("  ⚠ Görev #{$gorev->id} → Kullanıcı ID bulunamadı, atlanıyor.");
                }

                // Gönderildi işareti koy
                $gorevModel->markBildirimGonderildi($gorev->id);
                $basarili++;

            } catch (Exception $e) {
                webhookLog("  ✗ Görev #{$gorev->id} hatası: " . $e->getMessage());
                $basarisiz++;

                try {
                    $gorevModel->markBildirimGonderildi($gorev->id);
                } catch (Exception $e2) {
                    webhookLog("    Flag güncelleme hatası: " . $e2->getMessage());
                }
            }
        }

        webhookLog("Sonuç: $basarili başarılı, $basarisiz başarısız.");
        echo json_encode([
            'success' => true,
            'message' => 'Bildirimler işlendi.',
            'stats' => ['success' => $basarili, 'failed' => $basarisiz]
        ]);
    }

} catch (Exception $e) {
    webhookLog("HATA: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Sunucu Hatası: ' . $e->getMessage()]);
}

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2);
webhookLog("Webhook Cron Tamamlandı. Süre: {$executionTime}ms\n");
