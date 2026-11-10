<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\DuyuruModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Helper\Security;
use App\Model\PermissionPolicyModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $permissionPolicy = new PermissionPolicyModel();
    if ($permissionPolicy->isReady()) {
        Gate::authorizeApiPolicy('duyuru/api', (string) $action);
    } elseif (!Gate::allows('duyuru_etkinlik')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Duyuru işlemleri için yetkiniz bulunmamaktadır.']);
        exit;
    }
    $model = new DuyuruModel();
    $logModel = new SystemLogModel();

    try {
        $resolveId = static function ($raw): int {
            $dec = Security::decrypt((string) $raw);
            return ctype_digit((string) $dec) ? (int) $dec : 0;
        };

        switch ($action) {
            case 'ajax-list':
                $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
                $res = $model->ajaxList($_POST, $firmaId);
                $esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
                $rows = [];
                foreach ($res['data'] as $d) {
                    $encId = Security::encrypt($d->id);
                    $isExpired = (int) $d->suresi_doldu === 1;

                    $thumb = !empty($d->resim)
                        ? '<img src="' . $esc($d->resim) . '" class="duyuru-thumb" alt="">'
                        : '<div class="duyuru-thumb duyuru-thumb-empty"><i class="bx bx-image"></i></div>';
                    $baslik = '<div class="d-flex align-items-center gap-2">' . $thumb
                        . '<span class="duyuru-title">' . $esc($d->baslik) . '</span></div>';

                    $icerikText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $d->icerik), ENT_QUOTES, 'UTF-8')));
                    $icerik = '<span class="text-muted font-size-12 d-inline-block text-truncate" style="max-width:260px" title="'
                        . $esc(mb_strimwidth($icerikText, 0, 400, '...')) . '">' . $esc(mb_strimwidth($icerikText, 0, 120, '...')) . '</span>';

                    $hedef = '';
                    if ((int) $d->ana_sayfada_goster === 1) {
                        $hedef .= '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold me-1">Admin Ana Sayfa</span>';
                    }
                    if ((int) $d->pwa_goster === 1) {
                        $hedef .= '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Personel Ana Sayfa</span>';
                    }
                    if ($hedef === '') {
                        $hedef = '<span class="text-muted font-size-12">Gösterilmiyor</span>';
                    }

                    if ($d->alici_tipi === 'toplu') {
                        $kitle = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Tüm Personel</span>';
                    } else {
                        $adet = count(array_filter(explode(',', (string) $d->alici_ids), 'strlen'));
                        $kitle = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Özel Liste (' . $adet . ' kişi)</span>';
                    }

                    $tarih = '<div class="font-size-12 text-muted">Yayın: ' . $esc(date('d.m.Y', strtotime($d->tarih))) . '</div>';
                    if (!empty($d->etkinlik_tarihi)) {
                        $tarih .= '<div class="font-size-12 fw-semibold ' . ($isExpired ? 'text-danger' : 'text-primary') . '">Bitiş: ' . $esc(date('d.m.Y', strtotime($d->etkinlik_tarihi))) . '</div>';
                    }

                    $durumMap = [
                        'Yayında' => 'success',
                        'Taslak' => 'secondary',
                        'Kapalı' => 'dark',
                    ];
                    $dc = $durumMap[$d->durum] ?? 'secondary';
                    $durum = '<span class="badge bg-' . $dc . '-subtle text-' . $dc . ' border border-' . $dc . '-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">' . $esc($d->durum) . '</span>';
                    if ($isExpired) {
                        $durum .= ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Süresi Doldu</span>';
                    }

                    $actions = '<div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">'
                        . '<button type="button" class="btn btn-subtle-warning table-action-btn btn-edit" data-id="' . $esc($encId) . '" title="Düzenle" aria-label="Duyuruyu düzenle"><i class="bx bx-edit-alt font-size-14"></i></button>'
                        . '<button type="button" class="btn btn-subtle-danger table-action-btn btn-delete" data-id="' . $esc($encId) . '" title="Sil" aria-label="Duyuruyu sil"><i class="bx bx-trash font-size-14"></i></button>'
                        . '</div>';

                    $rows[] = [
                        'id' => '<span class="fw-semibold text-muted font-size-12">' . (int) $d->id . '</span>',
                        'baslik' => $baslik,
                        'icerik' => $icerik,
                        'hedef' => $hedef,
                        'kitle' => $kitle,
                        'tarih' => $tarih,
                        'durum' => $durum,
                        'actions' => $actions,
                    ];
                }
                $res['data'] = $rows;
                $res['summary'] = $model->summary($firmaId);
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                break;

            case 'save':
                $id = !empty($_POST['id']) ? $resolveId($_POST['id']) : null;
                if (!empty($_POST['id'])) {
                    $mevcut = $id ? $model->find($id) : null;
                    if (!$mevcut || (int) ($mevcut->firma_id ?? 0) !== (int) ($_SESSION['firma_id'] ?? 0)) {
                        throw new Exception('Geçersiz ID.');
                    }
                }
                $etkinlikTarihi = null;
                if (!empty($_POST['etkinlik_tarihi'])) {
                    $date = DateTime::createFromFormat('Y-m-d', $_POST['etkinlik_tarihi']);
                    if (!$date) {
                        $date = DateTime::createFromFormat('d.m.Y', $_POST['etkinlik_tarihi']);
                    }
                    if ($date) {
                        $etkinlikTarihi = $date->format('Y-m-d') . ' 23:59:59';
                    }
                }

                $data = [
                    'firma_id' => $_SESSION['firma_id'],
                    'baslik' => trim($_POST['baslik'] ?? ''),
                    'icerik' => trim($_POST['icerik'] ?? ''),
                    'hedef_sayfa' => $_POST['hedef_sayfa'] ?? '',
                    'durum' => $_POST['durum'] ?? 'Yayında',
                    'alici_tipi' => $_POST['alici_tipi'] ?? 'toplu',
                    'alici_ids' => is_array($_POST['personel_ids'] ?? null) ? implode(',', $_POST['personel_ids']) : ($_POST['alici_ids'] ?? ''),
                    'etkinlik_tarihi' => $etkinlikTarihi,
                    'ana_sayfada_goster' => isset($_POST['ana_sayfada_goster']) ? 1 : 0,
                    'pwa_goster' => isset($_POST['pwa_goster']) ? 1 : 0,
                ];

                if (empty($data['baslik'])) {
                    throw new Exception('Başlık zorunludur.');
                }

                // Resim Silme İsteği
                if (isset($_POST['resim_sil']) && $_POST['resim_sil'] == '1') {
                    $data['resim'] = null;
                }

                // Resim Yükleme
                if (isset($_FILES['resim']) && $_FILES['resim']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = dirname(__DIR__, 2) . '/uploads/duyuru/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $ext = pathinfo($_FILES['resim']['name'], PATHINFO_EXTENSION);
                    $fileName = uniqid('duyuru_') . '.' . $ext;
                    move_uploaded_file($_FILES['resim']['tmp_name'], $uploadDir . $fileName);
                    $data['resim'] = 'uploads/duyuru/' . $fileName;
                }

                if ($id) {
                    $model->updateDuyuru($id, $data);
                    $msg = 'Duyuru güncellendi.';
                    $logModel->logAction($_SESSION['user_id'] ?? 0, 'Duyuru Güncelleme', "Duyuru #{$id} ({$data['baslik']}) güncellendi.", SystemLogModel::LEVEL_INFO);
                } else {
                    $data['tarih'] = date('Y-m-d H:i:s');
                    $newId = $model->createDuyuru($data);
                    $msg = 'Duyuru oluşturuldu.';
                    $logModel->logAction($_SESSION['user_id'] ?? 0, 'Duyuru Oluşturma', "Yeni duyuru ({$data['baslik']}) oluşturuldu.", SystemLogModel::LEVEL_INFO);
                }

                unset($_SESSION['home_slider_notifications_cache_' . ($_SESSION['firma_id'] ?? 0)]);
                echo json_encode(['status' => 'success', 'message' => $msg]);
                break;

            case 'delete':
                $id = $resolveId($_POST['id'] ?? '');
                $mevcut = $id ? $model->find($id) : null;
                if ($mevcut && (int) ($mevcut->firma_id ?? 0) === (int) ($_SESSION['firma_id'] ?? 0)) {
                    $model->updateDuyuru($id, ['silinme_tarihi' => date('Y-m-d H:i:s')]);
                    $logModel->logAction($_SESSION['user_id'] ?? 0, 'Duyuru Silme', "Duyuru #{$id} silindi.", SystemLogModel::LEVEL_IMPORTANT);
                    unset($_SESSION['home_slider_notifications_cache_' . ($_SESSION['firma_id'] ?? 0)]);
                    echo json_encode(['status' => 'success', 'message' => 'Duyuru silindi.']);
                } else {
                    throw new Exception('Geçersiz ID.');
                }
                break;

            case 'get':
                $id = $resolveId($_POST['id'] ?? '');
                $item = $id ? $model->find($id) : null;
                if ($item && (int) ($item->firma_id ?? 0) === (int) ($_SESSION['firma_id'] ?? 0)) {
                    $item->enc_id = Security::encrypt($item->id);
                    echo json_encode(['status' => 'success', 'data' => $item]);
                } else {
                    throw new Exception('Kayıt bulunamadı.');
                }
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        error_log('duyuru/api: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'İşlem sırasında bir hata oluştu.']);
    }
}
