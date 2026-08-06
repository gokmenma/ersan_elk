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
$maxVideo = KacakKontrolModel::MAX_VIDEO;
$videoMaxSure = KacakKontrolModel::VIDEO_MAX_SURE;
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

    <!-- Çevrimdışı kuyruk durumu -->
    <section id="kacak-kuyruk-serit" class="px-4 pt-4" style="display:none">
        <div
            class="bg-amber-50 dark:bg-slate-800 border border-amber-200 dark:border-slate-800 rounded-xl p-3 flex items-center justify-between gap-3">
            <div class="flex-1">
                <p class="text-xs font-bold text-amber-700 dark:text-amber-400" id="kacak-kuyruk-baslik"></p>
                <p class="text-xs text-slate-500 mt-1" id="kacak-kuyruk-alt"></p>
            </div>
            <button type="button" onclick="kacakKuyrugunuGonder()" id="kacak-kuyruk-btn"
                class="shrink-0 px-3 py-2 rounded-xl bg-amber-500 text-white text-xs font-bold">Şimdi Gönder</button>
        </div>
    </section>

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
                <h3 id="kacak-form-title" class="text-lg font-bold text-slate-800 dark:text-white">Kaçak Tutanağı Bildir</h3>
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
                    <span id="kacak-tutanak-required" class="text-xs text-slate-400">Zorunlu</span>
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
                <div id="mevcut-tutanak-container"></div>
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
                <div id="mevcut-saha-container"></div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Video (en fazla
                    <?= (int) $maxVideo ?> adet, <?= (int) $videoMaxSure ?> sn)</label>
                <input type="file" id="kacak-video-input" accept="video/*" style="display:none">
                <button type="button" onclick="document.getElementById('kacak-video-input').click()"
                    class="w-full py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-bold">
                    Video Ekle
                </button>
                <p class="text-xs text-slate-400 mt-1">En fazla <?= (int) $videoMaxSure ?> saniye ve
                    <?= (int) round(KacakKontrolModel::VIDEO_MAX_BYTE / 1048576) ?> MB. Videolar çevrimiçiyken gönderilir.</p>
                <div id="kacak-video-preview" class="grid grid-cols-3 gap-2 mt-3"></div>
                <div id="mevcut-video-container"></div>
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
        const MAX_VIDEO = <?= (int) $maxVideo ?>;
        const VIDEO_MAX_SURE = <?= (int) $videoMaxSure ?>;
        const VIDEO_MAX_BYTE = <?= (int) KacakKontrolModel::VIDEO_MAX_BYTE ?>;
        let videoDosyalari = [];
        const BEN = <?= (int) $personel_id ?>;

        let kacakKayitlar = [];
        let aktifFiltre = 'all';
        let sahaDosyalari = [];
        let bekleyenKayitlar = [];
        let kacakEditToken = null;

        const REF_ANAHTAR = 'kacak_referans';
        const LISTE_ANAHTAR = 'kacak_liste';

        const cevrimici = () => navigator.onLine !== false;

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
            const duzenleBtn = k.duzenlenebilir
                ? `<div class="grid grid-cols-2 gap-2 mt-3"><button type="button" onclick="event.stopPropagation(); kacakDuzenle('${esc(k.edit_token)}')"
                    class="py-2 rounded-xl bg-primary/10 text-primary text-xs font-bold flex items-center justify-center gap-1"><span class="material-symbols-outlined text-base">edit</span>Düzenle</button><button type="button" onclick="event.stopPropagation(); kacakSil('${esc(k.edit_token)}')" class="py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 text-xs font-bold flex items-center justify-center gap-1"><span class="material-symbols-outlined text-base">delete</span>Sil</button></div>` : '';

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
                ${duzenleBtn}
            </div>`;
        }

        function kuyrukKartHtml(k) {
            const o = k.ozet || {};
            const hataMi = k.durum === 'hata';
            const ekToplam = (k.ekDosyalar || []).length;
            const rozet = hataMi
                ? '<span class="text-xs font-bold text-red-600">Gönderilemedi</span>'
                : (k.anaGonderildi
                    ? `<span class="text-xs font-bold text-amber-700">Fotoğraflar yükleniyor · ${k.ekGonderilen || 0}/${ekToplam}</span>`
                    : '<span class="text-xs font-bold text-slate-500">Gönderilmeyi bekliyor</span>');

            const ilerlemeSatiri = (k.anaGonderildi && ekToplam > 0)
                ? `<p class="text-xs text-slate-400 mt-1">Tutanak sunucuya ulaştı, kalan ${ekToplam - (k.ekGonderilen || 0)} fotoğraf gönderilecek.</p>`
                : '';

            const hataSatiri = hataMi
                ? `<p class="text-xs text-red-600 mt-2">${esc(k.hata || 'Sunucu kaydı kabul etmedi.')}</p>`
                : (k.hata
                    ? `<p class="text-xs text-amber-700 mt-2">${esc(k.hata)}${k.deneme ? ` · ${k.deneme}. deneme` : ''}</p>`
                    : '');

            const tekrarBtn = hataMi
                ? `<button type="button" onclick="kacakKuyrukTekrar('${esc(k.uuid)}')"
                        class="flex-1 py-2 rounded-xl bg-amber-500 text-white text-xs font-bold">Tekrar Dene</button>`
                : '';

            const fotoSatiri = (o.foto_sayisi || 0) > 0
                ? `<span class="text-xs text-slate-400">· ${o.foto_sayisi} belge</span>` : '';

            return `
            <div class="bg-white dark:bg-card-dark p-4 rounded-xl border border-amber-200 dark:border-slate-800">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-black text-slate-500">${esc(o.tur || 'Kaçak')}</span>
                    ${rozet}
                </div>
                <p class="text-sm font-bold text-slate-800 dark:text-white mt-2">${esc(o.abone_adi || 'Abone belirtilmemiş')}</p>
                <p class="text-xs text-slate-400 mt-1">
                    ${esc(o.tarih_formatted || '-')} · ${esc(o.ilce || 'İlçe yok')} · No: ${esc(o.tutanak_no || '-')} ${fotoSatiri}
                </p>
                ${ilerlemeSatiri}
                ${hataSatiri}
                <div class="flex items-center gap-2 mt-3">
                    ${tekrarBtn}
                    <button type="button" onclick="kacakKuyrukSil('${esc(k.uuid)}')"
                        class="flex-1 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-bold">Sil</button>
                </div>
            </div>`;
        }

        function listeyiCiz() {
            const liste = aktifFiltre === 'all'
                ? kacakKayitlar
                : kacakKayitlar.filter(k => k.onay_durumu === aktifFiltre);

            // Henüz sunucuya ulaşmamış kayıtlar en üstte, sadece ilgili sekmelerde.
            const kuyruk = (aktifFiltre === 'all' || aktifFiltre === 'beklemede') ? bekleyenKayitlar : [];

            const el = document.getElementById('kacak-list');
            el.innerHTML = (liste.length === 0 && kuyruk.length === 0)
                ? '<div class="text-center py-10 text-sm text-slate-400">Kayıt bulunamadı</div>'
                : kuyruk.map(kuyrukKartHtml).join('') + liste.map(kartHtml).join('');
        }

        async function kuyrugaBak() {
            if (!window.OfflineQueue) return;

            const tumu = await OfflineQueue.listele();
            bekleyenKayitlar = tumu.filter(k => k.action === 'saveKacakBildirim');

            const bekleyen = bekleyenKayitlar.filter(k => k.durum !== 'hata').length;
            const hatali = bekleyenKayitlar.filter(k => k.durum === 'hata').length;

            const serit = document.getElementById('kacak-kuyruk-serit');
            const btn = document.getElementById('kacak-kuyruk-btn');

            if (bekleyen === 0 && hatali === 0) {
                serit.style.display = 'none';
            } else {
                serit.style.display = '';
                const parcalar = [];
                if (bekleyen > 0) parcalar.push(`${bekleyen} kayıt gönderilmeyi bekliyor`);
                if (hatali > 0) parcalar.push(`${hatali} kayıt gönderilemedi`);

                document.getElementById('kacak-kuyruk-baslik').textContent = parcalar.join(' · ');
                document.getElementById('kacak-kuyruk-alt').textContent = cevrimici()
                    ? 'Bağlantı var, gönderim sürüyor.'
                    : 'İnternet geldiğinde otomatik gönderilecek.';

                btn.disabled = !cevrimici() || bekleyen === 0;
                btn.style.opacity = btn.disabled ? '0.5' : '';
            }

            listeyiCiz();
        }

        window.kacakKuyrugunuGonder = async function () {
            if (!cevrimici()) {
                return Alert.warning('Bağlantı Yok', 'İnternet bağlantısı sağlandığında kayıtlar otomatik gönderilir.');
            }

            Loading.show();
            try {
                const sonuc = await OfflineQueue.flush({ elle: true });
                await kuyrugaBak();
                if (sonuc.gonderildi > 0) {
                    await loadKacakKayitlar();
                    Toast.show(`${sonuc.gonderildi} kayıt gönderildi`, 'success');
                } else {
                    Toast.show('Gönderilebilen kayıt olmadı', 'warning');
                }
            } finally {
                Loading.hide();
            }
        };

        window.kacakKuyrukTekrar = async function (uuid) {
            Loading.show();
            try {
                await OfflineQueue.tekrarDene(uuid);
                await kuyrugaBak();
                await loadKacakKayitlar();
            } finally {
                Loading.hide();
            }
        };

        window.kacakKuyrukSil = async function (uuid) {
            const kayit = bekleyenKayitlar.find(k => k.uuid === uuid);
            const kalan = kayit ? (kayit.ekDosyalar || []).length - (kayit.ekGonderilen || 0) : 0;

            const mesaj = (kayit && kayit.anaGonderildi)
                ? `Bu tutanak sunucuya ulaştı, silmek onu geri almaz. Sadece henüz gönderilmemiş ${kalan} fotoğraf kaybolur. Devam edilsin mi?`
                : 'Bu tutanak henüz sunucuya gönderilmedi. Silerseniz fotoğraflarıyla birlikte kaybolur. Silmek istiyor musunuz?';

            const onay = await Alert.confirm('Kaydı Sil', mesaj, 'Sil', 'Vazgeç');
            if (!onay) return;

            await OfflineQueue.sil(uuid);
            await kuyrugaBak();
        };

        window.filterKacak = function (durum, btn) {
            aktifFiltre = durum;
            document.querySelectorAll('.kacak-tab').forEach(b => {
                b.className = 'kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500';
            });
            btn.className = 'kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-primary bg-white dark:bg-card-dark';
            listeyiCiz();
        };

        function ozetYaz(ist, onEk) {
            document.getElementById('kacak-ozet-satiri').textContent = (onEk ? onEk + ' · ' : '') +
                `${ist.toplam || 0} tutanak · ${ist.bekleyen || 0} bekleyen · ${ist.onayli || 0} onaylı`;
        }

        async function onbellektenCiz() {
            if (!window.OfflineQueue) return false;

            const saklanan = await OfflineQueue.referansOku(LISTE_ANAHTAR);
            if (!saklanan) return false;

            kacakKayitlar = saklanan.kayitlar || [];
            ozetYaz(saklanan.istatistik || {}, 'Çevrimdışı');
            listeyiCiz();
            return true;
        }

        window.loadKacakKayitlar = async function () {
            const el = document.getElementById('kacak-list');

            if (!cevrimici()) {
                if (!(await onbellektenCiz())) {
                    kacakKayitlar = [];
                    document.getElementById('kacak-ozet-satiri').textContent = 'Çevrimdışı · kayıtlar görüntülenemiyor';
                    listeyiCiz();
                }
                return;
            }

            el.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>';

            const res = await API.request('getKacakBildirimlerim', {
                start_date: document.getElementById('kacak-bas').value,
                end_date: document.getElementById('kacak-bit').value
            }, false);

            if (!res || !res.success) {
                if (!(await onbellektenCiz())) {
                    el.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Kayıtlar yüklenemedi</div>';
                }
                return;
            }

            kacakKayitlar = res.data.kayitlar || [];
            const ist = res.data.istatistik || {};
            ozetYaz(ist);
            listeyiCiz();

            // Bağlantı kesildiğinde liste boş kalmasın diye son görüntülenen hali saklanır.
            if (window.OfflineQueue) {
                OfflineQueue.referansKaydet(LISTE_ANAHTAR, { kayitlar: kacakKayitlar, istatistik: ist });
            }
        };

        /**
         * Ekip arkadaşı / ilçe / tür listeleri sunucuda üretiliyor; sayfa önbellekten
         * açıldığında güncel kalması için ayrıca IndexedDB'de tutulur.
         */
        async function referansTazele() {
            if (!window.OfflineQueue || !cevrimici()) return;

            const res = await API.request('getKacakReferans', {}, false);
            if (res && res.success && res.data) {
                OfflineQueue.referansKaydet(REF_ANAHTAR, res.data);
            }
        }

        async function referansUygula() {
            if (!window.OfflineQueue) return;

            const ref = await OfflineQueue.referansOku(REF_ANAHTAR);
            if (!ref) return;

            const doldur = (el, degerler, etiketle) => {
                if (!el || !degerler || !degerler.length) return;
                const secili = el.value;
                const bosluk = el.id === 'kacak-tur' ? '' : '<option value="">Seçiniz...</option>';
                el.innerHTML = bosluk + degerler.map(etiketle).join('');
                if (secili) el.value = secili;
            };

            doldur(document.getElementById('kacak-ekip-arkadasi'), ref.ekip_adaylari,
                a => `<option value="${esc(a.id)}">${esc(a.adi_soyadi)}</option>`);
            doldur(document.getElementById('kacak-ilce'), ref.ilceler,
                i => `<option value="${esc(i)}">${esc(i)}</option>`);
            doldur(document.getElementById('kacak-tur'), ref.turler,
                t => `<option value="${esc(t)}">${esc(t)}</option>`);
        }

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
                    ${k.fotograflar.map(f => {
                        const videoMu = f.medya_tipi === 'video';
                        const gorsel = (videoMu && !f.kucuk_var)
                            ? `<div class="w-full rounded-xl border border-slate-200 flex items-center justify-center bg-slate-100" style="height:6rem">
                                   <span class="material-symbols-outlined text-slate-400">movie</span></div>`
                            : `<img src="${esc(f.kucuk_url || f.url)}" loading="lazy" class="w-full rounded-xl border border-slate-200"
                                    style="height:6rem;object-fit:cover">`;
                        const rozet = videoMu
                            ? `<span class="text-white text-xs rounded" style="position:absolute;bottom:4px;right:4px;background:rgba(0,0,0,.7);padding:1px 4px">${f.sure_saniye ? OfflineQueue.sureBicimle(f.sure_saniye) : '▶'}</span>`
                            : '';
                        return `
                        <a href="${esc(f.url)}" target="_blank" rel="noopener" style="position:relative;display:block">
                            ${gorsel}
                            ${rozet}
                            <span class="text-xs text-slate-400 block text-center mt-1">${esc(f.tur_label)}</span>
                        </a>`;
                    }).join('')}
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

        window.kacakFotoSilPwa = async function (fotoId, btnEl) {
            const onay = await Alert.confirm('Fotoğrafı Sil', 'Bu görsel kayıttan kalıcı olarak silinecek. Onaylıyor musunuz?', 'Sil', 'Vazgeç');
            if (!onay) return;
            Loading.show();
            try {
                const res = await API.request('deleteKacakFoto', { foto_id: fotoId });
                if (!res.success) {
                    return Alert.error('Hata', res.message || 'Fotoğraf silinemedi.');
                }
                Toast.show('Fotoğraf silindi.', 'success');
                const container = btnEl.closest('.mevcut-foto-item, #mevcut-tutanak-card');
                if (container) container.remove();
                if (kacakEditToken) {
                    const k = kacakKayitlar.find(x => x.edit_token === kacakEditToken);
                    if (k && k.fotograflar) {
                        k.fotograflar = k.fotograflar.filter(f => parseInt(f.id, 10) !== parseInt(fotoId, 10));
                    }
                }
                await loadKacakKayitlar();
            } catch (e) {
                Alert.error('Hata', e.message || 'Fotoğraf silinemedi.');
            } finally {
                Loading.hide();
            }
        };

        window.openKacakBildirModal = function (editData = null) {
            document.getElementById('kacak-bildir-form').reset();
            kacakEditToken = editData?.edit_token || null;
            document.getElementById('kacak-form-title').textContent = editData ? 'Kaçak Tutanağını Düzenle' : 'Kaçak Tutanağı Bildir';
            document.getElementById('kacak-tutanak-required').textContent = editData ? 'Yeni belge isteğe bağlı' : 'Zorunlu';
            document.getElementById('kacak-submit-text').textContent = editData ? 'DEĞİŞİKLİKLERİ KAYDET' : 'BİLDİRİMİ GÖNDER';
            document.getElementById('kacak-tutanak-input').value = '';
            document.getElementById('kacak-tutanak-label').textContent = 'Fotoğraf Seç';
            document.getElementById('kacak-tutanak-preview').style.display = 'none';
            aiButonGuncelle();
            document.getElementById('kacak-saha-preview').innerHTML = '';
            sahaDosyalari = [];
            document.getElementById('kacak-video-preview').innerHTML = '';
            videoDosyalari = [];

            const tutanakBox = document.getElementById('mevcut-tutanak-container');
            const sahaBox = document.getElementById('mevcut-saha-container');
            const videoBox = document.getElementById('mevcut-video-container');
            if (tutanakBox) tutanakBox.innerHTML = '';
            if (sahaBox) sahaBox.innerHTML = '';
            if (videoBox) videoBox.innerHTML = '';

            if (editData) {
                const form = document.getElementById('kacak-bildir-form');
                ['tarih','ilce','tur','tutanak_no','abone_adi','sayac_no','endeks','sayi','aciklama'].forEach(ad => {
                    const alan=form.querySelector(`[name="${ad}"]`); if(alan) alan.value=editData[ad] ?? '';
                });
                const ekipIds=String(editData.personel_ids||'').split(',').map(Number);
                const arkadas=ekipIds.find(id=>id!==BEN);
                if(arkadas) document.getElementById('kacak-ekip-arkadasi').value=String(arkadas);

                const fotolar = editData.fotograflar || [];
                const tutanakFoto = fotolar.find(f => f.tur === 'tutanak');
                if (tutanakFoto && tutanakBox) {
                    tutanakBox.innerHTML = `
                        <div id="mevcut-tutanak-card" class="mt-3 p-3 rounded-xl bg-white dark:bg-card-dark border border-emerald-200 dark:border-emerald-800 flex items-center justify-between gap-3 shadow-sm">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <img src="${esc(tutanakFoto.kucuk_url || tutanakFoto.url)}" class="w-14 h-14 rounded-lg object-cover border border-slate-200 shrink-0">
                                <div class="overflow-hidden">
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span>
                                        <span class="text-xs font-bold text-slate-800 dark:text-white">Yüklü Tutanak Fotoğrafı</span>
                                    </div>
                                    <a href="${esc(tutanakFoto.url)}" target="_blank" rel="noopener" class="text-xs text-primary font-semibold underline block mt-0.5">Büyüt / Görüntüle</a>
                                </div>
                            </div>
                            <button type="button" onclick="kacakFotoSilPwa(${tutanakFoto.id}, this)" class="w-8 h-8 rounded-xl bg-red-50 dark:bg-red-950/40 text-red-600 flex items-center justify-center shrink-0" title="Sil">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>`;
                }

                const sahaFotolar = fotolar.filter(f => f.tur === 'saha' && f.medya_tipi !== 'video');
                if (sahaFotolar.length > 0 && sahaBox) {
                    sahaBox.innerHTML = `
                        <div class="mt-3">
                            <p class="text-xs font-bold text-slate-500 uppercase mb-2">Önceden Yüklenen Saha Fotoğrafları (${sahaFotolar.length})</p>
                            <div class="grid grid-cols-3 gap-2">
                                ${sahaFotolar.map(f => `
                                    <div class="mevcut-foto-item relative group rounded-xl overflow-hidden border border-slate-200 bg-slate-100 dark:bg-slate-800" style="height:6rem">
                                        <a href="${esc(f.url)}" target="_blank" rel="noopener" class="block w-full h-full">
                                            <img src="${esc(f.kucuk_url || f.url)}" loading="lazy" class="w-full h-full object-cover">
                                        </a>
                                        <button type="button" onclick="kacakFotoSilPwa(${f.id}, this)"
                                            class="absolute top-1 right-1 w-7 h-7 rounded-full bg-red-600 text-white flex items-center justify-center shadow-md" title="Sil">
                                            <span class="material-symbols-outlined text-sm">close</span>
                                        </button>
                                    </div>
                                `).join('')}
                            </div>
                        </div>`;
                }

                const videolar = fotolar.filter(f => f.medya_tipi === 'video');
                if (videolar.length > 0 && videoBox) {
                    videoBox.innerHTML = `
                        <div class="mt-3">
                            <p class="text-xs font-bold text-slate-500 uppercase mb-2">Önceden Yüklenen Videolar (${videolar.length})</p>
                            <div class="grid grid-cols-3 gap-2">
                                ${videolar.map(f => `
                                    <div class="mevcut-foto-item relative group rounded-xl overflow-hidden border border-slate-200 bg-slate-100 dark:bg-slate-800" style="height:6rem">
                                        <a href="${esc(f.url)}" target="_blank" rel="noopener" class="block w-full h-full flex items-center justify-center">
                                            ${f.kucuk_var
                                                ? `<img src="${esc(f.kucuk_url || f.url)}" loading="lazy" class="w-full h-full object-cover">`
                                                : `<span class="material-symbols-outlined text-slate-400 text-3xl">movie</span>`}
                                            <span class="absolute bottom-1 right-1 text-white text-[10px] rounded bg-black/70 px-1 font-bold">
                                                ${f.sure_saniye ? OfflineQueue.sureBicimle(f.sure_saniye) : '▶'}
                                            </span>
                                        </a>
                                        <button type="button" onclick="kacakFotoSilPwa(${f.id}, this)"
                                            class="absolute top-1 right-1 w-7 h-7 rounded-full bg-red-600 text-white flex items-center justify-center shadow-md" title="Sil">
                                            <span class="material-symbols-outlined text-sm">close</span>
                                        </button>
                                    </div>
                                `).join('')}
                            </div>
                        </div>`;
                }
            }
            Modal.open('kacak-bildir-modal');
        };

        window.kacakDuzenle = function (token) {
            const kayit = kacakKayitlar.find(k => k.edit_token === token && k.duzenlenebilir);
            if (!kayit) return Toast.show('Kayıt artık düzenlenemiyor.', 'warning');
            openKacakBildirModal(kayit);
        };

        window.kacakSil = async function (token) {
            const kayit = kacakKayitlar.find(k => k.edit_token === token && k.duzenlenebilir);
            if (!kayit) return Toast.show('Kayıt artık silinemiyor.', 'warning');
            const onay = await Alert.confirm('Kaçak Bildirimini Sil', 'Onay bekleyen bu bildirim silinecek. Devam edilsin mi?', 'Sil', 'Vazgeç');
            if (!onay) return;
            Loading.show();
            try {
                const response = await API.request('deleteKacakBildirim', {edit_token: token});
                if (!response.success) return Alert.error('Silinemedi', response.message || 'İşlem başarısız.');
                kacakKayitlar = kacakKayitlar.filter(k => k.edit_token !== token);
                listeyiCiz();
                await loadKacakKayitlar();
                Toast.show(response.message || 'Kaçak bildirimi silindi.', 'success');
            } catch (error) {
                Alert.error('Silinemedi', error.message || 'Sunucuya ulaşılamadı.');
            } finally {
                Loading.hide();
            }
        };

        // ----- Tutanak seçimi -----
        document.getElementById('kacak-tutanak-input').addEventListener('change', function (e) {
            const file = e.target.files[0];
            aiButonGuncelle();
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

        // ----- Videolar -----
        document.getElementById('kacak-video-input').addEventListener('change', async function (e) {
            const dosya = e.target.files[0];
            e.target.value = '';
            if (!dosya) return;

            if (videoDosyalari.length >= MAX_VIDEO) {
                return Alert.warning('Limit', `En fazla ${MAX_VIDEO} video ekleyebilirsiniz.`);
            }

            try {
                videoDosyalari.push(await OfflineQueue.videoIncele(dosya, VIDEO_MAX_SURE, VIDEO_MAX_BYTE));
                videoOnizlemeCiz();
            } catch (hata) {
                Alert.warning('Video Eklenemedi', hata.message);
            }
        });

        function videoOnizlemeCiz() {
            const box = document.getElementById('kacak-video-preview');
            box.innerHTML = '';
            videoDosyalari.forEach((v, i) => {
                const kapak = v.kapak
                    ? `<img src="${v.kapak}" class="w-full rounded-xl border border-slate-200" style="height:4.5rem;object-fit:cover">`
                    : `<div class="w-full rounded-xl border border-slate-200 flex items-center justify-center bg-slate-100" style="height:4.5rem">
                           <span class="material-symbols-outlined text-slate-400">movie</span></div>`;
                box.insertAdjacentHTML('beforeend', `
                <div style="position:relative">
                    ${kapak}
                    <span class="text-white text-xs rounded" style="position:absolute;bottom:4px;right:4px;background:rgba(0,0,0,.7);padding:1px 4px">${OfflineQueue.sureBicimle(v.sure)}</span>
                    <button type="button" onclick="videoSil(${i})"
                        class="text-white text-xs font-bold rounded-xl"
                        style="position:absolute;top:-6px;right:-6px;width:22px;height:22px;line-height:1;background:#dc2626">×</button>
                </div>`);
            });
        }

        window.videoSil = function (index) {
            videoDosyalari.splice(index, 1);
            videoOnizlemeCiz();
        };

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

        async function compressImageForAi(file, maxDimension = 1600, quality = 0.82) {
            if (!file || !file.type.startsWith('image/')) return file;
            if (file.size <= 2 * 1024 * 1024) return file;

            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        let width = img.width;
                        let height = img.height;
                        if (width > maxDimension || height > maxDimension) {
                            if (width > height) {
                                height = Math.round((height * maxDimension) / width);
                                width = maxDimension;
                            } else {
                                width = Math.round((width * maxDimension) / height);
                                height = maxDimension;
                            }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        canvas.toBlob((blob) => {
                            if (blob && blob.size < file.size) {
                                const newFile = new File([blob], (file.name || 'tutanak.jpg').replace(/\.[^/.]+$/, "") + ".jpg", {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                resolve(newFile);
                            } else {
                                resolve(file);
                            }
                        }, 'image/jpeg', quality);
                    };
                    img.onerror = () => resolve(file);
                    img.src = e.target.result;
                };
                reader.onerror = () => resolve(file);
                reader.readAsDataURL(file);
            });
        }

        // ----- Yapay zeka analizi -----
        window.analizEtKacak = async function () {
            const input = document.getElementById('kacak-tutanak-input');
            if (!input || !input.files.length) {
                return Alert.warning('Dosya Gerekli', 'Lütfen önce tutanak fotoğrafını seçin.');
            }

            if (!cevrimici()) {
                return Alert.warning('Bağlantı Yok', 'Tutanak okuma sunucuda yapılır, çevrimdışıyken alanları elle doldurun.');
            }

            const btn = document.getElementById('kacak-analiz-btn');
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.style.opacity = '0.75';
            btn.style.cursor = 'wait';
            btn.innerHTML = `
                <span class="inline-flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined animate-spin" style="font-size:18px">progress_activity</span>
                    <span>Analiz ediliyor...</span>
                </span>`;

            Loading.show();
            try {
                let fileToSend = input.files[0];
                if (fileToSend.type.startsWith('image/')) {
                    fileToSend = await compressImageForAi(fileToSend);
                }

                const fd = new FormData();
                fd.append('action', 'analyzeKacakTutanak');
                fd.append('tutanak_file', fileToSend);
                fd.append('tarih', document.querySelector('#kacak-bildir-form [name=tarih]')?.value || '');

                const res = await (await fetch('api.php?action=analyzeKacakTutanak', { method: 'POST', body: fd })).json();
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
                btn.removeAttribute('aria-busy');
                btn.style.cursor = '';
                aiButonGuncelle();
            }
        };

        function tarihAraligiGenislet(kayitTarihi) {
            const basInput = document.getElementById('kacak-bas');
            const bitInput = document.getElementById('kacak-bit');
            if (kayitTarihi && (!basInput.value || kayitTarihi < basInput.value)) {
                basInput.value = kayitTarihi;
            }
            if (kayitTarihi && (!bitInput.value || kayitTarihi > bitInput.value)) {
                bitInput.value = kayitTarihi;
            }

            aktifFiltre = 'all';
            document.querySelectorAll('.kacak-tab').forEach((tab, index) => {
                tab.className = index === 0
                    ? 'kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-primary bg-white dark:bg-card-dark'
                    : 'kacak-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500';
            });
        }

        // Videolar boyutları nedeniyle cihaz kuyruğuna yazılmaz; kayıt sunucuya
        // ulaştıktan sonra ayrı isteklerle gönderilir. Gönderilemeyen video sayısı döner.
        async function videolariGonder(clientUuid) {
            let eksik = 0;
            for (let i = 0; i < videoDosyalari.length; i++) {
                const v = videoDosyalari[i];
                try {
                    const fd = new FormData();
                    fd.append('action', 'addKacakVideo');
                    fd.append('client_uuid', clientUuid);
                    fd.append('video', v.dosya, v.dosya.name);
                    if (v.sure) fd.append('sure', v.sure);
                    if (v.kapak) fd.append('kapak', v.kapak);

                    const res = await (await fetch('api.php', { method: 'POST', body: fd })).json();
                    if (!res.success) {
                        eksik++;
                        console.error('Video gönderilemedi:', res.message);
                    }
                } catch (hata) {
                    eksik++;
                    console.error('Video gönderim hatası:', hata);
                }
            }
            return eksik;
        }

        // Kuyruk yazması başarısız olduğunda (cihaz depolaması dolu, IndexedDB
        // engelli vb.) kullanılan emniyet yolu. Kuyruktaki gibi parçalı gönderir:
        // önce kayıt + tutanak, sonra saha fotoğrafları teker teker.
        async function dogrudanGonder(alanlar, dosyalar, sahaFotolari) {
            if (!alanlar.client_uuid) {
                alanlar.client_uuid = OfflineQueue.uuid();
            }
            alanlar.beklenen_foto_sayisi = 1 + sahaFotolari.length;

            const ana = await OfflineQueue.istekGonder('saveKacakBildirim', alanlar, dosyalar, 'kayıt');
            if (ana.sonuc !== 'tamam') {
                return { success: false, message: ana.mesaj };
            }

            let eksik = 0;
            for (let i = 0; i < sahaFotolari.length; i++) {
                const f = sahaFotolari[i];
                const cevap = await OfflineQueue.istekGonder(
                    'addKacakSahaFoto',
                    { client_uuid: alanlar.client_uuid, sira: i, toplam: sahaFotolari.length },
                    [{ alan: 'foto', ad: f.ad, tip: f.tip, blob: f.blob }],
                    `fotoğraf ${i + 1}/${sahaFotolari.length}`
                );
                if (cevap.sonuc !== 'tamam') eksik++;
            }

            return {
                success: true,
                eksik,
                message: eksik > 0
                    ? `Tutanak kaydedildi ancak ${eksik} fotoğraf gönderilemedi.`
                    : 'Bildiriminiz iletildi. Yönetici onayı bekleniyor.',
            };
        }

        // ----- Form gönderimi -----
        // Kayıt her durumda önce cihazdaki kuyruğa yazılır, sonra gönderilmeye çalışılır.
        // Böylece bağlantı kopsa, sayfa kapansa ya da telefon kilitlense bile
        // tutanak kaybolmaz; client_uuid sayesinde sunucuya iki kez düşmez.
        document.getElementById('kacak-bildir-form').addEventListener('submit', async function (e) {
            e.preventDefault();

            const tutanakInput = document.getElementById('kacak-tutanak-input');
            if (!kacakEditToken && !tutanakInput.files.length) {
                return Alert.warning('Tutanak Zorunlu', 'Lütfen tutanağın fotoğrafını ekleyin.');
            }
            if (!window.OfflineQueue) {
                return Alert.error('Hata', 'Uygulama bileşenleri yüklenemedi, sayfayı yenileyin.');
            }

            const btn = document.getElementById('kacak-submit-btn');
            const btnText = document.getElementById('kacak-submit-text');
            btn.disabled = true;
            btnText.textContent = 'HAZIRLANIYOR...';

            try {
                if (kacakEditToken) {
                    if (!cevrimici()) return Alert.warning('Bağlantı Gerekli', 'Kayıt düzenleme işlemi çevrimiçi yapılabilir.');
                    const fd = new FormData(this);
                    fd.append('action', 'updateKacakBildirim');
                    fd.append('edit_token', kacakEditToken);
                    btnText.textContent = 'HAZIRLANIYOR...';
                    if (tutanakInput.files[0]) {
                        const tutanakKucuk = await OfflineQueue.fotografKucult(tutanakInput.files[0], 2200, 0.82);
                        fd.append('tutanak_foto', tutanakKucuk.blob, tutanakKucuk.ad);
                    }
                    for (const file of sahaDosyalari) {
                        const sahaKucuk = await OfflineQueue.fotografKucult(file, 1600, 0.7);
                        fd.append('saha_fotolari[]', sahaKucuk.blob, sahaKucuk.ad);
                    }
                    videoDosyalari.forEach(v => {
                        fd.append('videolar[]', v.dosya, v.dosya.name);
                        fd.append('video_sureleri[]', v.sure || '');
                        fd.append('video_kapaklari[]', v.kapak || '');
                    });
                    btnText.textContent = 'GÜNCELLENİYOR...';
                    const res = await (await fetch('api.php', {method:'POST', body:fd})).json();
                    if (!res.success) return Alert.error('Güncellenemedi', res.message || 'İşlem başarısız.');
                    Modal.close('kacak-bildir-modal');
                    await loadKacakKayitlar();
                    return Alert.success('Güncellendi', res.message || 'Kaçak bildirimi güncellendi.');
                }
                const alanlar = {};
                new FormData(this).forEach((deger, ad) => { alanlar[ad] = deger; });

                // Fotoğraflar zayıf bağlantıda gönderilebilsin ve cihazda az yer kaplasın
                // diye küçültülür; tutanak okunabilirliği için daha yüksek çözünürlük kalır.
                const tutanak = await OfflineQueue.fotografKucult(tutanakInput.files[0], 2200, 0.82);
                const dosyalar = [{ alan: 'tutanak_foto', ad: tutanak.ad, tip: tutanak.tip, blob: tutanak.blob }];

                // Saha fotoğrafları ana istekle değil, her biri ayrı istekle gider.
                const sahaFotolari = [];
                for (const dosya of sahaDosyalari) {
                    const kucuk = await OfflineQueue.fotografKucult(dosya, 1600, 0.7);
                    sahaFotolari.push({ ad: kucuk.ad, tip: kucuk.tip, blob: kucuk.blob });
                }

                const ozet = {
                    tur: alanlar.tur,
                    ilce: alanlar.ilce,
                    tutanak_no: alanlar.tutanak_no,
                    abone_adi: alanlar.abone_adi,
                    tarih_formatted: (alanlar.tarih || '').split('-').reverse().join('.'),
                    foto_sayisi: dosyalar.length + sahaFotolari.length,
                };
                alanlar.beklenen_foto_sayisi = dosyalar.length + sahaFotolari.length;

                btnText.textContent = 'GÖNDERİLİYOR...';

                let kayit;
                try {
                    kayit = await OfflineQueue.ekle('saveKacakBildirim', alanlar, dosyalar, ozet, {
                        action: 'addKacakSahaFoto',
                        alan: 'foto',
                        dosyalar: sahaFotolari,
                    });
                } catch (kuyrukHatasi) {
                    console.error('Kuyruğa yazılamadı:', kuyrukHatasi);

                    if (!cevrimici()) {
                        return Alert.error('Kaydedilemedi',
                            'Tutanak telefona kaydedilemedi ve bağlantı da yok. Telefonunuzda yer açıp tekrar deneyin.');
                    }

                    let res;
                    try {
                        res = await dogrudanGonder(alanlar, dosyalar, sahaFotolari);
                    } catch (agHatasi) {
                        console.error('Doğrudan gönderim hatası:', agHatasi);
                        return Alert.error('Gönderilemedi',
                            'Tutanak telefona kaydedilemedi ve sunucuya da ulaşılamadı. Telefonunuzda yer açıp tekrar deneyin.');
                    }

                    if (res && res.success) {
                        const eksikVideo = await videolariGonder(alanlar.client_uuid);
                        Modal.close('kacak-bildir-modal');
                        tarihAraligiGenislet(alanlar.tarih);
                        await loadKacakKayitlar();
                        return Alert.success('Gönderildi', eksikVideo > 0
                            ? `Tutanak iletildi ancak ${eksikVideo} video gönderilemedi.`
                            : (res.message || 'Bildiriminiz iletildi. Yönetici onayı bekleniyor.'));
                    }

                    return Alert.error('Gönderilemedi', (res && res.message) || 'Sunucu kaydı kabul etmedi.');
                }

                Modal.close('kacak-bildir-modal');
                tarihAraligiGenislet(alanlar.tarih);
                await kuyrugaBak();

                if (!cevrimici()) {
                    return Alert.success('Cihaza Kaydedildi', videoDosyalari.length > 0
                        ? 'Bağlantı olmadığı için tutanak telefonunuza kaydedildi ve internet geldiğinde otomatik gönderilecek. '
                          + 'Videolar cihazda saklanamadığı için kaydı çevrimiçiyken açıp videoları tekrar eklemeniz gerekir.'
                        : 'Bağlantı olmadığı için tutanak telefonunuza kaydedildi. İnternet geldiğinde otomatik gönderilecek.');
                }

                await OfflineQueue.flush();
                const kalan = await OfflineQueue.oku(kayit.uuid);
                await kuyrugaBak();

                if (!kalan) {
                    const eksikVideo = await videolariGonder(kayit.uuid);
                    await loadKacakKayitlar();
                    return Alert.success('Gönderildi', eksikVideo > 0
                        ? `Tutanak iletildi ancak ${eksikVideo} video gönderilemedi.`
                        : 'Bildiriminiz iletildi. Yönetici onayı bekleniyor.');
                }
                if (kalan.durum === 'hata') {
                    return Alert.error('Gönderilemedi', kalan.hata || 'Sunucu kaydı kabul etmedi.');
                }
                Alert.warning('Onay Alınamadı',
                    'Tutanak telefonunuzda güvende. Sunucuya ulaştıysa birazdan listede görünecek, '
                    + 'ulaşmadıysa otomatik olarak tekrar gönderilecek — tekrar doldurmanıza gerek yok.'
                    + (kalan.hata ? '\n\nSebep: ' + kalan.hata : ''));
            } catch (err) {
                console.error('Kaçak bildirim hatası:', err);
                Alert.error('Hata', 'İşlem tamamlanamadı. Lütfen tekrar deneyin.');
            } finally {
                btn.disabled = false;
                btnText.textContent = 'BİLDİRİMİ GÖNDER';
            }
        });

        // Yapay zeka okuması sunucuda çalışır, çevrimdışıyken kullanılamaz.
        function aiButonGuncelle() {
            const btn = document.getElementById('kacak-analiz-btn');
            const dosyaVar = document.getElementById('kacak-tutanak-input').files.length > 0;

            btn.disabled = !dosyaVar || !cevrimici();
            btn.style.opacity = btn.disabled ? '0.5' : '';
            btn.textContent = cevrimici() ? 'Yapay Zeka ile Oku' : 'Çevrimdışı — Elle Doldurun';
        }

        // pwa-app.js sayfa içeriğinden sonra yüklendiği için API/Alert/Modal
        // ancak DOMContentLoaded anında hazır olur.
        document.addEventListener('DOMContentLoaded', async function () {
            const bugun = new Date();
            const bas = new Date();
            bas.setDate(bugun.getDate() - 29);
            const iso = d => d.toISOString().slice(0, 10);

            document.getElementById('kacak-bas').value = iso(bas);
            document.getElementById('kacak-bit').value = iso(bugun);

            await referansUygula();
            aiButonGuncelle();
            await kuyrugaBak();
            await loadKacakKayitlar();
            referansTazele();

            // Kuyruk hem bu sayfadan hem de arka plan senkronizasyonundan değişebilir.
            window.addEventListener('kuyruk-degisti', async (e) => {
                await kuyrugaBak();
                if (e.detail && e.detail.gonderildi > 0) {
                    await loadKacakKayitlar();
                }
            });

            window.addEventListener('online', async () => {
                aiButonGuncelle();
                await kuyrugaBak();
                await loadKacakKayitlar();
                referansTazele();
            });

            window.addEventListener('offline', () => {
                aiButonGuncelle();
                kuyrugaBak();
            });
        });
    })();
</script>
