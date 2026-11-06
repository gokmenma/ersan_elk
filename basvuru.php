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
    <style>
        :root { --bg:#eef1f5; --card:#fff; --ink:#1b2430; --muted:#64748b; --line:#dbe2ea; --brand:#1f5eff; --ok:#12805c; --err:#c0352b; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .wrap { max-width:640px; margin:0 auto; padding:16px; }
        .card { background:var(--card); border-radius:20px; padding:20px; margin-bottom:14px; box-shadow:0 4px 18px rgba(15,23,42,.06); }
        h1 { font-size:21px; margin:0 0 4px; }
        h2 { font-size:15px; margin:0 0 12px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
        .firma { color:var(--muted); font-size:14px; margin-bottom:10px; }
        .meta span { display:inline-block; background:#eef3ff; color:var(--brand); border-radius:999px; padding:3px 12px; font-size:13px; margin:0 6px 6px 0; }
        .aciklama { white-space:pre-line; font-size:15px; }
        label { display:block; font-size:13px; color:var(--muted); margin:12px 0 4px; }
        input, select, textarea { width:100%; min-height:48px; border:1px solid var(--line); border-radius:12px; padding:10px 12px; font:inherit; background:#fff; color:var(--ink); }
        textarea { min-height:96px; resize:vertical; }
        input:focus, select:focus, textarea:focus { outline:2px solid var(--brand); border-color:transparent; }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        @media (max-width:520px) { .row { grid-template-columns:1fr; } }
        .check { display:flex; gap:10px; align-items:flex-start; font-size:14px; color:var(--ink); margin-top:14px; }
        .check input { width:22px; min-height:22px; margin-top:2px; flex:0 0 22px; }
        .kvkk { max-height:140px; overflow:auto; font-size:13px; color:var(--muted); background:#f6f8fb; border-radius:12px; padding:10px 12px; }
        button { width:100%; min-height:52px; border:0; border-radius:14px; background:var(--brand); color:#fff; font:600 17px inherit; margin-top:16px; cursor:pointer; }
        button:disabled { opacity:.6; }
        .hp { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
        .msg { padding:12px 14px; border-radius:12px; margin-top:12px; font-size:15px; display:none; }
        .msg.err { background:#fdecea; color:var(--err); display:block; }
        .done { text-align:center; padding:36px 20px; }
        .done .ok { font-size:46px; color:var(--ok); }
    </style>
</head>
<body>
<div class="wrap">
<?php if (!$ilan): ?>
    <div class="card done">
        <h1>Bağlantı geçersiz</h1>
        <p class="firma">Bu başvuru bağlantısı kapatılmış veya süresi dolmuş olabilir.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="firma"><?= $e($ilan->firma_adi) ?></div>
        <h1><?= $e($ilan->baslik) ?></h1>
        <div class="meta">
            <?php if (!empty($ilan->departman)): ?><span><?= $e($ilan->departman) ?></span><?php endif; ?>
            <?php if (!empty($ilan->calisma_yeri)): ?><span><?= $e($ilan->calisma_yeri) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($ilan->aciklama)): ?><div class="aciklama"><?= $e($ilan->aciklama) ?></div><?php endif; ?>
        <?php if (!empty($ilan->aranan_nitelikler)): ?>
            <h2 style="margin-top:14px;">Aranan nitelikler</h2>
            <div class="aciklama"><?= $e($ilan->aranan_nitelikler) ?></div>
        <?php endif; ?>
    </div>

    <form id="basvuruForm" class="card" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="i" value="<?= $e(strtolower((string) $_GET['i'])) ?>">
        <input type="hidden" name="csrf" value="<?= $e($_SESSION['ik_basvuru_csrf']) ?>">
        <div class="hp" aria-hidden="true"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <h2>Kişisel bilgiler</h2>
        <label for="ad_soyad">Ad Soyad *</label>
        <input id="ad_soyad" name="ad_soyad" maxlength="150" autocomplete="name" required>
        <div class="row">
            <div><label for="telefon">Cep Telefonu *</label><input id="telefon" name="telefon" type="tel" inputmode="tel" maxlength="20" autocomplete="tel" placeholder="05xx xxx xx xx" required></div>
            <div><label for="email">E-posta</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email"></div>
        </div>
        <div class="row">
            <div><label for="dogum_tarihi">Doğum Tarihi</label><input id="dogum_tarihi" name="dogum_tarihi" type="date"></div>
            <div><label for="sehir">Yaşadığınız Şehir *</label><input id="sehir" name="sehir" maxlength="100" required></div>
        </div>

        <h2 style="margin-top:22px;">Eğitim ve deneyim</h2>
        <div class="row">
            <div><label for="egitim">Eğitim Durumu *</label>
                <select id="egitim" name="egitim" required><option value="">Seçiniz</option>
                    <?php foreach ($egitimler as $g): ?><option value="<?= $e($g) ?>"><?= $e($g) ?></option><?php endforeach; ?>
                </select></div>
            <div><label for="ehliyet">Ehliyet</label>
                <select id="ehliyet" name="ehliyet"><option value="">Seçiniz</option>
                    <?php foreach ($ehliyetler as $g): ?><option value="<?= $e($g) ?>"><?= $e($g) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="row">
            <div><label for="deneyim_yili">Toplam Deneyim (yıl)</label><input id="deneyim_yili" name="deneyim_yili" type="number" inputmode="decimal" min="0" max="60" step="0.5"></div>
            <div><label for="maas_beklentisi">Net Maaş Beklentisi (₺)</label><input id="maas_beklentisi" name="maas_beklentisi" type="number" inputmode="numeric" min="0" step="100"></div>
        </div>
        <label for="deneyim_ozet">Deneyiminizi kısaca anlatın *</label>
        <textarea id="deneyim_ozet" name="deneyim_ozet" maxlength="3000" required></textarea>
        <label for="ise_baslama">Ne zaman işe başlayabilirsiniz?</label>
        <input id="ise_baslama" name="ise_baslama" maxlength="100" placeholder="Hemen / 15 gün sonra ...">

        <?php if ($sorular): ?>
            <h2 style="margin-top:22px;">Pozisyona özel sorular</h2>
            <?php foreach ($sorular as $i => $soru): ?>
                <label for="soru_<?= (int) $i ?>"><?= $e($soru) ?> *</label>
                <textarea id="soru_<?= (int) $i ?>" name="soru[<?= (int) $i ?>]" maxlength="1500" required></textarea>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2 style="margin-top:22px;">Özgeçmiş</h2>
        <label for="cv">CV (PDF, en fazla 8 MB)</label>
        <input id="cv" name="cv" type="file" accept="application/pdf">

        <h2 style="margin-top:22px;">Kişisel verilerin korunması</h2>
        <div class="kvkk">
            Paylaştığınız kişisel veriler, 6698 sayılı KVKK kapsamında yalnızca işe alım sürecinin yürütülmesi, başvurunuzun değerlendirilmesi ve sizinle iletişime geçilmesi amacıyla <?= $e($ilan->firma_adi) ?> tarafından işlenir. Başvurunuz, ilan kriterlerine göre ön değerlendirme için yapay zekâ destekli bir araçla analiz edilebilir; bu analiz yalnızca insan kaynakları çalışanlarına yol gösterir ve nihai karar insan tarafından verilir. Verileriniz süreç sonunda makul bir süre saklanır ve yetkisiz kişilerle paylaşılmaz. Haklarınız için firmamızla iletişime geçebilirsiniz.
        </div>
        <label class="check"><input type="checkbox" name="kvkk" value="1" required><span>Aydınlatma metnini okudum, kişisel verilerimin başvurum kapsamında işlenmesini ve yapay zekâ destekli ön değerlendirmeyi kabul ediyorum.</span></label>

        <div id="mesaj" class="msg" role="alert"></div>
        <button type="submit" id="gonderBtn">Başvuruyu Gönder</button>
    </form>

    <div id="tamam" class="card done" style="display:none;">
        <div class="ok">✓</div>
        <h1>Başvurunuz alındı</h1>
        <p class="firma">Teşekkür ederiz. Başvurunuz değerlendirildikten sonra uygun görülmeniz halinde sizinle iletişime geçilecektir.</p>
    </div>

    <script>
    (function () {
        var form = document.getElementById('basvuruForm');
        var mesaj = document.getElementById('mesaj');
        var btn = document.getElementById('gonderBtn');
        function hata(t) { mesaj.textContent = t; mesaj.className = 'msg err'; mesaj.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            mesaj.className = 'msg';
            if (!form.checkValidity()) { form.reportValidity(); return; }
            var cv = document.getElementById('cv').files[0];
            if (cv && cv.size > 8 * 1024 * 1024) { hata('CV dosyası en fazla 8 MB olabilir.'); return; }
            btn.disabled = true; btn.textContent = 'Gönderiliyor...';
            fetch('basvuru-gonder.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function () { return { ok: false, mesaj: 'Beklenmeyen bir yanıt alındı.' }; }); })
                .then(function (j) {
                    if (j.ok) { form.style.display = 'none'; document.getElementById('tamam').style.display = 'block'; window.scrollTo(0, 0); return; }
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
