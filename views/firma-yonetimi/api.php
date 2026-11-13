<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Helper\Security;
use App\Model\FirmaModel;
use App\Service\Gate;

function firmaYonetimiYanit(array $veri, int $kod = 200): never
{
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = (int) ($_SESSION['user']->id ?? 0);
if (empty($_SESSION['loggedin']) || $userId <= 0) {
    firmaYonetimiYanit(['status' => 'error', 'message' => 'Oturum bulunamadı.'], 401);
}

$action = (string) ($_REQUEST['action'] ?? '');
$yazma = ['save', 'delete', 'set-default'];
if (!in_array($action, array_merge(['list', 'summary', 'get'], $yazma), true)) {
    firmaYonetimiYanit(['status' => 'error', 'message' => 'Tanımsız işlem.'], 400);
}

Gate::authorizeApiPolicy('firma-yonetimi/api', $action);

if (in_array($action, $yazma, true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        firmaYonetimiYanit(['status' => 'error', 'message' => 'Bu işlem POST isteği gerektirir.'], 405);
    }
    $csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        firmaYonetimiYanit(['status' => 'error', 'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.'], 403);
    }
}

$firma = new FirmaModel();
$izinli = $firma->izinliFirmaIdleri($userId);

function firmaYonetimiId(string $sifreli): int
{
    return (int) Security::decrypt($sifreli);
}

try {
    switch ($action) {
        case 'list':
            firmaYonetimiYanit($firma->ajaxList($_REQUEST, $izinli));

        case 'summary':
            firmaYonetimiYanit(['status' => 'success', 'data' => $firma->summary($izinli)]);

        case 'get':
            $id = firmaYonetimiId((string) ($_GET['id'] ?? ''));
            $kayit = $id > 0 ? $firma->getForYonetim($id, $izinli) : null;
            if (!$kayit) {
                firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma bulunamadı.'], 404);
            }
            $kayit['enc_id'] = Security::encrypt((string) $kayit['id']);
            unset($kayit['id']);
            firmaYonetimiYanit(['status' => 'success', 'data' => $kayit]);

        case 'save':
            $data = $_POST;
            unset($data['id'], $data['kayit_yapan'], $data['logo_yolu']);
            $id = !empty($data['enc_id']) ? firmaYonetimiId((string) $data['enc_id']) : 0;
            unset($data['enc_id']);

            if ($id > 0) {
                if (!$firma->getForYonetim($id, $izinli)) {
                    firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma bulunamadı.'], 404);
                }
                $data['id'] = $id;
            } else {
                if ($izinli !== null) {
                    firmaYonetimiYanit(['status' => 'error', 'message' => 'Yeni firma ekleme yetkiniz yok.'], 403);
                }
                $data['kayit_yapan'] = $userId;
            }

            if (trim((string) ($data['firma_adi'] ?? '')) === '') {
                firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma adı boş bırakılamaz.']);
            }
            $vergiNo = preg_replace('/\s+/', '', (string) ($data['vergi_no'] ?? ''));
            if ($vergiNo !== '' && $vergiNo !== '0' && !preg_match('/^\d{10,11}$/', $vergiNo)) {
                firmaYonetimiYanit(['status' => 'error', 'message' => 'Vergi No 10 haneli VKN veya 11 haneli TCKN olmalıdır.']);
            }
            $data['vergi_no'] = $vergiNo === '' ? '0' : $vergiNo;
            $iban = strtoupper(preg_replace('/\s+/', '', (string) ($data['firma_iban'] ?? '')));
            if ($iban !== '' && !preg_match('/^TR\d{24}$/', $iban)) {
                firmaYonetimiYanit(['status' => 'error', 'message' => 'IBAN TR ile başlayan 26 karakter olmalıdır.']);
            }
            $data['firma_iban'] = $iban;
            $eposta = trim((string) ($data['email'] ?? ''));
            if ($eposta !== '' && !filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
                firmaYonetimiYanit(['status' => 'error', 'message' => 'E-posta adresi geçersiz.']);
            }

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK || $_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma logosu yüklenemedi veya 2 MB sınırını aşıyor.']);
                }
                $mimeMap = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['logo']['tmp_name']);
                if (!isset($mimeMap[$mime])) {
                    firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma logosu PNG veya JPG olmalıdır.']);
                }
                $dizin = dirname(__DIR__, 2) . '/uploads/firma-logolari/';
                if (!is_dir($dizin) && !mkdir($dizin, 0777, true) && !is_dir($dizin)) {
                    firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma logo klasörü oluşturulamadı.']);
                }
                $ad = 'firma_' . ($id > 0 ? $id : 'yeni') . '_' . bin2hex(random_bytes(8)) . '.' . $mimeMap[$mime];
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dizin . $ad)) {
                    firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma logosu kaydedilemedi.']);
                }
                $data['logo_yolu'] = 'uploads/firma-logolari/' . $ad;
            }

            $firma->saveFirma($data);
            firmaYonetimiYanit(['status' => 'success', 'message' => 'Firma başarıyla kaydedildi.']);

        case 'delete':
            $id = firmaYonetimiId((string) ($_POST['id'] ?? ''));
            if ($id <= 0 || !$firma->getForYonetim($id, $izinli)) {
                firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma bulunamadı.'], 404);
            }
            if ($id === (int) ($_SESSION['firma_id'] ?? 0)) {
                firmaYonetimiYanit(['status' => 'error', 'message' => 'Aktif çalıştığınız firma silinemez.']);
            }
            $firma->deleteFirma($id);
            firmaYonetimiYanit(['status' => 'success', 'message' => 'Firma silindi.']);

        case 'set-default':
            $id = firmaYonetimiId((string) ($_POST['id'] ?? ''));
            if ($id <= 0 || !$firma->getForYonetim($id, $izinli)) {
                firmaYonetimiYanit(['status' => 'error', 'message' => 'Firma bulunamadı.'], 404);
            }
            $firma->setDefault($id);
            firmaYonetimiYanit(['status' => 'success', 'message' => 'Varsayılan firma güncellendi.']);
    }
} catch (\Throwable $e) {
    error_log('Firma yönetimi API hatası: ' . $e->getMessage());
    $mesaj = $e instanceof \Exception && in_array($action, ['delete'], true) ? $e->getMessage() : 'İşlem sırasında bir hata oluştu.';
    firmaYonetimiYanit(['status' => 'error', 'message' => $mesaj], 500);
}
