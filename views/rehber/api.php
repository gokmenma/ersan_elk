<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\RehberModel;
use App\Model\PermissionPolicyModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

$Rehber = new RehberModel();
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('rehber/api', $action);
} elseif (!Gate::allows('rehber/list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Rehber işlemleri için yetkiniz bulunmamaktadır.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// DataTables Server-Side AJAX Listesi
if ($action === 'rehber-ajax-list' || $action === 'list') {
    try {
        $result = $Rehber->ajaxList($_POST);

        $formattedData = [];
        $i = max(0, (int) ($_POST['start'] ?? 0));

        foreach ($result['data'] as $row) {
            $i++;
            $enc_id = Security::encrypt($row->id);
            $safeAdi = htmlspecialchars($row->adi_soyadi ?? '', ENT_QUOTES, 'UTF-8');
            $safeKurum = htmlspecialchars($row->kurum_adi ?? '', ENT_QUOTES, 'UTF-8');
            $safeTel = htmlspecialchars($row->telefon ?? '', ENT_QUOTES, 'UTF-8');
            $safeEmail = htmlspecialchars($row->email ?? '', ENT_QUOTES, 'UTF-8');
            $safeAdres = htmlspecialchars($row->adres ?? '', ENT_QUOTES, 'UTF-8');
            $safeAciklama = htmlspecialchars($row->aciklama ?? '', ENT_QUOTES, 'UTF-8');

            // Adı Soyadı (Cari formatı: temiz ve çakışmasız)
            $adiHtml = '<a href="javascript:void(0);" class="fw-bold text-dark font-size-13 kayit-duzenle d-inline-flex align-items-center" data-id="' . $enc_id . '">'
                . '<i class="bx bx-user text-primary me-1.5 font-size-15"></i>'
                . '<span>' . $safeAdi . '</span>'
                . '</a>';

            $kurumHtml = !empty($safeKurum)
                ? '<span class="fw-medium text-secondary font-size-12"><i class="bx bx-buildings text-muted me-1"></i>' . $safeKurum . '</span>'
                : '<span class="text-muted">-</span>';

            $cleanPhone = preg_replace('/[^0-9+]/', '', (string) ($row->telefon ?? ''));
            $telHtml = !empty($safeTel)
                ? '<a href="tel:' . $cleanPhone . '" class="text-dark fw-semibold font-size-12 d-inline-flex align-items-center gap-1 text-nowrap"><i class="bx bx-phone text-primary font-size-14"></i>' . $safeTel . '</a>'
                : '<span class="text-muted">-</span>';

            $isEmail = filter_var(trim((string) ($row->email ?? '')), FILTER_VALIDATE_EMAIL);
            if ($isEmail) {
                $emailHtml = '<a href="mailto:' . $safeEmail . '" class="text-secondary font-size-12 d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 200px;"><i class="bx bx-envelope font-size-14 text-muted"></i>' . $safeEmail . '</a>';
            } elseif (!empty($safeEmail)) {
                $emailHtml = '<span class="text-secondary font-size-12 text-truncate d-inline-block" style="max-width: 200px;" title="' . $safeEmail . '">' . $safeEmail . '</span>';
            } else {
                $emailHtml = '<span class="text-muted">-</span>';
            }

            $adresHtml = !empty($safeAdres)
                ? '<span class="text-secondary font-size-12 text-truncate d-inline-block" style="max-width: 200px;" title="' . $safeAdres . '"><i class="bx bx-map-pin text-muted me-1"></i>' . $safeAdres . '</span>'
                : '<span class="text-muted">-</span>';

            $aciklamaHtml = !empty($safeAciklama)
                ? '<span class="text-secondary font-size-12 text-truncate d-inline-block" style="max-width: 220px;" title="' . $safeAciklama . '"><i class="bx bx-note text-muted me-1"></i>' . $safeAciklama . '</span>'
                : '<span class="text-muted">-</span>';

            $tarihHtml = !empty($row->kayit_tarihi)
                ? '<span class="badge bg-light text-secondary border font-size-11 fw-normal">' . date('d.m.Y', strtotime($row->kayit_tarihi)) . '</span>'
                : '<span class="text-muted">-</span>';

            $actionsHtml = '
                <div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">
                    <button type="button" class="btn btn-subtle-warning table-action-btn kayit-duzenle" data-id="' . $enc_id . '" title="Düzenle">
                        <i class="bx bx-edit-alt font-size-14"></i>
                    </button>
                    <button type="button" class="btn btn-subtle-danger table-action-btn kayit-sil" data-id="' . $enc_id . '" data-name="' . $safeAdi . '" title="Sil">
                        <i class="bx bx-trash font-size-14"></i>
                    </button>
                </div>';

            $formattedData[] = [
                'id' => '<span class="fw-semibold text-muted font-size-12">' . $i . '</span>',
                'adi_soyadi' => $adiHtml,
                'kurum_adi' => $kurumHtml,
                'telefon' => $telHtml,
                'email' => $emailHtml,
                'adres' => $adresHtml,
                'aciklama' => $aciklamaHtml,
                'kayit_tarihi' => $tarihHtml,
                'actions' => $actionsHtml,
                'DT_RowId' => 'rehber_row_' . $row->id,
                'DT_RowData' => ['id' => $enc_id]
            ];
        }

        echo json_encode([
            'draw' => $result['draw'],
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $formattedData
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $ex) {
        error_log('Rehber ajaxList hatası: ' . $ex->getMessage());
        echo json_encode([
            'draw' => (int) ($_POST['draw'] ?? 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Rehber listesi yüklenirken bir hata oluştu.'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Kayıt Ekle / Güncelle
if ($action === 'kaydet') {
    $rawId = $_POST['id'] ?? 0;
    $id = 0;
    if (!empty($rawId) && $rawId !== '0') {
        $id = Security::decrypt($rawId);
        if (!$id && is_numeric($rawId)) {
            $id = (int) $rawId;
        }
    }

    try {
        $adi_soyadi = trim((string) ($_POST['adi_soyadi'] ?? ''));
        $telefon = trim((string) ($_POST['telefon'] ?? ''));

        if ($adi_soyadi === '' || $telefon === '') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Adı Soyadı ve Telefon alanları zorunludur.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = [
            'adi_soyadi' => $adi_soyadi,
            'kurum_adi' => trim((string) ($_POST['kurum_adi'] ?? '')),
            'telefon' => $telefon,
            'email' => trim((string) ($_POST['email'] ?? '')),
            'adres' => trim((string) ($_POST['adres'] ?? '')),
            'aciklama' => trim((string) ($_POST['aciklama'] ?? ''))
        ];

        if (!empty($id)) {
            $data['id'] = $id;
        }

        $lastInsertedId = $Rehber->saveWithAttr($data) ?? $id;

        echo json_encode([
            'status' => 'success',
            'message' => !empty($id) ? 'Kişi bilgileri başarıyla güncellendi.' : 'Yeni kayıt başarıyla oluşturuldu.'
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $ex) {
        error_log('Rehber kaydet hatası: ' . $ex->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Kayıt işlemi sırasında bir hata oluştu.'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Kayıt Sil
if ($action === 'kayitSil') {
    $rawId = $_POST['id'] ?? 0;
    $id = Security::decrypt($rawId);
    if (!$id && is_numeric($rawId)) {
        $id = (int) $rawId;
    }

    try {
        if (empty($id)) {
            throw new \Exception('Geçersiz kayıt ID.');
        }

        $Rehber->delete($id);

        echo json_encode([
            'status' => 'success',
            'message' => 'Kayıt başarıyla silindi.'
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $ex) {
        error_log('Rehber silme hatası: ' . $ex->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Kayıt silinirken bir hata oluştu.'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Güncelleme için kayıt bilgilerini getir
if ($action === 'kayitGetir') {
    $rawId = $_POST['id'] ?? 0;
    $id = Security::decrypt($rawId);
    if (!$id && is_numeric($rawId)) {
        $id = (int) $rawId;
    }

    $kisi = $Rehber->find($id);
    if ($kisi) {
        echo json_encode($kisi, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
