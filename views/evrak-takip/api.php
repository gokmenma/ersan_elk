<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\EvrakTakipModel;
use App\Helper\Date;
use App\Helper\Security;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $Model = new EvrakTakipModel();

    try {
        switch ($action) {
            case 'evrak-kaydet':
                $data = $_POST;
                $id = isset($data['id']) ? intval($data['id']) : 0;

                unset($data['action']);
                $data['firma_id'] = $_SESSION['firma_id'];

                if (!empty($data['tarih'])) {
                    $data['tarih'] = Date::Ymd($data['tarih']);
                } else {
                    $data['tarih'] = date('Y-m-d');
                }

                // Dosya yükleme işlemi
                if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] == 0) {
                    $upload_dir = dirname(__DIR__, 2) . '/uploads/evrak-takip/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_ext = pathinfo($_FILES['dosya']['name'], PATHINFO_EXTENSION);
                    $file_name = time() . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['dosya']['tmp_name'], $upload_path)) {
                        $data['dosya_yolu'] = 'uploads/evrak-takip/' . $file_name;
                    }
                }

                // Checkboxlar için varsayılan değerler
                $data['personel_bildirim_durumu'] = isset($data['personel_bildirim_durumu']) ? 1 : 0;
                $data['cevap_verildi_mi'] = isset($data['cevap_verildi_mi']) ? 1 : 0;

                if (!empty($data['cevap_tarihi'])) {
                    $data['cevap_tarihi'] = Date::Ymd($data['cevap_tarihi']);
                } else {
                    $data['cevap_tarihi'] = null;
                }

                // İlişkili evrak ID
                $data['ilgili_evrak_id'] = !empty($data['ilgili_evrak_id']) ? intval($data['ilgili_evrak_id']) : null;

                // Boş değerleri null yap
                foreach ($data as $key => $value) {
                    if ($value === '' && $key != 'id' && $key != 'action') {
                        $data[$key] = null;
                    }
                }

                if ($id > 0) {
                    $Model->saveWithAttr($data);
                    $message = "Evrak başarıyla güncellendi.";
                } else {
                    $data['olusturan_kullanici_id'] = $_SESSION['user_id'] ?? null;
                    $id = $Model->saveWithAttr($data);
                    $message = "Evrak başarıyla kaydedildi.";
                }

                // Eğer giden evrak ise ve ilgili bir gelen evrak seçildiyse 
                // o gelen evrakı "cevap verildi" olarak işaretle
                if ($data['evrak_tipi'] == 'giden' && !empty($data['ilgili_evrak_id'])) {
                    $Model->markAsReplied($data['ilgili_evrak_id'], $data['tarih']);
                }

                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'evrak-sil':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Geçersiz evrak ID.");
                }

                $Model->softDelete($id);
                echo json_encode(['status' => 'success', 'message' => 'Evrak başarıyla silindi.']);
                break;

            case 'evrak-detay':
                $id = intval($_POST['id'] ?? 0);
                $evrak = $Model->getById($id);

                if (!$evrak) {
                    throw new Exception("Evrak bulunamadı.");
                }

                echo json_encode(['status' => 'success', 'data' => $evrak]);
                break;

            case 'evrak-listesi':
                $evraklar = $Model->all();
                echo json_encode(['status' => 'success', 'data' => $evraklar]);
                break;

            case 'evrak-istatistik':
                $stats = $Model->getStats();
                echo json_encode(['status' => 'success', 'data' => $stats]);
                break;

            case 'get-next-evrak-no':
                $tip = $_POST['tip'] ?? 'gelen';
                $next_no = $Model->getMaxEvrakNo($tip);
                echo json_encode(['status' => 'success', 'next_no' => $next_no]);
                break;

            case 'get-konular':
                $konular = $Model->getDistinctKonular();
                echo json_encode(['status' => 'success', 'data' => $konular]);
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>