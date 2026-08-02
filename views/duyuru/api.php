<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\DuyuruModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Helper\Security;

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $model = new DuyuruModel();
    $logModel = new SystemLogModel();

    try {
        switch ($action) {
            case 'save':
                $id = $_POST['id'] ?? null;
                $etkinlikTarihi = null;
                if (!empty($_POST['etkinlik_tarihi'])) {
                    $date = DateTime::createFromFormat('Y-m-d', $_POST['etkinlik_tarihi']);
                    if (!$date) {
                        $date = DateTime::createFromFormat('d.m.Y', $_POST['etkinlik_tarihi']);
                    }
                    if ($date) {
                        $etkinlikTarihi = $date->format('Y-m-d') . ' 23:59:59';
                    }
                }

                $data = [
                    'firma_id' => $_SESSION['firma_id'],
                    'baslik' => trim($_POST['baslik'] ?? ''),
                    'icerik' => trim($_POST['icerik'] ?? ''),
                    'hedef_sayfa' => $_POST['hedef_sayfa'] ?? '',
                    'durum' => $_POST['durum'] ?? 'Yayında',
                    'alici_tipi' => $_POST['alici_tipi'] ?? 'toplu',
                    'alici_ids' => is_array($_POST['personel_ids'] ?? null) ? implode(',', $_POST['personel_ids']) : ($_POST['alici_ids'] ?? ''),
                    'etkinlik_tarihi' => $etkinlikTarihi,
                    'ana_sayfada_goster' => isset($_POST['ana_sayfada_goster']) ? 1 : 0,
                    'pwa_goster' => isset($_POST['pwa_goster']) ? 1 : 0,
                ];

                if (empty($data['baslik'])) {
                    throw new Exception('Başlık zorunludur.');
                }

                // Resim Silme İsteği
                if (isset($_POST['resim_sil']) && $_POST['resim_sil'] == '1') {
                    $data['resim'] = null;
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
                    $logModel->logAction($_SESSION['user_id'] ?? 0, 'Duyuru Güncelleme', "Duyuru #{$id} ({$data['baslik']}) güncellendi.", SystemLogModel::LEVEL_INFO);
                } else {
                    $data['tarih'] = date('Y-m-d H:i:s');
                    $newId = $model->createDuyuru($data);
                    $msg = 'Duyuru oluşturuldu.';
                    $logModel->logAction($_SESSION['user_id'] ?? 0, 'Duyuru Oluşturma', "Yeni duyuru ({$data['baslik']}) oluşturuldu.", SystemLogModel::LEVEL_INFO);
                }

                echo json_encode(['status' => 'success', 'message' => $msg]);
                break;

            case 'delete':
                $id = $_POST['id'] ?? 0;
                if ($id) {
                    $model->updateDuyuru($id, ['silinme_tarihi' => date('Y-m-d H:i:s')]);
                    $logModel->logAction($_SESSION['user_id'] ?? 0, 'Duyuru Silme', "Duyuru #{$id} silindi.", SystemLogModel::LEVEL_IMPORTANT);
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
