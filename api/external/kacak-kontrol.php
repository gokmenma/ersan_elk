<?php
// Harici entegrasyon API'si: Dış sistemlerin kaçak/abonesiz tutanak kayıtlarını
// bu sisteme POST ile göndermesi için kullanılır. Session tabanlı değildir;
// kimlik doğrulama API key + kullanıcı adı + şifre ile yapılır (bkz. .env: KACAK_API_*).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Helper\Date;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

header('Content-Type: application/json; charset=utf-8');

function respond(int $httpCode, array $payload): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['status' => 'error', 'message' => 'Sadece POST metodu desteklenir.']);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$actionType = 'Harici Kaçak API Girişi';

$firmaId = (int) ($_ENV['KACAK_API_FIRMA_ID'] ?? 0);
if ($firmaId <= 0) {
    error_log('kacak-kontrol external API: KACAK_API_FIRMA_ID .env üzerinde tanımlı değil.');
    respond(500, ['status' => 'error', 'message' => 'Sunucu yapılandırma hatası.']);
}
// Model katmanı $_SESSION['firma_id'] üzerinden çalıştığı için burada oturumu simüle ediyoruz.
$_SESSION['firma_id'] = $firmaId;

$SystemLog = new SystemLogModel();

// --- Rate limit: son 15 dakikada aynı IP'den 10'dan fazla başarısız kimlik doğrulama varsa engelle ---
if ($SystemLog->countRecentFailedApiAttempts($ip, $actionType, 15) >= 10) {
    respond(429, ['status' => 'error', 'message' => 'Çok fazla başarısız deneme yapıldı. Lütfen daha sonra tekrar deneyin.']);
}

// --- İstek gövdesini oku (JSON veya form-data desteklenir) ---
$raw = file_get_contents('php://input');
$body = $_POST;
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = array_merge($body, $decoded);
    }
}

// --- Kimlik doğrulama: API key (header) + kullanıcı adı/şifre (gövde) ---
$headers = function_exists('getallheaders') ? getallheaders() : [];
$apiKey = $headers['X-Api-Key'] ?? $headers['X-API-Key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');

$expectedKey = (string) ($_ENV['KACAK_API_KEY'] ?? '');
$expectedUser = (string) ($_ENV['KACAK_API_USERNAME'] ?? '');
$expectedPass = (string) ($_ENV['KACAK_API_PASSWORD'] ?? '');

$authOk = $expectedKey !== '' && $expectedUser !== '' && $expectedPass !== ''
    && hash_equals($expectedKey, (string) $apiKey)
    && hash_equals($expectedUser, $username)
    && hash_equals($expectedPass, $password);

if (!$authOk) {
    $SystemLog->logAction(0, $actionType, "AUTH_FAIL | IP: {$ip} | Kimlik doğrulama başarısız (key/kullanıcı/şifre uyuşmadı).");
    respond(401, ['status' => 'error', 'message' => 'Kimlik doğrulama başarısız.']);
}

