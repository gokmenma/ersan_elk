<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\MobilePersonelModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function personnelResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function personnelInitials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) {
        return '?';
    }
    $selected = count($parts) > 1 ? [$parts[0], $parts[count($parts) - 1]] : [$parts[0]];
    return implode('', array_map(static fn(string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), $selected));
}

function personnelPublicRow(array $row, ?array $workDetails = null): array
{
    $active = empty($row['isten_cikis_tarihi']) || $row['isten_cikis_tarihi'] === '0000-00-00';
    $name = trim((string) ($row['adi_soyadi'] ?? '')) ?: 'İsimsiz Personel';
    $encryptedId = Security::encrypt((string) $row['id']);
    $hasPhoto = trim((string) ($row['personel_resim_yolu'] ?? $row['resim_yolu'] ?? '')) !== '';
    return [
        'id' => $encryptedId,
        'avatar' => $hasPhoto ? '/api/personnel-photo.php?id=' . rawurlencode($encryptedId) : null,
        'name' => $name,
        'initials' => personnelInitials($name),
        'phone' => trim((string) ($row['cep_telefonu'] ?? '')),
        'email' => trim((string) ($row['email_adresi'] ?? '')),
        'job' => trim((string) ($row['gorev'] ?? '')),
        'department' => trim((string) ($row['departman'] ?? '')),
        'team' => trim((string) ($row['team'] ?? '')),
        'region' => trim((string) ($row['team_region'] ?? $row['ekip_bolge'] ?? '')),
        'start_date' => !empty($row['ise_giris_tarihi']) && $row['ise_giris_tarihi'] !== '0000-00-00' ? $row['ise_giris_tarihi'] : null,
        'end_date' => !$active ? $row['isten_cikis_tarihi'] : null,
        'active' => $active,
        'general' => [
            'tc_kimlik_no' => trim((string) ($row['tc_kimlik_no'] ?? '')),
            'dogum_tarihi' => !empty($row['dogum_tarihi']) && $row['dogum_tarihi'] !== '0000-00-00' ? $row['dogum_tarihi'] : '',
            'cinsiyet' => (string) ($row['cinsiyet'] ?? ''),
            'medeni_durum' => (string) ($row['medeni_durum'] ?? ''),
            'kan_grubu' => (string) ($row['kan_grubu'] ?? ''),
            'anne_adi' => (string) ($row['anne_adi'] ?? ''),
            'baba_adi' => (string) ($row['baba_adi'] ?? ''),
            'dogum_yeri_il' => (string) ($row['dogum_yeri_il'] ?? ''),
            'dogum_yeri_ilce' => (string) ($row['dogum_yeri_ilce'] ?? ''),
            'ehliyet_sinifi' => (string) ($row['ehliyet_sinifi'] ?? ''),
            'seyahat_engeli' => (string) ($row['seyahat_engeli'] ?? 'Yok'),
            'cep_telefonu' => trim((string) ($row['cep_telefonu'] ?? '')),
            'cep_telefonu_2' => trim((string) ($row['cep_telefonu_2'] ?? '')),
            'email_adresi' => trim((string) ($row['email_adresi'] ?? '')),
            'adres' => (string) ($row['adres'] ?? ''),
            'ayakkabi_numarasi' => (string) ($row['ayakkabi_numarasi'] ?? ''),
            'ust_beden_no' => (string) ($row['ust_beden_no'] ?? ''),
            'alt_beden_no' => (string) ($row['alt_beden_no'] ?? ''),
            'esi_calisiyor_mu' => (string) ($row['esi_calisiyor_mu'] ?? 'Hayır'),
            'kaski_kullanici_adi' => (string) ($row['kaski_kullanici_adi'] ?? ''),
            'kaski_sifre' => (string) ($row['kaski_sifre'] ?? ''),
            'has_login_password' => !empty($row['sifre']),
            'acil_kisi_adi_soyadi' => (string) ($row['acil_kisi_adi_soyadi'] ?? ''),
            'acil_kisi_yakinlik' => (string) ($row['acil_kisi_yakinlik'] ?? ''),
            'acil_kisi_telefonu' => (string) ($row['acil_kisi_telefonu'] ?? ''),
        ],
        'work' => $workDetails ?: [
            'ise_giris_tarihi' => !empty($row['ise_giris_tarihi']) && $row['ise_giris_tarihi'] !== '0000-00-00' ? $row['ise_giris_tarihi'] : null,
            'isten_cikis_tarihi' => !$active ? $row['isten_cikis_tarihi'] : null,
            'personel_sinifi' => $row['personel_sinifi'] ?? 'Beyaz Yaka',
            'saha_takibi' => (int) ($row['saha_takibi'] ?? 0),
            'arac_kullanim' => $row['arac_kullanim'] ?? 'Yok',
            'sgk_yapilan_firma' => $row['sgk_yapilan_firma'] ?? 'Yok',
            'isten_ayrilis_nedeni' => $row['isten_ayrilis_nedeni'] ?? null,
            'isten_ayrilis_belge_yolu' => !empty($row['isten_ayrilis_belge_yolu']) ? '/' . ltrim($row['isten_ayrilis_belge_yolu'], '/') : null,
            'departman' => $row['departman'] ?? '',
            'gorev' => $row['gorev'] ?? '',
            'aktif_calisma_var' => $active,
            'ekip_gecmisi' => [],
            'calisma_gecmisi' => [],
        ],
    ];
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user']) || empty($_SESSION['firma_id'])) {
    personnelResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
}
if (!Gate::allows('personel_listesi')) {
    personnelResponse(['success' => false, 'message' => 'Personel listesini görüntüleme yetkiniz bulunmuyor.'], 403);
}

