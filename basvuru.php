<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/Autoloader.php';

use App\Model\IkIlanModel;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$ilan = null;
try {
    $ilan = (new IkIlanModel())->tokenIleBul(strtolower(trim((string) ($_GET['i'] ?? ''))));
} catch (Throwable $ex) {
    error_log('basvuru.php hatası: ' . $ex->getMessage());
    $ilan = null;
}

if (!$ilan) {
    http_response_code(404);
} else {
    if (empty($_SESSION['ik_basvuru_csrf'])) {
        $_SESSION['ik_basvuru_csrf'] = bin2hex(random_bytes(32));
    }
    $_SESSION['ik_basvuru_form_zamani'] = time();
    $sorular = json_decode((string) ($ilan->sorular ?? ''), true);
    $sorular = is_array($sorular) ? array_values(array_filter(array_map('strval', $sorular), static fn($s) => trim($s) !== '')) : [];
}

$egitimler = ['İlkokul', 'Ortaokul', 'Lise', 'Meslek Lisesi', 'Ön Lisans', 'Lisans', 'Yüksek Lisans'];
$ehliyetler = ['Yok', 'B', 'C', 'D', 'E', 'A2', 'B ve C', 'B ve E'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $ilan ? $e($ilan->baslik . ' - İş Başvurusu') : 'Başvuru Bağlantısı Geçersiz' ?></title>
    <?php if ($ilan): ?>
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?= $e($ilan->baslik . ' - İş Başvurusu') ?>">
        <meta property="og:description" content="<?= $e($ilan->firma_adi) ?> için dijital başvuru formu">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef1f5; --card:#fff; --ink:#1c2530; --muted:#6b7686; --brand:#0b5ed7; --brand-soft:#e7f0ff; --ok:#198754; --ok-soft:#e4f5ec; --line:#e3e8ef; --err:#dc3545; --err-soft:#fdecea; }
        @media (prefers-color-scheme: dark) { :root { --bg:#14181f; --card:#1d232d; --ink:#eef1f6; --muted:#9aa5b5; --brand:#6ea8fe; --brand-soft:#1e2d47; --ok:#4cc38a; --ok-soft:#173326; --line:#2c3541; --err:#ff7b86; --err-soft:#3a1e22; } }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font:16px/1.5 'Outfit',-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        button, input, select, textarea { font-family:inherit; }
        .wrap { max-width:600px; margin:0 auto; padding:16px 16px 40px; }
        .hero { background:linear-gradient(135deg,#0b5ed7,#0a3d91); color:#fff; border-radius:22px; padding:24px 20px; margin-bottom:14px; }
        .hero small { opacity:.8; letter-spacing:.04em; text-transform:uppercase; font-size:12px; }
        .hero h1 { margin:6px 0 10px; font-size:23px; line-height:1.25; }
        .chips span { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.16); border-radius:99px; padding:3px 12px; font-size:13px; margin:0 6px 6px 0; }
        .hero p { margin:6px 0 0; opacity:.92; font-size:14.5px; white-space:pre-line; }
        .card { background:var(--card); border-radius:18px; padding:18px; margin-bottom:14px; border:1px solid var(--line); }
        .card h2 { margin:0 0 14px; font-size:16px; display:flex; align-items:center; gap:10px; }
        .ico { width:32px; height:32px; border-radius:10px; background:var(--brand-soft); color:var(--brand); display:inline-flex; align-items:center; justify-content:center; font-size:16px; flex:none; }
        .nitelik { white-space:pre-line; font-size:15px; }
        .alan { margin-top:12px; }
        .alan:first-of-type { margin-top:0; }
        label.et { display:block; font-size:13px; font-weight:500; color:var(--muted); margin-bottom:5px; }
        label.et b { color:var(--err); }
        input[type=text], input[type=tel], input[type=email], input[type=date], input[type=number], select, textarea { width:100%; min-height:50px; border:1.5px solid var(--line); border-radius:12px; padding:11px 13px; font-size:16px; background:var(--card); color:var(--ink); transition:border-color .15s, box-shadow .15s; }
        textarea { min-height:100px; resize:vertical; }
        input:focus, select:focus, textarea:focus { outline:0; border-color:var(--brand); box-shadow:0 0 0 3px var(--brand-soft); }
        input.gecersiz, select.gecersiz, textarea.gecersiz { border-color:var(--err); background:var(--err-soft); }
        .ip { font-size:12.5px; color:var(--muted); margin-top:4px; }
        .row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px; }
        .row2 .alan { margin-top:0; }
        @media (max-width:520px) { .row2 { grid-template-columns:1fr; } }
        .yukle { display:flex; gap:14px; align-items:center; border:1.5px dashed var(--line); border-radius:14px; padding:12px; cursor:pointer; background:var(--bg); transition:border-color .15s; }
        .yukle:hover, .yukle:focus-within { border-color:var(--brand); }
        .yukle input { position:absolute; opacity:0; width:1px; height:1px; }
        .onizleme { width:72px; height:92px; border-radius:12px; background:var(--brand-soft); color:var(--brand); display:flex; align-items:center; justify-content:center; font-size:30px; flex:none; overflow:hidden; }
        .onizleme img { width:100%; height:100%; object-fit:cover; }
        .cvikon { width:56px; height:56px; border-radius:14px; background:var(--brand-soft); color:var(--brand); display:flex; align-items:center; justify-content:center; font-size:26px; flex:none; }
        .yukle .t { font-weight:600; font-size:15px; }
        .yukle .n { font-size:13px; color:var(--muted); }
        .yukle .sec { display:inline-block; margin-top:6px; padding:6px 14px; border-radius:10px; border:1.5px solid var(--brand); color:var(--brand); font-size:13.5px; font-weight:600; }
        .kaldir { border:0; background:transparent; color:var(--err); font-size:13px; font-weight:600; padding:6px 0; cursor:pointer; display:none; }
        .kvkk { max-height:130px; overflow:auto; font-size:13px; color:var(--muted); background:var(--bg); border-radius:12px; padding:10px 12px; }
        .onay { display:flex; gap:12px; align-items:flex-start; margin-top:14px; font-size:14px; cursor:pointer; min-height:44px; }
        .onay input { width:22px; height:22px; flex:none; margin-top:2px; accent-color:var(--brand); }
        button.gonder { width:100%; min-height:54px; border:0; border-radius:14px; background:var(--brand); color:#fff; font-size:17px; font-weight:600; margin-top:6px; cursor:pointer; box-shadow:0 6px 16px rgba(11,94,215,.28); }
        button.gonder:disabled { opacity:.65; cursor:progress; box-shadow:none; }
        .hp { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
        .msg { padding:12px 14px; border-radius:12px; margin-bottom:12px; font-size:14.5px; display:none; background:var(--err-soft); color:var(--err); font-weight:500; }
        .msg.goster { display:block; }
        .tamam { text-align:center; padding:40px 20px; }
        .tamam .ok { width:72px; height:72px; margin:0 auto 14px; border-radius:50%; background:var(--ok-soft); color:var(--ok); font-size:38px; display:flex; align-items:center; justify-content:center; }
        .tamam h1 { margin:0 0 6px; font-size:22px; }
        .tamam p { margin:0; color:var(--muted); }
        .alt { text-align:center; font-size:12.5px; color:var(--muted); margin-top:8px; }
    </style>
</head>
<body>
<div class="wrap">
<?php if (!$ilan): ?>
    <div class="card tamam">
        <div class="ok" style="background:var(--err-soft);color:var(--err)">!</div>
        <h1>Bağlantı geçersiz</h1>
        <p>Bu başvuru bağlantısı kapatılmış veya süresi dolmuş olabilir.</p>
    </div>
<?php else: ?>
    <div class="hero">
        <small><?= $e($ilan->firma_adi) ?></small>
        <h1><?= $e($ilan->baslik) ?></h1>
        <div class="chips">
            <?php if (!empty($ilan->departman)): ?><span>🏢 <?= $e($ilan->departman) ?></span><?php endif; ?>
            <?php if (!empty($ilan->calisma_yeri)): ?><span>📍 <?= $e($ilan->calisma_yeri) ?></span><?php endif; ?>
            <?php if (!empty($ilan->gecerlilik_tarihi)): ?><span>⏳ Son başvuru: <?= $e(date('d.m.Y', strtotime((string) $ilan->gecerlilik_tarihi))) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($ilan->aciklama)): ?><p><?= $e($ilan->aciklama) ?></p><?php endif; ?>
    </div>

    <?php if (!empty($ilan->aranan_nitelikler)): ?>
    <div class="card">
        <h2><span class="ico">✓</span> Aranan nitelikler</h2>
        <div class="nitelik"><?= $e($ilan->aranan_nitelikler) ?></div>
    </div>
    <?php endif; ?>

    <form id="basvuruForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="i" value="<?= $e(strtolower((string) $_GET['i'])) ?>">
        <input type="hidden" name="csrf" value="<?= $e($_SESSION['ik_basvuru_csrf']) ?>">
        <div class="hp" aria-hidden="true"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div class="card">
            <h2><span class="ico">👤</span> Kişisel bilgiler</h2>
            <div class="alan"><label class="et" for="ad_soyad">Ad Soyad <b>*</b></label><input type="text" id="ad_soyad" name="ad_soyad" maxlength="150" autocomplete="name" required></div>
            <div class="row2">
                <div class="alan"><label class="et" for="telefon">Cep Telefonu <b>*</b></label><input type="tel" id="telefon" name="telefon" inputmode="tel" maxlength="20" autocomplete="tel" placeholder="05xx xxx xx xx" required></div>
                <div class="alan"><label class="et" for="email">E-posta</label><input type="email" id="email" name="email" maxlength="190" autocomplete="email"></div>
            </div>
            <div class="row2">
                <div class="alan"><label class="et" for="dogum_tarihi">Doğum Tarihi</label><input type="date" id="dogum_tarihi" name="dogum_tarihi"></div>
                <div class="alan"><label class="et" for="sehir">Yaşadığınız Şehir <b>*</b></label><input type="text" id="sehir" name="sehir" maxlength="100" autocomplete="address-level2" required></div>
            </div>
            <div class="alan" style="margin-top:14px">
                <label class="et">Vesikalık fotoğraf</label>
                <label class="yukle" id="fotoKutu">
                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png" aria-label="Vesikalık fotoğraf seç">
                    <span class="onizleme" id="fotoOnizleme">📷</span>
                    <span>
                        <span class="t">Vesikalık fotoğrafınız</span><br>
                        <span class="n">Yüzünüzün net göründüğü, düz fonlu bir fotoğraf (JPG/PNG)</span><br>
                        <span class="sec" id="fotoSec">Fotoğraf seç / çek</span>
                    </span>
                </label>
                <button type="button" class="kaldir" id="fotoKaldir">Fotoğrafı kaldır</button>
            </div>
        </div>

        <div class="card">
            <h2><span class="ico">🎓</span> Eğitim ve deneyim</h2>
            <div class="row2" style="margin-top:0">
                <div class="alan"><label class="et" for="egitim">Eğitim Durumu <b>*</b></label>
                    <select id="egitim" name="egitim" required><option value="">Seçiniz</option>
                        <?php foreach ($egitimler as $g): ?><option value="<?= $e($g) ?>"><?= $e($g) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="alan"><label class="et" for="ehliyet">Ehliyet</label>
                    <select id="ehliyet" name="ehliyet"><option value="">Seçiniz</option>
                        <?php foreach ($ehliyetler as $g): ?><option value="<?= $e($g) ?>"><?= $e($g) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="row2">
                <div class="alan"><label class="et" for="deneyim_yili">Toplam Deneyim (yıl)</label><input type="number" id="deneyim_yili" name="deneyim_yili" inputmode="decimal" min="0" max="60" step="0.5"></div>
                <div class="alan"><label class="et" for="maas_beklentisi">Net Maaş Beklentisi (₺)</label><input type="number" id="maas_beklentisi" name="maas_beklentisi" inputmode="numeric" min="0" step="100"></div>
            </div>
            <div class="alan"><label class="et" for="deneyim_ozet">Deneyiminizi kısaca anlatın <b>*</b></label><textarea id="deneyim_ozet" name="deneyim_ozet" maxlength="3000" required></textarea><div class="ip">Daha önce çalıştığınız yerler, yaptığınız işler, sahip olduğunuz belgeler.</div></div>
            <div class="alan"><label class="et" for="ise_baslama">Ne zaman işe başlayabilirsiniz?</label><input type="text" id="ise_baslama" name="ise_baslama" maxlength="100" placeholder="Hemen / 15 gün sonra ..."></div>
        </div>

        <?php if ($sorular): ?>
        <div class="card">
            <h2><span class="ico">💬</span> Pozisyona özel sorular</h2>
            <?php foreach ($sorular as $i => $soru): ?>
                <div class="alan"><label class="et" for="soru_<?= (int) $i ?>"><?= $e($soru) ?> <b>*</b></label><textarea id="soru_<?= (int) $i ?>" name="soru[<?= (int) $i ?>]" maxlength="1500" required style="min-height:80px"></textarea></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2><span class="ico">📄</span> Özgeçmiş (CV)</h2>
            <label class="yukle">
                <input type="file" id="cv" name="cv" accept="application/pdf" aria-label="CV dosyası seç">
                <span class="cvikon" id="cvIkon">PDF</span>
                <span>
                    <span class="t" id="cvAd">CV yükleyin (isteğe bağlı)</span><br>
                    <span class="n">PDF, en fazla 8 MB</span><br>
                    <span class="sec">Dosya seç</span>
                </span>
            </label>
            <button type="button" class="kaldir" id="cvKaldir">CV'yi kaldır</button>
        </div>

        <div class="card">
            <h2><span class="ico">🔒</span> Kişisel verilerin korunması</h2>
            <div class="kvkk">
                Paylaştığınız kişisel veriler, 6698 sayılı KVKK kapsamında yalnızca işe alım sürecinin yürütülmesi, başvurunuzun değerlendirilmesi ve sizinle iletişime geçilmesi amacıyla <?= $e($ilan->firma_adi) ?> tarafından işlenir. Başvurunuz, ilan kriterlerine göre ön değerlendirme için yapay zekâ destekli bir araçla analiz edilebilir; bu analiz yalnızca insan kaynakları çalışanlarına yol gösterir ve nihai karar insan tarafından verilir. Verileriniz süreç sonunda makul bir süre saklanır ve yetkisiz kişilerle paylaşılmaz. Haklarınız için firmamızla iletişime geçebilirsiniz.
            </div>
            <label class="onay"><input type="checkbox" name="kvkk" id="kvkk" value="1" required><span>Aydınlatma metnini okudum; kişisel verilerimin başvurum kapsamında işlenmesini ve yapay zekâ destekli ön değerlendirmeyi kabul ediyorum.</span></label>
        </div>

        <div id="mesaj" class="msg" role="alert"></div>
        <button type="submit" class="gonder" id="gonderBtn">Başvuruyu Gönder</button>
        <div class="alt">Başvurunuz güvenli bağlantı üzerinden iletilir.</div>
    </form>

    <div id="tamam" class="card tamam" style="display:none;">
        <div class="ok">✓</div>
        <h1>Başvurunuz alındı</h1>
        <p>Teşekkür ederiz. Başvurunuz değerlendirildikten sonra uygun görülmeniz halinde sizinle iletişime geçilecektir.</p>
    </div>

    <script>
    (function () {
        var form = document.getElementById('basvuruForm');
        var mesaj = document.getElementById('mesaj');
        var btn = document.getElementById('gonderBtn');
        var foto = document.getElementById('foto');
        var cv = document.getElementById('cv');

        function hata(t) { mesaj.textContent = t; mesaj.className = 'msg goster'; mesaj.scrollIntoView({ behavior: 'smooth', block: 'center' }); }

        foto.addEventListener('change', function () {
            var f = foto.files[0];
            var o = document.getElementById('fotoOnizleme');
            if (!f) { o.textContent = '📷'; document.getElementById('fotoKaldir').style.display = 'none'; return; }
            if (f.size > 8 * 1024 * 1024) { hata('Fotoğraf en fazla 8 MB olabilir.'); foto.value = ''; return; }
            var r = new FileReader();
            r.onload = function (ev) { o.innerHTML = ''; var im = document.createElement('img'); im.alt = 'Fotoğraf önizleme'; im.src = ev.target.result; o.appendChild(im); };
            r.readAsDataURL(f);
            document.getElementById('fotoKaldir').style.display = 'inline-block';
        });
        document.getElementById('fotoKaldir').addEventListener('click', function () { foto.value = ''; foto.dispatchEvent(new Event('change')); });

        cv.addEventListener('change', function () {
            var f = cv.files[0];
            if (f && f.size > 8 * 1024 * 1024) { hata('CV dosyası en fazla 8 MB olabilir.'); cv.value = ''; f = null; }
            document.getElementById('cvAd').textContent = f ? f.name : 'CV yükleyin (isteğe bağlı)';
            document.getElementById('cvKaldir').style.display = f ? 'inline-block' : 'none';
        });
        document.getElementById('cvKaldir').addEventListener('click', function () { cv.value = ''; cv.dispatchEvent(new Event('change')); });

        form.addEventListener('input', function (ev) { ev.target.classList.remove('gecersiz'); });

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            mesaj.className = 'msg';
            var gecersiz = form.querySelectorAll(':invalid');
            if (gecersiz.length) {
                gecersiz.forEach(function (el) { el.classList.add('gecersiz'); });
                gecersiz[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                gecersiz[0].focus({ preventScroll: true });
                hata('Lütfen işaretli zorunlu alanları doldurun.');
                return;
            }
            btn.disabled = true; btn.textContent = 'Gönderiliyor...';
            fetch('basvuru-gonder.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function () { return { ok: false, mesaj: 'Beklenmeyen bir yanıt alındı.' }; }); })
                .then(function (j) {
                    if (j.ok) { form.style.display = 'none'; document.getElementById('tamam').style.display = 'block'; window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
                    hata(j.mesaj || 'Başvuru gönderilemedi.'); btn.disabled = false; btn.textContent = 'Başvuruyu Gönder';
                })
                .catch(function () { hata('Bağlantı hatası. Lütfen tekrar deneyin.'); btn.disabled = false; btn.textContent = 'Başvuruyu Gönder'; });
        });
    })();
    </script>
<?php endif; ?>
</div>
</body>
</html>
