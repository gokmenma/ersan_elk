<?php
require_once '../../vendor/autoload.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\UserModel;
use App\Model\SystemLogModel;

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

        $data = [
            'id' => (int)$userId,
            'user_name' => trim($_POST['user_name'] ?? ''),
            'adi_soyadi' => trim($_POST['adi_soyadi'] ?? ''),
            'email_adresi' => trim($_POST['email_adresi'] ?? ''),
            'telefon' => trim($_POST['telefon'] ?? '')
        ];

        // Şifre boş değilse güncelle
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
        }

        // Kullanıcı güncellemesini yapıyoruz
        $User->saveWithAttr($data);

        // Kullanıcı güncellendi, Session içindeki bazı bilgileri de güncelleyelim
        if (isset($_SESSION["user"])) {
            $_SESSION["user"]->adi_soyadi = $data["adi_soyadi"];
            $_SESSION["user_full_name"] = $data["adi_soyadi"];
        }

        try {
            $log = new SystemLogModel();
            $log->logAction($userId, 'Profil Güncelleme', 'Profil bilgileri güncellendi.');
        } catch (\Exception $e) {}

        echo json_encode([
            'status' => 'success',
            'message' => 'Profil bilgileriniz başarıyla güncellendi.'
        ]);

    } catch (\PDOException $ex) {
        if ($ex->getCode() == 23000) {
            $message = "Bu kullanıcı adı veya e-posta zaten kullanımda.";
        } else {
            $message = "Bir hata oluştu: " . $ex->getMessage();
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => $message
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

        $User->saveWithAttr([
            'id' => (int)$userId,
            'show_favorites_bar' => $showFavoritesBar
        ]);

        if (isset($_SESSION["user"]) && is_object($_SESSION["user"])) {
            $_SESSION["user"]->show_favorites_bar = $showFavoritesBar;
        }
        $_SESSION["show_favorites_bar"] = $showFavoritesBar;

        try {
            $log = new SystemLogModel();
            $log->logAction($userId, 'Arayüz Ayarları', 'Sık Kullanılanlar Çubuğu tercihi ' . ($showFavoritesBar ? 'açıldı.' : 'kapatıldı.'));
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
