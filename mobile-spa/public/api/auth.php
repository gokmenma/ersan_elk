<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\FirmaModel;
use App\Model\SozlesmeDonemModel;
use App\Model\SystemLogModel;
use App\Model\UserModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

const MOBILE_LOGIN_MAX_ATTEMPTS = 5;
const MOBILE_LOGIN_LOCK_SECONDS = 900;

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function csrfToken(): string
{
    return Security::csrf();
}

function requireCsrf(array $data): void
{
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '');
    $known = (string) ($_SESSION['csrf_token'] ?? '');
    if ($known === '' || $sent === '' || !hash_equals($known, $sent)) {
        respond(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'], 403);
    }
}

function mobileCan(array $permissionKeys): bool
{
    return Gate::allowsAny($permissionKeys);
}

function publicUser(object $user): array
{
    $name = trim((string) ($user->adi_soyadi ?? $user->user_name ?? 'Kullanıcı'));
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return [
        'name' => $name,
        'username' => (string) ($user->user_name ?? ''),
        'initials' => $initials ?: 'EE',
    ];
}

function rawAllowedCompanies(object $user): array
{
    $companies = (new FirmaModel())->listActive();
    $rawIds = trim((string) ($user->firma_ids ?? ''));
    if ($rawIds !== '') {
        $allowedIds = array_map('intval', array_filter(array_map('trim', explode(',', $rawIds)), 'strlen'));
        $companies = array_values(array_filter(
            $companies,
            static fn(object $company): bool => in_array((int) $company->id, $allowedIds, true)
        ));
    }
    return $companies;
}

function allowedCompanies(object $user): array
{
    $companies = rawAllowedCompanies($user);

    return array_map(static fn(object $company): array => [
        'id' => Security::encrypt((string) $company->id),
        'name' => (string) $company->firma_adi,
        'code' => (string) ($company->firma_kodu ?? ''),
    ], $companies);
}

