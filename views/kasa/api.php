<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Helper\Security;
use App\Model\KasaModel;
use App\Model\PermissionPolicyModel;
use App\Service\Gate;

$Kasa = new KasaModel();
$action = trim((string) ($_POST['action'] ?? ''));
header('Content-Type: application/json; charset=utf-8');

if (!in_array($action, ['kasa_kaydet', 'kasa_sil'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tanımsız kasa API aksiyonu.']);
    exit;
}

$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('kasa/api', $action);
} elseif (!Gate::allows('gelir_gider_takibi')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu kasa işlemi için yetkiniz bulunmamaktadır.']);
    exit;
}

if ($_POST['action'] == 'kasa_kaydet') {

    $id = Security::decrypt($_POST['enc_kasa_id']);
    $varsayilan_kasa = isset($_POST['varsayilan_kasa']) ? 1 : 0;
    $owner_id = $_SESSION['owner_id'];

    /**Diğer kasaları varsayılan olmaktan çıkar */
    if ($varsayilan_kasa == 1) {
        $Kasa->resetDefaultCashboxesExcept($owner_id);
    }

    /** Kasa Kodu varmı kontrol et */
    if ($Kasa->isCashboxCodeExists($owner_id, $_POST['hesap_no'], $id)) {
        $status = "error";
        $message = "Bu Hesap Numarası zaten mevcut.";
        echo json_encode(['status' => $status, 'message' => $message]);
        exit;
    }

    try {
        $Kasa->db->beginTransaction();
        $data = [
            'id' => $id,
            'owner_id' => $owner_id,
            "sube_id" => $_SESSION['sube_id'],
            'kasa_adi' => $_POST['kasa_adi'],
            'hesap_no' => $_POST['hesap_no'],
            'varsayilan_mi' => $varsayilan_kasa,
            'aciklama' => $_POST['aciklama'],
            'aktif' => isset($_POST['aktif']) ? 1 : 0,
        ];

        $Kasa->saveWithAttr($data);

        $Kasa->db->commit();
        $status = "success";
        $message = "Kasa başarıyla kaydedildi.";
    } catch (PDOException $ex) {
        $Kasa->db->rollBack();
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ];
    echo json_encode($res);
}


/**Kasa Sil */
if ($_POST['action'] == 'kasa_sil') {
    $id = Security::decrypt($_POST['enc_kasa_id']);

    try {
        $Kasa->db->beginTransaction();

        // Kasa kaydını sil
        $Kasa->softDelete($id);

        $Kasa->db->commit();
        $status = "success";
        $message = "Kasa başarıyla silindi.";
    } catch (PDOException $ex) {
        $Kasa->db->rollBack();
        $status = "error";
        $message = $ex->getMessage();
    }

    $res = [
        'status' => $status,
        'message' => $message,
    ];
    echo json_encode($res);
}
