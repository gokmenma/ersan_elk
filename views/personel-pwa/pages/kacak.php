<?php
/**
 * Personel PWA - Kaçak İşlemleri
 * Kaçak/Abonesiz tutanak bildirimi ve takibi
 */

use App\Model\KacakKontrolModel;

$KacakModel = new KacakKontrolModel();
$ekipAdaylari = $KacakModel->getEkipAdaylari($personel_id);
$ilceler = KacakKontrolModel::ILCELER;
$maxSahaFoto = KacakKontrolModel::MAX_SAHA_FOTO;
?>

<div class="flex flex-col min-h-screen bg-slate-50 dark:bg-background-dark pb-20">

    <!-- Minimal başlık -->
    <header
        class="bg-white dark:bg-card-dark px-4 pt-4 pb-3 sticky top-0 z-30 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-800 dark:text-white">Kaçak İşlemleri</h1>
                <p class="text-xs text-slate-400 mt-1" id="kacak-ozet-satiri">Yükleniyor...</p>
            </div>
            <button onclick="openKacakBildirModal()"
                class="flex items-center gap-1 bg-primary text-white px-4 py-2 rounded-xl font-bold text-sm">
                <span class="material-symbols-outlined text-lg">add</span>
                <span>Bildir</span>
            </button>
        </div>
    </header>

    <!-- Dönem -->
    <section class="px-4 pt-4">
        <div class="flex items-center gap-2">
            <input type="date" id="kacak-bas" class="flex-1 px-3 py-2 rounded-xl bg-white dark:bg-card-dark
                border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-800 dark:text-white">
            <input type="date" id="kacak-bit" class="flex-1 px-3 py-2 rounded-xl bg-white dark:bg-card-dark
                border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-800 dark:text-white">
            <button onclick="loadKacakKayitlar()"
                class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center">
                <span class="material-symbols-outlined text-lg">search</span>
            </button>
        </div>
    </section>

    <!-- Filtre sekmeleri -->
    <section class="px-4 pt-4 mb-3">
        <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
            <button onclick="filterKacak('all', this)"
                class="kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-primary bg-white dark:bg-card-dark">Tümü</button>
            <button onclick="filterKacak('beklemede', this)"
                class="kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500">Bekleyen</button>
            <button onclick="filterKacak('onaylandi', this)"
                class="kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500">Onaylı</button>
            <button onclick="filterKacak('reddedildi', this)"
                class="kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500">Red</button>
        </div>
    </section>

    <!-- Liste -->
    <section class="px-4 flex-1">
        <div id="kacak-list" class="space-y-3">
            <div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>
        </div>
    </section>
</div>

