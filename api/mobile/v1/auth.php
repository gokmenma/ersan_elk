<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\MobileAuthModel;
use App\Model\PersonelGirisLogModel;

$input = mobile_input();
$action = $input['action'] ?? '';

switch ($action) {
    case 'login':
        mobile_action_login($input);
        break;

    case 'refresh':
        mobile_action_refresh($input);
        break;

    case 'me':
        mobile_action_me();
        break;

    case 'logout':
        mobile_action_logout();
        break;

    default:
        mobile_fail('Gecersiz istek.', 404);
}

function mobile_issue_session(int $personelId): array
{
    return [
        'access_token' => mobile_jwt_issue($personelId, 'access', MOBILE_ACCESS_TTL),
        'refresh_token' => mobile_jwt_issue($personelId, 'refresh', MOBILE_REFRESH_TTL),
        'expires_in' => MOBILE_ACCESS_TTL,
    ];
}

function mobile_log_giris(object $personel): void
{
    try {
        (new PersonelGirisLogModel())->logLogin(
            (int) $personel->id,
            mobile_client_ip(),
            'ReactNative/' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')
        );
    } catch (\Throwable $e) {
        error_log('Mobil giris log hatasi: ' . $e->getMessage());
    }
}

function mobile_action_login(array $input): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        mobile_fail('Bu uc yalnizca POST kabul eder.', 405);
    }

    $rateKey = 'login_' . mobile_client_ip();
    mobile_rate_limit_check($rateKey);

    $loginInput = trim((string) ($input['login_input'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($loginInput === '' || $password === '') {
        mobile_fail('Lutfen tum alanlari doldurun.', 422);
    }

    try {
        $model = new MobileAuthModel();
        $personel = $model->findForLogin($loginInput);
    } catch (\Throwable $e) {
        error_log('Mobil login sorgu hatasi: ' . $e->getMessage());
        mobile_fail('Giris yapilamadi, lutfen daha sonra tekrar deneyin.', 500);
    }

    if (!$personel) {
        mobile_rate_limit_hit($rateKey);
        mobile_fail('Bu bilgilerle kayitli personel bulunamadi.', 401);
    }

    if ($model->isPasif($personel)) {
        mobile_fail('Hesabiniz pasif durumdadir. Lutfen yonetici ile iletisime geciniz.', 403);
    }

    $storedHash = (string) ($personel->sifre ?? '');

    if ($storedHash === '') {
        mobile_fail('Bu hesap icin henuz sifre belirlenmemis. Lutfen yoneticinizle iletisime gecin.', 403);
    }

    if (!password_verify($password, $storedHash)) {
        mobile_rate_limit_hit($rateKey);
        mobile_fail('Sifre hatali.', 401);
    }

    mobile_rate_limit_clear($rateKey);
    mobile_log_giris($personel);

    if (!empty($personel->firma_id)) {
        $_SESSION['firma_id'] = $personel->firma_id;
    }

    mobile_ok(mobile_issue_session((int) $personel->id) + [
        'personel' => mobile_personel_ozet($personel),
    ]);
}

function mobile_action_refresh(array $input): void
{
    $refreshToken = (string) ($input['refresh_token'] ?? '');

    if ($refreshToken === '') {
        mobile_fail('Yenileme anahtari eksik.', 422);
    }

    $claims = mobile_jwt_verify($refreshToken, 'refresh');

    if ($claims === null) {
        mobile_fail('Oturum suresi doldu, lutfen tekrar giris yapin.', 401, ['code' => 'REFRESH_INVALID']);
    }

    $personel = mobile_load_personel((int) $claims['sub']);

    mobile_ok(mobile_issue_session((int) $personel->id) + [
        'personel' => mobile_personel_ozet($personel),
    ]);
}

function mobile_action_me(): void
{
    $personel = mobile_require_auth();

    mobile_ok(['personel' => mobile_personel_ozet($personel)]);
}

function mobile_action_logout(): void
{
    $personel = mobile_require_auth();

    error_log('Mobil cikis: personel_id=' . (int) $personel->id . ' ip=' . mobile_client_ip());

    mobile_ok(['message' => 'Cikis yapildi.']);
}
