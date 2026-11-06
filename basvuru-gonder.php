<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/Autoloader.php';

use App\Helper\Security;
use App\Model\IkBasvuruModel;
use App\Model\IkIlanModel;
use App\Model\PersonelKarsilamaModel;
use App\Model\SystemLogModel;
use App\Service\GuvenliBelgeYuklemeService;
use App\Service\IkBasvuruAiService;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

const IK_SAATLIK_DENEME_SINIRI = 20;

$yanit = static function (bool $ok, string $mesaj, int $kod = 200): void {
    http_response_code($kod);
    echo json_encode(['ok' => $ok, 'mesaj' => $mesaj], JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $yanit(false, 'Geçersiz istek.', 405);
}

$siteBaglami = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($siteBaglami !== '' && !in_array($siteBaglami, ['same-origin', 'none'], true)) {
    $yanit(false, 'Geçersiz istek.', 403);
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = (string) parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
    if (!hash_equals(strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')), strtolower($originHost))) {
        $yanit(false, 'Geçersiz istek.', 403);
    }
}

$sessionCsrf = (string) ($_SESSION['ik_basvuru_csrf'] ?? '');
if ($sessionCsrf === '' || !hash_equals($sessionCsrf, (string) ($_POST['csrf'] ?? ''))) {
    $yanit(false, 'Oturum süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.', 403);
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    $yanit(true, 'Başvurunuz alındı.');
}
if (time() - (int) ($_SESSION['ik_basvuru_form_zamani'] ?? 0) < 4) {
    $yanit(false, 'Lütfen formu doldurduktan sonra tekrar gönderin.', 429);
}

$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'bilinmiyor'), 0, 45);
$kayitliDosya = null;
$basvuruId = 0;

