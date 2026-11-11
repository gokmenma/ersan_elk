<?php

require_once dirname(__DIR__, 3) . '/bootstrap.php';

use App\Model\MobileAuthModel;

ini_set('error_log', PROJECT_ROOT . '/cache/mobile-api/error.log');
ini_set('log_errors', 1);
ini_set('display_errors', 0);

const MOBILE_ACCESS_TTL = 7200;
const MOBILE_REFRESH_TTL = 2592000;
const MOBILE_LOGIN_MAX_ATTEMPTS = 5;
const MOBILE_LOGIN_LOCK_SECONDS = 900;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function mobile_json(array $payload, int $status = 200): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function mobile_ok(array $data = []): void
{
    mobile_json(['success' => true] + $data);
}

function mobile_fail(string $message, int $status = 400, array $extra = []): void
{
    mobile_json(['success' => false, 'message' => $message] + $extra, $status);
}

function mobile_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST + $_GET;
}

function mobile_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function mobile_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function mobile_b64url_decode(string $data): string
{
    return (string) base64_decode(strtr($data, '-_', '+/'), true);
}

function mobile_jwt_secret(): string
{
    $secret = $_ENV['MOBILE_JWT_SECRET'] ?? '';
    if (empty($secret)) {
        error_log('MOBILE_JWT_SECRET .env dosyasinda tanimli degil.');
        mobile_fail('Servis yapilandirmasi eksik.', 500);
    }

    return $secret;
}

function mobile_jwt_issue(int $personelId, string $type, int $ttl): string
{
    $now = time();
    $header = mobile_b64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = mobile_b64url_encode(json_encode([
        'sub' => $personelId,
        'typ' => $type,
        'iat' => $now,
        'exp' => $now + $ttl,
        'jti' => bin2hex(random_bytes(8)),
        'actor' => 'personel',
    ]));

    $signature = mobile_b64url_encode(
        hash_hmac('sha256', $header . '.' . $payload, mobile_jwt_secret(), true)
    );

    return $header . '.' . $payload . '.' . $signature;
}

function mobile_jwt_verify(string $token, string $expectedType): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$header, $payload, $signature] = $parts;

    $expected = mobile_b64url_encode(
        hash_hmac('sha256', $header . '.' . $payload, mobile_jwt_secret(), true)
    );

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $claims = json_decode(mobile_b64url_decode($payload), true);
    if (!is_array($claims)) {
        return null;
    }

    if (($claims['typ'] ?? '') !== $expectedType) {
        return null;
    }

    if ((int) ($claims['exp'] ?? 0) < time()) {
        return null;
    }

    if ((int) ($claims['sub'] ?? 0) <= 0) {
        return null;
    }

    if (($claims['actor'] ?? '') !== 'personel') {
        return null;
    }

    return $claims;
}

function mobile_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($header === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $key => $value) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }

    return '';
}

function mobile_rate_limit_path(string $key): string
{
    return PROJECT_ROOT . '/cache/mobile-api/rl_' . md5($key) . '.json';
}

function mobile_rate_limit_check(string $key): void
{
    $path = mobile_rate_limit_path($key);
    if (!is_file($path)) {
        return;
    }

    $state = json_decode((string) file_get_contents($path), true);
    $lockedUntil = (int) ($state['locked_until'] ?? 0);

    if ($lockedUntil > time()) {
        $wait = (int) ceil(($lockedUntil - time()) / 60);
        mobile_fail("Cok fazla basarisiz deneme. $wait dakika sonra tekrar deneyin.", 429);
    }
}

function mobile_rate_limit_hit(string $key): void
{
    $path = mobile_rate_limit_path($key);
    $state = is_file($path)
        ? (json_decode((string) file_get_contents($path), true) ?: [])
        : [];

    $attempts = (int) ($state['attempts'] ?? 0) + 1;
    $lockedUntil = 0;

    if ($attempts >= MOBILE_LOGIN_MAX_ATTEMPTS) {
        $attempts = 0;
        $lockedUntil = time() + MOBILE_LOGIN_LOCK_SECONDS;
    }

    file_put_contents(
        $path,
        json_encode(['attempts' => $attempts, 'locked_until' => $lockedUntil]),
        LOCK_EX
    );
}

function mobile_rate_limit_clear(string $key): void
{
    $path = mobile_rate_limit_path($key);
    if (is_file($path)) {
        @unlink($path);
    }
}

function mobile_load_personel(int $personelId): object
{
    $model = new MobileAuthModel();
    $personel = $model->find($personelId);

    if ($model->isPasif($personel)) {
        mobile_fail('Hesabiniz pasif durumdadir.', 401, ['code' => 'ACCOUNT_INACTIVE']);
    }

    if (!empty($personel->firma_id)) {
        $_SESSION['firma_id'] = $personel->firma_id;
    }

    $_SESSION['personel_id'] = (int) $personel->id;

    return $personel;
}

function mobile_require_auth(): object
{
    $token = mobile_bearer_token();
    if ($token === '') {
        mobile_fail('Yetkilendirme basligi eksik.', 401, ['code' => 'TOKEN_MISSING']);
    }

    $claims = mobile_jwt_verify($token, 'access');
    if ($claims === null) {
        mobile_fail('Oturum suresi doldu.', 401, ['code' => 'TOKEN_INVALID']);
    }

    return mobile_load_personel((int) $claims['sub']);
}

function mobile_base_url(): string
{
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $projectPath = preg_replace('#/api/mobile/v1$#', '', $scriptDir);

    return $scheme . '://' . $host . rtrim((string) $projectPath, '/');
}

function mobile_asset_url(?string $path): ?string
{
    if (empty($path)) {
        return null;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return mobile_base_url() . '/' . ltrim($path, '/');
}

function mobile_personel_ozet(object $personel): array
{
    $resim = $personel->personel_resim_yolu ?? ($personel->resim_yolu ?? '');

    return [
        'id' => (int) $personel->id,
        'adi_soyadi' => $personel->adi_soyadi ?? '',
        'gorev' => $personel->gorev ?? '',
        'departman' => $personel->departman ?? '',
        'resim_url' => mobile_asset_url($resim),
        'saha_takibi' => (int) ($personel->saha_takibi ?? 0) === 1,
    ];
}