<!-- ============ BİLDİRİM MODALI ============ -->
<div id="kacak-bildir-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content"
        style="display:flex !important; flex-direction:column !important; max-height:90vh !important; overflow:hidden !important; padding:0 !important;">

        <div
            class="px-6 pt-3 pb-2 bg-white dark:bg-card-dark z-10 border-b border-slate-200 dark:border-slate-800">
            <div class="modal-handle mb-4"></div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Kaçak Tutanağı Bildir</h3>
                <button onclick="Modal.close('kacak-bildir-modal')"
                    class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-500">close</span>
                </button>
            </div>
        </div>

        <form id="kacak-bildir-form" class="p-6 pt-4 overflow-y-auto flex-1 space-y-4"
            style="overscroll-behavior-y: contain;">

            <!-- Tutanak + AI -->
            <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-bold text-slate-500 uppercase">Tutanak Fotoğrafı</span>
                    <span class="text-xs text-slate-400">Zorunlu</span>
                </div>

                <input type="file" id="kacak-tutanak-input" accept="image/*,application/pdf" style="display:none">

                <div class="flex gap-2">
                    <button type="button" onclick="document.getElementById('kacak-tutanak-input').click()"
                        class="flex-1 py-2 rounded-xl bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold">
                        <span id="kacak-tutanak-label">Fotoğraf Seç</span>
                    </button>
                    <button type="button" id="kacak-analiz-btn" onclick="analizEtKacak()" disabled
                        class="flex-1 py-2 rounded-xl bg-amber-500 text-white text-xs font-black">
                        Yapay Zeka ile Oku
                    </button>
                </div>

                <div id="kacak-tutanak-preview" class="mt-3" style="display:none">
                    <img class="w-full rounded-xl border border-slate-200" style="max-height:12rem;object-fit:contain"
                        alt="Tutanak">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Tarih</label>
                <input type="date" name="tarih" required value="<?= date('Y-m-d') ?>"
                    class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Ekip Arkadaşınız</label>
                <select name="ekip_arkadasi_id" id="kacak-ekip-arkadasi" required
                    class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                    <option value="">Seçiniz...</option>
                    <?php foreach ($ekipAdaylari as $aday): ?>
                        <option value="<?= (int) $aday['id'] ?>">
                            <?= htmlspecialchars($aday['adi_soyadi'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-400 mt-1">Kaçak ekipleri 2 kişiden oluşur.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">İlçe</label>
                    <select name="ilce" id="kacak-ilce" required
                        class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                        <option value="">Seçiniz...</option>
                        <?php foreach ($ilceler as $ilce): ?>
                            <option value="<?= htmlspecialchars($ilce, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($ilce, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Tür</label>
                    <select name="tur" id="kacak-tur" required
                        class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                        <?php foreach (KacakKontrolModel::TURLER as $tur): ?>
                            <option value="<?= htmlspecialchars($tur, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($tur, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Tutanak No</label>
                    <input type="text" name="tutanak_no" id="kacak-tutanak-no" inputmode="numeric"
                        class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Sayaç No</label>
                    <input type="text" name="sayac_no" id="kacak-sayac-no"
                        class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Abone Adı Soyadı</label>
                <input type="text" name="abone_adi" id="kacak-abone-adi"
                    class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Endeks</label>
                    <input type="text" name="endeks" id="kacak-endeks" inputmode="numeric"
                        class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Sayı</label>
                    <input type="number" name="sayi" id="kacak-sayi" min="1" value="1" required
                        class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Saha Fotoğrafları (en fazla
                    <?= (int) $maxSahaFoto ?>)</label>
                <input type="file" id="kacak-saha-input" accept="image/*" multiple style="display:none">
                <button type="button" onclick="document.getElementById('kacak-saha-input').click()"
                    class="w-full py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-bold">
                    Fotoğraf Ekle
                </button>
                <div id="kacak-saha-preview" class="grid grid-cols-3 gap-2 mt-3"></div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Açıklama</label>
                <textarea name="aciklama" id="kacak-aciklama" rows="2"
                    class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm text-slate-800 dark:text-white"></textarea>
            </div>

            <button type="submit" id="kacak-submit-btn"
                class="w-full py-4 rounded-xl bg-primary text-white font-black text-sm">
                <span id="kacak-submit-text">BİLDİRİMİ GÖNDER</span>
            </button>
        </form>
    </div>
</div>

<!-- ============ DETAY MODALI ============ -->
<div id="kacak-detay-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content"
        style="display:flex !important; flex-direction:column !important; max-height:85vh !important; overflow:hidden !important; padding:0 !important;">
        <div class="px-6 pt-3 pb-2 bg-white dark:bg-card-dark z-10">
            <div class="modal-handle mb-4"></div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Tutanak Detayı</h3>
                <button onclick="Modal.close('kacak-detay-modal')"
                    class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-500">close</span>
                </button>
            </div>
        </div>
        <div class="p-6 pt-4 overflow-y-auto flex-1" id="kacak-detay-content"
            style="overscroll-behavior-y: contain; padding-bottom:2.5rem;"></div>
    </div>
</div>

<script>
    (function () {
        const MAX_SAHA_FOTO = <?= (int) $maxSahaFoto ?>;
        const BEN = <?= (int) $personel_id ?>;

        let kacakKayitlar = [];
        let aktifFiltre = 'all';
        let sahaDosyalari = [];

        function esc(v) {
            if (v === null || v === undefined) return '';
            return String(v).replace(/[&<>"']/g, m => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            }[m]));
        }

        function durumRozeti(k) {
            const map = {
                beklemede: ['text-amber-600', 'Onay Bekliyor'],
                onaylandi: ['text-emerald-600', 'Onaylandı'],
                reddedildi: ['text-red-600', 'Reddedildi']
            };
            const [renk, etiket] = map[k.onay_durumu] || map.beklemede;
            let html = `<span class="text-xs font-bold ${renk}">${etiket}</span>`;
            if (k.durum === 'iptal') {
                html += ` <span class="text-xs font-bold text-slate-400">· İPTAL</span>`;
            }
            return html;
        }

        function kartHtml(k) {
            const turRenk = k.tur === 'Kaçak' ? 'text-red-600' : 'text-amber-600';
            const fotoSatiri = parseInt(k.foto_sayisi || 0, 10) > 0
                ? `<span class="text-xs text-slate-400">· ${k.foto_sayisi} belge</span>` : '';
            const redSatiri = (k.onay_durumu === 'reddedildi' && k.red_nedeni)
                ? `<p class="text-xs text-red-600 mt-2">Red nedeni: ${esc(k.red_nedeni)}</p>` : '';

            return `
            <div onclick="kacakDetayAc(${k.id})"
                class="bg-white dark:bg-card-dark p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-black ${turRenk}">${esc(k.tur)}</span>
                    ${durumRozeti(k)}
                </div>
                <p class="text-sm font-bold text-slate-800 dark:text-white mt-2">${esc(k.abone_adi || 'Abone belirtilmemiş')}</p>
                <p class="text-xs text-slate-400 mt-1">
                    ${esc(k.tarih_formatted)} · ${esc(k.ilce || 'İlçe yok')} · No: ${esc(k.tutanak_no || '-')} ${fotoSatiri}
                </p>
                ${redSatiri}
            </div>`;
        }

        function listeyiCiz() {
            const liste = aktifFiltre === 'all'
                ? kacakKayitlar
                : kacakKayitlar.filter(k => k.onay_durumu === aktifFiltre);

            const el = document.getElementById('kacak-list');
            el.innerHTML = liste.length === 0
                ? '<div class="text-center py-10 text-sm text-slate-400">Kayıt bulunamadı</div>'
                : liste.map(kartHtml).join('');
        }

        window.filterKacak = function (durum, btn) {
            aktifFiltre = durum;
            document.querySelectorAll('.kacak-tab').forEach(b => {
                b.className = 'kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500';
            });
            btn.className = 'kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-primary bg-white dark:bg-card-dark';
            listeyiCiz();
        };

        window.loadKacakKayitlar = async function () {
            const el = document.getElementById('kacak-list');
            el.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>';

            const res = await API.request('getKacakBildirimlerim', {
                start_date: document.getElementById('kacak-bas').value,
                end_date: document.getElementById('kacak-bit').value
            }, false);

            if (!res || !res.success) {
                el.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Kayıtlar yüklenemedi</div>';
                return;
            }

            kacakKayitlar = res.data.kayitlar || [];
            const ist = res.data.istatistik || {};
            document.getElementById('kacak-ozet-satiri').textContent =
                `${ist.toplam || 0} tutanak · ${ist.bekleyen || 0} bekleyen · ${ist.onayli || 0} onaylı`;

            listeyiCiz();
        };

        window.kacakDetayAc = function (id) {
            const k = kacakKayitlar.find(x => parseInt(x.id, 10) === parseInt(id, 10));
            if (!k) return;

            const satir = (etiket, deger) => `
            <div class="flex items-center justify-between py-3 border-b border-slate-200 dark:border-slate-800">
                <span class="text-xs font-semibold text-slate-400">${etiket}</span>
                <span class="text-xs font-bold text-slate-800 dark:text-white text-right">${esc(deger || '-')}</span>
            </div>`;

            let fotoHtml = '';
            if ((k.fotograflar || []).length > 0) {
                fotoHtml = `
                <p class="text-xs font-bold text-slate-400 uppercase mt-4 mb-2">Belgeler</p>
                <div class="grid grid-cols-3 gap-2">
                    ${k.fotograflar.map(f => `
                        <a href="${esc(f.url)}" target="_blank" rel="noopener">
                            <img src="${esc(f.url)}" class="w-full rounded-xl border border-slate-200"
                                 style="height:6rem;object-fit:cover">
                            <span class="text-xs text-slate-400 block text-center mt-1">${esc(f.tur_label)}</span>
                        </a>`).join('')}
                </div>`;
            }

            document.getElementById('kacak-detay-content').innerHTML = `
            <div class="text-center mb-3">${durumRozeti(k)}</div>
            ${satir('Tarih', k.tarih_formatted)}
            ${satir('Tür', k.tur)}
            ${satir('İlçe', k.ilce)}
            ${satir('Tutanak No', k.tutanak_no)}
            ${satir('Abone Adı', k.abone_adi)}
            ${satir('Sayaç No', k.sayac_no)}
            ${satir('Endeks', k.endeks)}
            ${satir('Sayı', k.sayi)}
            ${satir('Ekip', k.ekip_adi)}
            ${k.aciklama ? satir('Açıklama', k.aciklama) : ''}
            ${k.onay_durumu === 'reddedildi' && k.red_nedeni ? satir('Red Nedeni', k.red_nedeni) : ''}
            ${k.durum === 'iptal' ? satir('İptal Açıklaması', k.iptal_aciklama) : ''}
            ${fotoHtml}
            <button onclick="Modal.close('kacak-detay-modal')"
                class="w-full mt-4 py-3 bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold rounded-xl">Kapat</button>`;

            Modal.open('kacak-detay-modal');
        };

        window.openKacakBildirModal = function () {
            document.getElementById('kacak-bildir-form').reset();
            document.getElementById('kacak-tutanak-input').value = '';
            document.getElementById('kacak-tutanak-label').textContent = 'Fotoğraf Seç';
            document.getElementById('kacak-tutanak-preview').style.display = 'none';
            document.getElementById('kacak-analiz-btn').disabled = true;
            document.getElementById('kacak-saha-preview').innerHTML = '';
            sahaDosyalari = [];
            Modal.open('kacak-bildir-modal');
        };

        // ----- Tutanak seçimi -----
        document.getElementById('kacak-tutanak-input').addEventListener('change', function (e) {
            const file = e.target.files[0];
            const analizBtn = document.getElementById('kacak-analiz-btn');
            analizBtn.disabled = !file;
            if (!file) {
                document.getElementById('kacak-tutanak-label').textContent = 'Fotoğraf Seç';
                document.getElementById('kacak-tutanak-preview').style.display = 'none';
                return;
            }

            document.getElementById('kacak-tutanak-label').textContent = file.name.slice(0, 18);

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = ev => {
                    const box = document.getElementById('kacak-tutanak-preview');
                    box.querySelector('img').src = ev.target.result;
                    box.style.display = '';
                };
                reader.readAsDataURL(file);
            }
        });

        // ----- Saha fotoğrafları -----
        document.getElementById('kacak-saha-input').addEventListener('change', function (e) {
            for (const file of e.target.files) {
                if (sahaDosyalari.length >= MAX_SAHA_FOTO) {
                    Alert.warning('Limit', `En fazla ${MAX_SAHA_FOTO} fotoğraf ekleyebilirsiniz.`);
                    break;
                }
                sahaDosyalari.push(file);
            }
            e.target.value = '';
            sahaOnizlemeCiz();
        });

        function sahaOnizlemeCiz() {
            const box = document.getElementById('kacak-saha-preview');
            box.innerHTML = '';
            sahaDosyalari.forEach((file, i) => {
                const reader = new FileReader();
                reader.onload = ev => {
                    box.insertAdjacentHTML('beforeend', `
                    <div style="position:relative">
                        <img src="${ev.target.result}" class="w-full rounded-xl border border-slate-200"
                             style="height:4.5rem;object-fit:cover">
                        <button type="button" onclick="sahaFotoSil(${i})"
                            class="text-white text-xs font-bold rounded-xl"
                            style="position:absolute;top:-6px;right:-6px;width:22px;height:22px;line-height:1;background:#dc2626">×</button>
                    </div>`);
                };
                reader.readAsDataURL(file);
            });
        }

        window.sahaFotoSil = function (index) {
            sahaDosyalari.splice(index, 1);
            sahaOnizlemeCiz();
        };

        // ----- Yapay zeka analizi -----
        window.analizEtKacak = async function () {
            const input = document.getElementById('kacak-tutanak-input');
            if (!input.files.length) {
                return Alert.warning('Dosya Gerekli', 'Lütfen önce tutanak fotoğrafını seçin.');
            }

            const btn = document.getElementById('kacak-analiz-btn');
            const btnOriginalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.style.opacity = '0.75';
            btn.style.cursor = 'wait';
            btn.innerHTML = `
                <span class="inline-flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined animate-spin" style="font-size:18px">progress_activity</span>
                    <span>Analiz ediliyor...</span>
                </span>`;

            const fd = new FormData();
            fd.append('action', 'analyzeKacakTutanak');
            fd.append('tutanak_file', input.files[0]);
            fd.append('tarih', document.querySelector('#kacak-bildir-form [name=tarih]').value);

            Loading.show();
            try {
                const res = await (await fetch('api.php', { method: 'POST', body: fd })).json();
                if (!res.success) {
                    return Alert.error('Analiz Başarısız', res.message || 'Tutanak okunamadı.');
                }

                const satir = (res.data || [])[0];
                if (!satir) {
                    return Alert.warning('Sonuç Yok', 'Tutanaktan veri çıkartılamadı, lütfen elle doldurun.');
                }

                const form = document.getElementById('kacak-bildir-form');
                if (satir.tarih) form.querySelector('[name=tarih]').value = satir.tarih;
                if (satir.ilce) document.getElementById('kacak-ilce').value = satir.ilce;
                if (satir.tur) document.getElementById('kacak-tur').value = satir.tur;
                if (satir.tutanak_no) document.getElementById('kacak-tutanak-no').value = satir.tutanak_no;
                if (satir.abone_adi) document.getElementById('kacak-abone-adi').value = satir.abone_adi;
                if (satir.sayac_no) document.getElementById('kacak-sayac-no').value = satir.sayac_no;
                if (satir.endeks) document.getElementById('kacak-endeks').value = satir.endeks;
                if (satir.sayi) document.getElementById('kacak-sayi').value = satir.sayi;
                if (satir.aciklama) document.getElementById('kacak-aciklama').value = satir.aciklama;

                const digerPersonel = (satir.personel_ids || []).find(id => String(id) !== String(BEN));
                if (digerPersonel) {
                    const sel = document.getElementById('kacak-ekip-arkadasi');
                    if ([...sel.options].some(o => o.value === String(digerPersonel))) {
                        sel.value = String(digerPersonel);
                    }
                }

                Toast.show('Tutanak okundu, lütfen kontrol edin', 'success');
            } catch (err) {
                console.error('Kaçak analiz hatası:', err);
                Alert.error('Bağlantı Hatası', 'Sunucuya ulaşılamadı.');
            } finally {
                Loading.hide();
                btn.disabled = !input.files.length;
                btn.removeAttribute('aria-busy');
                btn.style.opacity = '';
                btn.style.cursor = '';
                btn.innerHTML = btnOriginalHtml;
            }
        };

        // ----- Form gönderimi -----
        document.getElementById('kacak-bildir-form').addEventListener('submit', async function (e) {
            e.preventDefault();

            const tutanakInput = document.getElementById('kacak-tutanak-input');
            if (!tutanakInput.files.length) {
                return Alert.warning('Tutanak Zorunlu', 'Lütfen tutanağın fotoğrafını ekleyin.');
            }

            const btn = document.getElementById('kacak-submit-btn');
            const btnText = document.getElementById('kacak-submit-text');
            btn.disabled = true;
            btnText.textContent = 'GÖNDERİLİYOR...';

            const fd = new FormData(this);
            fd.append('action', 'saveKacakBildirim');
            fd.append('tutanak_foto', tutanakInput.files[0]);
            sahaDosyalari.forEach(f => fd.append('saha_fotolari[]', f));

            try {
                const res = await (await fetch('api.php', { method: 'POST', body: fd })).json();
                if (res.success) {
                    Modal.close('kacak-bildir-modal');
                    Alert.success('Gönderildi', res.message);
                    loadKacakKayitlar();
                } else {
                    Alert.error('Hata', res.message || 'Kayıt gönderilemedi.');
                }
            } catch (err) {
                console.error('Kaçak bildirim hatası:', err);
                Alert.error('Bağlantı Hatası', 'Sunucuya ulaşılamadı.');
            } finally {
                btn.disabled = false;
                btnText.textContent = 'BİLDİRİMİ GÖNDER';
            }
        });

        // pwa-app.js sayfa içeriğinden sonra yüklendiği için API/Alert/Modal
        // ancak DOMContentLoaded anında hazır olur.
        document.addEventListener('DOMContentLoaded', function () {
            const bugun = new Date();
            const bas = new Date();
            bas.setDate(bugun.getDate() - 29);
            const iso = d => d.toISOString().slice(0, 10);

            document.getElementById('kacak-bas').value = iso(bas);
            document.getElementById('kacak-bit').value = iso(bugun);

            loadKacakKayitlar();
        });
    })();
</script>
