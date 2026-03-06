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
    $Settings = new \App\Model\SettingsModel();

    $offset = (int) ($Settings->getSettings('gorev_bildirim_dakika') ?? 0);
    webhookLog("Sistem Zamanı: " . date('H:i:s') . " | Tarih: " . date('d-m-Y'));
    webhookLog("Bildirim Offset: $offset dakika");

    // Bildirim bekleyen (Saati gelmiş/yaklaşmış) görevleri al
    $bekleyenGorevler = $gorevModel->getBildirimBekleyenGorevler();

    // Özel bildirim alacak kullanıcıları ayardan al
    $recipientsSetting = $Settings->getSettings('gorev_bildirim_kullanicilar');
    $targetUserIds = [];
    if (!empty($recipientsSetting)) {
        $encryptedIds = explode(',', $recipientsSetting);
        foreach ($encryptedIds as $encId) {
            $decId = \App\Helper\Security::decrypt(trim($encId));
            if ($decId) {
                $targetUserIds[] = (int) $decId;
            }
        }
    }

    if (empty($bekleyenGorevler)) {
        webhookLog("Bildirim bekleyen görev yok.");
        echo json_encode(['success' => true, 'message' => 'Bekleyen görev yok.']);
    } else {
        webhookLog(count($bekleyenGorevler) . " görev için bildirim süreci başladı.");

        $pushService = new PushNotificationService();
        $basarili = 0;
        $basarisiz = 0;

        foreach ($bekleyenGorevler as $gorev) {
            if (!$gorev || !isset($gorev->id))
                continue;

            try {
                webhookLog("İşlenen Görev ID: #{$gorev->id} | Tip: {$gorev->bildirim_tipi} | Başlık: {$gorev->baslik}");

                // Bildirim mesajını hazırla
                $saatStr = ' (Saat: ' . substr($gorev->saat ?? '09:00:00', 0, 5) . ')';
                $listeStr = !empty($gorev->liste_adi) ? ' [' . $gorev->liste_adi . ']' : '';

                if ($gorev->bildirim_tipi === 'on') {
                    $title = "⏳ Göreve $offset Dakika Var";
                    $body = "Hatırlatma: " . $gorev->baslik . $saatStr . $listeStr;
                } else {
                    $title = '🔔 Görev Zamanı Geldi';
                    $body = "Zamanı Geldi: " . $gorev->baslik . $saatStr . $listeStr;
                }

                $link = 'index.php?p=gorevler/list';

                // Eğer ayarlardan kullanıcı seçilmişse onlara, seçilmemişse görev/liste sahibine gönder
                $usersToNotify = !empty($targetUserIds) ? $targetUserIds : [$gorev->olusturan_id ?? $gorev->liste_olusturan_id];
                $usersToNotify = array_unique(array_filter($usersToNotify));

                if (!empty($usersToNotify)) {
                    foreach ($usersToNotify as $userId) {
                        webhookLog("  - Personel #{$userId} için bildirim+mail tetikleniyor...");

                        // In-app bildirim oluştur
                        $bildirimModel->createNotification($userId, $title, $body, $link, 'task', 'warning');

                        // Push ve Mail gönder
                        $payload = [
                            'title' => $title,
                            'body' => $body,
                            'url' => $link,
                            'icon' => '/assets/images/logo-sm.png',
                            'badge' => '/assets/images/logo-sm.png'
                        ];
                        $pushService->sendToPersonel($userId, $payload);
                        webhookLog("  ✓ Görev #{$gorev->id} → Personel #{$userId} başarıyla işlendi.");
                    }
                } else {
                    webhookLog("  ⚠ Görev #{$gorev->id} → Alıcı bulunamadı, atlanıyor.");
                }

                // Gönderildi işareti koy
                $gorevModel->markBildirimGonderildi($gorev->id, $gorev->bildirim_tipi);
                $basarili++;

            } catch (Exception $e) {
                webhookLog("  ✗ Görev #{$gorev->id} hatası: " . $e->getMessage());
                $basarisiz++;

                try {
                    $gorevModel->markBildirimGonderildi($gorev->id, $gorev->bildirim_tipi ?? 'tam');
                } catch (Exception $e2) {
                    webhookLog("    Flag güncelleme hatası: " . $e2->getMessage());
                }
            }
        }

        webhookLog("Sonuç: $basarili görev işlendi, $basarisiz hata.");

        // =====================================================
        // 3. RAPORLAMA (KULLANICI TALEBİ)
        // =====================================================
        $reportEmail = "beyzade83@hotmail.com";
        $reportSubject = "Ersan Elk - Görev Bildirim Raporu (" . date('H:i') . ")";

        $reportHtmlContent = "<p><b>Tarih:</b> " . date('d.m.Y H:i:s') . "</p>";
        $reportHtmlContent .= "<p>İşlem özeti aşağıdadır:</p>";
        $reportHtmlContent .= "<ul>
            <li><b>İşlenen Görev Sayısı:</b> <span class='highlight'>$basarili</span></li>
            <li><b>Hata Sayısı:</b> <span style='color: #ef4444; font-weight: 600;'>$basarisiz</span></li>
        </ul>";

        if ($basarili > 0 || $basarisiz > 0) {
            try {
                $reportFullHtml = \App\Helper\EmailTemplateHelper::getTemplate(
                    "Görev Bildirim Raporu",
                    $reportHtmlContent
                );

                \App\Service\MailGonderService::gonder(
                    [$reportEmail],
                    $reportSubject,
                    $reportFullHtml
                );
                webhookLog("Rapor maili başarıyla gönderildi: $reportEmail");
            } catch (Exception $e) {
                webhookLog("Rapor maili GÖNDERİLEMEDİ: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Bildirimler işlendi.',
            'stats' => ['processed' => $basarili, 'failed' => $basarisiz]
        ]);
    }

} catch (Exception $e) {
    webhookLog("HATA: " . $e->getMessage());

    // Kritik hataları da raporla
    try {
        $errorContent = "<p>Sistemde kritik bir hata meydana geldi:</p>";
        $errorContent .= "<div style='background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; color: #991b1b; font-family: monospace;'>{$e->getMessage()}</div>";
        $errorContent .= "<p>Zaman: " . date('d.m.Y H:i:s') . "</p>";

        $errorFullHtml = \App\Helper\EmailTemplateHelper::getTemplate(
            "Kritik Sistem Hatası",
            $errorContent
        );

        \App\Service\MailGonderService::gonder(
            ["beyzade83@hotmail.com"],
            "Ersan Elk - KRİTİK CRON HATASI",
            $errorFullHtml
        );
    } catch (Exception $mailErr) {
        webhookLog("Kritik hata raporlanamadı: " . $mailErr->getMessage());
    }

    echo json_encode(['success' => false, 'message' => 'Sunucu Hatası: ' . $e->getMessage()]);
}

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2);
webhookLog("Webhook Cron Tamamlandı. Süre: {$executionTime}ms\n");
