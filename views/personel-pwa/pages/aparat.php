<?php
/**
 * Personel PWA - Aparat Takip
 * Kesme/açma saha kaydı, ekip stoğu ve ekipler arası aparat transferi.
 */

use App\Model\AparatStokModel;
use App\Model\AparatTipiModel;
use App\Model\KesmeAcmaIslemModel;

$AparatStok = new AparatStokModel();
$AparatTip = new AparatTipiModel();

$aktifEkip = $AparatStok->aktifEkip((int) $personel_id);
$aparatTipleri = $AparatTip->listele(true);
$aparatDurumlari = KesmeAcmaIslemModel::APARAT_DURUMLARI;
?>

<div class="flex flex-col min-h-screen bg-slate-50 dark:bg-background-dark pb-20">

    <header class="bg-white dark:bg-card-dark px-4 pt-4 pb-3 sticky top-0 z-30 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-800 dark:text-white">Aparat Takip</h1>
                <p class="text-xs text-slate-400 mt-1" id="aparat-ekip-satiri">
                    <?= $aktifEkip ? htmlspecialchars($aktifEkip['tur_adi'], ENT_QUOTES, 'UTF-8') : 'Ekip tanımlı değil' ?>
                </p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400">Bugün</p>
                <p class="text-sm font-bold text-slate-800 dark:text-white" id="aparat-bugun-sayi">0 işlem</p>
            </div>
        </div>
    </header>

    <?php if (!$aktifEkip): ?>
        <section class="px-4 pt-6">
            <div class="bg-white dark:bg-card-dark rounded-xl p-6 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">group_off</span>
                <p class="text-sm font-bold text-slate-800 dark:text-white mt-3">Ekibiniz tanımlı değil</p>
                <p class="text-xs text-slate-400 mt-2">Aparat kaydı girebilmek için bir ekibe atanmanız gerekiyor. Şefinizle görüşün.</p>
            </div>
        </section>
    <?php elseif (empty($aparatTipleri)): ?>
        <section class="px-4 pt-6">
            <div class="bg-white dark:bg-card-dark rounded-xl p-6 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">inventory_2</span>
                <p class="text-sm font-bold text-slate-800 dark:text-white mt-3">Aparat tipi tanımlanmamış</p>
                <p class="text-xs text-slate-400 mt-2">Yönetici panelden aparat tiplerini tanımladıktan sonra kayıt girebilirsiniz.</p>
            </div>
        </section>
    <?php else: ?>

        <section id="aparat-kuyruk-serit" class="px-4 pt-4" style="display:none">
            <div class="bg-amber-50 dark:bg-slate-800 border border-amber-200 dark:border-slate-800 rounded-xl p-3 flex items-center justify-between gap-3">
                <div class="flex-1">
                    <p class="text-xs font-bold text-amber-700 dark:text-amber-400" id="aparat-kuyruk-baslik"></p>
                    <p class="text-xs text-slate-500 mt-1" id="aparat-kuyruk-alt"></p>
                </div>
                <button type="button" onclick="aparatKuyrugunuGonder()"
                    class="shrink-0 px-3 py-2 rounded-xl bg-amber-500 text-white text-xs font-bold">Şimdi Gönder</button>
            </div>
        </section>

        <section class="px-4 pt-4 mb-3">
            <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
                <button onclick="aparatSekme('yeni', this)"
                    class="aparat-tab flex-1 py-2 rounded-xl text-xs font-bold text-primary bg-white dark:bg-card-dark">Yeni</button>
                <button onclick="aparatSekme('ekibim', this)"
                    class="aparat-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500">Ekibim</button>
                <button onclick="aparatSekme('transfer', this)" id="aparat-tab-transfer"
                    class="aparat-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500 relative">
                    Transfer
                    <span id="aparat-transfer-rozet"
                        class="absolute top-0 right-0 bg-red-500 text-white text-xs font-bold rounded-full px-1"
                        style="display:none">0</span>
                </button>
                <button onclick="aparatSekme('gecmis', this)"
                    class="aparat-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500">Geçmiş</button>
            </div>
        </section>

        <!-- ============ YENİ İŞLEM ============ -->
        <section id="aparat-pane-yeni" class="px-4 flex-1">
            <form id="aparat-form" class="space-y-4">

                <div class="grid grid-cols-2 gap-3">
                    <button type="button" onclick="aparatIslemSec('kesme')" id="aparat-btn-kesme"
                        class="py-4 rounded-xl font-bold text-sm bg-red-500 text-white flex flex-col items-center gap-1">
                        <span class="material-symbols-outlined text-2xl">water_drop</span>
                        KESME
                    </button>
                    <button type="button" onclick="aparatIslemSec('acma')" id="aparat-btn-acma"
                        class="py-4 rounded-xl font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 flex flex-col items-center gap-1">
                        <span class="material-symbols-outlined text-2xl">water_full</span>
                        AÇMA
                    </button>
                </div>

                <div class="bg-white dark:bg-card-dark rounded-xl p-4 space-y-3">
                    <div>
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Abone No</label>
                        <input type="text" name="abone_no" id="aparat-abone-no" inputmode="numeric" required
                            class="w-full mt-1 px-3 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-base font-semibold text-slate-800 dark:text-white">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Sayaç No</label>
                        <input type="text" name="sayac_no" id="aparat-sayac-no"
                            class="w-full mt-1 px-3 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-base font-semibold text-slate-800 dark:text-white">
                    </div>
                </div>

                <div class="bg-white dark:bg-card-dark rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Aparat Tipi</label>
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-500">
                            <input type="checkbox" id="aparat-yok" class="rounded" style="width:1rem;height:1rem">
                            Aparat kullanılmadı
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-2" id="aparat-tip-listesi">
                        <?php foreach ($aparatTipleri as $tip): ?>
                            <button type="button" class="aparat-tip-btn py-3 px-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 flex flex-col items-center gap-1"
                                data-id="<?= (int) $tip['id'] ?>" onclick="aparatTipSec(this)">
                                <span><?= htmlspecialchars($tip['ad'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-xs font-normal aparat-tip-stok" data-stok-id="<?= (int) $tip['id'] ?>">-</span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex items-center justify-between mt-4" id="aparat-adet-satiri">
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Adet</span>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="aparatAdet(-1)"
                                class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold">−</button>
                            <span class="text-lg font-bold text-slate-800 dark:text-white w-8 text-center" id="aparat-adet">1</span>
                            <button type="button" onclick="aparatAdet(1)"
                                class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold">+</button>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-card-dark rounded-xl p-4" id="aparat-durum-kutusu" style="display:none">
                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Aparat geri alındı mı?</label>
                    <div class="space-y-2 mt-2">
                        <?php $ilk = true; foreach ($aparatDurumlari as $anahtar => $etiket): ?>
                            <button type="button" class="aparat-durum-btn py-3 rounded-xl text-xs font-bold <?= $ilk ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' ?>"
                                data-durum="<?= htmlspecialchars($anahtar, ENT_QUOTES, 'UTF-8') ?>" onclick="aparatDurumSec(this)">
                                <?= htmlspecialchars($etiket, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php $ilk = false; endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="bg-white dark:bg-card-dark rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" id="aparat-sayac-foto-kutu">
                        <span class="material-symbols-outlined text-3xl text-slate-400">speed</span>
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Sayaç Fotoğrafı</span>
                        <span class="text-xs text-slate-400" id="aparat-sayac-foto-durum">Zorunlu</span>
                        <input type="file" id="aparat-sayac-foto" accept="image/*" capture="environment" class="hidden">
                    </label>
                    <label class="bg-white dark:bg-card-dark rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" id="aparat-foto-kutu">
                        <span class="material-symbols-outlined text-3xl text-slate-400">build</span>
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Aparat Fotoğrafı</span>
                        <span class="text-xs text-slate-400" id="aparat-foto-durum">Zorunlu</span>
                        <input type="file" id="aparat-foto" accept="image/*" capture="environment" class="hidden">
                    </label>
                </div>

                <div class="bg-white dark:bg-card-dark rounded-xl p-4">
                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Açıklama</label>
                    <textarea name="aciklama" id="aparat-aciklama" rows="2"
                        class="w-full mt-1 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-sm text-slate-800 dark:text-white"></textarea>
                </div>

                <button type="submit" id="aparat-submit-btn"
                    class="w-full py-4 rounded-xl bg-primary text-white font-bold text-sm">
                    <span id="aparat-submit-text">KAYDET</span>
                </button>
            </form>
        </section>

        <!-- ============ EKİBİM ============ -->
        <section id="aparat-pane-ekibim" class="px-4 flex-1" style="display:none">
            <div class="space-y-3" id="aparat-stok-listesi">
                <div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>
            </div>
        </section>

        <!-- ============ TRANSFER ============ -->
        <section id="aparat-pane-transfer" class="px-4 flex-1" style="display:none">
            <button type="button" onclick="aparatTransferModalAc()"
                class="w-full py-3 rounded-xl bg-primary text-white font-bold text-sm mb-4 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">send</span>
                Aparat Gönder
            </button>
            <div class="space-y-3" id="aparat-transfer-listesi">
                <div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>
            </div>
        </section>

        <!-- ============ GEÇMİŞ ============ -->
        <section id="aparat-pane-gecmis" class="px-4 flex-1" style="display:none">
            <div class="space-y-3" id="aparat-gecmis-listesi">
                <div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>
            </div>
        </section>

        <!-- ============ TRANSFER MODALI ============ -->
        <div id="aparat-transfer-modal" class="fixed inset-0 z-50 bg-black/50 items-end justify-center" style="display:none">
            <div class="bg-white dark:bg-card-dark w-full rounded-t-xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-800 dark:text-white">Aparat Gönder</h2>
                    <button type="button" onclick="aparatTransferModalKapat()" class="text-slate-400">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Alan Ekip</label>
                    <select id="aparat-transfer-ekip"
                        class="w-full mt-1 px-3 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-sm font-semibold text-slate-800 dark:text-white"></select>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Aparat Tipi</label>
                    <select id="aparat-transfer-tip"
                        class="w-full mt-1 px-3 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                        <?php foreach ($aparatTipleri as $tip): ?>
                            <option value="<?= (int) $tip['id'] ?>"><?= htmlspecialchars($tip['ad'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Adet</label>
                    <input type="number" id="aparat-transfer-adet" min="1" value="1"
                        class="w-full mt-1 px-3 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-base font-semibold text-slate-800 dark:text-white">
                </div>

                <button type="button" onclick="aparatTransferGonder()" id="aparat-transfer-btn"
                    class="w-full py-3 rounded-xl bg-primary text-white font-bold text-sm">GÖNDER</button>
                <p class="text-xs text-slate-400 text-center">Aparatlar karşı ekip onayladığında stoğunuzdan düşer.</p>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php if ($aktifEkip && !empty($aparatTipleri)): ?>
<script>
    (function () {
        'use strict';

        const APARAT_REF = 'aparat-referans';
        let aparatIslemTipi = 'kesme';
        let aparatTipId = 0;
        let aparatDurumu = 'alindi';
        let aparatAdedi = 1;
        let aparatSayacDosya = null;
        let aparatDosya = null;
        let aparatBilgi = null;
        let aparatKonum = null;

        function cevrimici() {
            return !window.OfflineQueue || OfflineQueue.cevrimici();
        }

        function kacir(deger) {
            const d = document.createElement('div');
            d.textContent = deger === null || deger === undefined ? '' : deger;
            return d.innerHTML;
        }

        // ----- Sekmeler -----
        window.aparatSekme = function (ad, btn) {
            ['yeni', 'ekibim', 'transfer', 'gecmis'].forEach(function (s) {
                const el = document.getElementById('aparat-pane-' + s);
                if (el) el.style.display = s === ad ? '' : 'none';
            });

            document.querySelectorAll('.aparat-tab').forEach(function (t) {
                const ekstra = t.id === 'aparat-tab-transfer' ? ' relative' : '';
                t.className = 'aparat-tab flex-1 py-2 rounded-xl text-xs font-bold '
                    + (t === btn ? 'text-primary bg-white dark:bg-card-dark' : 'text-slate-500')
                    + ekstra;
            });

            if (ad === 'ekibim') aparatStokYukle();
            if (ad === 'transfer') aparatTransferleriYukle();
            if (ad === 'gecmis') aparatGecmisYukle();
        };

        // ----- Form durumu -----
        window.aparatIslemSec = function (tip) {
            aparatIslemTipi = tip;

            const kesme = document.getElementById('aparat-btn-kesme');
            const acma = document.getElementById('aparat-btn-acma');
            const pasif = 'py-4 rounded-xl font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 flex flex-col items-center gap-1';

            kesme.className = tip === 'kesme'
                ? 'py-4 rounded-xl font-bold text-sm bg-red-500 text-white flex flex-col items-center gap-1'
                : pasif;
            acma.className = tip === 'acma'
                ? 'py-4 rounded-xl font-bold text-sm bg-emerald-500 text-white flex flex-col items-center gap-1'
                : pasif;

            aparatDurumKutusu();
        };

        function aparatDurumKutusu() {
            const goster = aparatIslemTipi === 'acma' && !document.getElementById('aparat-yok').checked;
            document.getElementById('aparat-durum-kutusu').style.display = goster ? '' : 'none';
        }

        window.aparatTipSec = function (btn) {
            aparatTipId = parseInt(btn.dataset.id, 10);

            document.querySelectorAll('.aparat-tip-btn').forEach(function (b) {
                b.className = 'aparat-tip-btn py-3 px-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 flex flex-col items-center gap-1';
            });

            btn.className = 'aparat-tip-btn py-3 px-2 rounded-xl text-xs font-bold bg-primary text-white flex flex-col items-center gap-1';
        };

        window.aparatDurumSec = function (btn) {
            aparatDurumu = btn.dataset.durum;

            document.querySelectorAll('.aparat-durum-btn').forEach(function (b) {
                b.className = 'aparat-durum-btn py-3 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-500';
            });

            btn.className = 'aparat-durum-btn py-3 rounded-xl text-xs font-bold bg-primary text-white';
        };

        window.aparatAdet = function (fark) {
            aparatAdedi = Math.max(1, aparatAdedi + fark);
            document.getElementById('aparat-adet').textContent = aparatAdedi;
        };

        document.getElementById('aparat-yok').addEventListener('change', function () {
            const kapali = this.checked;
            document.getElementById('aparat-tip-listesi').style.opacity = kapali ? '0.4' : '1';
            document.getElementById('aparat-adet-satiri').style.display = kapali ? 'none' : '';
            document.getElementById('aparat-foto-kutu').style.display = kapali ? 'none' : '';
            aparatDurumKutusu();
        });

        // ----- Fotoğraflar -----
        function fotoBagla(inputId, durumId, atayici) {
            const input = document.getElementById(inputId);
            input.addEventListener('change', function () {
                const dosya = this.files[0];
                atayici(dosya || null);
                document.getElementById(durumId).textContent = dosya ? 'Eklendi ✓' : 'Zorunlu';
            });
        }

        fotoBagla('aparat-sayac-foto', 'aparat-sayac-foto-durum', function (d) { aparatSayacDosya = d; });
        fotoBagla('aparat-foto', 'aparat-foto-durum', function (d) { aparatDosya = d; });

        // ----- Konum -----
        function konumAl() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(function (p) {
                aparatKonum = { enlem: p.coords.latitude.toFixed(7), boylam: p.coords.longitude.toFixed(7) };
            }, function () { }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
        }

        // ----- Referans veri -----
        async function aparatBilgiYukle() {
            if (!cevrimici() && window.OfflineQueue) {
                const saklanan = await OfflineQueue.referansOku(APARAT_REF);
                if (saklanan) {
                    aparatBilgi = saklanan;
                    aparatBilgiUygula();
                    return;
                }
            }

            try {
                const res = await (await fetch('api.php?action=getAparatBilgi', { credentials: 'same-origin' })).json();
                if (!res.success) return;

                aparatBilgi = res.data;
                aparatBilgiUygula();

                if (window.OfflineQueue) {
                    OfflineQueue.referansKaydet(APARAT_REF, res.data);
                }
            } catch (e) {
                console.error('Aparat bilgisi alınamadı:', e);
            }
        }

        function aparatBilgiUygula() {
            if (!aparatBilgi) return;

            (aparatBilgi.tipler || []).forEach(function (t) {
                const el = document.querySelector(`[data-stok-id="${t.id}"]`);
                if (el) el.textContent = t.adet + ' adet';
            });

            const rozet = document.getElementById('aparat-transfer-rozet');
            const bekleyen = aparatBilgi.bekleyen_transfer || 0;
            rozet.textContent = bekleyen;
            rozet.style.display = bekleyen > 0 ? '' : 'none';

            const secim = document.getElementById('aparat-transfer-ekip');
            if (secim && !secim.options.length) {
                (aparatBilgi.ekipler || []).forEach(function (e) {
                    const opt = document.createElement('option');
                    opt.value = e.id;
                    opt.textContent = e.ad;
                    secim.appendChild(opt);
                });
            }
        }

        // ----- Ekip stoğu -----
        function aparatStokYukle() {
            const kutu = document.getElementById('aparat-stok-listesi');
            if (!aparatBilgi) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Bilgi yüklenemedi.</div>';
                return;
            }

            let html = `<div class="bg-white dark:bg-card-dark rounded-xl p-4 mb-3">
                <p class="text-xs text-slate-400">Ekip</p>
                <p class="text-base font-bold text-slate-800 dark:text-white">${kacir(aparatBilgi.ekip.ad)}</p>
            </div>`;

            (aparatBilgi.tipler || []).forEach(function (t) {
                const negatif = t.adet < 0;
                html += `<div class="bg-white dark:bg-card-dark rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-slate-800 dark:text-white">${kacir(t.ad)}</p>
                        <p class="text-xs text-slate-400 mt-1">${kacir(t.kod)}</p>
                    </div>
                    <p class="text-xl font-bold ${negatif ? 'text-red-500' : 'text-primary'}">${t.adet}</p>
                </div>`;
            });

            kutu.innerHTML = html;
        }

        // ----- Transferler -----
        async function aparatTransferleriYukle() {
            const kutu = document.getElementById('aparat-transfer-listesi');

            if (!cevrimici()) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Transferleri görmek için bağlantı gerekli.</div>';
                return;
            }

            try {
                const res = await (await fetch('api.php?action=getAparatTransferler', { credentials: 'same-origin' })).json();
                if (!res.success) {
                    kutu.innerHTML = `<div class="text-center py-10 text-sm text-slate-400">${kacir(res.message)}</div>`;
                    return;
                }

                const liste = res.data.transferler || [];
                if (!liste.length) {
                    kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Transfer kaydı yok.</div>';
                    return;
                }

                let html = '';
                liste.forEach(function (t) {
                    const durumRenk = t.durum === 'beklemede' ? 'text-amber-600'
                        : (t.durum === 'onaylandi' ? 'text-emerald-600' : 'text-red-500');
                    const durumMetin = t.durum === 'beklemede' ? 'Bekliyor'
                        : (t.durum === 'onaylandi' ? 'Onaylandı' : (t.durum === 'reddedildi' ? 'Reddedildi' : 'İptal'));

                    html += `<div class="bg-white dark:bg-card-dark rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-slate-800 dark:text-white">
                                    ${t.yon === 'gelen' ? 'Gelen' : 'Giden'} · ${kacir(t.karsi_ekip)}
                                </p>
                                <p class="text-xs text-slate-400 mt-1">${kacir(t.aparat_adi)} · ${t.adet} adet</p>
                            </div>
                            <span class="text-xs font-bold ${durumRenk}">${durumMetin}</span>
                        </div>`;

                    if (t.onaylanabilir) {
                        html += `<div class="grid grid-cols-2 gap-2 mt-3">
                            <button type="button" onclick="aparatTransferYanit(${t.id}, 'onayla')"
                                class="py-2 rounded-xl bg-emerald-500 text-white text-xs font-bold">Onayla</button>
                            <button type="button" onclick="aparatTransferYanit(${t.id}, 'reddet')"
                                class="py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-bold">Reddet</button>
                        </div>`;
                    }

                    if (t.red_nedeni) {
                        html += `<p class="text-xs text-slate-400 mt-2">${kacir(t.red_nedeni)}</p>`;
                    }

                    html += '</div>';
                });

                kutu.innerHTML = html;
            } catch (e) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Transferler yüklenemedi.</div>';
            }
        }

        window.aparatTransferYanit = async function (id, karar) {
            if (karar === 'reddet') {
                const neden = prompt('Red gerekçesi:');
                if (!neden || !neden.trim()) return;

                await aparatTransferIstek({ id: id, karar: 'reddet', red_nedeni: neden.trim() });
                return;
            }

            const onay = await Alert.confirm('Transfer Onayı', 'Aparatlar ekibinizin stoğuna eklenecek. Onaylıyor musunuz?');
            if (!onay) return;

            await aparatTransferIstek({ id: id, karar: 'onayla' });
        };

        async function aparatTransferIstek(veri) {
            const fd = new FormData();
            fd.append('action', 'cevaplaAparatTransfer');
            Object.keys(veri).forEach(function (k) { fd.append(k, veri[k]); });

            try {
                const res = await (await fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin' })).json();
                if (!res.success) return Alert.error('Hata', res.message || 'İşlem tamamlanamadı.');

                Alert.success('Tamam', res.message);
                await aparatBilgiYukle();
                aparatTransferleriYukle();
            } catch (e) {
                Alert.error('Hata', 'Sunucuya ulaşılamadı.');
            }
        }

        window.aparatTransferModalAc = function () {
            document.getElementById('aparat-transfer-modal').style.display = 'flex';
        };

        window.aparatTransferModalKapat = function () {
            document.getElementById('aparat-transfer-modal').style.display = 'none';
        };

        window.aparatTransferGonder = async function () {
            const ekip = document.getElementById('aparat-transfer-ekip').value;
            const tip = document.getElementById('aparat-transfer-tip').value;
            const adet = parseInt(document.getElementById('aparat-transfer-adet').value, 10);

            if (!ekip) return Alert.warning('Ekip Seçin', 'Aparatı göndereceğiniz ekibi seçin.');
            if (!adet || adet < 1) return Alert.warning('Adet Hatalı', 'En az 1 adet girmelisiniz.');

            const btn = document.getElementById('aparat-transfer-btn');
            btn.disabled = true;

            const fd = new FormData();
            fd.append('action', 'saveAparatTransfer');
            fd.append('alan_ekip_id', ekip);
            fd.append('aparat_tip_id', tip);
            fd.append('adet', adet);

            try {
                const res = await (await fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin' })).json();
                if (!res.success) return Alert.error('Gönderilemedi', res.message || 'İşlem başarısız.');

                aparatTransferModalKapat();
                Alert.success('Gönderildi', res.message);
                aparatTransferleriYukle();
            } catch (e) {
                Alert.error('Hata', 'Sunucuya ulaşılamadı.');
            } finally {
                btn.disabled = false;
            }
        };

        // ----- Geçmiş -----
        async function aparatGecmisYukle() {
            const kutu = document.getElementById('aparat-gecmis-listesi');

            if (!cevrimici()) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Geçmişi görmek için bağlantı gerekli.</div>';
                return;
            }

            try {
                const res = await (await fetch('api.php?action=getAparatSonIslemler&gun=7', { credentials: 'same-origin' })).json();
                if (!res.success) return;

                const liste = res.data.islemler || [];
                if (!liste.length) {
                    kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Son 7 günde kaydınız yok.</div>';
                    return;
                }

                let html = '';
                liste.forEach(function (k) {
                    const kesme = k.islem_tipi === 'kesme';
                    html += `<div class="bg-white dark:bg-card-dark rounded-xl p-4 flex items-center justify-between ${k.durum === 'iptal' ? 'opacity-50' : ''}">
                        <div>
                            <p class="text-sm font-bold ${kesme ? 'text-red-500' : 'text-emerald-600'}">
                                ${kesme ? 'Kesme' : 'Açma'} · ${kacir(k.abone_no || '-')}
                            </p>
                            <p class="text-xs text-slate-400 mt-1">${kacir(k.aparat_adi || '-')} · ${k.adet} adet</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400">${(k.tarih || '').split('-').reverse().join('.')}</p>
                            <p class="text-xs text-slate-400">${kacir(k.saat)}</p>
                            ${k.negatif_stok == 1 ? '<p class="text-xs font-bold text-red-500">negatif stok</p>' : ''}
                        </div>
                    </div>`;
                });

                kutu.innerHTML = html;
                document.getElementById('aparat-bugun-sayi').textContent =
                    liste.filter(function (k) { return k.tarih === new Date().toISOString().slice(0, 10); }).length + ' işlem';
            } catch (e) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Geçmiş yüklenemedi.</div>';
            }
        }

        // ----- Çevrimdışı kuyruk şeridi -----
        async function aparatKuyrukDurumu() {
            if (!window.OfflineQueue) return;

            const tumu = await OfflineQueue.listele();
            const bizim = tumu.filter(function (k) { return k.action === 'saveAparatIslem'; });
            const serit = document.getElementById('aparat-kuyruk-serit');

            if (!bizim.length) {
                serit.style.display = 'none';
                return;
            }

            const hatali = bizim.filter(function (k) { return k.durum === 'hata'; }).length;
            serit.style.display = '';
            document.getElementById('aparat-kuyruk-baslik').textContent =
                bizim.length + ' kayıt gönderilmeyi bekliyor';
            document.getElementById('aparat-kuyruk-alt').textContent = hatali > 0
                ? hatali + ' kayıt sunucu tarafından kabul edilmedi.'
                : 'Bağlantı geldiğinde otomatik gönderilecek.';
        }

        window.aparatKuyrugunuGonder = async function () {
            if (!window.OfflineQueue) return;
            await OfflineQueue.flush({ elle: true });
            await aparatKuyrukDurumu();
            await aparatBilgiYukle();
        };

        window.addEventListener('kuyruk-degisti', function () {
            aparatKuyrukDurumu();
            aparatBilgiYukle();
        });

        // ----- Kayıt gönderimi -----
        document.getElementById('aparat-form').addEventListener('submit', async function (e) {
            e.preventDefault();

            const aparatsiz = document.getElementById('aparat-yok').checked;
            const aboneNo = document.getElementById('aparat-abone-no').value.trim();

            if (!aboneNo) return Alert.warning('Abone No Gerekli', 'Abone numarasını girin.');
            if (!aparatsiz && !aparatTipId) return Alert.warning('Aparat Seçin', 'Kullanılan aparat tipini seçin.');
            if (!aparatSayacDosya) return Alert.warning('Fotoğraf Gerekli', 'Sayaç fotoğrafı zorunludur.');
            if (!aparatsiz && !aparatDosya) return Alert.warning('Fotoğraf Gerekli', 'Aparat fotoğrafı zorunludur.');
            if (!window.OfflineQueue) return Alert.error('Hata', 'Uygulama bileşenleri yüklenemedi, sayfayı yenileyin.');

            const btn = document.getElementById('aparat-submit-btn');
            const btnText = document.getElementById('aparat-submit-text');
            btn.disabled = true;
            btnText.textContent = 'HAZIRLANIYOR...';

            try {
                const alanlar = {
                    islem_tipi: aparatIslemTipi,
                    abone_no: aboneNo,
                    sayac_no: document.getElementById('aparat-sayac-no').value.trim(),
                    aparat_tip_id: aparatsiz ? 0 : aparatTipId,
                    adet: aparatsiz ? 1 : aparatAdedi,
                    aparatsiz: aparatsiz ? 1 : 0,
                    aparat_durumu: aparatIslemTipi === 'acma' ? aparatDurumu : '',
                    aciklama: document.getElementById('aparat-aciklama').value.trim(),
                };

                if (aparatKonum) {
                    alanlar.enlem = aparatKonum.enlem;
                    alanlar.boylam = aparatKonum.boylam;
                }

                const sayac = await OfflineQueue.fotografKucult(aparatSayacDosya, 1600, 0.7);
                const dosyalar = [{ alan: 'sayac_foto', ad: sayac.ad, tip: sayac.tip, blob: sayac.blob }];

                const ekDosyalar = [];
                if (!aparatsiz && aparatDosya) {
                    const ap = await OfflineQueue.fotografKucult(aparatDosya, 1600, 0.7);
                    ekDosyalar.push({ ad: ap.ad, tip: ap.tip, blob: ap.blob });
                }

                const ozet = {
                    islem_tipi: aparatIslemTipi,
                    abone_no: aboneNo,
                    adet: alanlar.adet,
                };

                btnText.textContent = 'GÖNDERİLİYOR...';

                await OfflineQueue.ekle('saveAparatIslem', alanlar, dosyalar, ozet, {
                    action: 'addAparatFoto',
                    alan: 'foto',
                    dosyalar: ekDosyalar,
                });

                const sonuc = await OfflineQueue.flush({ elle: true });
                aparatFormuSifirla();
                await aparatBilgiYukle();
                await aparatKuyrukDurumu();

                if (sonuc && sonuc.gonderildi > 0) {
                    Alert.success('Kaydedildi', 'İşlem sunucuya iletildi.');
                } else {
                    Alert.success('Kuyruğa Alındı', 'Kayıt telefonunuza yazıldı, bağlantı gelince gönderilecek.');
                }
            } catch (hata) {
                console.error('Aparat kaydı hatası:', hata);
                Alert.error('Kaydedilemedi', 'Kayıt telefona yazılamadı. Cihazınızda yer açıp tekrar deneyin.');
            } finally {
                btn.disabled = false;
                btnText.textContent = 'KAYDET';
            }
        });

        function aparatFormuSifirla() {
            document.getElementById('aparat-abone-no').value = '';
            document.getElementById('aparat-sayac-no').value = '';
            document.getElementById('aparat-aciklama').value = '';
            document.getElementById('aparat-sayac-foto').value = '';
            document.getElementById('aparat-foto').value = '';
            document.getElementById('aparat-sayac-foto-durum').textContent = 'Zorunlu';
            document.getElementById('aparat-foto-durum').textContent = 'Zorunlu';
            aparatSayacDosya = null;
            aparatDosya = null;
            aparatAdedi = 1;
            document.getElementById('aparat-adet').textContent = '1';
            konumAl();
        }

        // OfflineQueue ve Alert sayfa gövdesinden sonra yükleniyor; ilk çağrılar
        // DOM hazır olduğunda yapılır ki kuyruk şeridi ilk açılışta da dolsun.
        document.addEventListener('DOMContentLoaded', function () {
            konumAl();
            aparatBilgiYukle();
            aparatKuyrukDurumu();
        });
    })();
</script>
<?php endif; ?>
