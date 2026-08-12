<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Model\SettingsModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

function apiClientRespond(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!Gate::isSuperAdmin()) {
    apiClientRespond(['status' => 'error', 'message' => 'Bu alan yalnızca Superadmin kullanıcıya açıktır.'], 403);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    apiClientRespond(['status' => 'error', 'message' => 'Geçersiz istek gövdesi.'], 400);
}

$csrf = (string) ($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
    apiClientRespond(['status' => 'error', 'message' => 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'], 419);
}

$method = strtoupper((string) ($input['method'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    apiClientRespond(['status' => 'error', 'message' => 'Yalnızca GET ve POST metotları desteklenir.'], 400);
}

$url = trim((string) ($input['url'] ?? ''));
$parts = parse_url($url);
if (!$parts || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
    apiClientRespond(['status' => 'error', 'message' => 'Geçerli bir HTTP/HTTPS endpoint adresi girin.'], 400);
}

// Postman benzeri serbest adres girişini korurken yerel ağ ve metadata servislerine SSRF erişimini engelle.
$host = strtolower((string) $parts['host']);
$ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_values(array_unique(array_merge(gethostbynamel($host) ?: [], array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'))));
if (!$ips) {
    apiClientRespond(['status' => 'error', 'message' => 'Endpoint alan adı çözümlenemedi.'], 400);
}
foreach ($ips as $ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        apiClientRespond(['status' => 'error', 'message' => 'Yerel veya ayrılmış ağ adreslerine istek gönderilemez.'], 400);
    }
}

$requestHeaders = [];
foreach (preg_split('/\R/', (string) ($input['headers'] ?? '')) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    if (!str_contains($line, ':')) apiClientRespond(['status' => 'error', 'message' => 'Header satırı "Ad: Değer" biçiminde olmalıdır.'], 400);
    [$name] = explode(':', $line, 2);
    $lower = strtolower(trim($name));
    if (in_array($lower, ['host', 'content-length', 'connection', 'transfer-encoding'], true)) continue;
    $requestHeaders[] = $line;
}

$preset = (string) ($input['preset'] ?? 'ozel');
if (in_array($preset, ['endeks', 'kesme_acma'], true)) {
    $settings = (new SettingsModel())->getAllSettingsAsKeyValue();
    $settingName = $preset === 'endeks' ? 'api_endeks_sifre' : 'api_puantaj_sifre';
    $urlSettingName = $preset === 'endeks' ? 'api_endeks_url' : 'api_puantaj_url';
    $defaultUrl = $preset === 'endeks'
        ? 'https://yonetim.maraskaski.gov.tr/api/api_okuma_secure.php?action=getData'
        : 'https://yonetim.maraskaski.gov.tr/api/api_isemri_secure.php?action=getIsEmri';
    $presetUrl = trim((string) ($settings[$urlSettingName] ?? $defaultUrl));
    // Mevcut EndeskOkumaService ve KesmeAcmaService ile aynı geriye dönük
    // uyumluluk anahtarını kullan. Ayarlarda değer varsa her zaman o önceliklidir.
    $defaultApiKey = 'sk_live_DSOSTjHN195B4NUpEaB9NdYtW7xQ8EVjZD2p2ssW';
    $configuredApiKey = trim((string) ($settings[$settingName] ?? ''));
    $apiKey = $configuredApiKey !== '' ? $configuredApiKey : $defaultApiKey;
    // Hazır endpoint değiştirilirse gizli anahtarı farklı bir adrese kesinlikle gönderme.
    if ($apiKey !== '' && hash_equals($presetUrl, $url)) {
        $requestHeaders[] = 'Authorization: Bearer ' . $apiKey;
    }
}

$body = (string) ($input['body'] ?? '');
if ($method === 'POST') {
    json_decode($body);
    if ($body !== '' && json_last_error() !== JSON_ERROR_NONE) apiClientRespond(['status' => 'error', 'message' => 'POST gövdesi geçerli JSON değil: ' . json_last_error_msg()], 400);
    if (!array_filter($requestHeaders, fn($h) => stripos($h, 'content-type:') === 0)) $requestHeaders[] = 'Content-Type: application/json';
}

$responseBody = '';
$tooLarge = false;
$started = microtime(true);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $requestHeaders,
    CURLOPT_POSTFIELDS => $method === 'POST' ? $body : null,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_ENCODING => '',
    CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody, &$tooLarge): int {
        if (strlen($responseBody) + strlen($chunk) > 2 * 1024 * 1024) { $tooLarge = true; return 0; }
        $responseBody .= $chunk; return strlen($chunk);
    },
]);
curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($tooLarge) apiClientRespond(['status' => 'error', 'message' => 'Yanıt 2 MB güvenlik sınırını aştı.'], 413);
if ($curlError !== '') apiClientRespond(['status' => 'error', 'message' => 'Bağlantı hatası: ' . $curlError], 502);

$prettyBody = $responseBody;
$decoded = json_decode($responseBody, true);
if (json_last_error() === JSON_ERROR_NONE) $prettyBody = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$bytes = strlen($responseBody);

apiClientRespond([
    'status' => 'success', 'http_code' => $httpCode, 'content_type' => $contentType,
    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
    'size_label' => $bytes < 1024 ? $bytes . ' B' : round($bytes / 1024, 1) . ' KB',
    'body' => $responseBody, 'pretty_body' => $prettyBody,
]);