try {
    $Ilan = new IkIlanModel();
    $Basvuru = new IkBasvuruModel();

    $ilan = $Ilan->tokenIleBul(strtolower(trim((string) ($_POST['i'] ?? ''))));
    if (!$ilan) {
        $yanit(false, 'Başvuru bağlantısı geçersiz veya kapatılmış.', 404);
    }
    $ilanId = (int) $ilan->id;
    $firmaId = (int) $ilan->firma_id;

    if ($Basvuru->denemeSayisi($ip, 60) >= IK_SAATLIK_DENEME_SINIRI) {
        $yanit(false, 'Çok fazla deneme yapıldı. Lütfen bir süre sonra tekrar deneyin.', 429);
    }
    $Basvuru->denemeKaydet($ilanId, $ip, 'deneme');

    $metin = static fn(string $alan, int $limit): string => trim(mb_substr(str_replace("\r", '', (string) ($_POST[$alan] ?? '')), 0, $limit, 'UTF-8'));

    $adSoyad = $metin('ad_soyad', 150);
    $telefon = PersonelKarsilamaModel::telefonNormalize($metin('telefon', 20));
    $email = $metin('email', 190);
    $sehir = $metin('sehir', 100);
    $egitim = $metin('egitim', 150);
    $deneyimOzet = $metin('deneyim_ozet', 3000);

    if (mb_strlen($adSoyad, 'UTF-8') < 5 || !preg_match('/\s/u', $adSoyad)) {
        $yanit(false, 'Lütfen adınızı ve soyadınızı eksiksiz yazın.', 422);
    }
    if ($telefon === '' || strlen($telefon) !== 12 || strncmp($telefon, '905', 3) !== 0) {
        $yanit(false, 'Geçerli bir cep telefonu numarası girin.', 422);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $yanit(false, 'Geçerli bir e-posta adresi girin.', 422);
    }
    if ($sehir === '' || $egitim === '' || $deneyimOzet === '') {
        $yanit(false, 'Zorunlu alanları doldurun.', 422);
    }
    if (($_POST['kvkk'] ?? '') !== '1') {
        $yanit(false, 'Aydınlatma metnini onaylamanız gerekir.', 422);
    }

    $dogumTarihi = null;
    $dt = trim((string) ($_POST['dogum_tarihi'] ?? ''));
    if ($dt !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $dt);
        if (!$d || $d->format('Y-m-d') !== $dt || $d > new DateTime('-15 years') || $d < new DateTime('-90 years')) {
            $yanit(false, 'Doğum tarihi geçersiz.', 422);
        }
        $dogumTarihi = $dt;
    }

    $deneyimYili = ($_POST['deneyim_yili'] ?? '') !== '' ? round((float) $_POST['deneyim_yili'], 1) : null;
    if ($deneyimYili !== null && ($deneyimYili < 0 || $deneyimYili > 60)) {
        $yanit(false, 'Deneyim süresi geçersiz.', 422);
    }
    $maas = ($_POST['maas_beklentisi'] ?? '') !== '' ? round((float) $_POST['maas_beklentisi'], 2) : null;
    if ($maas !== null && ($maas < 0 || $maas > 10000000)) {
        $yanit(false, 'Maaş beklentisi geçersiz.', 422);
    }

    $sorular = json_decode((string) ($ilan->sorular ?? ''), true);
    $sorular = is_array($sorular) ? array_values(array_filter(array_map('strval', $sorular), static fn($s) => trim($s) !== '')) : [];
    $cevaplar = [];
    $gelenCevaplar = is_array($_POST['soru'] ?? null) ? $_POST['soru'] : [];
    foreach ($sorular as $i => $soru) {
        $cevap = trim(mb_substr(str_replace("\r", '', (string) ($gelenCevaplar[$i] ?? '')), 0, 1500, 'UTF-8'));
        if ($cevap === '') {
            $yanit(false, 'Tüm pozisyon sorularını yanıtlayın.', 422);
        }
        $cevaplar[$soru] = $cevap;
    }

    if ($Basvuru->telefonVarMi($ilanId, $telefon)) {
        $yanit(false, 'Bu telefon numarasıyla bu ilana daha önce başvuru yapılmış.', 409);
    }

    $cvMetin = '';
    $cvMime = null;
    if (isset($_FILES['cv']) && (int) $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $islenmis = GuvenliBelgeYuklemeService::isle($_FILES['cv']);
        } catch (DomainException $ex) {
            $yanit(false, $ex->getMessage(), 422);
        }
        if ($islenmis['mime'] !== 'application/pdf') {
            $yanit(false, 'CV yalnızca PDF olabilir.', 422);
        }
        try {
            $cvMetin = trim((new \Smalot\PdfParser\Parser())->parseContent($islenmis['veri'])->getText());
            $cvMetin = mb_substr(preg_replace('/\s+/u', ' ', $cvMetin) ?? '', 0, 60000, 'UTF-8');
        } catch (Throwable $ex) {
            error_log('basvuru-gonder CV metni okunamadı: ' . $ex->getMessage());
            $cvMetin = '';
        }

        $dizin = __DIR__ . '/uploads/ik_basvuru/' . $firmaId . '/';
        if (!is_dir($dizin) && !@mkdir($dizin, 0733, true) && !is_dir($dizin)) {
            throw new RuntimeException('CV klasörü oluşturulamadı.');
        }
        $erisimDosyasi = __DIR__ . '/uploads/ik_basvuru/.htaccess';
        if (!is_file($erisimDosyasi)) {
            @file_put_contents($erisimDosyasi, "Require all denied\n");
        }
        $kayitliDosya = bin2hex(random_bytes(16)) . '.enc';
        if (file_put_contents($dizin . $kayitliDosya, Security::encryptFile($islenmis['veri']), LOCK_EX) === false) {
            $kayitliDosya = null;
            throw new RuntimeException('CV kaydedilemedi.');
        }
        $cvMime = $islenmis['mime'];
    }

    $basvuruId = $Basvuru->ekle([
        'firma_id' => $firmaId,
        'ilan_id' => $ilanId,
        'ad_soyad' => $adSoyad,
        'telefon' => $telefon,
        'email' => $email !== '' ? $email : null,
        'dogum_tarihi' => $dogumTarihi,
        'sehir' => $sehir,
        'egitim' => $egitim,
        'deneyim_yili' => $deneyimYili,
        'deneyim_ozet' => $deneyimOzet,
        'ehliyet' => $metin('ehliyet', 50) ?: null,
        'maas_beklentisi' => $maas,
        'ise_baslama' => $metin('ise_baslama', 100) ?: null,
        'cevaplar' => $cevaplar ? json_encode($cevaplar, JSON_UNESCAPED_UNICODE) : null,
        'cv_dosya' => $kayitliDosya,
        'cv_mime' => $cvMime,
        'cv_metin' => $cvMetin !== '' ? $cvMetin : null,
        'ip_adresi' => $ip,
    ]);
    $kayitliDosya = null;

    $Basvuru->denemeKaydet($ilanId, $ip, 'basarili');
    $Basvuru->olayEkle($firmaId, $basvuruId, null, 'Başvuru alındı', 'Aday dijital başvuru formunu gönderdi.');
    (new SystemLogModel())->logActionForFirm($firmaId, 0, 'İşe Alım Başvurusu', 'Yeni başvuru alındı (İlan ID: ' . $ilanId . ', Başvuru ID: ' . $basvuruId . ')', SystemLogModel::LEVEL_INFO);
    unset($_SESSION['ik_basvuru_csrf']);

    ignore_user_abort(true);
    $govde = json_encode(['ok' => true, 'mesaj' => 'Başvurunuz alındı.'], JSON_UNESCAPED_UNICODE);
    header('Content-Length: ' . strlen($govde));
    header('Connection: close');
    echo $govde;
    if (function_exists('fastcgi_finish_request')) {
        session_write_close();
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    set_time_limit(180);
    try {
        $sonuc = (new IkBasvuruAiService())->degerlendir($ilan, [
            'sehir' => $sehir,
            'egitim' => $egitim,
            'deneyim_yili' => $deneyimYili,
            'deneyim_ozet' => $deneyimOzet,
            'ehliyet' => $metin('ehliyet', 50),
            'maas_beklentisi' => $maas,
            'ise_baslama' => $metin('ise_baslama', 100),
            'cevaplar' => $cevaplar,
            'cv_metin' => $cvMetin,
        ], $firmaId);
        $Basvuru->aiSonucKaydet($basvuruId, $sonuc);
        $Basvuru->olayEkle($firmaId, $basvuruId, null, 'Yapay zekâ değerlendirmesi', 'Puan: ' . $sonuc['puan'] . ' / Etiket: ' . $sonuc['etiket']);
    } catch (Throwable $ex) {
        error_log('İK başvuru AI değerlendirme hatası (Başvuru ' . $basvuruId . '): ' . $ex->getMessage());
        $Basvuru->aiHataKaydet($basvuruId);
        $Basvuru->olayEkle($firmaId, $basvuruId, null, 'Yapay zekâ hatası', 'Değerlendirme tamamlanamadı; yeniden denenebilir.');
    }
} catch (Throwable $ex) {
    error_log('basvuru-gonder.php hatası: ' . $ex->getMessage());
    if ($kayitliDosya !== null && isset($firmaId)) {
        @unlink(__DIR__ . '/uploads/ik_basvuru/' . $firmaId . '/' . $kayitliDosya);
    }
    if ($basvuruId === 0 && !headers_sent()) {
        $yanit(false, 'Başvuru şu anda kaydedilemedi. Lütfen daha sonra tekrar deneyin.', 500);
    }
}
