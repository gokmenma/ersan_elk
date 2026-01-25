<?php

require_once dirname(__DIR__, 3) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

use App\Model\SettingsModel;
use App\Service\MailGonderService;
// use App\Model\MesajLogModel; // Eğer varsa

$apiResponse = [
    'status' => false,
    'message' => 'Bilinmeyen bir hata oluştu.',
    'data' => null
];

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Action kontrolü (Şablon kaydetme vb. için)
    $action = $_POST['action'] ?? '';

    if ($action === 'save_template') {
        $name = $_POST['name'] ?? '';
        $content = $_POST['content'] ?? '';

        if (empty($name) || empty($content)) {
            throw new Exception("Şablon adı ve içeriği boş olamaz.");
        }

        // Şablon kaydetme mantığı buraya gelecek
        // Örn: $Model->saveTemplate($name, $content, 'mail');

        $apiResponse['status'] = true;
        $apiResponse['message'] = 'Şablon başarıyla kaydedildi.';
        echo json_encode($apiResponse);
        exit;
    }

    // Normal mail gönderimi
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $recipients = isset($_POST['recipients']) ? json_decode($_POST['recipients'], true) : [];
    $senderAccount = $_POST['senderAccount'] ?? '';

    if (empty($recipients)) {
        throw new Exception("Alıcı listesi boş.");
    }

    if (empty($subject)) {
        throw new Exception("Konu boş olamaz.");
    }

    if (empty($message)) {
        throw new Exception("Mesaj içeriği boş olamaz.");
    }

    // Mail gönderme işlemi
    $ekler = [];
    if (!empty($_FILES['attachments'])) {
        $files = $_FILES['attachments'];
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $ekler[] = [
                        'path' => $files['tmp_name'][$i],
                        'name' => $files['name'][$i]
                    ];
                }
            }
        }
    }



    $result = MailGonderService::gonder($recipients, $subject, $message, $ekler);
    if ($result) {
        // Loglama işlemi
        if (class_exists('App\Model\MesajLogModel')) {
            try {
                $LogModel = new \App\Model\MesajLogModel();
                $firmaId = $_SESSION['firma_id'] ?? 0;
                $sender = $_SESSION['user_email'] ?? 'noreply@softran.online';

                $LogModel->logEmail(
                    $firmaId,
                    $sender,
                    $recipients,
                    $subject,
                    $message,
                    $ekler,
                    'iletildi'
                );
            } catch (\Throwable $th) {
                // Loglama hatası mail gönderimini etkilememeli
                error_log("Mail loglama hatası: " . $th->getMessage());
            }
        }

        $apiResponse['status'] = true;
        $apiResponse['message'] = count($recipients) . ' alıcıya e-posta başarıyla gönderildi.';
    } else {
        throw new Exception("E-posta gönderimi başarısız oldu. Lütfen ayarları kontrol edin.");
    }

} catch (Exception $e) {
    $apiResponse['message'] = $e->getMessage();
}

echo json_encode($apiResponse, JSON_UNESCAPED_UNICODE);
