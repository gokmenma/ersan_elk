<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\DuyuruModel;
use App\Model\PersonelModel;
use App\Helper\Security;

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $model = new DuyuruModel();

    try {
        switch ($action) {
            case 'save':
                $id = $_POST['id'] ?? null;
                $data = [
                    'firma_id' => $_SESSION['firma_id'],
                    'baslik' => trim($_POST['baslik'] ?? ''),
                    'icerik' => trim($_POST['icerik'] ?? ''),
                    'hedef_sayfa' => $_POST['hedef_sayfa'] ?? '',
                    'durum' => $_POST['durum'] ?? 'Yayında',
                    'alici_tipi' => $_POST['alici_tipi'] ?? 'toplu',
                    'alici_ids' => is_array($_POST['personel_ids'] ?? null) ? implode(',', $_POST['personel_ids']) : ($_POST['alici_ids'] ?? ''),
                    'etkinlik_tarihi' => !empty($_POST['etkinlik_tarihi']) ? $_POST['etkinlik_tarihi'] : null,
                    'ana_sayfada_goster' => isset($_POST['ana_sayfada_goster']) ? 1 : 0,
                    'pwa_goster' => isset($_POST['pwa_goster']) ? 1 : 0,
                ];

                if (empty($data['baslik'])) {
                    throw new Exception('Başlık zorunludur.');
                }

                // Resim Yükleme
                if (isset($_FILES['resim']) && $_FILES['resim']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = dirname(__DIR__, 2) . '/uploads/duyuru/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $ext = pathinfo($_FILES['resim']['name'], PATHINFO_EXTENSION);
                    $fileName = uniqid('duyuru_') . '.' . $ext;
                    move_uploaded_file($_FILES['resim']['tmp_name'], $uploadDir . $fileName);
                    $data['resim'] = 'uploads/duyuru/' . $fileName;
                }

                if ($id) {
                    $model->updateDuyuru($id, $data);
                    $msg = 'Duyuru güncellendi.';
                } else {
                    $data['tarih'] = date('Y-m-d H:i:s');
                    $model->createDuyuru($data);
                    $msg = 'Duyuru oluşturuldu.';
                }

                echo json_encode(['status' => 'success', 'message' => $msg]);
                break;

            case 'delete':
                $id = $_POST['id'] ?? 0;
                if ($id) {
                    $model->updateDuyuru($id, ['silinme_tarihi' => date('Y-m-d H:i:s')]);
                    echo json_encode(['status' => 'success', 'message' => 'Duyuru silindi.']);
                } else {
                    throw new Exception('Geçersiz ID.');
                }
                break;

            case 'get':
                $id = $_POST['id'] ?? 0;
                $item = $model->find($id);
                if ($item) {
                    echo json_encode(['status' => 'success', 'data' => $item]);
                } else {
                    throw new Exception('Kayıt bulunamadı.');
                }
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
