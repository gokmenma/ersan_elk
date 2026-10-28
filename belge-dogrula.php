<?php
require_once __DIR__ . '/Autoloader.php';

use App\Model\EvrakDogrulamaModel;
use App\Model\SystemLogModel;
use App\Helper\Security;

session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$maxFailures = 10;

$kod = strtoupper(trim((string) ($_POST['kod'] ?? $_GET['kod'] ?? '')));
$error = '';
$result = null;

if (!isset($_SESSION['belge_dogrula_csrf'])) {
    $_SESSION['belge_dogrula_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['belge_dogrula_csrf'];

try {
    $Dogrulama = new EvrakDogrulamaModel();

    if (isset($_GET['indir'])) {
        $ok = $_SESSION['belge_dogrula_ok'] ?? null;
        $kayit = is_array($ok) && ($ok['exp'] ?? 0) >= time() ? $Dogrulama->findByKod((string) $ok['kod']) : null;
        if (!$kayit) {
            http_response_code(403);
            exit('Bu belgeyi indirmek için önce kod ve PIN ile doğrulama yapmalısınız.');
        }
        $evrak = $Dogrulama->getEvrakOzet((int) $kayit->firma_id, (int) $kayit->evrak_id);
        $path = $evrak && !empty($evrak->pades_pdf_yolu) ? dirname(__FILE__) . '/' . ltrim((string) $evrak->pades_pdf_yolu, '/') : '';
        $real = $path !== '' ? realpath($path) : false;
        $base = realpath(__DIR__ . '/uploads/evrak-pades');
        if (!$real || !$base || strncmp($real, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0 || !is_file($real)) {
            http_response_code(404);
            exit('Belge dosyası bulunamadı.');
        }
        $pdf = Security::decryptFile((string) file_get_contents($real));
        if (empty($evrak->pades_pdf_sha256) || !hash_equals((string) $evrak->pades_pdf_sha256, hash('sha256', $pdf))) {
            error_log('belge-dogrula: PDF bütünlük hatası evrak_id=' . (int) $kayit->evrak_id);
            http_response_code(500);
            exit('Belge bütünlük kontrolünden geçemedi.');
        }
        (new SystemLogModel())->logActionForFirm((int) $kayit->firma_id, 0, 'Belge Doğrulama', 'Belge indirildi. Kod: ' . $kayit->dogrulama_kodu . ' IP: ' . $ip, SystemLogModel::LEVEL_INFO);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="belge-' . preg_replace('/[^A-Za-z0-9\-]/', '', $kayit->dogrulama_kodu) . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pin = preg_replace('/\D/', '', (string) ($_POST['pin'] ?? ''));
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            $error = 'Oturum süresi doldu, lütfen sayfayı yenileyip tekrar deneyin.';
        } elseif ($Dogrulama->countRecentFailures($ip) >= $maxFailures) {
            http_response_code(429);
            $error = 'Çok fazla başarısız deneme yapıldı. Lütfen 15 dakika sonra tekrar deneyin.';
        } elseif (!preg_match('/^EVR-[A-F0-9]{16}$/', $kod) || !preg_match('/^\d{5,6}$/', $pin)) {
            $Dogrulama->logAttempt($ip, $kod, false);
            $error = 'Doğrulama kodu veya PIN hatalı.';
        } else {
            $kayit = $Dogrulama->verify($kod, $pin);
            if (!$kayit) {
                $Dogrulama->logAttempt($ip, $kod, false);
                $error = 'Doğrulama kodu veya PIN hatalı.';
            } else {
                $Dogrulama->logAttempt($ip, $kod, true);
                $Dogrulama->markVerified((int) $kayit->id);
                $evrak = $Dogrulama->getEvrakOzet((int) $kayit->firma_id, (int) $kayit->evrak_id);
                if (!$evrak) {
                    $error = 'Doğrulama kodu veya PIN hatalı.';
                } else {
                    $result = [
                        'evrak' => $evrak,
                        'imzalayanlar' => $Dogrulama->getImzalayanlar((int) $kayit->firma_id, (int) $kayit->evrak_id),
                        'indirilebilir' => !empty($evrak->pades_pdf_yolu),
                    ];
                    $_SESSION['belge_dogrula_ok'] = ['kod' => $kayit->dogrulama_kodu, 'exp' => time() + 600];
                    (new SystemLogModel())->logActionForFirm((int) $kayit->firma_id, 0, 'Belge Doğrulama', 'Belge doğrulandı. Kod: ' . $kayit->dogrulama_kodu . ' IP: ' . $ip, SystemLogModel::LEVEL_INFO);
                }
            }
        }
    }
} catch (\Throwable $ex) {
    error_log('belge-dogrula: ' . $ex->getMessage());
    http_response_code(500);
    $error = 'İşlem sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
    $result = null;
}

$fmtDate = static fn($v, string $f = 'd.m.Y'): string => !empty($v) && strtotime((string) $v) ? date($f, strtotime((string) $v)) : '-';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Belge Doğrulama | Ersan Elektrik</title>
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--gold:#e2bd61;--gold-glow:rgba(226,189,97,.35);--gold-soft:rgba(226,189,97,.12);--dark:#0b0f19;--text:#0f172a;--muted:#64748b;--border:#e2e8f0}
        *{box-sizing:border-box}
        html,body{margin:0;min-height:100vh}
        body{font-family:'Plus Jakarta Sans','Inter',-apple-system,BlinkMacSystemFont,sans-serif;background:#f8fafc;color:var(--text);overflow-x:hidden}
        .wrap{min-height:100vh;display:flex;width:100%}
        .form-col{flex:0 0 100%;max-width:100%;background:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:2rem 1.25rem;position:relative;z-index:2;box-shadow:10px 0 30px rgba(0,0,0,.03)}
        @media(min-width:992px){.form-col{flex:0 0 42%;max-width:42%;padding:3.5rem}}
        @media(min-width:1400px){.form-col{flex:0 0 38%;max-width:38%;padding:4rem 4.5rem}}
        .brand{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:2rem}
        .brand img{max-height:48px;width:auto;object-fit:contain}
        .badge{display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;color:#475569;padding:4px 12px;border-radius:9999px;font-size:.72rem;font-weight:600;letter-spacing:.03em;text-transform:uppercase;border:1px solid var(--border);white-space:nowrap}
        .badge i{width:6px;height:6px;border-radius:50%;background:var(--gold);box-shadow:0 0 6px var(--gold)}
        .inner{width:100%;max-width:440px;margin:auto}
        h1{font-size:1.65rem;font-weight:700;letter-spacing:-.02em;margin:0 0 .35rem}
        .sub{font-size:.925rem;color:var(--muted);margin:0 0 1.75rem;line-height:1.5}
        label{display:block;font-size:.825rem;font-weight:600;color:#334155;margin:0 0 .4rem}
        .field{margin-bottom:1.15rem}
        input[type=text],input[type=tel]{width:100%;height:48px;padding:.65rem 1rem;font:500 .95rem 'Plus Jakarta Sans',sans-serif;color:var(--text);background:#f8fafc;border:1.5px solid var(--border);border-radius:12px;transition:all .25s;letter-spacing:.02em}
        input:hover{background:#fff;border-color:#cbd5e1}
        input:focus{background:#fff;border-color:var(--gold);box-shadow:0 0 0 4px var(--gold-soft);outline:none}
        .btn{width:100%;height:48px;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1.25rem;background:linear-gradient(135deg,#e2bd61,#d4a942);color:#1a1a1a;border:0;border-radius:12px;font:700 .95rem 'Plus Jakarta Sans',sans-serif;letter-spacing:.02em;cursor:pointer;text-decoration:none;box-shadow:0 4px 14px var(--gold-glow);transition:all .25s}
        .btn:hover{background:linear-gradient(135deg,#e8c772,#dbb24c);transform:translateY(-1.5px);box-shadow:0 6px 20px rgba(226,189,97,.45)}
        .btn.ghost{background:#fff;color:#334155;border:1.5px solid var(--border);box-shadow:none;margin-top:.75rem}
        .btn.ghost:hover{background:#f8fafc;transform:none}
        .alert{margin:0 0 1.25rem;padding:12px 14px;border-radius:12px;font-size:.875rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
        .ok{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;font-weight:600;font-size:.9rem;margin-bottom:1.1rem}
        .ok svg{flex:0 0 22px}
        dl{margin:0}
        .row{display:flex;gap:12px;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:.86rem}
        .row dt{flex:0 0 120px;color:var(--muted);font-weight:500}
        .row dd{margin:0;flex:1;word-break:break-word;font-weight:600}
        .hash{font-family:ui-monospace,Menlo,monospace;font-size:.72rem!important;font-weight:500!important}
        h2.sec{font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:1.25rem 0 .25rem}
        .foot{font-size:.78rem;color:#94a3b8;text-align:center;margin-top:2rem}
        .show-col{flex:1;background:var(--dark);position:relative;overflow:hidden;display:none;flex-direction:column;justify-content:space-between;padding:3.5rem 4rem;color:#fff}
        @media(min-width:992px){.show-col{display:flex}}
        .mesh{position:absolute;inset:0;background-image:radial-gradient(at 15% 15%,rgba(226,189,97,.18) 0,transparent 55%),radial-gradient(at 85% 80%,rgba(59,130,246,.16) 0,transparent 60%),radial-gradient(at 50% 50%,rgba(15,23,42,.85) 0,#0b0f19 100%)}
        .grid{position:absolute;inset:0;background-size:36px 36px;background-image:linear-gradient(to right,rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(to bottom,rgba(255,255,255,.03) 1px,transparent 1px);-webkit-mask-image:radial-gradient(ellipse at center,#000 40%,transparent 80%);mask-image:radial-gradient(ellipse at center,#000 40%,transparent 80%)}
        .show{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;justify-content:space-between;gap:2rem}
        .show .top{display:flex;align-items:center;gap:8px;font-weight:700;font-size:.95rem;letter-spacing:.05em;color:#f8fafc}
        .tag{display:inline-block;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--gold);background:var(--gold-soft);border:1px solid rgba(226,189,97,.3);padding:5px 12px;border-radius:9999px;margin-bottom:1.25rem}
        .hero{font-size:2.25rem;font-weight:800;line-height:1.22;letter-spacing:-.03em;margin:0 0 1.25rem}
        .hero span{background:linear-gradient(135deg,#fff 30%,#e2bd61 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .desc{font-size:1rem;color:#94a3b8;line-height:1.6;margin:0 0 2rem;max-width:520px}
        .steps{display:grid;gap:12px;max-width:520px}
        .step{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;border-radius:14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}
        .step b{flex:0 0 30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--gold-soft);color:var(--gold);font-size:.85rem}
        .step p{margin:0;font-size:.875rem;color:#cbd5e1;line-height:1.5}
        .step p strong{color:#fff;display:block;margin-bottom:2px}
        .secure{display:inline-flex;align-items:center;gap:8px;font-size:.8rem;color:#94a3b8}
        .secure i{width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981}
    </style>
</head>
<body>
<div class="wrap">
    <div class="form-col">
        <div class="brand">
            <img src="assets/images/logo.png" alt="Ersan Elektrik">
            <span class="badge"><i></i>Belge Doğrulama</span>
        </div>

        <div class="inner">
            <h1>Belge Doğrulama</h1>
            <p class="sub">Belgenin altındaki doğrulama kodu ve PIN ile belgenin Ersan Elektrik sisteminde kayıtlı ve elektronik imzalı olduğunu doğrulayın.</p>

            <?php if ($error !== ''): ?>
                <div class="alert"><?= $e($error) ?></div>
            <?php endif; ?>

            <?php if ($result): $ev = $result['evrak']; ?>
                <div class="ok">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m8 12.5 2.8 2.8L16 9.5"/></svg>
                    Belge doğrulandı. Bu belge sistemde kayıtlıdır.
                </div>
                <dl>
                    <div class="row"><dt>Doğrulama Kodu</dt><dd><?= $e($kod) ?></dd></div>
                    <div class="row"><dt>Düzenleyen</dt><dd><?= $e($ev->firma_adi) ?></dd></div>
                    <div class="row"><dt>Evrak No</dt><dd><?= $e($ev->evrak_no ?: '-') ?></dd></div>
                    <div class="row"><dt>Evrak Tarihi</dt><dd><?= $e($fmtDate($ev->tarih)) ?></dd></div>
                    <div class="row"><dt>Konu</dt><dd><?= $e($ev->konu ?: '-') ?></dd></div>
                    <div class="row"><dt>Muhatap</dt><dd><?= $e($ev->kurum_adi ?: '-') ?></dd></div>
                    <div class="row"><dt>Onay Durumu</dt><dd><?= $ev->onay_durumu === 'onaylandi' ? 'Onaylandı' : 'Onay sürecinde' ?></dd></div>
                    <div class="row"><dt>Belge SHA-256</dt><dd class="hash"><?= $e($ev->pades_pdf_sha256 ?: '-') ?></dd></div>
                </dl>
                <?php if ($result['imzalayanlar']): ?>
                    <h2 class="sec">Elektronik İmzalar</h2>
                    <dl>
                        <?php foreach ($result['imzalayanlar'] as $imza): ?>
                            <div class="row"><dt><?= $e($fmtDate($imza->onay_tarihi, 'd.m.Y H:i')) ?></dt><dd><?= $e($imza->adi_soyadi) ?> &middot; <?= $e($imza->imza_turu ?: 'Elektronik imza') ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <?php if ($result['indirilebilir']): ?>
                    <a class="btn" href="belge-dogrula?indir=1">İmzalı PDF'i İndir</a>
                <?php endif; ?>
                <a class="btn ghost" href="belge-dogrula">Yeni Doğrulama</a>
            <?php else: ?>
                <form method="post" action="belge-dogrula" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <div class="field">
                        <label for="kod">Belge Doğrulama Kodu</label>
                        <input type="text" id="kod" name="kod" value="<?= $e($kod) ?>" placeholder="EVR-XXXXXXXXXXXXXXXX" maxlength="20" required>
                    </div>
                    <div class="field">
                        <label for="pin">PIN Kodu</label>
                        <input type="tel" id="pin" name="pin" inputmode="numeric" maxlength="6" placeholder="PIN" required>
                    </div>
                    <button class="btn" type="submit">Belgeyi Doğrula</button>
                </form>
            <?php endif; ?>

            <div class="foot">&copy; <?= date('Y') ?> Ersan Elektrik İnşaat Ticaret Taahhüt Ltd. Şti.</div>
        </div>
        <div></div>
    </div>

    <div class="show-col">
        <div class="mesh"></div>
        <div class="grid"></div>
        <div class="show">
            <div class="top"><img src="assets/images/logo-sm.svg" alt="" height="24" style="opacity:.9">ERSAN ELEKTRİK</div>
            <div>
                <span class="tag">Güvenli Elektronik Belge</span>
                <h2 class="hero">Belgenin Gerçekliğini <br><span>Saniyeler İçinde Doğrulayın.</span></h2>
                <p class="desc">Ersan Elektrik tarafından düzenlenen ve güvenli elektronik imza ile imzalanan resmî yazıların özgünlüğünü buradan kontrol edebilirsiniz.</p>
                <div class="steps">
                    <div class="step"><b>1</b><p><strong>Kodu girin</strong>Belgenin alt bilgisindeki Belge Doğrulama Kodu'nu yazın veya QR kodu okutun.</p></div>
                    <div class="step"><b>2</b><p><strong>PIN'i girin</strong>Kodun yanında yazan PIN ile erişimi onaylayın.</p></div>
                    <div class="step"><b>3</b><p><strong>Sonucu görün</strong>Belge bilgileri, imzalayanlar ve belge özeti (SHA-256) listelenir.</p></div>
                </div>
            </div>
            <div class="secure"><i></i>Bağlantı ve kayıtlar güvenle korunur</div>
        </div>
    </div>
</div>
</body>
</html>
