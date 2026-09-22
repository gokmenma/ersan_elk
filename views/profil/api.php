<?php
require_once '../../vendor/autoload.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\UserModel;
use App\Model\SystemLogModel;
use App\Model\UserNotificationPreferenceModel;

$User = new UserModel();

session_start();

$action = $_POST["action"] ?? '';

if ($action == "profil-guncelle") {
    $userId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? 0;

    if ($userId == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Oturum bulunamadı.']);
        exit;
    }

    try {
        $currentUser = $User->find((int)$userId);
        if (!$currentUser) {
            echo json_encode(['status' => 'error', 'message' => 'Kullanıcı bulunamadı.']);
            exit;
        }

        $password = trim($_POST['password'] ?? '');
        if (empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Lütfen yeni bir şifre giriniz.']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['status' => 'error', 'message' => 'Şifre en az 6 karakter olmalıdır.']);
            exit;
        }

        $data = [
            'id' => (int)$userId,
            'password' => password_hash($password, PASSWORD_BCRYPT)
        ];

        // Kullanıcı şifresini güncelliyoruz
        $User->saveWithAttr($data);

        try {
            $log = new SystemLogModel();
            $log->logAction($userId, 'Şifre Güncelleme', 'Kullanıcı şifresi başarıyla güncellendi.');
        } catch (\Exception $e) {}

        echo json_encode([
            'status' => 'success',
            'message' => 'Şifreniz başarıyla güncellendi.'
        ]);

    } catch (\PDOException $ex) {
        error_log("Profil şifre güncelleme hatası: " . $ex->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Şifre güncellenirken bir hata oluştu.'
        ]);
    }
    exit;
}

if ($action == "ayarlari-guncelle") {
    $userId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? 0;

    if ($userId == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Oturum bulunamadı.']);
        exit;
    }

    try {
        $showFavoritesBar = isset($_POST['show_favorites_bar']) && ($_POST['show_favorites_bar'] === '1' || $_POST['show_favorites_bar'] === 'on') ? 1 : 0;
        $postedNotificationPreferences = $_POST['notification_preferences'] ?? [];
        if (!is_array($postedNotificationPreferences)) {
            $postedNotificationPreferences = [];
        }

        $User->saveWithAttr([
            'id' => (int)$userId,
            'show_favorites_bar' => $showFavoritesBar
        ]);

        $notificationPreferences = new UserNotificationPreferenceModel();
        foreach (UserNotificationPreferenceModel::TYPES as $notificationType) {
            $notificationPreferences->setPreference(
                (int) $userId,
                $notificationType,
                isset($postedNotificationPreferences[$notificationType])
            );
        }

        if (isset($_SESSION["user"]) && is_object($_SESSION["user"])) {
            $_SESSION["user"]->show_favorites_bar = $showFavoritesBar;
        }
        $_SESSION["show_favorites_bar"] = $showFavoritesBar;

        try {
            $log = new SystemLogModel();
            $log->logAction(
                $userId,
                'Kullanıcı Ayarları',
                'Sık Kullanılanlar Çubuğu ve bildirim tercihleri güncellendi.'
            );
        } catch (\Exception $e) {}

        echo json_encode([
            'status' => 'success',
            'message' => 'Sistem tercihleriniz başarıyla kaydedildi.'
        ]);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Ayarlar kaydedilirken hata oluştu: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == "save-mobile-menu-order") {
    $userId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? 0;
    $order = $_POST['order'] ?? '';

    if ($userId == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Oturum bulunamadı.']);
        exit;
    }

    try {
        $User->saveWithAttr([
            'id' => (int)$userId,
            'mobile_menu_order' => $order
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Menü sıralaması güncellendi.']);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action == "reset-mobile-menu-order") {
    $userId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? 0;

    if ($userId == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Oturum bulunamadı.']);
        exit;
    }

    try {
        $User->saveWithAttr([
            'id' => (int)$userId,
            'mobile_menu_order' => null
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Menü sıralaması varsayılana sıfırlandı.']);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
