<?php
require_once '../../vendor/autoload.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\UserModel;
use App\Model\SystemLogModel;

$User = new UserModel();

// if(!Helper::isAdmin()) {
//     echo json_encode([
//         'status' => 'error',
//         'message' => 'Unauthorized access.'
//     ]);
//     exit;
// };
session_start();

if ($_POST["action"] == "kullanici-kaydet") {
    $id = Security::decrypt($_POST['user_id']) ?? 0;

    $lastInsertedId = 0; // Son eklenen ID başlangıç değeri
    $rowData = ''; // Satır verisi başlangıç değeri

    //User_branchs multiple select, bunu virgül ile birleştir
    if (isset($_POST['user_firms']) && is_array($_POST['user_firms'])) {

        $user_firma_ids = implode(',', $_POST['user_firms']);
    } else {
        $user_firma_ids = '';
    }

    try {
        // Mail bildirim checkbox'larını işle (checkbox işaretliyse 'Evet', değilse 'Hayır')
        $mail_avans_talep = isset($_POST['mail_avans_talep']) && $_POST['mail_avans_talep'] == 'Evet' ? 'Evet' : 'Hayır';
        $mail_izin_talep = isset($_POST['mail_izin_talep']) && $_POST['mail_izin_talep'] == 'Evet' ? 'Evet' : 'Hayır';
        $mail_genel_talep = isset($_POST['mail_genel_talep']) && $_POST['mail_genel_talep'] == 'Evet' ? 'Evet' : 'Hayır';
        $mail_ariza_talep = isset($_POST['mail_ariza_talep']) && $_POST['mail_ariza_talep'] == 'Evet' ? 'Evet' : 'Hayır';

        $yonetilen_departman = "";
        if (isset($_POST['yonetilen_departman']) && is_array($_POST['yonetilen_departman'])) {
            $yonetilen_departman = implode(',', $_POST['yonetilen_departman']);
        } else {
            $yonetilen_departman = $_POST['yonetilen_departman'] ?? "";
        }

        $data = [
            'id' => $id, // Eğer id varsa deşifre et
            'user_name' => $_POST['user_name'],
            'adi_soyadi' => $_POST['adi_soyadi'],
            'email_adresi' => $_POST['email_adresi'],
            'telefon' => $_POST['telefon'],
            'gorevi' => $_POST['gorevi'],
            'aciklama' => $_POST['aciklama'],
            'owner_id' => $_SESSION["owner_id"],
            'roles' => is_array($_POST['roles']) ? implode(',', $_POST['roles']) : $_POST['roles'],
            'firma_ids' => $user_firma_ids,
            'izin_onayi_yapacakmi' => $_POST['izin_onayi_yapacakmi'],
            'izin_onay_sirasi' => $_POST['izin_onay_sirasi'],
            'mail_avans_talep' => $mail_avans_talep,
            'mail_izin_talep' => $mail_izin_talep,
            'mail_genel_talep' => $mail_genel_talep,
            'mail_ariza_talep' => $mail_ariza_talep,
            'yonetilen_departman' => $yonetilen_departman,
            'durum' => $_POST['durum'] ?? 'Aktif'
        ];
        $passwordChanged = false;
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $passwordChanged = true;
        }

        $lastInsertedId = $User->saveWithAttr($data) ?? $_POST['user_id'];

        $logModel = new SystemLogModel();
        $currentUserId = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
        $targetAdi = $_POST['adi_soyadi'] ?? '';
        if ($id == 0) {
            $logModel->logAction($currentUserId, 'Kullanıcı Eklendi', "Yeni kullanıcı eklendi: $targetAdi", SystemLogModel::LEVEL_IMPORTANT);
        } else {
            $logModel->logAction($currentUserId, 'Kullanıcı Güncellendi', "Kullanıcı güncellendi: $targetAdi (ID: $id)", SystemLogModel::LEVEL_IMPORTANT);
            if ($passwordChanged) {
                $logModel->logAction($currentUserId, 'Kullanıcı Şifre Değişimi', "Kullanıcı şifresi değiştirildi: $targetAdi (ID: $id)", SystemLogModel::LEVEL_CRITICAL);
            }
        }

        $status = "success";
        $message = "Kullanıcı başarıyla kaydedildi.";
    } catch (PDOException $ex) {
        error_log('kullanici-kaydet hatası: ' . $ex->getMessage());
        if ($ex->getCode() == 23000) {
            $message = "Bu kullanıcı adı veya e-posta zaten kayıtlı.";
        } else {
            $message = "Kayıt sırasında bir hata oluştu.";
        }
        $status = "error";
    }
    $res = [
        'status' => $status,
        'message' => $message,
        'lastInsertedId' => $lastInsertedId,
        'rowData' => $rowData
    ];
    echo json_encode($res);
}


//Kullanıcı silme işlemi
if ($_POST["action"] == "kullanici-sil") {
    $id = $_POST['id'];

    try {
        $User->delete($id);
        $logModel = new SystemLogModel();
        $logModel->logAction($_SESSION['id'] ?? 0, 'Kullanıcı Silindi', "Kullanıcı silindi. ID: $id", SystemLogModel::LEVEL_CRITICAL);
        $status = "success";
        $message = "Kullanıcı başarıyla silindi.";
    } catch (PDOException $ex) {
        error_log('kullanici-sil hatası: ' . $ex->getMessage());
        $status = "error";
        $message = "Silme işlemi sırasında bir hata oluştu.";
    }
    echo json_encode([
        'status' => $status,
        'message' => $message
    ]);
}

// Kullanıcı durum değiştirme işlemi
if ($_POST["action"] == "kullanici-durum-degistir") {
    $id = Security::decrypt($_POST['id']);
    $status_new = $_POST['status'];

    try {
        $User->updateStatus($id, $status_new);
        $logModel = new SystemLogModel();
        $logModel->logAction($_SESSION['id'] ?? 0, 'Kullanıcı Durum Değişimi', "Kullanıcı durumu '$status_new' olarak güncellendi. ID: $id", SystemLogModel::LEVEL_IMPORTANT);
        $status = "success";
        $message = "Kullanıcı durumu başarıyla güncellendi.";
    } catch (PDOException $ex) {
        error_log('kullanici-durum-degistir hatası: ' . $ex->getMessage());
        $status = "error";
        $message = "Durum güncellenirken bir hata oluştu.";
    }
    echo json_encode([
        'status' => $status,
        'message' => $message
    ]);
}
