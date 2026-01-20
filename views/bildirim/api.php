<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\PushNotificationService;
use App\Model\PushSubscriptionModel;
use App\Model\PersonelModel;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    $pushService = new PushNotificationService();
    $subscriptionModel = new PushSubscriptionModel();
    $personelModel = new PersonelModel();

    try {
        switch ($action) {
            case 'send-notification':
                $alici_tipi = $_POST['alici_tipi'] ?? 'tekli';
                $baslik = trim($_POST['baslik'] ?? '');
                $mesaj = trim($_POST['mesaj'] ?? '');
                $hedef_sayfa = $_POST['hedef_sayfa'] ?? '';

                if (empty($baslik) || empty($mesaj)) {
                    throw new Exception('Başlık ve mesaj zorunludur.');
                }

                $payload = [
                    'title' => $baslik,
                    'body' => $mesaj,
                    'url' => $hedef_sayfa ?: 'index.php'
                ];

                $gonderildi = 0;
                $hata = 0;

                if ($alici_tipi === 'toplu') {
                    // Tüm abonelere gönder
                    $db = $subscriptionModel->getDb();
                    $stmt = $db->query("SELECT DISTINCT personel_id FROM push_subscriptions");
                    $personelIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($personelIds as $pid) {
                        try {
                            if ($pushService->sendToPersonel($pid, $payload)) {
                                $gonderildi++;
                            } else {
                                $hata++;
                            }
                        } catch (Exception $e) {
                            $hata++;
                        }
                    }
                } else {
                    // Seçili personellere gönder
                    $personelIds = $_POST['personel_ids'] ?? [];

                    if (empty($personelIds)) {
                        throw new Exception('Lütfen en az bir personel seçin.');
                    }

                    foreach ($personelIds as $pid) {
                        try {
                            if ($pushService->sendToPersonel($pid, $payload)) {
                                $gonderildi++;
                            } else {
                                $hata++;
                            }
                        } catch (Exception $e) {
                            $hata++;
                        }
                    }
                }

                $message = "Bildirim gönderildi. Başarılı: $gonderildi";
                if ($hata > 0) {
                    $message .= ", Hatalı: $hata";
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $message,
                    'gonderildi' => $gonderildi,
                    'hata' => $hata
                ]);
                break;

            case 'test-notification':
                // Mevcut kullanıcının personel_id'sini bul
                $user_id = $_SESSION['user_id'] ?? 0;

                // Test için örnek payload
                $payload = [
                    'title' => '🔔 Test Bildirimi',
                    'body' => 'Bu bir test bildirimidir. Bildirimler düzgün çalışıyor!',
                    'url' => 'index.php'
                ];

                // Eğer kullanıcının kendisi bir personelse ve aboneliği varsa
                // Abone olan herhangi birine gönder
                $db = $subscriptionModel->getDb();
                $stmt = $db->query("SELECT DISTINCT personel_id FROM push_subscriptions LIMIT 1");
                $testPersonelId = $stmt->fetchColumn();

                if (!$testPersonelId) {
                    throw new Exception('Henüz hiç bildirim aboneliği yok. Önce PWA uygulamasında bildirim iznini verin.');
                }

                if ($pushService->sendToPersonel($testPersonelId, $payload)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Test bildirimi gönderildi!'
                    ]);
                } else {
                    throw new Exception('Test bildirimi gönderilemedi.');
                }
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu.'
    ]);
}
