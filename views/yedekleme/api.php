<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
date_default_timezone_set('Europe/Istanbul');

use App\Model\PermissionPolicyModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\YedekYonetimService;

function yedekYanit(array $veri, int $kod = 200): never
{
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function yedekYanitGonderVeDevamEt(array $veri): void
{
    ignore_user_abort(true);
    set_time_limit(0);
    session_write_close();

    $govde = json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Encoding: none');
    header('Connection: close');
    header('Content-Length: ' . strlen($govde));
    echo $govde;
    flush();

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    yedekYanit(['status' => 'error', 'message' => 'Oturum bulunamadı.'], 401);
}

if (!Gate::isSuperAdmin()) {
    yedekYanit(['status' => 'error', 'message' => 'Bu alan yalnızca Süper Admin kullanıcıya açıktır.'], 403);
}

$action = (string) ($_POST['action'] ?? '');
$okumaAksiyonlari = ['liste', 'is-durumu', 'test-detay'];
$yazmaAksiyonlari = ['is-baslat', 'indir'];
if (!in_array($action, array_merge($okumaAksiyonlari, $yazmaAksiyonlari), true)) {
    yedekYanit(['status' => 'error', 'message' => 'Tanımsız işlem.'], 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    yedekYanit(['status' => 'error', 'message' => 'Bu işlem POST gerektirir.'], 405);
}

$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('yedekleme/api', $action);
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
    yedekYanit(['status' => 'error', 'message' => 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'], 419);
}

$kullaniciId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

try {
    $servis = new YedekYonetimService();

    switch ($action) {
        case 'liste':
            $liste = $servis->liste(($_POST['drive_yenile'] ?? '') === '1');
            yedekYanit([
                'status' => 'success',
                'data' => $liste['satirlar'],
                'ozet' => $servis->ozet($liste),
                'drive_durum' => $liste['drive_durum'],
                'drive_hata' => $liste['drive_hata'],
                'drive_zaman' => $liste['drive_zaman'],
            ]);

        case 'is-durumu':
            yedekYanit(['status' => 'success', 'is' => $servis->isDurumu()]);

        case 'test-detay':
            $ad = (string) ($_POST['dosya'] ?? '');
            if (!YedekYonetimService::dosyaAdiGecerliMi($ad)) {
                yedekYanit(['status' => 'error', 'message' => 'Geçersiz yedek dosya adı.'], 400);
            }
            yedekYanit(['status' => 'success', 'test' => $servis->testDetayi($ad)]);

        case 'is-baslat':
            $is = $servis->isBaslat(
                (string) ($_POST['is'] ?? ''),
                ['dosya' => (string) ($_POST['dosya'] ?? '')],
                $kullaniciId
            );
            yedekYanitGonderVeDevamEt(['status' => 'success', 'message' => $is['baslik'] . ' başlatıldı.', 'is' => $is]);
            $servis->isCalistir();
            exit;

        case 'indir':
            $ad = (string) ($_POST['dosya'] ?? '');
            $yol = $servis->yerelDosyaYolu($ad);
            try {
                (new SystemLogModel())->logAction(
                    $kullaniciId,
                    'Veritabani Yedek Indirme',
                    sprintf('Yedek dosyasi tarayicidan indirildi: %s (%s MB, IP: %s)', $ad,
                        number_format(filesize($yol) / 1048576, 2), $_SERVER['REMOTE_ADDR'] ?? '-'),
                    SystemLogModel::LEVEL_CRITICAL
                );
            } catch (Throwable $e) {
                error_log('[yedekleme/api] indirme logu yazilamadi: ' . $e->getMessage());
                yedekYanit(['status' => 'error', 'message' => 'İndirme kaydı oluşturulamadığı için işlem durduruldu.'], 500);
            }

            session_write_close();
            set_time_limit(0);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename="' . $ad . '"');
            header('Content-Length: ' . filesize($yol));
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
            readfile($yol);
            exit;
    }
} catch (DomainException $e) {
    error_log('[yedekleme/api] ' . $e->getMessage());
    yedekYanit(['status' => 'error', 'message' => $e->getMessage()], 409);
} catch (Throwable $e) {
    error_log('[yedekleme/api] ' . $e->getMessage());
    yedekYanit(['status' => 'error', 'message' => 'İşlem sırasında beklenmeyen bir hata oluştu.'], 500);
}
