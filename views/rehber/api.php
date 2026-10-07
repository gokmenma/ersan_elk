<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../vendor/autoload.php';
//require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\RehberModel;
use App\Model\PermissionPolicyModel;
use App\Service\Gate;

$Rehber = new RehberModel();
$action = (string) ($_POST['action'] ?? '');
$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('rehber/api', $action);
} elseif (!Gate::allows('rehber/list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Rehber işlemleri için yetkiniz bulunmamaktadır.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action == 'kaydet') {
    $id = Security::decrypt($_POST['id']);

    try {
        $data = [
            'id' => $id,
            'adi_soyadi' => $_POST['adi_soyadi'],
            'kurum_adi' => $_POST['kurum_adi'],
            'telefon' => $_POST['telefon'],
            'email' => $_POST['email'],
            'adres' => $_POST['adres'],
            'aciklama' => $_POST['aciklama']
        ];
        $lastInsertedId = $Rehber->saveWithAttr($data) ?? $_POST['id'];
        $rowData = $Rehber->getTableRow($lastInsertedId);
        $status = 'success';
        $message = 'Kişi başarıyla kaydedildi.';
    } catch (PDOException $ex) {
        $status = 'error';
        $message = $ex->getMessage();
    }
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'rowData' => $rowData
    ]);
}

if ($action == 'kayitSil') {
    $id = ($_POST['id']);
    try {
        $Rehber->delete($id);
        $status = 'success';
        $message = 'Kişi başarıyla silindi.';
    } catch (PDOException $ex) {
        $status = 'error';
        $message = $ex->getMessage();
    }
    echo json_encode([
        'status' => $status,
        'message' => $message
    ]);
}

//Güncelleme için kayıt bilgilerini getir
if ($action == 'kayitGetir') {
    $id = Security::decrypt($_POST['id']);
    $kisi = $Rehber->find($id);
    echo json_encode($kisi);
}
