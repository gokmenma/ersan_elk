<?php
require_once __DIR__ . '/Autoloader.php';

use App\Model\PersonelKarsilamaModel;
use App\Model\SystemLogModel;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$kayit = null;
try {
    $Karsilama = new PersonelKarsilamaModel();
    $kayit = $Karsilama->findByToken(strtolower(trim((string) ($_GET['t'] ?? ''))));
    if ($kayit) {
        $sablon = $Karsilama->getSablon((int) $kayit->firma_id);
        $firmaAdi = $Karsilama->firmaAdi((int) $kayit->firma_id);
        if (empty($kayit->ilk_acilma_tarihi)) {
            (new SystemLogModel())->logActionForFirm((int) $kayit->firma_id, 0, 'Karşılama Mesajı', 'Bilgilendirme sayfası personel tarafından açıldı (Personel ID: ' . (int) $kayit->personel_id . ')', SystemLogModel::LEVEL_INFO);
        }
        $Karsilama->acilmaKaydet((int) $kayit->id);
    }
} catch (Throwable $ex) {
    error_log('karsilama.php hatası: ' . $ex->getMessage());
    $kayit = null;
}

if (!$kayit) {
    http_response_code(404);
}

$maddeler = [];
if ($kayit) {
    foreach (preg_split('/\n/', (string) $sablon['evraklar']) as $satir) {
        $satir = trim($satir);
        if ($satir === '') {
            continue;
        }
        if ($satir[0] === '-' && $maddeler) {
            $maddeler[count($maddeler) - 1]['not'][] = trim(ltrim($satir, '- '));
        } else {
            $maddeler[] = ['ad' => $satir, 'not' => []];
        }
    }
    $ilkAd = trim((string) strtok((string) $kayit->adi_soyadi, ' '));
    $baslikMeta = 'İşe Alım Bilgilendirmesi - ' . $firmaAdi;
    $aciklamaMeta = 'Gerekli evraklar ve banka maaş hesabı adımı. ' . count($maddeler) . ' evrak maddesi.';
    $kaseSatirlari = array_values(array_filter(array_map('trim', explode("\n", (string) $sablon['kase_bilgisi']))));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $kayit ? $e($baslikMeta) : 'Bağlantı Geçersiz' ?></title>
    <?php if ($kayit): ?>
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?= $e($baslikMeta) ?>">
        <meta property="og:description" content="<?= $e($aciklamaMeta) ?>">
    <?php endif; ?>
    <style>
        :root { --bg: #eef1f5; --card: #fff; --ink: #1c2530; --muted: #6b7686; --brand: #0b5ed7; --brand-soft: #e7f0ff; --ok: #198754; --ok-soft: #e4f5ec; --line: #e3e8ef; }
        @media (prefers-color-scheme: dark) { :root { --bg: #14181f; --card: #1d232d; --ink: #eef1f6; --muted: #9aa5b5; --brand: #6ea8fe; --brand-soft: #1e2d47; --ok: #4cc38a; --ok-soft: #173326; --line: #2c3541; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 16px; }
        .hero { background: linear-gradient(135deg, #0b5ed7, #0a3d91); color: #fff; border-radius: 22px; padding: 24px 20px; margin-bottom: 14px; }
        .hero small { opacity: .8; letter-spacing: .04em; text-transform: uppercase; font-size: 12px; }
        .hero h1 { margin: 6px 0 4px; font-size: 22px; line-height: 1.25; }
        .hero p { margin: 0; opacity: .9; font-size: 14px; }
        .card { background: var(--card); border-radius: 18px; padding: 18px; margin-bottom: 14px; border: 1px solid var(--line); }
        .card h2 { margin: 0 0 12px; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .ico { width: 30px; height: 30px; border-radius: 9px; background: var(--brand-soft); color: var(--brand); display: inline-flex; align-items: center; justify-content: center; font-size: 15px; flex: none; }
        .progress { font-size: 12px; color: var(--muted); margin: -6px 0 12px; }
        .bar { height: 6px; background: var(--line); border-radius: 99px; overflow: hidden; margin-bottom: 14px; }
        .bar i { display: block; height: 100%; width: 0; background: var(--ok); transition: width .25s; }
        ul.list { list-style: none; margin: 0; padding: 0; }
        ul.list li { border-top: 1px solid var(--line); }
        ul.list li:first-child { border-top: 0; }
        label.item { display: flex; gap: 12px; align-items: flex-start; padding: 12px 2px; min-height: 48px; cursor: pointer; }
        label.item input { position: absolute; opacity: 0; }
        .box { width: 24px; height: 24px; border-radius: 8px; border: 2px solid var(--line); flex: none; display: flex; align-items: center; justify-content: center; color: transparent; font-size: 14px; margin-top: 1px; }
        label.item input:checked + .box { background: var(--ok); border-color: var(--ok); color: #fff; }
        label.item input:checked ~ .txt .ad { color: var(--muted); text-decoration: line-through; }
        label.item input:focus-visible + .box { outline: 3px solid var(--brand); outline-offset: 2px; }
        .not { display: block; font-size: 13px; color: var(--muted); margin-top: 2px; }
        .adim { background: var(--ok-soft); border-radius: 14px; padding: 14px; font-size: 15px; }
        .kase { background: var(--bg); border: 1px dashed var(--muted); border-radius: 14px; padding: 14px; font-size: 15px; line-height: 1.6; }
        .kase b { display: block; }
        button.kopya { margin-top: 12px; width: 100%; min-height: 48px; border: 0; border-radius: 12px; background: var(--brand); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
        .foot { text-align: center; color: var(--muted); font-size: 12px; padding: 8px 0 24px; }
        .hata { text-align: center; padding: 60px 20px; }
    </style>
</head>
<body>
<div class="wrap">
<?php if (!$kayit): ?>
    <div class="card hata">
        <h2 style="justify-content:center;">Bağlantı geçersiz veya süresi dolmuş</h2>
        <p style="color:var(--muted);">Lütfen size bu bağlantıyı gönderen kişiyle iletişime geçin.</p>
    </div>
<?php else: ?>
    <div class="hero">
        <small><?= $e($firmaAdi) ?></small>
        <h1>Merhaba <?= $e($ilkAd) ?>, aramıza hoş geldin</h1>
        <p>İşe başlangıç için hazırlaman gerekenleri aşağıda topladık.</p>
    </div>

    <div class="card">
        <h2><span class="ico">&#9776;</span> <?= $e($sablon['sayfa_baslik']) ?></h2>
        <div class="progress" id="ilerleme">0 / <?= count($maddeler) ?> hazır</div>
        <div class="bar"><i id="bar"></i></div>
        <ul class="list">
            <?php foreach ($maddeler as $i => $m): ?>
                <li>
                    <label class="item">
                        <input type="checkbox" class="chk" data-i="<?= (int) $i ?>">
                        <span class="box">&#10003;</span>
                        <span class="txt">
                            <span class="ad"><?= $e($m['ad']) ?></span>
                            <?php foreach ($m['not'] as $not): ?><span class="not"><?= $e($not) ?></span><?php endforeach; ?>
                        </span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (trim((string) $sablon['banka_adimi']) !== ''): ?>
        <div class="card">
            <h2><span class="ico">&#9830;</span> Maaş Hesabı</h2>
            <div class="adim"><?= nl2br($e($sablon['banka_adimi'])) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($kaseSatirlari): ?>
        <div class="card">
            <h2><span class="ico">&#9998;</span> Firma Bilgileri (Kaşe)</h2>
            <div class="kase" id="kaseMetin"><b><?= $e($kaseSatirlari[0]) ?></b><?php foreach (array_slice($kaseSatirlari, 1) as $s): ?><?= $e($s) ?><br><?php endforeach; ?></div>
            <button type="button" class="kopya" id="btnKopya">Bilgileri Kopyala</button>
        </div>
    <?php endif; ?>

    <div class="foot">Bu sayfa size özel hazırlanmıştır, lütfen başkalarıyla paylaşmayın.</div>
<?php endif; ?>
</div>
<?php if ($kayit): ?>
<script>
(function () {
    var anahtar = 'karsilama_<?= substr(hash('sha256', (string) $kayit->token_hash), 0, 16) ?>';
    var kutular = document.querySelectorAll('.chk');
    var durum = {};
    try { durum = JSON.parse(localStorage.getItem(anahtar) || '{}'); } catch (e) {}
    function guncelle() {
        var n = 0;
        kutular.forEach(function (k) { if (k.checked) n++; });
        document.getElementById('ilerleme').textContent = n + ' / ' + kutular.length + ' hazır';
        document.getElementById('bar').style.width = (kutular.length ? n * 100 / kutular.length : 0) + '%';
    }
    kutular.forEach(function (k) {
        k.checked = !!durum[k.dataset.i];
        k.addEventListener('change', function () {
            durum[k.dataset.i] = k.checked;
            try { localStorage.setItem(anahtar, JSON.stringify(durum)); } catch (e) {}
            guncelle();
        });
    });
    guncelle();
    var btn = document.getElementById('btnKopya');
    if (btn) {
        btn.addEventListener('click', function () {
            var metin = document.getElementById('kaseMetin').innerText;
            var bitti = function () { btn.textContent = 'Kopyalandı ✓'; setTimeout(function () { btn.textContent = 'Bilgileri Kopyala'; }, 1800); };
            if (navigator.clipboard) { navigator.clipboard.writeText(metin).then(bitti, bitti); } else { bitti(); }
        });
    }
})();
</script>
<?php endif; ?>
</body>
</html>