try {
    // --- Girdi doğrulama ---
    $tarihRaw = trim((string) ($body['tarih'] ?? ''));
    $tutanakNo = trim((string) ($body['tutanak_no'] ?? ''));
    $ilce = trim((string) ($body['ilce'] ?? ''));
    $tur = trim((string) ($body['tur'] ?? 'Kaçak'));
    $aboneAdi = trim((string) ($body['abone_adi'] ?? ''));
    $sayacNo = trim((string) ($body['sayac_no'] ?? ''));
    $endeks = trim((string) ($body['endeks'] ?? ''));
    $sayi = (int) ($body['sayi'] ?? 1);
    $aciklama = trim((string) ($body['aciklama'] ?? ''));

    $hatalar = [];
    if ($tarihRaw === '') {
        $hatalar[] = 'tarih zorunludur.';
    }
    if ($tutanakNo === '') {
        $hatalar[] = 'tutanak_no zorunludur (mükerrer kayıt kontrolü bu alana göre yapılır).';
    }
    if ($ilce === '') {
        $hatalar[] = 'ilce zorunludur.';
    }
    if (!in_array($tur, ['Kaçak', 'Abonesiz'], true)) {
        $hatalar[] = "tur sadece 'Kaçak' veya 'Abonesiz' olabilir.";
    }
    if ($sayi <= 0) {
        $sayi = 1;
    }

    $dbTarih = $tarihRaw !== '' ? (Date::convertExcelDate($tarihRaw, 'Y-m-d') ?: null) : null;
    if ($tarihRaw !== '' && !$dbTarih) {
        $hatalar[] = 'tarih geçerli bir formatta değil (örnek: 2026-07-17 veya 17.07.2026).';
    }

    if (!empty($hatalar)) {
        respond(400, ['status' => 'error', 'message' => 'Gönderilen veri eksik veya hatalı.', 'hatalar' => $hatalar]);
    }

    // --- Personel eşleştirme: ID listesi ya da isim listesi ile gönderilebilir ---
    $Personel = new PersonelModel();
    $personelIds = [];
    $eslesmeyenIsimler = [];

    if (!empty($body['personel_ids']) && is_array($body['personel_ids'])) {
        foreach ($body['personel_ids'] as $pid) {
            $p = $Personel->find((int) $pid);
            if ($p && empty($p->silinme_tarihi) && (int) $p->firma_id === $firmaId) {
                $personelIds[] = (int) $p->id;
            }
        }
    } elseif (!empty($body['personel_isimleri']) && is_array($body['personel_isimleri'])) {
        foreach ($body['personel_isimleri'] as $isim) {
            $p = $Personel->findByAdiSoyadi((string) $isim);
            if ($p) {
                $personelIds[] = (int) $p->id;
            } else {
                $eslesmeyenIsimler[] = $isim;
            }
        }
    }

    $ekipAdi = null;
    if (!empty($personelIds)) {
        $isimler = [];
        foreach ($personelIds as $pid) {
            $p = $Personel->find($pid);
            if ($p) {
                $isimler[] = $p->adi_soyadi;
            }
        }
        $ekipAdi = implode(', ', $isimler) ?: null;
    }

    $Puantaj = new PuantajModel();
    $sonuc = $Puantaj->insertKacakKontrolExternal([
        'firma_id' => $firmaId,
        'tarih' => $dbTarih,
        'personel_ids' => implode(',', $personelIds),
        'ekip_adi' => $ekipAdi,
        'ilce' => $ilce,
        'tur' => $tur,
        'tutanak_no' => $tutanakNo,
        'abone_adi' => $aboneAdi ?: null,
        'sayac_no' => $sayacNo ?: null,
        'endeks' => $endeks ?: null,
        'sayi' => $sayi,
        'aciklama' => $aciklama ?: null,
    ]);

    if ($sonuc['duplicate']) {
        $SystemLog->logAction(0, $actionType, "Mükerrer kayıt reddedildi. Tutanak No: {$tutanakNo}, Tarih: {$dbTarih}, IP: {$ip}", SystemLogModel::LEVEL_INFO);
        respond(409, ['status' => 'error', 'message' => 'Bu tutanak numarası ve tarih için zaten bir kayıt mevcut.', 'kayit_id' => $sonuc['id']]);
    }

    $SystemLog->logAction(0, $actionType, "Yeni kayıt eklendi. ID: {$sonuc['id']}, Tutanak No: {$tutanakNo}, Tarih: {$dbTarih}, IP: {$ip}", SystemLogModel::LEVEL_IMPORTANT);

    $yanit = ['status' => 'success', 'message' => 'Kayıt başarıyla oluşturuldu.', 'kayit_id' => $sonuc['id']];
    if (!empty($eslesmeyenIsimler)) {
        $yanit['uyari'] = 'Şu personel isimleri sistemde bulunamadı ve kayda eklenmedi: ' . implode(', ', $eslesmeyenIsimler);
    }
    respond(201, $yanit);
} catch (\Throwable $e) {
    error_log('kacak-kontrol external API hatası: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    respond(500, ['status' => 'error', 'message' => 'Sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin.']);
}