function selectCompanySession(object $company): void
{
    $companyId = (int) $company->id;
    $_SESSION['firma_id'] = $companyId;
    $_SESSION['sube_id'] = $companyId;
    $_SESSION['firma_adi'] = (string) $company->firma_adi;
    $_SESSION['firma_kodu'] = (string) ($company->firma_kodu ?? '');

    // 30 günlük varsayılan firma çerezi (web paneli ve mobil uyumlu)
    setcookie('varsayilan_firma_id', (string) $companyId, [
        'expires'  => time() + (30 * 24 * 60 * 60),
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!empty($company->firma_kodu)) {
        setcookie('varsayilan_firma_kodu', (string) $company->firma_kodu, [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $period = (new SozlesmeDonemModel())->getActiveDonem($companyId);
    if ($period) {
        $_SESSION['aktif_donem_id'] = (int) $period->id;
        $_SESSION['aktif_donem_adi'] = (string) $period->donem_adi;
    } else {
        unset($_SESSION['aktif_donem_id'], $_SESSION['aktif_donem_adi']);
    }
}

function resolveDefaultCompany(object $user): ?object
{
    $companies = rawAllowedCompanies($user);
    if (empty($companies)) {
        return null;
    }

    // 1. Çerezde kayıtlı varsayılan firma
    $fromCookies = (new FirmaModel())->resolveDefaultFirmaFromCookies($_COOKIE, $companies);
    if ($fromCookies) {
        return $fromCookies;
    }

    // 2. Veritabanında varsayilan_mi = 1 olan firma
    foreach ($companies as $comp) {
        if (!empty($comp->varsayilan_mi)) {
            return $comp;
        }
    }

    // 3. Kullanıcının tek firması varsa veya listenin ilk firması
    return $companies[0] ?? null;
}

function sessionPayload(): array
{
    $user = $_SESSION['user'] ?? null;
    if ((!$user || empty($user->id)) && !empty($_COOKIE['remember_me'])) {
        $rememberedId = (int) Security::decrypt((string) $_COOKIE['remember_me']);
        if ($rememberedId > 0) {
            $rememberedUser = (new UserModel())->find($rememberedId);
            if ($rememberedUser && ($rememberedUser->durum ?? 'Aktif') !== 'Pasif') {
                session_regenerate_id(true);
                $_SESSION['loggedin'] = true;
                $_SESSION['user'] = $rememberedUser;
                $_SESSION['user_id'] = (int) $rememberedUser->id;
                $_SESSION['id'] = (int) $rememberedUser->id;
                $_SESSION['owner_id'] = (int) $rememberedUser->owner_id;
                $_SESSION['username'] = (string) $rememberedUser->user_name;
                $_SESSION['user_full_name'] = (string) $rememberedUser->adi_soyadi;
                $_SESSION['sube_id'] = (int) ($rememberedUser->sube_id ?? 0);
                $_SESSION['personel_id'] = (int) ($rememberedUser->personel_id ?? 0);
                $user = $rememberedUser;
            }
        }
    }
    if (!$user || empty($user->id)) {
        return ['authenticated' => false, 'csrf_token' => csrfToken()];
    }

    $freshUser = (new UserModel())->find((int) $user->id);
    if (!$freshUser || ($freshUser->durum ?? 'Aktif') === 'Pasif') {
        session_unset();
        session_destroy();
        return ['authenticated' => false, 'csrf_token' => ''];
    }

    $_SESSION['user'] = $freshUser;

    // Rol/yetki değişiklikleri açık mobil oturuma bir sonraki rota geçişinde
    // yansısın; eski Gate oturum önbelleği yetkisiz sayfayı görünür tutmasın.
    unset($_SESSION['permission_cache'][(int) $freshUser->id]);

    // Firma seçili değilse varsayılan firmayı otomatik bağla
    if (empty($_SESSION['firma_id'])) {
        $defaultCompany = resolveDefaultCompany($freshUser);
        if ($defaultCompany) {
            selectCompanySession($defaultCompany);
        }
    }

    $permissions = [
        'personnel' => mobileCan(['personel_listesi']),
        'personnel_edit' => Gate::allows('personel_listesi') && Gate::allows('personel_duzenle'),
        'personnel_documents' => Gate::allows('personel_duzenle') && Gate::allows('evrak_bilgileri_sekmesi'),
        'tasks' => mobileCan(['gorevler']),
        'reports' => mobileCan(['puantaj/raporlar']),
        'puantaj' => mobileCan(['personel/puantaj']),
        'requests' => mobileCan(['talepler', 'avans_talepleri', 'izin_talepleri', 'ariza_talepleri']),
        'vehicles' => mobileCan(['arac_takip']),
        'km_approvals' => mobileCan(['arac_km_onaylari']),
        'invoices' => mobileCan(['efatura/dashboard', 'efatura/giden-list', 'efatura/gelen-list', 'efatura/taslak-list']),
        'invoice_dashboard' => mobileCan(['efatura/dashboard']),
        'invoice_outgoing' => mobileCan(['efatura/giden-list']),
        'invoice_incoming' => mobileCan(['efatura/gelen-list']),
        'invoice_drafts' => mobileCan(['efatura/taslak-list']),
        'invoice_create' => mobileCan(['efatura/olustur']),
        'cash' => mobileCan(['gelir_gider_takibi']),
        'current_accounts' => mobileCan(['cari_takibi']),
        'documents' => mobileCan(['evrak-takip/list']),
        'incoming_documents' => mobileCan(['evrak-takip/gelen-evrak']),
        'outgoing_documents' => mobileCan(['evrak-takip/giden-evrak']),
        'shifts' => mobileCan(['yonetici_onayi']),
        'shift_schedule' => mobileCan(['nobet/list']),
        'payroll' => mobileCan(['bordro/list']),
        'contracts' => mobileCan(['hakedis']),
        'notices' => mobileCan(['ihbar/list']),
        'illegal' => mobileCan(['kacak_islemleri']),
        'illegal_approve' => Gate::allows('kacak_onay') || Gate::isSuperAdmin(),
        'illegal_cancel' => Gate::allows('kacak_iptal_ekle') || Gate::isSuperAdmin(),
        'illegal_revert_cancel' => Gate::allows('kacak_iptal') || Gate::isSuperAdmin(),
        'support' => mobileCan(['admin_destek_talebi', 'Destek Taleplerim (Kullanıcı)']),
        'live_support' => mobileCan(['canli_sohbet_ayarlari_sekmesi']),
        'logs' => mobileCan(['log_kayitlari']),
    ];
    return [
        'authenticated' => true,
        'csrf_token' => csrfToken(),
        'user' => publicUser($freshUser),
        'company_selected' => !empty($_SESSION['firma_id']),
        'company' => !empty($_SESSION['firma_id']) ? [
            'id' => Security::encrypt((string) $_SESSION['firma_id']),
            'name' => (string) ($_SESSION['firma_adi'] ?? ''),
            'code' => (string) ($_SESSION['firma_kodu'] ?? ''),
        ] : null,
        'companies' => allowedCompanies($freshUser),
        'permissions' => $permissions,
    ];
}

$action = (string) ($_GET['action'] ?? 'session');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'session') {
        respond(['success' => true, ...sessionPayload()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['success' => false, 'message' => 'Desteklenmeyen istek yöntemi.'], 405);
    }

    $data = requestData();
    requireCsrf($data);

    if ($action === 'login') {
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($username === '' || $password === '') {
            respond(['success' => false, 'message' => 'Kullanıcı adı ve şifre zorunludur.'], 422);
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $lockKey = 'mobile_login_attempts_' . hash('sha256', $ip);
        $attempt = $_SESSION[$lockKey] ?? ['count' => 0, 'first_attempt' => time()];
        if ($attempt['count'] >= MOBILE_LOGIN_MAX_ATTEMPTS) {
            $remaining = MOBILE_LOGIN_LOCK_SECONDS - (time() - (int) $attempt['first_attempt']);
            if ($remaining > 0) {
                respond(['success' => false, 'message' => 'Çok fazla başarısız deneme. ' . (int) ceil($remaining / 60) . ' dakika sonra tekrar deneyin.'], 429);
            }
            $attempt = ['count' => 0, 'first_attempt' => time()];
        }

        $user = (new UserModel())->findByUsername($username);
        if (!$user || ($user->durum ?? 'Aktif') === 'Pasif' || !password_verify($password, (string) $user->password)) {
            $attempt['count']++;
            if ($attempt['count'] === 1) {
                $attempt['first_attempt'] = time();
            }
            $_SESSION[$lockKey] = $attempt;
            respond(['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'], 401);
        }

        unset(
            $_SESSION[$lockKey],
            $_SESSION['permission_cache'],
            $_SESSION['topbar_firma_option_cache'],
            $_SESSION['firma_id'],
            $_SESSION['firma_adi'],
            $_SESSION['firma_kodu'],
            $_SESSION['aktif_donem_id'],
            $_SESSION['aktif_donem_adi']
        );
        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['id'] = (int) $user->id;
        $_SESSION['owner_id'] = (int) $user->owner_id;
        $_SESSION['username'] = (string) $user->user_name;
        $_SESSION['user_full_name'] = (string) $user->adi_soyadi;
        $_SESSION['sube_id'] = (int) ($user->sube_id ?? 0);
        $_SESSION['personel_id'] = (int) ($user->personel_id ?? 0);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(48));

        if (!empty($data['remember'])) {
            setcookie('remember_me', Security::encrypt((string) $user->id), [
                'expires' => time() + 30 * 24 * 60 * 60,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        try {
            (new SystemLogModel())->logAction(
                (int) $user->id,
                'Başarılı Giriş (Mobil)',
                "{$user->adi_soyadi} ({$user->user_name}) mobil uygulamaya giriş yaptı. IP: {$ip}",
                SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (Throwable $ignored) {
        }

        respond(['success' => true, ...sessionPayload()]);
    }

    if ($action === 'select-company') {
        $user = $_SESSION['user'] ?? null;
        if (!$user || empty($user->id)) {
            respond(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
        }
        $companyId = (int) Security::decrypt((string) ($data['company_id'] ?? ''));
        $allowed = allowedCompanies($user);
        $allowedIds = array_map(static fn(array $company): int => (int) Security::decrypt($company['id']), $allowed);
        if ($companyId <= 0 || !in_array($companyId, $allowedIds, true)) {
            respond(['success' => false, 'message' => 'Bu çalışma alanına erişim yetkiniz bulunmuyor.'], 403);
        }

        $company = (new FirmaModel())->find($companyId);
        if (!$company || !is_null($company->silinme_tarihi ?? null)) {
            respond(['success' => false, 'message' => 'Çalışma alanı aktif değil.'], 404);
        }
        selectCompanySession($company);
        respond(['success' => true, ...sessionPayload()]);
    }

    if ($action === 'page-view') {
        $user = $_SESSION['user'] ?? null;
        if (!$user || empty($user->id) || empty($_SESSION['firma_id'])) {
            respond(['success' => false, 'message' => 'Oturum veya çalışma alanı bulunamadı.'], 401);
        }

        $routeNames = [
            'home' => 'ana-sayfa',
            'evrak-takip' => 'evrak-takip',
            'giden-evrak' => 'giden-evrak',
            'arac-takip' => 'arac-takip',
            'km-onaylari' => 'km-onaylari',
            'tasks' => 'gorevler',
            'reports' => 'raporlar',
            'requests' => 'talepler',
            'profile' => 'profil',
            'personnel' => 'personel/list',
            'personnel-detail' => 'personel/detay',
            'personnel-section' => 'personel/bolum',
            'personnel-create' => 'personel/yeni',
            'personnel-edit' => 'personel/duzenle',
            'cari-list' => 'cari/list',
            'cari-detail' => 'cari/detay',
            'fatura' => 'fatura',
            'kasa' => 'kasa',
            'payroll' => 'bordro',
            'sozlesme-hakedis' => 'sozlesme-hakedis',
            'sozlesme-detail' => 'sozlesme/detay',
            'hakedis-detail' => 'hakedis/detay',
            'puantaj' => 'personel/puantaj',
            'kacak-kontrol' => 'kacak-kontrol',
            'nobet-onay' => 'nobet-onay',
            'ihbar' => 'ihbar',
            'logs' => 'logs/list',
        ];
        $routeName = trim((string) ($data['route_name'] ?? ''));
        if (!isset($routeNames[$routeName])) {
            respond(['success' => false, 'message' => 'Geçersiz sayfa bilgisi.'], 422);
        }

        (new SystemLogModel())->logPageView((int) $user->id, $routeNames[$routeName], 'Mobil SPA');
        respond(['success' => true]);
    }

    if ($action === 'logout') {
        session_unset();
        session_destroy();
        setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        respond(['success' => true, 'authenticated' => false]);
    }

    respond(['success' => false, 'message' => 'Geçersiz işlem.'], 404);
} catch (Throwable $error) {
    error_log('Mobile auth API: ' . $error->getMessage());
    respond(['success' => false, 'message' => 'İşlem sırasında bir sunucu hatası oluştu.'], 500);
}
