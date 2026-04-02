<?php
session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\NotModel;
use App\Helper\Security;

header('Content-Type: application/json');

$notModel = new NotModel();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$firma_id = $_SESSION['firma_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

if (!$firma_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Oturum kapalı.']);
    exit;
}

switch ($action) {
    case 'get-defterler':
        $defterler = $notModel->getDefterler($firma_id, $user_id);
        foreach ($defterler as &$d) {
            $d->id_enc = Security::encrypt($d->id);
            // We keep raw ID if we need it, but frontend should use id_enc
        }
        echo json_encode(['success' => true, 'data' => $defterler]);
        break;

    case 'add-defter':
        $baslik = $_POST['baslik'] ?? '';
        if (empty($baslik)) {
            echo json_encode(['success' => false, 'message' => 'Başlık zorunludur.']);
            exit;
        }
        $id = $notModel->addDefter([
            'firma_id' => $firma_id,
            'baslik' => $baslik,
            'renk' => $_POST['renk'] ?? '#4285f4',
            'icon' => $_POST['icon'] ?? 'bx-book',
            'olusturan_id' => $user_id
        ]);
        echo json_encode(['success' => true, 'id' => Security::encrypt($id)]);
        break;

    case 'update-defter':
        $id = Security::decrypt($_POST['defter_id'] ?? '');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz ID.']);
            exit;
        }
        $res = $notModel->updateDefter($id, [
            'baslik' => $_POST['baslik'] ?? null,
            'renk' => $_POST['renk'] ?? null,
            'icon' => $_POST['icon'] ?? null
        ]);
        echo json_encode(['success' => $res]);
        break;

    case 'delete-defter':
        $id = Security::decrypt($_POST['defter_id'] ?? '');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz ID.']);
            exit;
        }
        $res = $notModel->deleteDefter($id);
        echo json_encode(['success' => $res]);
        break;

    case 'get-notlar':
        $defter_id = Security::decrypt($_POST['defter_id'] ?? '');
        if (!$defter_id && ($_POST['defter_id'] !== 'tum')) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz Defter ID.']);
            exit;
        }
        
        if ($_POST['defter_id'] === 'tum') {
            $notlar = $notModel->getTumNotlar($firma_id, $user_id);
        } else {
            $notlar = $notModel->getNotlar($defter_id, $firma_id, $user_id);
        }
        
        // Encrypt IDs for security in frontend
        foreach ($notlar as &$n) {
            $n->id_enc = Security::encrypt($n->id);
            $n->defter_id_enc = Security::encrypt($n->defter_id);
            unset($n->id, $n->defter_id);
        }
        
        echo json_encode(['success' => true, 'data' => $notlar]);
        break;

    case 'add-not':
        $defter_id = Security::decrypt($_POST['defter_id'] ?? '');
        if (!$defter_id) {
            // Check if there is a default notebook
            $defterler = $notModel->getDefterler($firma_id, $user_id);
            if (empty($defterler)) {
                $defter_id = $notModel->addDefter([
                    'firma_id' => $firma_id,
                    'baslik' => 'Genel Notlarım',
                    'olusturan_id' => $user_id
                ]);
            } else {
                $defter_id = $defterler[0]->id;
            }
        }
        
        $id = $notModel->addNot([
            'defter_id' => $defter_id,
            'firma_id' => $firma_id,
            'baslik' => $_POST['baslik'] ?? null,
            'icerik' => $_POST['icerik'] ?? null,
            'renk' => $_POST['renk'] ?? null,
            'olusturan_id' => $user_id
        ]);
        echo json_encode(['success' => true, 'id' => Security::encrypt($id)]);
        break;

    case 'update-not':
        $id = Security::decrypt($_POST['not_id'] ?? '');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz ID.']);
            exit;
        }
        
        $data = [];
        if (isset($_POST['baslik'])) $data['baslik'] = $_POST['baslik'];
        if (isset($_POST['icerik'])) $data['icerik'] = $_POST['icerik'];
        if (isset($_POST['renk'])) $data['renk'] = $_POST['renk'];
        if (isset($_POST['pinli'])) $data['pinli'] = (int)$_POST['pinli'];
        
        if (isset($_POST['defter_id'])) {
            $new_defter_id = Security::decrypt($_POST['defter_id']);
            if ($new_defter_id) $data['defter_id'] = $new_defter_id;
        }

        $res = $notModel->updateNot($id, $data);
        echo json_encode(['success' => $res]);
        break;

    case 'delete-not':
        $id = Security::decrypt($_POST['not_id'] ?? '');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz ID.']);
            exit;
        }
        $res = $notModel->deleteNot($id);
        echo json_encode(['success' => $res]);
        break;

    case 'pin-not':
        $id = Security::decrypt($_POST['not_id'] ?? '');
        $pinli = (int)($_POST['pinli'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz ID.']);
            exit;
        }
        $res = $notModel->pinNot($id, $pinli);
        echo json_encode(['success' => $res]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Geçersiz işlem.']);
        break;
}
