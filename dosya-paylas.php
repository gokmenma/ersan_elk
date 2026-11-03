<?php
require_once __DIR__ . '/Autoloader.php';

use App\Model\DosyaModel;
use App\Model\DosyaPaylasimModel;
use App\Service\DosyaYonetimiService;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

function paylasimSayfa(string $baslik, string $icerik, int $durum = 200): never
{
    http_response_code($durum);
    $b = htmlspecialchars($baslik, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . $b . '</title>'
        . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#e6eaef;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#191d24}'
        . '.k{background:#fff;border-radius:24px;padding:32px;max-width:420px;width:calc(100% - 32px);box-shadow:0 12px 32px rgba(25,29,36,.12);text-align:center}'
        . 'h1{font-size:18px;margin:0 0 8px}p{margin:6px 0;color:#5b6573;font-size:14px;word-break:break-word}'
        . 'input,button{font:inherit;border-radius:14px;padding:12px 14px;border:1px solid #cdd3dc;width:100%;box-sizing:border-box;margin-top:12px}'
        . 'button{background:#191d24;color:#fff;border:0;cursor:pointer;min-height:44px}.h{color:#c0392b}</style></head><body><div class="k">' . $icerik . '</div></body></html>';
    exit;
}

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$model = new DosyaPaylasimModel();

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

$engel = static function (callable $kontrol): bool {
    try {
        return (bool) $kontrol();
    } catch (Throwable $e) {
        error_log('dosya-paylas deneme sınırı: ' . $e->getMessage());
        return false;
    }
};
$basarisiz = static function () use ($model, $ip, $token): void {
    try {
        $model->basarisizDenemeKaydet($ip, $token);
    } catch (Throwable $e) {
        error_log('dosya-paylas deneme kaydı: ' . $e->getMessage());
    }
};

if ($engel(static fn() => $model->ipEngelliMi($ip))) {
    paylasimSayfa('Çok fazla deneme', '<h1>Çok fazla deneme</h1><p>Lütfen 10 dakika sonra tekrar deneyin.</p>', 429);
}

$paylasim = $model->tokenIleBul($token);
if (!$paylasim) {
    $basarisiz();
    paylasimSayfa('Bağlantı bulunamadı', '<h1>Bağlantı bulunamadı</h1><p>Bu paylaşım bağlantısı geçersiz.</p>', 404);
}
$sorun = $model->gecerliMi($paylasim);
if ($sorun !== null) {
    paylasimSayfa('Bağlantı kullanılamıyor', '<h1>Bağlantı kullanılamıyor</h1><p>' . $h($sorun) . '</p>', 410);
}

$sifreHatasi = '';
if ($paylasim->sifre_hash !== null) {
    if ($engel(static fn() => $model->baglantiEngelliMi($token))) {
        paylasimSayfa('Çok fazla deneme', '<h1>Çok fazla hatalı deneme</h1><p>Bu bağlantı geçici olarak kilitlendi. Lütfen 10 dakika sonra tekrar deneyin.</p>', 429);
    }
    $girilen = (string) ($_POST['sifre'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !password_verify($girilen, $paylasim->sifre_hash)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $basarisiz();
            $sifreHatasi = '<p class="h">Şifre hatalı.</p>';
        }
        paylasimSayfa('Şifre gerekli', '<h1>' . $h($paylasim->orijinal_ad) . '</h1><p>Bu dosya şifre ile korunuyor.</p>' . $sifreHatasi
            . '<form method="post"><input type="hidden" name="t" value="' . $h($token) . '"><input type="password" name="sifre" placeholder="Şifre" autocomplete="off" required><button type="submit">Dosyayı indir</button></form>');
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paylasimSayfa('Dosya paylaşımı', '<h1>' . $h($paylasim->orijinal_ad) . '</h1><p>' . $h(number_format(((int) $paylasim->boyut) / 1024, 0, ',', '.')) . ' KB</p>'
        . '<form method="post"><input type="hidden" name="t" value="' . $h($token) . '"><button type="submit">Dosyayı indir</button></form>');
}

if (!$model->indirmeSayisiArtir((int) $paylasim->id)) {
    paylasimSayfa('Bağlantı kullanılamıyor', '<h1>Bağlantı kullanılamıyor</h1><p>Bu bağlantının indirme limiti dolmuş.</p>', 410);
}

try {
    (new DosyaModel())->erisimLogla((int) $paylasim->firma_id, (int) $paylasim->dosya_id, (int) $paylasim->id, 'paylasim_indir', null);
} catch (Throwable $e) {
    error_log('dosya-paylas log: ' . $e->getMessage());
}

DosyaYonetimiService::dosyaGonder((object) [
    'firma_id' => $paylasim->firma_id,
    'dosya_adi' => $paylasim->dosya_adi,
    'orijinal_ad' => $paylasim->orijinal_ad,
    'mime_tipi' => $paylasim->mime_tipi,
    'uzanti' => $paylasim->uzanti,
], false);