$firmaId = (int) $_SESSION['firma_id'];
$model = new MobilePersonelModel();
$action = (string) ($_GET['action'] ?? 'list');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $result = $model->mobileDirectory([
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? 'active',
            'department' => $_GET['department'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 24,
        ], $firmaId);
        $result['items'] = array_map(static fn($r) => personnelPublicRow($r), $result['items']);
        personnelResponse(['success' => true, 'can_edit' => Gate::allows('personel_duzenle'), ...$result]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'detail') {
        $personelId = (int) Security::decrypt((string) ($_GET['id'] ?? ''));
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $row = $model->mobileDirectoryDetail($personelId, $firmaId);
        if (!$row) {
            personnelResponse(['success' => false, 'message' => 'Personel bulunamadı veya erişim yetkiniz yok.'], 404);
        }
        $canEdit = Gate::allows('personel_duzenle');
        $canViewDocuments = $canEdit && Gate::allows('evrak_bilgileri_sekmesi');
        $section = (string) ($_GET['section'] ?? 'profile');
        $allowedSections = ['profile', 'genel', 'calisma', 'izinler', 'ek_odemeler', 'kesintiler', 'evraklar', 'icralar', 'zimmetler', 'puantaj'];
        if (!in_array($section, $allowedSections, true)) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel bölümü.'], 422);
        }
        if ($section !== 'profile' && !$canEdit) {
            personnelResponse(['success' => false, 'message' => 'Personel bölümünü görüntüleme yetkiniz bulunmuyor.'], 403);
        }
        if ($section === 'evraklar' && !$canViewDocuments) {
            personnelResponse(['success' => false, 'message' => 'Personel evraklarını görüntüleme yetkiniz bulunmuyor.'], 403);
        }

        $work = $section === 'calisma' ? $model->getWorkDetails($personelId, $firmaId) : [];
        $quickList = $section === 'profile' ? $model->mobileDirectoryQuickList($firmaId) : [];
        $payload = [
            'success' => true,
            'can_edit' => $canEdit,
            'can_view_documents' => $canViewDocuments,
            'personel_id_raw' => $personelId,
            'personnel' => personnelPublicRow($row, $work),
            'personnel_quick_list' => $quickList,
        ];

        if ($section === 'calisma') {
            $payload['work_options'] = $model->getWorkOptions($firmaId);
        } elseif ($section === 'izinler') {
            $data = $model->getLeaveDetails($personelId, $firmaId);
            $payload += ['leaves' => $data['leaves'], 'entitlement' => $data['entitlement'], 'leave_types' => $data['leave_types']];
        } elseif ($section === 'ek_odemeler') {
            $data = $model->getEkOdemelerDetails($personelId, $firmaId);
            $payload += ['ek_odemeler' => $data['items'], 'ek_odeme_ozet' => $data['summary'], 'gelir_parametreleri' => $data['gelir_parametreleri'], 'bordro_donemleri' => $data['donemler']];
        } elseif ($section === 'kesintiler') {
            $data = $model->getKesintilerDetails($personelId, $firmaId);
            $payload += ['kesintiler' => $data['items'], 'kesinti_ozet' => $data['summary'], 'kesinti_parametreleri' => $data['kesinti_parametreleri'], 'icra_dosyalari' => $data['icralar']];
        } elseif ($section === 'evraklar') {
            $data = $model->getEvrakDetails($personelId, $firmaId);
            $payload += ['evraklar' => $data['items'], 'evrak_ozet' => $data['summary'], 'evrak_turleri' => \App\Helper\Helper::EVRAK_TURLERI];
        } elseif ($section === 'icralar') {
            $data = $model->getIcraDetails($personelId, $firmaId);
            $payload += ['icralar' => $data['items'], 'icra_ozet' => $data['summary'], 'icra_daireleri' => $data['icra_daireleri']];
        } elseif ($section === 'zimmetler') {
            $data = $model->getZimmetDetails($personelId, $firmaId);
            $payload += ['zimmetler' => $data['items'], 'zimmet_ozet' => $data['summary']];
        } elseif ($section === 'puantaj') {
            $data = $model->getPuantajIsTakipDetails($personelId, $firmaId, ['limit' => 50]);
            $payload += ['puantaj_items' => $data['items'], 'puantaj_ozet' => $data['summary'], 'puantaj_work_types' => $data['work_types'], 'puantaj_work_results' => $data['work_results']];
        }

        personnelResponse($payload);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'quick_list') {
        personnelResponse(['success' => true, 'items' => $model->mobileDirectoryQuickList($firmaId)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_general') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['id']) ? 0 : (int) Security::decrypt((string) $data['id']);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $model->saveMobileGeneralInfo($personelId, $data, $firmaId);
        try {
            (new \App\Model\SystemLogModel())->logAction(
                (int) $_SESSION['user_id'],
                'Personel Genel Bilgiler Güncelleme (Mobil)',
                sprintf('%d ID\'li personelin genel bilgileri mobil uygulamadan güncellendi.', $personelId),
                \App\Model\SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (Throwable $ignored) {
        }
        $row = $model->mobileDirectoryDetail($personelId, $firmaId);
        $work = $model->getWorkDetails($personelId, $firmaId);
        personnelResponse(['success' => true, 'message' => 'Genel bilgiler başarıyla güncellendi.', 'personnel' => personnelPublicRow($row ?: [], $work)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_work_info') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['id']) ? 0 : (int) Security::decrypt((string) $data['id']);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $model->saveWorkInfo($personelId, $data, $firmaId);
        try {
            (new \App\Model\SystemLogModel())->logAction(
                (int) $_SESSION['user_id'],
                'Personel Çalışma Bilgileri Güncelleme (Mobil)',
                sprintf('%d ID\'li personelin çalışma bilgileri mobil uygulamadan güncellendi.', $personelId),
                \App\Model\SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (Throwable $ignored) {
        }
        $row = $model->mobileDirectoryDetail($personelId, $firmaId);
        $work = $model->getWorkDetails($personelId, $firmaId);
        personnelResponse(['success' => true, 'message' => 'Çalışma bilgileri başarıyla güncellendi.', 'personnel' => personnelPublicRow($row ?: [], $work)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_team_history') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        $res = $model->saveEkipGecmisi($data, $firmaId);
        $work = $model->getWorkDetails($personelId, $firmaId);
        personnelResponse(['success' => true, 'message' => $res['message'], 'work' => $work]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_team_history') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $id = (int) ($data['id'] ?? 0);
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        $force = !empty($data['force']);
        $res = $model->deleteEkipGecmisi($id, $firmaId, $force);
        if (($res['status'] ?? '') === 'confirm') {
            personnelResponse(['success' => false, 'status' => 'confirm', 'message' => $res['message']]);
        }
        $work = $personelId > 0 ? $model->getWorkDetails($personelId, $firmaId) : null;
        personnelResponse(['success' => true, 'message' => $res['message'], 'work' => $work]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_work_history') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        $res = $model->saveCalismaGecmisi($data, $firmaId);
        $work = $model->getWorkDetails($personelId, $firmaId);
        personnelResponse(['success' => true, 'message' => $res['message'], 'work' => $work]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_work_history') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $id = (int) ($data['id'] ?? 0);
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        $res = $model->deleteCalismaGecmisi($id, $firmaId);
        $work = $personelId > 0 ? $model->getWorkDetails($personelId, $firmaId) : null;
        personnelResponse(['success' => true, 'message' => $res['message'], 'work' => $work]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_leave') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        $userId = (int) $_SESSION['user_id'];
        $res = $model->saveLeave($data, $firmaId, $userId);
        $leaveData = $model->getLeaveDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'leaves' => $leaveData['leaves'],
            'entitlement' => $leaveData['entitlement'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_leave') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $id = (int) ($data['id'] ?? 0);
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        $userId = (int) $_SESSION['user_id'];
        $res = $model->deleteLeave($id, $personelId, $firmaId, $userId);
        $leaveData = $model->getLeaveDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'leaves' => $leaveData['leaves'],
            'entitlement' => $leaveData['entitlement'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve_or_reject_leave') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $id = (int) ($data['id'] ?? 0);
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        $status = (string) ($data['status'] ?? 'Onaylandı');
        $note = !empty($data['note']) ? (string) $data['note'] : null;
        $userId = (int) $_SESSION['user_id'];
        $res = $model->approveOrRejectLeave($id, $personelId, $status, $note, $firmaId, $userId);
        $leaveData = $model->getLeaveDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'leaves' => $leaveData['leaves'],
            'entitlement' => $leaveData['entitlement'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_ek_odeme') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        $userId = (int) $_SESSION['user_id'];
        $res = $model->saveEkOdeme($data, $firmaId, $userId);
        $ekOdemelerData = $model->getEkOdemelerDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'ek_odemeler' => $ekOdemelerData['items'],
            'ek_odeme_ozet' => $ekOdemelerData['summary'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_ek_odeme') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $id = (int) ($data['id'] ?? 0);
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        if ($id <= 0 || $personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz işlem parametreleri.'], 422);
        }
        $userId = (int) $_SESSION['user_id'];
        $res = $model->deleteEkOdeme($id, $personelId, $firmaId, $userId);
        $ekOdemelerData = $model->getEkOdemelerDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'ek_odemeler' => $ekOdemelerData['items'],
            'ek_odeme_ozet' => $ekOdemelerData['summary'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'sonlandir_ek_odeme') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $id = (int) ($data['id'] ?? 0);
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        $bitisDonemi = !empty($data['bitis_donemi']) ? (string) $data['bitis_donemi'] : null;
        if ($id <= 0 || $personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz işlem parametreleri.'], 422);
        }
        $userId = (int) $_SESSION['user_id'];
        $res = $model->sonlandirEkOdeme($id, $personelId, $bitisDonemi, $firmaId, $userId);
        $ekOdemelerData = $model->getEkOdemelerDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'ek_odemeler' => $ekOdemelerData['items'],
            'ek_odeme_ozet' => $ekOdemelerData['summary'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_kesinti') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        $userId = (int) $_SESSION['user_id'];
        $res = $model->saveKesinti($data, $firmaId, $userId);
        $kesintilerData = $model->getKesintilerDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'kesintiler' => $kesintilerData['items'],
            'kesinti_ozet' => $kesintilerData['summary'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_kesinti') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $id = (int) ($data['id'] ?? 0);
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        if ($id <= 0 || $personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz işlem parametreleri.'], 422);
        }
        $userId = (int) $_SESSION['user_id'];
        $res = $model->deleteKesinti($id, $personelId, $firmaId, $userId);
        $kesintilerData = $model->getKesintilerDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'kesintiler' => $kesintilerData['items'],
            'kesinti_ozet' => $kesintilerData['summary'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'sonlandir_kesinti') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $id = (int) ($data['id'] ?? 0);
        $personelId = empty($data['personel_id']) ? 0 : (int) Security::decrypt((string) $data['personel_id']);
        $bitisDonemi = !empty($data['bitis_donemi']) ? (string) $data['bitis_donemi'] : null;
        if ($id <= 0 || $personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz işlem parametreleri.'], 422);
        }
        $userId = (int) $_SESSION['user_id'];
        $res = $model->sonlandirKesinti($id, $personelId, $bitisDonemi, $firmaId, $userId);
        $kesintilerData = $model->getKesintilerDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'],
            'kesintiler' => $kesintilerData['items'],
            'kesinti_ozet' => $kesintilerData['summary'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['id']) ? 0 : (int) Security::decrypt((string) $data['id']);
        $savedId = $model->saveMobileProfile($data, $firmaId, $personelId);
        try {
            (new \App\Model\SystemLogModel())->logAction(
                (int) $_SESSION['user_id'],
                $personelId > 0 ? 'Personel Güncelleme (Mobil)' : 'Personel Ekleme (Mobil)',
                sprintf('%s personel kaydı mobil uygulamadan %s.', (string) ($data['name'] ?? ''), $personelId > 0 ? 'güncellendi' : 'oluşturuldu'),
                \App\Model\SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (Throwable $ignored) {
        }
        $row = $model->mobileDirectoryDetail($savedId, $firmaId);
        personnelResponse(['success' => true, 'message' => 'Personel bilgileri kaydedildi.', 'personnel' => personnelPublicRow($row ?: [])]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload_evrak') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Evrak yükleme yetkiniz bulunmuyor.'], 403);
        }
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawPersonelId = (string) ($_POST['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        if (!isset($_FILES['dosya'])) {
            personnelResponse(['success' => false, 'message' => 'Yüklenecek dosya seçilmedi.'], 422);
        }
        $res = $model->uploadEvrak($personelId, $_FILES['dosya'], $_POST, $firmaId, (int) $_SESSION['user_id']);
        $evrakData = $model->getEvrakDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'] ?? 'Evrak başarıyla yüklendi.',
            'evraklar' => $evrakData['items'],
            'evrak_ozet' => $evrakData['summary'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_evrak') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Evrak silme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawId = (string) ($data['id'] ?? '');
        $id = ctype_digit($rawId) ? (int) $rawId : (int) Security::decrypt($rawId);
        $rawPersonelId = (string) ($data['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        if ($id <= 0 || $personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz kayıt bilgisi.'], 422);
        }
        $res = $model->deleteEvrak($id, $personelId, $firmaId, (int) $_SESSION['user_id']);
        $evrakData = $model->getEvrakDetails($personelId, $firmaId);
        personnelResponse([
            'success' => true,
            'message' => $res['message'] ?? 'Evrak silindi.',
            'evraklar' => $evrakData['items'],
            'evrak_ozet' => $evrakData['summary'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'icra_kesintileri') {
        $rawPersonelId = (string) ($_GET['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        $icraId = (int) ($_GET['icra_id'] ?? 0);
        if ($personelId <= 0 || $icraId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz kayıt bilgisi.'], 422);
        }
        $result = $model->getIcraKesintileri($icraId, $personelId, $firmaId);
        personnelResponse($result);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_icra') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'İcra dosyası düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawPersonelId = (string) ($data['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        $result = $model->saveIcra($data, $firmaId, (int) $_SESSION['user_id']);
        personnelResponse($result);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_icra') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'İcra dosyası silme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawPersonelId = (string) ($data['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        $icraId = (int) ($data['id'] ?? 0);
        if ($personelId <= 0 || $icraId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz kayıt bilgisi.'], 422);
        }
        $result = $model->deleteIcra($icraId, $personelId, $firmaId, (int) $_SESSION['user_id']);
        personnelResponse($result);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_zimmet') {
        if (!Gate::allows('personel_duzenle') && !Gate::allows('demirbas/zimmet')) {
            personnelResponse(['success' => false, 'message' => 'Zimmet verme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawPersonelId = (string) ($data['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        $result = $model->saveZimmet($data, $firmaId, (int) $_SESSION['user_id']);
        personnelResponse($result);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'iade_zimmet') {
        if (!Gate::allows('personel_duzenle') && !Gate::allows('demirbas/zimmet')) {
            personnelResponse(['success' => false, 'message' => 'Zimmet iade alma yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawPersonelId = (string) ($data['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        if (!empty($data['id']) && !ctype_digit((string) $data['id'])) {
            $data['id'] = (int) Security::decrypt((string) $data['id']);
        }
        $result = $model->iadeZimmet($data, $firmaId, (int) $_SESSION['user_id']);
        personnelResponse($result);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_zimmet') {
        if (!Gate::allows('personel_duzenle') && !Gate::allows('demirbas/zimmet')) {
            personnelResponse(['success' => false, 'message' => 'Zimmet silme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawPersonelId = (string) ($data['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        $rawZimmetId = (string) ($data['id'] ?? '');
        $zimmetId = ctype_digit($rawZimmetId) ? (int) $rawZimmetId : (int) Security::decrypt($rawZimmetId);
        if ($personelId <= 0 || $zimmetId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz kayıt bilgisi.'], 422);
        }
        $result = $model->deleteZimmet($zimmetId, $personelId, $firmaId, (int) $_SESSION['user_id']);
        personnelResponse($result);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'zimmet_hareketleri') {
        $rawPersonelId = (string) ($_GET['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        $rawZimmetId = (string) ($_GET['id'] ?? '');
        $zimmetId = ctype_digit($rawZimmetId) ? (int) $rawZimmetId : (int) Security::decrypt($rawZimmetId);
        if ($personelId <= 0 || $zimmetId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz kayıt bilgisi.'], 422);
        }
        $result = $model->getZimmetHareketleri($zimmetId, $personelId, $firmaId);
        personnelResponse($result);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'search_demirbas') {
        $search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 50);
        $options = $model->getDemirbasOptions($firmaId, $search);
        personnelResponse(['success' => true, 'options' => $options]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'puantaj_is_takip') {
        $rawPersonelId = (string) ($_GET['personel_id'] ?? $_GET['id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $filters = [
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
            'limit' => $_GET['limit'] ?? 150,
        ];
        $result = $model->getPuantajIsTakipDetails($personelId, $firmaId, $filters);
        personnelResponse(['success' => true, ...$result]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_puantaj_job') {
        if (!Gate::allows('personel_duzenle') && !Gate::allows('puantaj_yonetim')) {
            personnelResponse(['success' => false, 'message' => 'İş kaydı oluşturma yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawPersonelId = (string) ($data['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $data['personel_id'] = $personelId;
        $result = $model->savePuantajJob($data, $firmaId, (int) $_SESSION['user_id']);
        personnelResponse($result);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_puantaj_job') {
        if (!Gate::allows('personel_duzenle') && !Gate::allows('puantaj_yonetim')) {
            personnelResponse(['success' => false, 'message' => 'İş kaydı silme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $rawPersonelId = (string) ($data['personel_id'] ?? '');
        $personelId = ctype_digit($rawPersonelId) ? (int) $rawPersonelId : (int) Security::decrypt($rawPersonelId);
        $rawJobId = (string) ($data['id'] ?? '');
        $jobId = ctype_digit($rawJobId) ? (int) $rawJobId : (int) Security::decrypt($rawJobId);
        $type = (string) ($data['type'] ?? 'yapilan_is');
        if ($personelId <= 0 || $jobId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz kayıt bilgisi.'], 422);
        }
        $result = $model->deletePuantajJob($jobId, $type, $personelId, $firmaId, (int) $_SESSION['user_id']);
        personnelResponse($result);
    }

    personnelResponse(['success' => false, 'message' => 'Geçersiz işlem.'], $_SERVER['REQUEST_METHOD'] === 'GET' ? 404 : 405);
} catch (InvalidArgumentException $error) {
    personnelResponse(['success' => false, 'message' => $error->getMessage()], 422);
} catch (RuntimeException $error) {
    personnelResponse(['success' => false, 'message' => $error->getMessage()], 403);
} catch (Throwable $error) {
    error_log('Mobile personnel API: ' . $error->getMessage());
    personnelResponse(['success' => false, 'message' => 'Personel bilgileri alınamadı.'], 500);
}
