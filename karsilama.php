<?php
require_once __DIR__ . '/Autoloader.php';

use App\Model\PersonelKarsilamaModel;
use App\Model\PersonelModel;
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
        $per = (new PersonelModel())->find((int) $kayit->personel_id);
        $mevcutIban = strtoupper(preg_replace('/\s+/', '', (string) ($per->iban_numarasi ?? '')));
        $ibanDurum = $mevcutIban === '' ? 'bos' : (!empty($kayit->iban_girildi_tarihi) ? 'duzenlenebilir' : 'kilitli');
    }
} catch (Throwable $ex) {
    error_log('karsilama.php hatası: ' . $ex->getMessage());
    $kayit = null;
}

if (!$kayit) {
    http_response_code(404);
}

$maddeler = [];
$yuklemeler = [];
if ($kayit) {
    $maddeler = PersonelKarsilamaModel::maddeleriAyir((string) $sablon['evraklar']);
    foreach ($Karsilama->yuklemeler((int) $kayit->id) as $y) {
        $yuklemeler[(int) $y->madde_index][] = $y;
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
        .bilgi { background: var(--brand-soft); color: var(--ink); border-radius: 12px; padding: 10px 12px; font-size: 13px; margin-bottom: 8px; }
        .rozet { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 99px; margin-top: 4px; }
        .rozet.elden { background: #fff1d6; color: #8a5a00; }
        @media (prefers-color-scheme: dark) { .rozet.elden { background: #3a2c10; color: #f5c26b; } }
        .item.dijital { display: flex; gap: 12px; align-items: flex-start; padding: 12px 2px; }
        .box.durum.tamam { background: var(--ok); border-color: var(--ok); color: #fff; }
        .dosyalar { display: block; margin-top: 8px; }
        .dosya { display: flex; justify-content: space-between; align-items: center; gap: 8px; background: var(--ok-soft); border-radius: 10px; padding: 6px 10px; font-size: 13px; margin-bottom: 6px; }
        .kaldir { border: 0; background: transparent; color: #dc3545; font-size: 13px; font-weight: 600; min-height: 36px; padding: 0 6px; cursor: pointer; }
        .yukle-btn { display: inline-flex; align-items: center; gap: 8px; margin-top: 8px; min-height: 44px; padding: 0 16px; border: 1.5px solid var(--brand); background: transparent; color: var(--brand); border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .yukle-btn:disabled { opacity: .55; cursor: progress; }
        .hata-mesaj { display: block; color: #dc3545; font-size: 13px; margin-top: 6px; }
        .hata-mesaj:empty { display: none; }
        .iban-blok { margin-top: 14px; }
        .iban-baslik { font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
        .iban-kayitli { background: var(--ok-soft); border-radius: 12px; padding: 10px 12px; font-size: 14px; margin-bottom: 10px; }
        .iban-kayitli .not { display: block; font-size: 12.5px; color: var(--muted); margin-top: 2px; }
        .rozet.tamam { background: var(--ok); color: #fff; margin-left: 6px; margin-top: 0; }
        .alan { width: 100%; min-height: 48px; border: 1.5px solid var(--line); border-radius: 12px; background: var(--card); color: var(--ink); font: 600 15px/1.2 ui-monospace, SFMono-Regular, Menlo, monospace; padding: 0 12px; letter-spacing: .01em; }
        .alan:focus { outline: 3px solid var(--brand-soft); border-color: var(--brand); }
        .alan.gecersiz { border-color: #dc3545; }
        .onay { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; margin: 12px 0 4px; min-height: 44px; cursor: pointer; }
        .onay input { width: 22px; height: 22px; flex: none; margin-top: 1px; accent-color: var(--ok); }
        .yukle-btn.dolu { background: var(--brand); color: #fff; width: 100%; justify-content: center; }
        .ok-mesaj { display: block; color: var(--ok); font-size: 13px; font-weight: 600; margin-top: 6px; }
        .ok-mesaj:empty { display: none; }
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
        <div class="bilgi">Elden teslim yazanlar dışındaki evrakları buradan fotoğraf çekerek veya dosya seçerek yükleyebilirsiniz (JPG, PNG, PDF &middot; en fazla 8 MB).</div>
        <ul class="list" id="evrakListe">
            <?php foreach ($maddeler as $i => $m): $dosyalar = $yuklemeler[$i] ?? []; ?>
                <li class="madde" data-i="<?= (int) $i ?>" data-elden="<?= !empty($m['elden']) ? '1' : '0' ?>">
                    <?php if (!empty($m['elden'])): ?>
                        <label class="item">
                            <input type="checkbox" class="chk" data-i="<?= (int) $i ?>">
                            <span class="box">&#10003;</span>
                            <span class="txt">
                                <span class="ad"><?= $e($m['ad']) ?></span>
                                <span class="rozet elden">Elden teslim</span>
                                <?php foreach ($m['not'] as $not): ?><span class="not"><?= $e($not) ?></span><?php endforeach; ?>
                            </span>
                        </label>
                    <?php else: ?>
                        <div class="item dijital">
                            <span class="box durum <?= $dosyalar ? 'tamam' : '' ?>">&#10003;</span>
                            <span class="txt">
                                <span class="ad"><?= $e($m['ad']) ?></span>
                                <?php foreach ($m['not'] as $not): ?><span class="not"><?= $e($not) ?></span><?php endforeach; ?>
                                <span class="dosyalar">
                                    <?php foreach ($dosyalar as $k => $d): ?>
                                        <span class="dosya" data-id="<?= (int) $d->id ?>"><span>Dosya <?= $k + 1 ?> &middot; <?= $e(number_format($d->dosya_boyutu / 1024 / 1024, 2, ',', '.')) ?> MB</span><button type="button" class="kaldir" aria-label="Dosyayı kaldır">Kaldır</button></span>
                                    <?php endforeach; ?>
                                </span>
                                <span class="hata-mesaj" role="alert"></span>
                                <button type="button" class="yukle-btn"><span class="yl">&#8679;</span> <span class="yt"><?= $dosyalar ? 'Başka Dosya Ekle' : 'Fotoğraf Çek / Dosya Yükle' ?></span></button>
                                <input type="file" class="dosya-input" accept="image/jpeg,image/png,application/pdf" hidden>
                            </span>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php $ibanDosyalar = $yuklemeler[1000] ?? []; ?>
    <div class="card" id="maasKart">
        <h2><span class="ico">&#9830;</span> Maaş Hesabı</h2>
        <?php if (trim((string) $sablon['banka_adimi']) !== ''): ?>
            <div class="adim"><?= nl2br($e($sablon['banka_adimi'])) ?></div>
        <?php endif; ?>
        <div class="iban-blok" data-durum="<?= $e($ibanDurum) ?>">
            <div class="iban-baslik">IBAN numaranız</div>
            <?php if ($ibanDurum === 'kilitli'): ?>
                <div class="iban-kayitli">Hesap bilginiz kayıtlı: <b><?= $e(PersonelKarsilamaModel::ibanMaske($mevcutIban)) ?></b><span class="not">Değişiklik için lütfen yetkili ile iletişime geçin.</span></div>
            <?php else: ?>
                <div class="iban-kayitli" id="ibanKayitli" <?= $ibanDurum === 'bos' ? 'hidden' : '' ?>>Kayıtlı IBAN: <b id="ibanMaske"><?= $ibanDurum === 'duzenlenebilir' ? $e(PersonelKarsilamaModel::ibanMaske($mevcutIban)) : '' ?></b> <span class="rozet tamam">Kaydedildi</span></div>
                <input type="text" id="ibanInput" class="alan" inputmode="text" autocapitalize="characters" autocomplete="off" spellcheck="false" maxlength="32" placeholder="TR00 0000 0000 0000 0000 0000 00" aria-label="IBAN numarası">
                <label class="onay"><input type="checkbox" id="ibanOnay"> <span>Bu hesabın <b>kendi adıma</b> açılmış olduğunu onaylıyorum.</span></label>
                <button type="button" class="yukle-btn dolu" id="ibanKaydet"><span class="yt"><?= $ibanDurum === 'bos' ? 'IBAN\'ı Kaydet' : 'IBAN\'ı Güncelle' ?></span></button>
                <span class="hata-mesaj" id="ibanHata" role="alert"></span>
                <span class="ok-mesaj" id="ibanOk" role="status"></span>
            <?php endif; ?>
        </div>
        <div class="iban-baslik" style="margin-top:16px;">Hesap görseli (isteğe bağlı)</div>
        <div class="bilgi" style="margin-bottom:0;">IBAN'ınızın göründüğü banka kartı, hesap cüzdanı veya mobil bankacılık ekran görüntüsünü yükleyebilirsiniz.</div>
        <ul class="list">
            <li class="madde" data-i="iban" data-elden="0">
                <div class="item dijital">
                    <span class="box durum <?= $ibanDosyalar ? 'tamam' : '' ?>">&#10003;</span>
                    <span class="txt">
                        <span class="ad">Hesap görseli / belgesi</span>
                        <span class="dosyalar">
                            <?php foreach ($ibanDosyalar as $k => $d): ?>
                                <span class="dosya" data-id="<?= (int) $d->id ?>"><span>Dosya <?= $k + 1 ?> &middot; <?= $e(number_format($d->dosya_boyutu / 1024 / 1024, 2, ',', '.')) ?> MB</span><button type="button" class="kaldir" aria-label="Dosyayı kaldır">Kaldır</button></span>
                            <?php endforeach; ?>
                        </span>
                        <span class="hata-mesaj" role="alert"></span>
                        <button type="button" class="yukle-btn"><span class="yl">&#8679;</span> <span class="yt"><?= $ibanDosyalar ? 'Başka Dosya Ekle' : 'Fotoğraf Çek / Dosya Yükle' ?></span></button>
                        <input type="file" class="dosya-input" accept="image/jpeg,image/png,application/pdf" hidden>
                    </span>
                </div>
            </li>
        </ul>
    </div>

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
    var TOKEN = <?= json_encode((string) ($_GET['t'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var anahtar = 'karsilama_<?= substr(hash('sha256', (string) $kayit->token_hash), 0, 16) ?>';
    var kutular = document.querySelectorAll('.chk');
    var durum = {};
    try { durum = JSON.parse(localStorage.getItem(anahtar) || '{}'); } catch (e) {}

    function guncelle() {
        var n = 0, toplam = document.querySelectorAll('#evrakListe .madde').length;
        kutular.forEach(function (k) { if (k.checked) n++; });
        document.querySelectorAll('#evrakListe .madde[data-elden="0"]').forEach(function (m) {
            if (m.querySelector('.dosya')) n++;
        });
        document.getElementById('ilerleme').textContent = n + ' / ' + toplam + ' hazır';
        document.getElementById('bar').style.width = (toplam ? n * 100 / toplam : 0) + '%';
    }
    kutular.forEach(function (k) {
        k.checked = !!durum[k.dataset.i];
        k.addEventListener('change', function () {
            durum[k.dataset.i] = k.checked;
            try { localStorage.setItem(anahtar, JSON.stringify(durum)); } catch (e) {}
            guncelle();
        });
    });

    function mbBiçim(b) { return (b / 1048576).toFixed(2).replace('.', ',') + ' MB'; }

    function kucult(dosya) {
        return new Promise(function (coz) {
            if (!/^image\/(jpeg|png)$/.test(dosya.type) || !window.createImageBitmap) { return coz(dosya); }
            createImageBitmap(dosya).then(function (bmp) {
                var en = Math.max(bmp.width, bmp.height), olcek = en > 2200 ? 2200 / en : 1;
                var c = document.createElement('canvas');
                c.width = Math.round(bmp.width * olcek); c.height = Math.round(bmp.height * olcek);
                var g = c.getContext('2d');
                g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
                g.drawImage(bmp, 0, 0, c.width, c.height);
                c.toBlob(function (b) { coz(b || dosya); }, 'image/jpeg', 0.85);
            }, function () { coz(dosya); });
        });
    }

    function dosyaEkle(madde, d, no) {
        var kap = madde.querySelector('.dosyalar');
        var s = document.createElement('span'); s.className = 'dosya'; s.dataset.id = d.id;
        var t = document.createElement('span'); t.textContent = 'Dosya ' + no + ' · ' + mbBiçim(d.boyut);
        var b = document.createElement('button'); b.type = 'button'; b.className = 'kaldir'; b.textContent = 'Kaldır';
        b.setAttribute('aria-label', 'Dosyayı kaldır');
        s.appendChild(t); s.appendChild(b); kap.appendChild(s);
    }

    function maddeDurum(madde) {
        var var_ = !!madde.querySelector('.dosya');
        madde.querySelector('.box.durum').classList.toggle('tamam', var_);
        madde.querySelector('.yt').textContent = var_ ? 'Başka Dosya Ekle' : 'Fotoğraf Çek / Dosya Yükle';
        guncelle();
    }

    function hata(madde, m) { madde.querySelector('.hata-mesaj').textContent = m || ''; }

    function istek(fd) {
        return fetch('karsilama-yukle.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, mesaj: 'Sunucudan geçersiz yanıt alındı.' }; }); });
    }

    document.querySelectorAll('.madde[data-elden="0"]').forEach(function (madde) {
        var btn = madde.querySelector('.yukle-btn'), inp = madde.querySelector('.dosya-input');
        btn.addEventListener('click', function () { hata(madde, ''); inp.click(); });
        inp.addEventListener('change', function () {
            var f = inp.files && inp.files[0];
            inp.value = '';
            if (!f) return;
            if (f.size > 40 * 1048576) { return hata(madde, 'Dosya çok büyük.'); }
            var yt = madde.querySelector('.yt');
            btn.disabled = true; yt.textContent = 'Yükleniyor…';
            kucult(f).then(function (dosya) {
                var ad = dosya.type === 'application/pdf' ? 'belge.pdf' : (dosya.type === 'image/png' ? 'belge.png' : 'belge.jpg');
                if (dosya !== f) { ad = 'belge.jpg'; }
                if (dosya.size > 8388608) { throw new Error('Dosya boyutu en fazla 8 MB olabilir.'); }
                var fd = new FormData();
                fd.append('t', TOKEN); fd.append('a', 'yukle'); fd.append('madde', madde.dataset.i);
                fd.append('dosya', dosya, ad);
                return istek(fd);
            }).then(function (r) {
                if (r.ok) { dosyaEkle(madde, r.dosya, madde.querySelectorAll('.dosya').length + 1); } else { hata(madde, r.mesaj); }
            }).catch(function (e) {
                hata(madde, e && e.message ? e.message : 'Yükleme başarısız oldu. Lütfen tekrar deneyin.');
            }).then(function () { btn.disabled = false; maddeDurum(madde); });
        });
        madde.addEventListener('click', function (ev) {
            if (!ev.target.classList.contains('kaldir')) return;
            var satir = ev.target.closest('.dosya');
            if (!window.confirm('Bu dosya kaldırılsın mı?')) return;
            var fd = new FormData();
            fd.append('t', TOKEN); fd.append('a', 'sil'); fd.append('id', satir.dataset.id);
            istek(fd).then(function (r) {
                if (r.ok) {
                    satir.remove();
                    madde.querySelectorAll('.dosya > span:first-child').forEach(function (el, i) { el.textContent = el.textContent.replace(/^Dosya \d+/, 'Dosya ' + (i + 1)); });
                    maddeDurum(madde);
                } else { hata(madde, r.mesaj); }
            });
        });
    });


    var ibanInput = document.getElementById('ibanInput');
    if (ibanInput) {
        var ibanOk = function (t) {
            if (!/^TR\d{24}$/.test(t)) return false;
            var r = t.slice(4) + t.slice(0, 4), n = '';
            for (var i = 0; i < r.length; i++) { var c = r.charCodeAt(i); n += (c >= 65 && c <= 90) ? (c - 55) : r.charAt(i); }
            var m = 0;
            for (var j = 0; j < n.length; j += 7) { m = parseInt(String(m) + n.substr(j, 7), 10) % 97; }
            return m === 1;
        };
        var temiz = function () { return ibanInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase(); };
        ibanInput.addEventListener('input', function () {
            var t = temiz();
            if (t && /^\d/.test(t)) { t = 'TR' + t; }
            t = t.slice(0, 26);
            ibanInput.value = t.replace(/(.{4})/g, '$1 ').trim();
            ibanInput.classList.toggle('gecersiz', t.length === 26 && !ibanOk(t));
        });
        var kaydet = document.getElementById('ibanKaydet'), hataEl = document.getElementById('ibanHata'), okEl = document.getElementById('ibanOk');
        kaydet.addEventListener('click', function () {
            hataEl.textContent = ''; okEl.textContent = '';
            var t = temiz();
            if (!ibanOk(t)) { hataEl.textContent = 'Geçerli bir IBAN girin (TR ile başlayan 26 karakter).'; ibanInput.classList.add('gecersiz'); return; }
            if (!document.getElementById('ibanOnay').checked) { hataEl.textContent = 'Hesabın size ait olduğunu onaylamanız gerekir.'; return; }
            kaydet.disabled = true;
            var fd = new FormData();
            fd.append('t', TOKEN); fd.append('a', 'iban'); fd.append('iban', t); fd.append('onay', '1');
            istek(fd).then(function (r) {
                if (r.ok) {
                    document.getElementById('ibanMaske').textContent = r.maske;
                    document.getElementById('ibanKayitli').hidden = false;
                    kaydet.querySelector('.yt').textContent = 'IBAN\'ı Güncelle';
                    ibanInput.value = '';
                    document.getElementById('ibanOnay').checked = false;
                    okEl.textContent = 'IBAN numaranız kaydedildi' + (r.banka ? ' (' + r.banka + ')' : '') + '.';
                } else { hataEl.textContent = r.mesaj; }
            }).catch(function () { hataEl.textContent = 'Kaydedilemedi. Lütfen tekrar deneyin.'; })
              .then(function () { kaydet.disabled = false; });
        });
    }

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
