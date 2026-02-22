<?php
/**
 * Canlı Destek Sistemi - Yönetici API
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\DestekModel;
use App\Model\PersonelModel;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Yönetici oturum kontrolü
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek']);
    exit;
}

$action = $_POST['action'] ?? '';
$destekModel = new DestekModel();

try {
    switch ($action) {

        // Aktif konuşmaları listele
        case 'get-conversations':
            $conversations = $destekModel->getActiveConversations();
            $totalUnread = $destekModel->getTotalUnreadForAdmin();

            echo json_encode([
                'status' => 'success',
                'conversations' => $conversations,
                'total_unread' => $totalUnread
            ]);
            break;

        // Tüm konuşmaları listele (filtrelenebilir)
        case 'get-all-conversations':
            $durum = $_POST['durum'] ?? null;
            $conversations = $destekModel->getAllConversations($durum);

            echo json_encode([
                'status' => 'success',
                'conversations' => $conversations
            ]);
            break;

        // Konuşma mesajlarını getir
        case 'get-messages':
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            $afterId = (int) ($_POST['after_id'] ?? 0);

            if (!$konusmaId) {
                throw new Exception('Konuşma ID gerekli');
            }

            $conversation = $destekModel->getConversation($konusmaId);
            $messages = $destekModel->getMessages($konusmaId, $afterId);

            // Mesajları okundu olarak işaretle
            $destekModel->markMessagesAsRead($konusmaId, 'yonetici');

            $opponentLastReadId = $destekModel->getOpponentLastReadId($konusmaId, 'yonetici');

            echo json_encode([
                'status' => 'success',
                'conversation' => $conversation,
                'messages' => $messages,
                'opponent_last_read_id' => $opponentLastReadId
            ]);
            break;

        // Mesaj gönder
        case 'send-message':
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            $mesaj = trim($_POST['mesaj'] ?? '');

            if (!$konusmaId || empty($mesaj)) {
                throw new Exception('Konuşma ID ve mesaj gerekli');
            }

            $userId = $_SESSION['user_id'] ?? $_SESSION['user']->id ?? 0;

            // Dosya yükleme kontrolü
            $dosyaUrl = null;
            $dosyaTip = null;
            if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = handleFileUpload($_FILES['dosya']);
                $dosyaUrl = $uploadResult['url'];
                $dosyaTip = $uploadResult['type'];
            }

            $mesajId = $destekModel->sendMessage($konusmaId, [
                'tip' => 'yonetici',
                'id' => $userId
            ], $mesaj, $dosyaUrl, $dosyaTip);

            // Yönetici mesaj attığında durumu otomatikman çevrimiçi yap
            $settingsModel = new \App\Model\SettingsModel();
            $settingsModel->upsertSetting('canli_destek_admin_durum', 'cevrimici');

            // Push bildirim gönder
            try {
                $conversation = $destekModel->getConversation($konusmaId);
                if ($conversation) {
                    $pushService = new PushNotificationService();
                    $pushService->sendToPersonel($conversation->personel_id, [
                        'title' => 'Canlı Destek',
                        'body' => mb_substr($mesaj, 0, 100),
                        'url' => '?page=ana-sayfa',
                        'tag' => 'destek-' . $konusmaId
                    ]);
                }
            } catch (Exception $e) {
                error_log("Push notification error: " . $e->getMessage());
            }

            echo json_encode([
                'status' => 'success',
                'message_id' => $mesajId
            ]);
            break;

        // Sadece resim gönder
        case 'send-image':
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);

            if (!$konusmaId) {
                throw new Exception('Konuşma ID gerekli');
            }

            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Resim dosyası gerekli');
            }

            $userId = $_SESSION['user_id'] ?? $_SESSION['user']->id ?? 0;
            $uploadResult = handleFileUpload($_FILES['image']);

            $mesajId = $destekModel->sendMessage($konusmaId, [
                'tip' => 'yonetici',
                'id' => $userId
            ], '📷 Resim', $uploadResult['url'], $uploadResult['type']);

            // Yönetici mesaj attığında durumu otomatikman çevrimiçi yap
            $settingsModel = new \App\Model\SettingsModel();
            $settingsModel->upsertSetting('canli_destek_admin_durum', 'cevrimici');

            echo json_encode([
                'status' => 'success',
                'message_id' => $mesajId,
                'file_url' => $uploadResult['url']
            ]);
            break;

        // Konuşma durumunu güncelle
        case 'update-status':
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            $durum = $_POST['durum'] ?? '';

            if (!$konusmaId || !in_array($durum, ['acik', 'beklemede', 'cozuldu', 'kapali'])) {
                throw new Exception('Geçersiz parametreler');
            }

            $destekModel->updateStatus($konusmaId, $durum);

            // Kapanma mesajı gönder
            if ($durum === 'cozuldu' || $durum === 'kapali') {
                $destekModel->sendSystemMessage($konusmaId, '✅ Konuşma kapatıldı. İyi günler dileriz!');
            }

            echo json_encode(['status' => 'success']);
            break;

        // Okunmamış sayısını getir
        case 'get-unread-count':
            $totalUnread = $destekModel->getTotalUnreadForAdmin();
            echo json_encode([
                'status' => 'success',
                'count' => $totalUnread
            ]);
            break;

        // Yeni mesaj var mı kontrol et (polling)
        case 'check-new-messages':
            $lastCheck = $_POST['last_check'] ?? null;

            // lastCheck doğrulaması - geçersizse veya gelecekte ise son 5 saniyeyi kullan
            $serverNow = date('Y-m-d H:i:s');
            if (!$lastCheck || !strtotime($lastCheck) || strtotime($lastCheck) > time()) {
                $lastCheck = date('Y-m-d H:i:s', strtotime('-5 seconds'));
            }

            $newMessages = $destekModel->checkNewMessagesForAdmin($lastCheck);
            $totalUnread = $destekModel->getTotalUnreadForAdmin();
            $conversations = $destekModel->getAllConversations();

            echo json_encode([
                'status' => 'success',
                'new_messages' => $newMessages,
                'total_unread' => $totalUnread,
                'conversations' => $conversations,
                'server_time' => $serverNow
            ]);
            break;

        // Belirli konuşmanın yeni mesajlarını getir
        case 'poll-messages':
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            $afterId = (int) ($_POST['after_id'] ?? 0);

            if (!$konusmaId) {
                throw new Exception('Konuşma ID gerekli');
            }

            $messages = $destekModel->getMessages($konusmaId, $afterId);

            if (!empty($messages)) {
                $destekModel->markMessagesAsRead($konusmaId, 'yonetici');
            }

            $opponentLastReadId = $destekModel->getOpponentLastReadId($konusmaId, 'yonetici');

            echo json_encode([
                'status' => 'success',
                'messages' => $messages,
                'opponent_last_read_id' => $opponentLastReadId
            ]);
            break;

        // Konuşma sil (soft delete)
        case 'delete-conversation':
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            if (!$konusmaId) {
                throw new Exception('Konuşma ID gerekli');
            }

            $destekModel->softDeleteConversation($konusmaId);

            echo json_encode(['status' => 'success']);
            break;

        case 'set-admin-status':
            $settingsModel = new \App\Model\SettingsModel();
            $status = $_POST['status'] ?? 'cevrimici';
            if (!in_array($status, ['cevrimici', 'mesgul', 'cevrimdisi'])) {
                $status = 'cevrimici';
            }
            $settingsModel->upsertSetting('canli_destek_admin_durum', $status);
            echo json_encode(['status' => 'success']);
            break;

        case 'get-admin-status':
            $settingsModel = new \App\Model\SettingsModel();
            $adminStatus = $settingsModel->getSettings('canli_destek_admin_durum') ?: 'cevrimici';
            echo json_encode(['status' => 'success', 'admin_status' => $adminStatus]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Geçersiz aksiyon: ' . $action]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Dosya yükleme işlemi
 */
function handleFileUpload($file)
{
    $uploadDir = dirname(__DIR__, 2) . '/uploads/destek/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // MIME type kontrolü
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Sadece resim dosyaları yüklenebilir (JPEG, PNG, GIF, WebP)');
    }

    // Boyut kontrolü (5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Dosya boyutu 5MB\'dan büyük olamaz');
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'destek_' . uniqid() . '_' . time() . '.' . $ext;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Dosya yüklenirken hata oluştu');
    }

    // URL oluştur
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = "{$protocol}://{$host}";

    // Relative path
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    $relativePath = str_replace($docRoot, '', $uploadDir);
    $relativePath = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

    return [
        'url' => $baseUrl . $relativePath . $fileName,
        'type' => $mimeType
    ];
}
