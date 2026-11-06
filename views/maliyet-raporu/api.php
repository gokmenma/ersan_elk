<?php

session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;
use App\Model\ManuelGiderModel;
use App\Model\MaliyetRaporuModel;
use App\Model\PermissionPolicyModel;
use App\Service\Gate;

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum bulunamadı.']);
    exit;
}

$ManuelGider   = new ManuelGiderModel();
$MaliyetRaporu = new MaliyetRaporuModel();

$action = $_POST['action'] ?? '';
$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('maliyet-raporu/api', (string) $action);
} elseif (!Gate::allows('maliyet_raporu')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Maliyet raporu için yetkiniz bulunmamaktadır.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ------------------------------------------------------------------ */
/*  MALİYET LİSTESİ (DataTables server-side)                           */
/* ------------------------------------------------------------------ */
if ($action === 'maliyet-ajax-list') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        [$bas, $bit] = MaliyetRaporuModel::tarihAraligi($_POST['yil'] ?? '', $_POST['ay'] ?? '');
        $kat = trim((string) ($_POST['kategori'] ?? '')) ?: null;
        $sonuc = $MaliyetRaporu->ajaxList($_POST, $bas, $bit, $kat);

        $sonuc['data'] = array_map(static function ($r) {
            return [
                'tarih'        => $r->tarih ? date('d.m.Y', strtotime($r->tarih)) : '-',
                'tarih_sort'   => $r->tarih,
                'kategori'     => $r->kategori,
                'alt_kategori' => $r->alt_kategori ?? '-',
                'tutar'        => (float) $r->tutar,
                'kaynak_tablo' => $r->kaynak_tablo,
                'aciklama'     => $r->aciklama ?? '-',
            ];
        }, $sonuc['data']);

        echo json_encode($sonuc, JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        error_log('maliyet-ajax-list: ' . $e->getMessage());
        echo json_encode(['draw' => (int) ($_POST['draw'] ?? 0), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Liste yüklenemedi.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

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
            'tarih'        => Date::Ymd($_POST['tarih']),
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
        $kayit = $ManuelGider->getDetailById((int) $id);
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
