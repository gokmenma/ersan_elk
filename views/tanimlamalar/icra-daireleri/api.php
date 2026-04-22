<?php
session_start();
require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\IcraDaireleriModel;

$Model = new IcraDaireleriModel();

if (!isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek.']);
    exit;
}

$action = $_POST['action'];

try {
    switch ($action) {
        case 'getir':
            $id = Security::decrypt($_POST['id']);
            $data = $Model->find($id);
            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kayıt bulunamadı.']);
            }
            break;

        case 'kaydet':
            $id = Security::decrypt($_POST['id'] ?? '0');
            $data = [
                'daire_adi' => $_POST['daire_adi'],
                'daire_kodu' => $_POST['daire_kodu'],
                'il' => $_POST['il'],
                'ilce' => $_POST['ilce'],
                'adres' => $_POST['adres'],
                'telefon' => $_POST['telefon'],
                'faks' => $_POST['faks'],
                'email' => $_POST['email'],
                'iban' => $_POST['iban'],
                'vergi_dairesi' => $_POST['vergi_dairesi'],
                'vergi_no' => $_POST['vergi_no'],
                'aktif' => isset($_POST['aktif']) ? 1 : 0
            ];

            if ($id > 0) {
                $data['id'] = $id;
                $res = $Model->saveWithAttr($data);
                $message = 'Kayıt başarıyla güncellendi.';
                $is_update = true;
            } else {
                $res = $Model->saveWithAttr($data);
                $id = Security::decrypt($res);
                $message = 'Yeni kayıt başarıyla oluşturuldu.';
                $is_update = false;
            }

            echo json_encode([
                'status' => 'success', 
                'message' => $message, 
                'id' => $id, 
                'is_update' => $is_update
            ]);
            break;

        case 'sil':
            $id = Security::decrypt($_POST['id']);
            // Soft delete yoksa normal delete kullanıyoruz (user'ın SQL'inde silinme_tarihi yoktu)
            // Ama genel modelde softDelete metodu var. Bakalım tabloda silinme_tarihi var mı?
            // User'ın SQL'inde yoktu. O yüzden normal delete kullanacağım.
            $res = $Model->delete($_POST['id'], true);
            if ($res === true) {
                echo json_encode(['status' => 'success', 'message' => 'Kayıt silindi.', 'deleted_id' => $id]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Silme işlemi başarısız.']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Tanımsız işlem.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
