<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\PushNotificationService;
use App\Model\PushSubscriptionModel;
use App\Model\PersonelModel;
use App\Model\MesajLogModel;
use App\Model\BildirimModel;
use App\Helper\Helper;

header('Content-Type: application/json; charset=utf-8');

// Hataları ekrana basma, JSON yapısını bozar
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    $pushService = new PushNotificationService();
    $subscriptionModel = new PushSubscriptionModel();
    $personelModel = new PersonelModel();
    $mesajLogModel = new MesajLogModel();

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

                // Resim Yükleme İşlemi
                $imageUrl = null;
                if (isset($_FILES['resim']) && $_FILES['resim']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR;
                    
                    // Klasör yoksa oluştur
                    if (!is_dir($uploadDir)) {
                        if (!@mkdir($uploadDir, 0755, true)) {
                            throw new Exception('Yükleme klasörü oluşturulamadı: ' . $uploadDir);
                        }
                    }
                    
                    // Klasör yazılabilir mi kontrol et
                    if (!is_writable($uploadDir)) {
                        throw new Exception('Yükleme klasörü yazılabilir değil.');
                    }

                    $fileInfo = pathinfo($_FILES['resim']['name']);
                    $extension = strtolower($fileInfo['extension'] ?? '');
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (empty($extension) || !in_array($extension, $allowedExtensions)) {
                        throw new Exception('Geçersiz dosya formatı. Sadece resim dosyaları (jpg, jpeg, png, gif, webp) yüklenebilir.');
                    }

                    // MIME type kontrolü
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES['resim']['tmp_name']);
                    finfo_close($finfo);
                    
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($mimeType, $allowedMimes)) {
                        throw new Exception('Geçersiz dosya tipi: ' . $mimeType);
                    }

                    if ($_FILES['resim']['size'] > 2 * 1024 * 1024) { // 2MB
                        throw new Exception('Dosya boyutu 2MB\'dan büyük olamaz.');
                    }

                    $fileName = uniqid('push_') . '.' . $extension;
                    $uploadFile = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['resim']['tmp_name'], $uploadFile)) {
                        // URL oluştur - mutlak URL gerekli
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];
                        
                        // Base path'i DOCUMENT_ROOT'tan hesapla
                        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                        $uploadPath = str_replace('\\', '/', dirname(__DIR__, 2) . '/uploads/notifications/');
                        $relativePath = str_replace($docRoot, '', $uploadPath);
                        $relativePath = '/' . ltrim($relativePath, '/');

                        $imageUrl = "{$protocol}://{$host}{$relativePath}{$fileName}";
                    } else {
                        throw new Exception('Dosya yüklenirken bir hata oluştu. Hata kodu: ' . $_FILES['resim']['error']);
                    }
                } elseif (isset($_FILES['resim']) && $_FILES['resim']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Dosya seçilmiş ama hata var
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'Dosya boyutu PHP ini limitini aşıyor.',
                        UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor.',
                        UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
                        UPLOAD_ERR_CANT_WRITE => 'Dosya diske yazılamadı.',
                        UPLOAD_ERR_EXTENSION => 'PHP eklentisi dosya yüklemesini durdurdu.',
                    ];
                    $errorMsg = $uploadErrors[$_FILES['resim']['error']] ?? 'Bilinmeyen hata.';
                    throw new Exception('Resim yükleme hatası: ' . $errorMsg);
                }

                $payload = [
                    'title' => $baslik,
                    'body' => $mesaj,
                    'url' => $hedef_sayfa ?: 'index.php'
                ];

                if ($imageUrl) {
                    $payload['image'] = $imageUrl;
                }

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

                $recipientNames = [];
                if ($alici_tipi === 'toplu') {
                    $recipientNames[] = "Tüm Aboneler (" . count($personelIds) . " kişi)";
                } else {
                    foreach ($personelIds as $pid) {
                        $p = $personelModel->find($pid);
                        if ($p) {
                            $recipientNames[] = $p->adi_soyadi;
                        }
                    }
                }

                $logStatus = ($hata == 0) ? 'success' : (($gonderildi > 0) ? 'partial' : 'failed');
                $mesajLogModel->logPush(
                    $_SESSION['firma_id'] ?? 0,
                    $baslik,
                    $mesaj,
                    $recipientNames,
                    $payload,
                    $logStatus
                );

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
                    $mesajLogModel->logPush(
                        $_SESSION['firma_id'] ?? 0,
                        $payload['title'],
                        $payload['body'],
                        ['Test Kullanıcısı'],
                        $payload,
                        'success'
                    );
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Test bildirimi gönderildi!'
                    ]);
                } else {
                    $mesajLogModel->logPush(
                        $_SESSION['firma_id'] ?? 0,
                        $payload['title'],
                        $payload['body'],
                        ['Test Kullanıcısı'],
                        $payload,
                        'success'
                    );
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Test bildirimi gönderildi!'
                    ]);
                }
                break;

            // ===== In-App Notifications =====
            case 'get-unread':
                $userId = $_SESSION['user_id'] ?? 0;
                if ($userId <= 0) {
                    throw new Exception('Oturum bulunamadı.');
                }

                $BildirimModel = new BildirimModel();
                $notifications = $BildirimModel->getUnreadNotifications($userId);
                $count = count($notifications);

                // Format for frontend
                $formatted = [];
                foreach ($notifications as $n) {
                    // Timezone-aware time_ago hesaplama
                    $timeAgo = 'şimdi';
                    if (!empty($n->created_at)) {
                        try {
                            $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
                            $created = new DateTime($n->created_at, new DateTimeZone('Europe/Istanbul'));
                            $diff = $now->diff($created);

                            if ($diff->y > 0) {
                                $timeAgo = $diff->y . ' yıl önce';
                            } elseif ($diff->m > 0) {
                                $timeAgo = $diff->m . ' ay önce';
                            } elseif ($diff->d > 0) {
                                $timeAgo = $diff->d . ' gün önce';
                            } elseif ($diff->h > 0) {
                                $timeAgo = $diff->h . ' saat önce';
                            } elseif ($diff->i > 0) {
                                $timeAgo = $diff->i . ' dakika önce';
                            } else {
                                $timeAgo = 'şimdi';
                            }
                        } catch (Exception $e) {
                            $timeAgo = date('H:i', strtotime($n->created_at));
                        }
                    }

                    $formatted[] = [
                        'id' => $n->id,
                        'title' => $n->title,
                        'message' => $n->message,
                        'link' => $n->link,
                        'icon' => $n->icon,
                        'color' => $n->color,
                        'time_ago' => $timeAgo
                    ];
                }

                echo json_encode([
                    'status' => 'success',
                    'count' => $count,
                    'notifications' => $formatted
                ]);
                break;

            case 'mark-read':
                $userId = $_SESSION['user_id'] ?? 0;
                $id = $_POST['id'] ?? 0;

                if ($userId <= 0 || $id <= 0) {
                    throw new Exception('Geçersiz parametre.');
                }

                $BildirimModel = new BildirimModel();
                $BildirimModel->markAsRead($id, $userId);

                echo json_encode(['status' => 'success']);
                break;

            case 'mark-all-read':
                $userId = $_SESSION['user_id'] ?? 0;

                if ($userId <= 0) {
                    throw new Exception('Oturum bulunamadı.');
                }

                $BildirimModel = new BildirimModel();
                $BildirimModel->markAllAsRead($userId);

                echo json_encode(['status' => 'success']);
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu.'
    ]);
}
