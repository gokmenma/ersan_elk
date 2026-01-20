<?php
require_once dirname(__DIR__, 3) . '/Autoloader.php';
session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

use App\Model\PersonelEkOdemelerModel;

$action = $_REQUEST['action'] ?? '';
$personel_id = $_REQUEST['personel_id'] ?? 0;

if (!$personel_id) {
    echo json_encode(['error' => 'Personel ID missing']);
    exit;
}

$ekOdemeModel = new PersonelEkOdemelerModel();

try {
    switch ($action) {
        case 'save_ek_odeme':
            $tekrarTipi = $_POST['tekrar_tipi'] ?? 'tek_sefer';
            $hesaplamaTipi = $_POST['hesaplama_tipi'] ?? 'sabit';

            $data = [
                'personel_id' => $personel_id,
                'tur' => $_POST['tur'] ?? 'diger',
                'tekrar_tipi' => $tekrarTipi,
                'hesaplama_tipi' => $hesaplamaTipi,
                'tutar' => floatval($_POST['tutar'] ?? 0),
                'oran' => floatval($_POST['oran'] ?? 0),
                'aciklama' => $_POST['aciklama'] ?? '',
                'parametre_id' => !empty($_POST['parametre_id']) ? intval($_POST['parametre_id']) : null,
                'aktif' => 1
            ];

            if ($tekrarTipi === 'tek_sefer') {
                // Tek seferlik ödeme - dönem ID kullan
                $data['donem_id'] = $_POST['donem_id'] ?? null;
                $data['baslangic_donemi'] = null;
                $data['bitis_donemi'] = null;
            } else {
                // Sürekli ödeme - dönem aralığı kullan
                $data['donem_id'] = null;
                $data['baslangic_donemi'] = $_POST['baslangic_donemi'] ?? date('Y-m');
                $data['bitis_donemi'] = !empty($_POST['bitis_donemi']) ? $_POST['bitis_donemi'] : null;
            }

            $result = $ekOdemeModel->saveWithAttr($data);

            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                echo json_encode(['error' => 'Kayıt oluşturulamadı']);
            }
            break;

        case 'update_ek_odeme':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Ek ödeme ID gerekli']);
                break;
            }

            $tekrarTipi = $_POST['tekrar_tipi'] ?? 'tek_sefer';
            $hesaplamaTipi = $_POST['hesaplama_tipi'] ?? 'sabit';

            $data = [
                'tur' => $_POST['tur'] ?? 'diger',
                'tekrar_tipi' => $tekrarTipi,
                'hesaplama_tipi' => $hesaplamaTipi,
                'tutar' => floatval($_POST['tutar'] ?? 0),
                'oran' => floatval($_POST['oran'] ?? 0),
                'aciklama' => $_POST['aciklama'] ?? '',
                'parametre_id' => !empty($_POST['parametre_id']) ? intval($_POST['parametre_id']) : null
            ];

            if ($tekrarTipi === 'tek_sefer') {
                $data['donem_id'] = $_POST['donem_id'] ?? null;
            } else {
                $data['baslangic_donemi'] = $_POST['baslangic_donemi'] ?? date('Y-m');
                $data['bitis_donemi'] = !empty($_POST['bitis_donemi']) ? $_POST['bitis_donemi'] : null;
            }

            $result = $ekOdemeModel->updateEkOdeme($id, $data);
            echo json_encode(['success' => $result]);
            break;

        case 'sonlandir_ek_odeme':
            $id = intval($_POST['id'] ?? 0);
            $bitis_donemi = $_POST['bitis_donemi'] ?? date('Y-m');

            if (!$id) {
                echo json_encode(['error' => 'Ek ödeme ID gerekli']);
                break;
            }

            $result = $ekOdemeModel->sonlandirSurekliOdeme($id, $bitis_donemi);
            echo json_encode(['success' => $result]);
            break;

        case 'get_ek_odeme':
            $id = intval($_REQUEST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Ek ödeme ID gerekli']);
                break;
            }

            $ekOdeme = $ekOdemeModel->getEkOdeme($id);
            echo json_encode($ekOdeme);
            break;

        case 'get_donem_kayitlari':
            // Sürekli ödemeden oluşturulan dönem kayıtlarını getir
            $ana_odeme_id = intval($_REQUEST['ana_odeme_id'] ?? 0);
            if (!$ana_odeme_id) {
                echo json_encode(['error' => 'Ana ödeme ID gerekli']);
                break;
            }

            $kayitlar = $ekOdemeModel->getDonemKayitlari($ana_odeme_id);
            echo json_encode($kayitlar);
            break;

        case 'delete_ek_odeme':
            $id = $_POST['id'];
            $ekOdemeModel->softDelete($id);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
