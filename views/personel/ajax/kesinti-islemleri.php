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
            $data = [
                'personel_id' => $personel_id,
                'donem_id' => $_POST['kesinti_donem'],
                'tur' => $_POST['kesinti_tur'],
                'tutar' => $_POST['tutar'],
                'aciklama' => $_POST['aciklama'] ?? '',
                'icra_id' => ($_POST['kesinti_tur'] == 'icra' && !empty($_POST['icra_id'])) ? $_POST['icra_id'] : null
            ];
            $kesintiModel->saveWithAttr($data);
            echo json_encode(['success' => true]);
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
            $id = $_POST['id'];
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
