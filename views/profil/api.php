<?php
require_once '../../vendor/autoload.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\UserModel;

$User = new UserModel();

session_start();

if ($_POST["action"] == "profil-guncelle") {
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
}
