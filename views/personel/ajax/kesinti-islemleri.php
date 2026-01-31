<?php
require_once dirname(__DIR__, 3) . '/Autoloader.php';
session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

use App\Model\PersonelKesintileriModel;
use App\Model\PersonelIcralariModel;
use App\Model\BordroDonemModel;

$action = $_REQUEST['action'] ?? '';
$personel_id = $_REQUEST['personel_id'] ?? 0;

if (!$personel_id) {
    echo json_encode(['error' => 'Personel ID missing']);
    exit;
}

$kesintiModel = new PersonelKesintileriModel();
$icraModel = new PersonelIcralariModel();

try {
    switch ($action) {
        case 'get_icralar':
            $icralar = $icraModel->getDevamEdenIcralar($personel_id);
            echo json_encode($icralar);
            break;

        case 'save_kesinti':
            $tekrarTipi = $_POST['tekrar_tipi'] ?? 'tek_sefer';
            $hesaplamaTipi = $_POST['hesaplama_tipi'] ?? 'sabit';

            // Tek seferlik kesintilerde dönem kontrolü yap
            if ($tekrarTipi === 'tek_sefer' && !empty($_POST['donem_id'])) {
                $BordroDonem = new BordroDonemModel();
                $donem = $BordroDonem->getDonemById(intval($_POST['donem_id']));
                if ($donem && $donem->kapali_mi == 1) {
                    echo json_encode(['error' => 'Bu dönem kapatılmış. Kapalı dönemlere kesinti eklenemez.']);
                    break;
                }
            }

            $data = [
                'personel_id' => $personel_id,
                'tur' => $_POST['tur'] ?? 'diger',
                'tekrar_tipi' => $tekrarTipi,
                'hesaplama_tipi' => $hesaplamaTipi,
                'tutar' => floatval($_POST['tutar'] ?? 0),
                'oran' => floatval($_POST['oran'] ?? 0),
                'aciklama' => $_POST['aciklama'] ?? '',
                'parametre_id' => !empty($_POST['parametre_id']) ? intval($_POST['parametre_id']) : null,
                'icra_id' => !empty($_POST['icra_id']) ? intval($_POST['icra_id']) : null,
                'aktif' => 1
            ];

            if ($tekrarTipi === 'tek_sefer') {
                // Tek seferlik kesinti - dönem ID kullan
                $data['donem_id'] = $_POST['donem_id'] ?? null;
                $data['baslangic_donemi'] = null;
                $data['bitis_donemi'] = null;
            } else {
                // Sürekli kesinti - dönem aralığı kullan
                $data['donem_id'] = null;
                $data['baslangic_donemi'] = $_POST['baslangic_donemi'] ?? date('Y-m');
                $data['bitis_donemi'] = !empty($_POST['bitis_donemi']) ? $_POST['bitis_donemi'] : null;
            }

            $result = $kesintiModel->saveWithAttr($data);

            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                echo json_encode(['error' => 'Kayıt oluşturulamadı']);
            }
            break;

        case 'update_kesinti':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Kesinti ID gerekli']);
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
                'parametre_id' => !empty($_POST['parametre_id']) ? intval($_POST['parametre_id']) : null,
                'icra_id' => !empty($_POST['icra_id']) ? intval($_POST['icra_id']) : null
            ];

            if ($tekrarTipi === 'tek_sefer') {
                $data['donem_id'] = $_POST['donem_id'] ?? null;
            } else {
                $data['baslangic_donemi'] = $_POST['baslangic_donemi'] ?? date('Y-m');
                $data['bitis_donemi'] = !empty($_POST['bitis_donemi']) ? $_POST['bitis_donemi'] : null;
            }

            $result = $kesintiModel->updateKesinti($id, $data);
            echo json_encode(['success' => $result]);
            break;

        case 'sonlandir_kesinti':
            $id = intval($_POST['id'] ?? 0);
            $bitis_donemi = $_POST['bitis_donemi'] ?? date('Y-m');

            if (!$id) {
                echo json_encode(['error' => 'Kesinti ID gerekli']);
                break;
            }

            $result = $kesintiModel->sonlandirSurekliKesinti($id, $bitis_donemi);
            echo json_encode(['success' => $result]);
            break;

        case 'get_kesinti':
            $id = intval($_REQUEST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Kesinti ID gerekli']);
                break;
            }

            $kesinti = $kesintiModel->getKesinti($id);
            echo json_encode($kesinti);
            break;

        case 'get_donem_kayitlari':
            // Sürekli kesintiden oluşturulan dönem kayıtlarını getir
            $ana_kesinti_id = intval($_REQUEST['ana_kesinti_id'] ?? 0);
            if (!$ana_kesinti_id) {
                echo json_encode(['error' => 'Ana kesinti ID gerekli']);
                break;
            }

            $kayitlar = $kesintiModel->getDonemKayitlari($ana_kesinti_id);
            echo json_encode($kayitlar);
            break;

        case 'save_icra':
            $data = [
                'personel_id' => $personel_id,
                'dosya_no' => $_POST['dosya_no'],
                'icra_dairesi' => $_POST['icra_dairesi'],
                'toplam_borc' => $_POST['toplam_borc'],
                'aylik_kesinti_tutari' => $_POST['aylik_kesinti_tutari'],
                'baslangic_tarihi' => $_POST['baslangic_tarihi'],
                'durum' => 'devam_ediyor'
            ];
            $icraModel->saveWithAttr($data);
            echo json_encode(['success' => true]);
            break;

        case 'delete_kesinti':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['error' => 'Kesinti ID gerekli']);
                break;
            }

            // Kesintinin bağlı olduğu dönemi kontrol et
            $kesinti = $kesintiModel->getKesinti($id);
            if ($kesinti && $kesinti->donem_id) {
                $BordroDonem = new BordroDonemModel();
                $donem = $BordroDonem->getDonemById($kesinti->donem_id);
                if ($donem && $donem->kapali_mi == 1) {
                    echo json_encode(['error' => 'Bu dönem kapatılmış. Kapalı dönemlerdeki kesintiler silinemez.']);
                    break;
                }
            }

            $kesintiModel->softDelete($id);
            echo json_encode(['success' => true]);
            break;

        case 'delete_icra':
            $id = $_POST['id'];
            $icraModel->softDelete($id);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
