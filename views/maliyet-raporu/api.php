<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\ManuelGiderModel;
use App\Model\MaliyetRaporuModel;

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum bulunamadı.']);
    exit;
}

$ManuelGider   = new ManuelGiderModel();
$MaliyetRaporu = new MaliyetRaporuModel();

$action = $_POST['action'] ?? '';

/* ------------------------------------------------------------------ */
/*  MANUEL GİDER KAYDET / GÜNCELLE                                     */
/* ------------------------------------------------------------------ */
if ($action === 'manuel-gider-kaydet') {
    $id = Security::decrypt($_POST['manuel_gider_id'] ?? '');

    try {
        $data = [
            'kategori'     => $_POST['kategori'],
            'alt_kategori' => $_POST['alt_kategori'] ?? null,
            'tutar'        => Helper::formattedMoneyToNumber($_POST['tutar']),
            'tarih'        => $_POST['tarih'],
            'aciklama'     => $_POST['aciklama'] ?? null,
            'belge_no'     => $_POST['belge_no'] ?? null,
        ];

        if ($id > 0) {
            $ManuelGider->updateById($id, $data);
            $lastInsertId = Security::encrypt($id);
        } else {
            $lastInsertId = $ManuelGider->create($data);
        }

        $status  = 'success';
        $message = 'Gider kaydı başarıyla kaydedildi.';
    } catch (\Exception $ex) {
        $status  = 'error';
        $message = $ex->getMessage();
    }

    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'id'      => $lastInsertId ?? null,
    ]);
    exit;
}

/* ------------------------------------------------------------------ */
/*  MANUEL GİDER SİL (Soft Delete)                                     */
/* ------------------------------------------------------------------ */
if ($action === 'manuel-gider-sil') {
    $id = Security::decrypt($_POST['id'] ?? '');

    try {
        $ManuelGider->softDelete($id);
        $status  = 'success';
        $message = 'Kayıt silindi.';
    } catch (\Exception $ex) {
        $status  = 'error';
        $message = $ex->getMessage();
    }

    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

/* ------------------------------------------------------------------ */
/*  MANUEL GİDER DETAY (Düzenleme için)                                */
/* ------------------------------------------------------------------ */
if ($action === 'manuel-gider-detay') {
    $id = Security::decrypt($_POST['id'] ?? '');

    try {
        $kayit = $ManuelGider->find($id);
        if ($kayit) {
            $kayit->enc_id = Security::encrypt($kayit->id);
            $status = 'success';
        } else {
            $status = 'error';
            $kayit  = null;
        }
    } catch (\Exception $ex) {
        $status = 'error';
        $kayit  = null;
    }

    echo json_encode(['status' => $status, 'data' => $kayit]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem.']);
