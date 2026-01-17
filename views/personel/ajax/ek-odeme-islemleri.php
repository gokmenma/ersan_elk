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
            $data = [
                'personel_id' => $personel_id,
                'donem' => $_POST['ek_odeme_donem'],
                'tur' => $_POST['ek_odeme_tur'],
                'tutar' => $_POST['tutar'],
                'aciklama' => $_POST['aciklama'] ?? ''
            ];
            $ekOdemeModel->saveWithAttr($data);
            echo json_encode(['success' => true]);
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
