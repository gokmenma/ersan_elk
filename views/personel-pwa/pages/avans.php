<?php
/**
 * Personel PWA - Avans Sayfası
 * Avans talebi ve takibi
 */
if (!isset($personel_id) || !$personel_id) { http_response_code(403); return; }
if (($personel->personel_tipi ?? '') === 'kaski_kacak') { http_response_code(403); return; }
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
        <?php pwaPageHeader([
        'eyebrow' => 'KAZANÇLARIM',
        'title' => 'Avans İşlemleri',
        'subtitle' => 'Avans taleplerinizi yönetin',
    ]); ?>


        <!-- Content Area -->
        <div class="flex-1 px-4 py-4">
            <!-- Avans List -->
            <div id="avans-tab">
                <div id="avans-kisit-uyari"
                    class="hidden card p-4 mb-3 bg-orange-50 dark:bg-orange-900/20 flex items-center gap-3">
                    <span class="material-symbols-outlined text-orange-600 dark:text-orange-400">info</span>
                    <p class="text-sm font-medium text-orange-600 dark:text-orange-400" id="avans-kisit-mesaj"></p>
                </div>
                <div class="pwa-hero-dark mb-3">
                    <div class="pwa-hero-dark__top">
                        <div>
                            <span class="pwa-hero-dark__eyebrow">AVANS MERKEZİ</span>
                            <h2 class="pwa-hero-dark__value"><span id="avans-bekleyen">0</span> bekleyen talep</h2>
                            <p class="pwa-hero-dark__sub">Avans taleplerinizi buradan takip edin</p>
                        </div>
                        <span class="pwa-hero-dark__icon"><span class="material-symbols-outlined">account_balance_wallet</span></span>
                    </div>
                    <div class="pwa-hero-dark__stats">
                        <div class="pwa-hero-dark__stat">
                            <span class="material-symbols-outlined">payments</span>
                            <strong id="advance-limit">0 ₺</strong>
                            <small>Kullanılabilir Limit</small>
                        </div>
                        <div class="pwa-hero-dark__stat">
                            <span class="material-symbols-outlined">check_circle</span>
                            <strong id="avans-onaylanan">0 ₺</strong>
                            <small>Onaylanan Toplam</small>
                        </div>
                    </div>
                </div>

                <div class="pwa-pill-row mb-3" role="tablist" aria-label="Avans filtresi">
                    <button type="button" role="tab" onclick="filterAvans('tum')" class="avans-filtre-btn pwa-filter-pill is-active" data-filtre="tum">
                        <span class="material-symbols-outlined">apps</span>Tümü <em data-avans-sayi="tum">0</em>
                    </button>
                    <button type="button" role="tab" onclick="filterAvans('beklemede')" class="avans-filtre-btn pwa-filter-pill" data-filtre="beklemede">
                        <span class="material-symbols-outlined">schedule</span>Beklemede <em data-avans-sayi="beklemede">0</em>
                    </button>
                    <button type="button" role="tab" onclick="filterAvans('onaylandi')" class="avans-filtre-btn pwa-filter-pill" data-filtre="onaylandi">
                        <span class="material-symbols-outlined">task_alt</span>Onaylandı <em data-avans-sayi="onaylandi">0</em>
                    </button>
                    <button type="button" role="tab" onclick="filterAvans('reddedildi')" class="avans-filtre-btn pwa-filter-pill" data-filtre="reddedildi">
                        <span class="material-symbols-outlined">cancel</span>Reddedildi <em data-avans-sayi="reddedildi">0</em>
                    </button>
                </div>

                <div class="flex flex-col gap-3" id="avans-list">
                    <div class="shimmer h-20 rounded-xl"></div>
                    <div class="shimmer h-20 rounded-xl"></div>
                </div>
            </div>
        </div>
</div>

<!-- Avans Talebi Modal -->
<div id="avans-modal" class="modal-overlay">
    <div class="modal-content pwa-sheet flex flex-col" style="max-height: min(92dvh, 720px);">
        <div class="pwa-sheet__handle"></div>

        <div class="pwa-sheet__head">
            <span class="pwa-sheet__icon"><span class="material-symbols-outlined">account_balance_wallet</span></span>
            <div class="flex-1 min-w-0">
                <h3>Avans Talebi</h3>
                <p>Maksimum limit: <span id="max-limit">2.000,00 ₺</span></p>
            </div>
            <button type="button" onclick="Modal.close('avans-modal')" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="avans-form" class="flex flex-col flex-1 min-h-0">
            <input type="hidden" name="id" id="avans-id">
            <div class="flex-1 overflow-y-auto pwa-sheet__body">
                <section class="pwa-sheet-card">
                    <div class="pwa-field">
                        <label>Talep Edilen Tutar</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 font-extrabold text-primary">₺</span>
                            <input type="number" name="tutar" id="avans-tutar" class="pwa-input" style="padding-left:40px" placeholder="0.00" inputmode="decimal" min="0.01" step="0.01" required oninput="clampAvansTutar(this)">
                        </div>
                    </div>

                    <div class="pwa-field">
                        <label>Geri Ödeme Seçeneği</label>
                        <select name="odeme_sekli" class="pwa-input">
                            <option value="tek">En Yakın Maaş (Tek Seferde)</option>
                            <option value="2">2 Taksit</option>
                            <option value="3">3 Taksit</option>
                        </select>
                    </div>

                    <div class="pwa-field">
                        <label>Talep Nedeni</label>
                        <textarea name="aciklama" class="pwa-input" placeholder="Lütfen avans talebinizin nedenini açıklayın..."></textarea>
                    </div>

                    <label for="onay" class="pwa-check">
                        <input type="checkbox" id="onay" name="onay" required>
                        <span>Bu tutarın, seçilen geri ödeme seçeneğine göre gelecekteki bordrolarımdan otomatik olarak düşüleceğini anlıyorum.</span>
                    </label>
                </section>
            </div>

            <div class="pwa-sheet__footer">
                <button type="button" onclick="Modal.close('avans-modal')" class="pwa-btn pwa-btn--ghost">İptal</button>
                <button type="submit" class="pwa-btn pwa-btn--primary" id="avans-submit-btn">Talebi Gönder</button>
            </div>
        </form>
    </div>
</div>

<!-- Avans Detay Modal -->
<div id="avans-detay-modal" class="modal-overlay">
    <div class="modal-content pwa-sheet p-6 pt-0">
        <div class="pwa-sheet__handle"></div>

        <div class="pwa-sheet__head" style="padding-left:0;padding-right:0">
            <span class="pwa-sheet__icon"><span class="material-symbols-outlined">receipt_long</span></span>
            <div class="flex-1 min-w-0"><h3>Avans Detayı</h3></div>
            <button type="button" onclick="Modal.close('avans-detay-modal')" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div id="avans-detay-content">
            <!-- Content will be loaded dynamically -->
        </div>

    </div>
</div>

<style>
    .pwa-filter-pill em { font-style: normal; font-size: 11px; opacity: .7; margin-left: 4px; }
</style>

<script>
    let currentAdvanceLimit = 0;
    let currentEditingAvans = null;
    let avansTalepEdebilir = true;
    let avansKisitMesaji = '';
    let avansListesi = [];
    let avansFiltre = 'tum';
    const spinnerHtml = '<span class="material-symbols-outlined animate-spin">refresh</span>';

    document.addEventListener('DOMContentLoaded', function () {

        loadAvansTalepleri();
        loadStats();

        // Avans form submit
        document.getElementById('avans-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await submitAvansTalebi(this);
        });
    });

    async function loadStats() {
        try {
            const response = await API.request('getBordroStats');
            if (response.success) {
                const yearlyNetEl = document.getElementById('yearly-net');
                if (yearlyNetEl) yearlyNetEl.textContent = Format.currency(response.data.yearly_net || 0);

                const advanceLimitEl = document.getElementById('advance-limit');
                if (advanceLimitEl) advanceLimitEl.textContent = Format.currency(response.data.advance_limit || 0);

                const pendingRequestsEl = document.getElementById('pending-requests');
                if (pendingRequestsEl) pendingRequestsEl.textContent = response.data.pending_requests || 0;

                const maxLimitEl = document.getElementById('max-limit');
                if (maxLimitEl) maxLimitEl.textContent = Format.currency(response.data.advance_limit || 0);

                currentAdvanceLimit = parseFloat(response.data.advance_limit || 0);

                avansTalepEdebilir = response.data.avans_talep_edebilir !== false;
                avansKisitMesaji = response.data.avans_talep_mesaji || '';
                updateAvansKisitDurumu();
            }
        } catch (error) {
            console.error('Stats load error:', error);
        }
    }

    async function loadAvansTalepleri() {
        try {
            const response = await API.request('getAvansTalepleri');
            avansListesi = response.success ? response.data : [];
            updateAvansOzet();
            renderAvanslar();
        } catch (error) {
            console.error('Avans load error:', error);
        }
    }

    function updateAvansOzet() {
        const sayi = { tum: avansListesi.length, beklemede: 0, onaylandi: 0, reddedildi: 0 };
        let onaylanan = 0;
        avansListesi.forEach(a => {
            if (sayi[a.durum] !== undefined) sayi[a.durum]++;
            if (a.durum === 'onaylandi') onaylanan += parseFloat(a.tutar || 0);
        });
        document.querySelectorAll('[data-avans-sayi]').forEach(el => {
            el.textContent = sayi[el.dataset.avansSayi] || 0;
        });
        document.getElementById('avans-bekleyen').textContent = sayi.beklemede;
        document.getElementById('avans-onaylanan').textContent = Format.currency(onaylanan);
    }

    function filterAvans(filtre) {
        avansFiltre = filtre;
        document.querySelectorAll('.avans-filtre-btn').forEach(btn => {
            const aktif = btn.dataset.filtre === filtre;
            btn.classList.toggle('is-active', aktif);
            btn.setAttribute('aria-selected', aktif ? 'true' : 'false');
        });
        renderAvanslar();
    }

    function renderAvanslar() {
        const container = document.getElementById('avans-list');
        const liste = avansFiltre === 'tum' ? avansListesi : avansListesi.filter(a => a.durum === avansFiltre);

        if (liste.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">request_quote</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Avans talebiniz yok</p>
                    <p class="text-sm text-slate-500">Yeni avans talebi oluşturabilirsiniz.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = liste.map(avans => `
                <div class="avc" onclick='showAvansDetay(${JSON.stringify(avans).replace(/'/g, "&#39;")})'>
                    <div class="avc__head">
                        <div class="avc__icon">
                            <span class="material-symbols-outlined">request_quote</span>
                        </div>
                        <div class="avc__main">
                            <strong>${Format.currency(avans.tutar)}</strong>
                            <span>Talep: ${avans.tarih}</span>
                        </div>
                        <span class="badge ${getStatusBadge(avans.durum)}">${avans.durum_text}</span>
                    </div>
                    ${avans.aciklama ? `<p class="avc__note">${String(avans.aciklama).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</p>` : ''}
                    ${avans.durum === 'beklemede' ? `
                    <div class="avc__actions">
                        <button type="button" onclick='event.stopPropagation(); editAvans(${JSON.stringify(avans).replace(/'/g, "&#39;")})' class="avc__btn avc__btn--edit">
                            <span class="material-symbols-outlined">edit</span>
                            Düzenle
                        </button>
                        <button type="button" onclick="event.stopPropagation(); deleteAvans(${avans.id}, this)" class="avc__btn avc__btn--del" aria-label="Sil" title="Sil">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                    ` : ''}
                </div>
            `).join('');
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'onaylandi': return 'badge-success';
            case 'beklemede': return 'badge-warning';
            case 'reddedildi': return 'badge-danger';
            default: return 'badge-gray';
        }
    }

    function showAvansDetay(avans) {
        const odeme = avans.odeme_sekli === 'tek' ? 'Tek Seferde' : avans.odeme_sekli + ' Taksit';
        const bekliyor = avans.durum === 'beklemede';
        document.getElementById('avans-detay-content').innerHTML = `
            <div class="avd">
                <div class="avd__hero">
                    <span>Talep Edilen Tutar</span>
                    <strong>${Format.currency(avans.tutar)}</strong>
                    <span class="badge ${getStatusBadge(avans.durum)}">${avans.durum_text}</span>
                </div>

                <div class="avd__grid">
                    <div class="avd__box">
                        <small>TALEP TARİHİ</small>
                        <strong>${avans.tarih}</strong>
                    </div>
                    <div class="avd__box">
                        <small>ÖDEME ŞEKLİ</small>
                        <strong>${odeme}</strong>
                    </div>
                </div>

                <div class="avd__note">
                    <small>AÇIKLAMA / SEBEP</small>
                    <p>${avans.aciklama ? escapeHtml(avans.aciklama) : '<em>Belirtilmemiş</em>'}</p>
                </div>

                <div class="avc__actions">
                    <button type="button" onclick="Modal.close('avans-detay-modal')" class="avc__btn avd__close">Kapat</button>
                    ${bekliyor ? `<button type="button" class="avc__btn avc__btn--edit" onclick='Modal.close("avans-detay-modal"); editAvans(${JSON.stringify(avans).replace(/'/g, "&#39;")})'><span class="material-symbols-outlined">edit</span>Düzenle</button>` : ''}
                </div>
            </div>
        `;
        Modal.open('avans-detay-modal');
    }

    function getAvansMaxLimit() {
        let limit = currentAdvanceLimit;
        if (currentEditingAvans) limit += parseFloat(currentEditingAvans.tutar || 0);
        return Math.max(0, Math.round(limit * 100) / 100);
    }

    function clampAvansTutar(input) {
        const max = getAvansMaxLimit();
        const val = parseFloat(input.value);
        if (!isNaN(val) && val > max) {
            input.value = max;
            Toast.show(`Talep edilen tutar avans limitinizi (${Format.currency(max)}) aşamaz.`, 'error');
        }
        if (!isNaN(val) && val < 0) input.value = '';
    }

    function updateAvansKisitDurumu() {
        const btn = document.getElementById('avans-yeni-btn');
        const uyari = document.getElementById('avans-kisit-uyari');
        const mesaj = document.getElementById('avans-kisit-mesaj');

        if (avansTalepEdebilir) {
            if (btn) btn.classList.remove('hidden');
            if (uyari) uyari.classList.add('hidden');
            return;
        }

        if (btn) btn.classList.add('hidden');
        if (mesaj) mesaj.textContent = avansKisitMesaji;
        if (uyari) uyari.classList.remove('hidden');
    }

    function openNewAvansModal() {
        if (!avansTalepEdebilir) {
            Toast.show(avansKisitMesaji || 'Avans talebi oluşturamazsınız.', 'error');
            return;
        }

        currentEditingAvans = null;
        document.getElementById('avans-form').reset();
        document.getElementById('avans-id').value = '';
        document.getElementById('avans-submit-btn').textContent = 'Talebi Gönder';
        
        // Modal açıldığında limit bilgisini gösterelim
        document.getElementById('max-limit').textContent = Format.currency(currentAdvanceLimit);
        document.getElementById('avans-tutar').max = getAvansMaxLimit();
        
        Modal.open('avans-modal');
    }

    function editAvans(avans) {
        if (!avansTalepEdebilir) {
            Toast.show(avansKisitMesaji || 'Avans talebi düzenleyemezsiniz.', 'error');
            return;
        }

        currentEditingAvans = avans;
        const form = document.getElementById('avans-form');
        form.reset();

        document.getElementById('avans-id').value = avans.id;
        document.getElementById('avans-tutar').value = avans.tutar;
        form.querySelector('[name="odeme_sekli"]').value = avans.odeme_sekli || 'tek';
        form.querySelector('[name="aciklama"]').value = avans.aciklama || '';
        form.querySelector('[name="onay"]').checked = true;

        // Düzenlerken limit: (kalan limit + bu avansın eski tutarı)
        const limitForEdit = currentAdvanceLimit + parseFloat(avans.tutar || 0);
        document.getElementById('max-limit').textContent = Format.currency(limitForEdit);
        document.getElementById('avans-tutar').max = getAvansMaxLimit();

        document.getElementById('avans-submit-btn').textContent = 'Güncelle';
        Modal.open('avans-modal');
    }

    async function deleteAvans(id, btn) {
        const isConfirmed = await Alert.confirm(
            'Sil',
            'Bu avans talebini silmek istediğinize emin misiniz?',
            'Evet, Sil',
            'Vazgeç'
        );

        if (!isConfirmed) return;

        const eskiHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = spinnerHtml;
        }

        try {
            const response = await API.request('deleteAvansTalebi', { id: id });

            if (response.success) {
                Toast.show('Avans talebi silindi', 'success');
                loadAvansTalepleri();
                loadStats();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = eskiHtml; }
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = eskiHtml; }
        }
    }

    async function submitAvansTalebi(form) {
        const formData = Form.serialize(form);
        const isUpdate = !!formData.id;
        const action = isUpdate ? 'updateAvansTalebi' : 'createAvansTalebi';

        if (!Form.validate(form)) {
            Toast.show('Lütfen gerekli alanları doldurun', 'error');
            return;
        }

        const requestedTutar = parseFloat(formData.tutar || 0);
        let limit = currentAdvanceLimit;
        if (isUpdate && currentEditingAvans) {
            limit += parseFloat(currentEditingAvans.tutar || 0);
        }

        if (requestedTutar > limit) {
            Toast.show(`Talep edilen tutar avans limitinizi (${Format.currency(limit)}) aşamaz.`, 'error');
            return;
        }

        const submitBtn = document.getElementById('avans-submit-btn');
        const eskiHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = spinnerHtml;

        try {
            const response = await API.request(action, formData);

            if (response.success) {
                Toast.show(isUpdate ? 'Avans talebi güncellendi' : 'Avans talebi oluşturuldu', 'success');
                Modal.close('avans-modal');
                form.reset();
                loadAvansTalepleri();
                loadStats();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = eskiHtml;
        }
    }

</script>

<button type="button" onclick="openNewAvansModal()" id="avans-yeni-btn" class="avans-fab" aria-label="Avans Talebi" title="Avans Talebi">
    <span class="material-symbols-outlined">add</span>
</button>

<style>
    .avans-fab { position: fixed; right: 1rem; bottom: 6rem; z-index: 45; width: 3.5rem; height: 3.5rem; border-radius: 9999px; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; border: none; box-shadow: 0 10px 24px rgba(0, 0, 0, .28); }
    .avc { background: #fff; border: 1px solid rgba(255,255,255,.9); border-radius: 24px; padding: 14px; box-shadow: 0 4px 14px -6px rgba(15,23,42,.07), 0 2px 4px rgba(15,23,42,.02); cursor: pointer; transition: transform .15s ease; }
    .avc:active { transform: scale(.985); }
    .dark .avc { background: #1d222a; border-color: rgba(255,255,255,.07); }
    .avc__head { display: flex; align-items: center; gap: 12px; }
    .avc__icon { width: 44px; height: 44px; border-radius: 14px; background: #ffedd5; color: #ea580c; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .dark .avc__icon { background: rgba(234,88,12,.18); }
    .avc__main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .avc__main strong { font-size: 16px; font-weight: 800; color: #0f172a; }
    .dark .avc__main strong { color: #fff; }
    .avc__main span { font-size: 11px; font-weight: 600; color: #64748b; }
    .avc__note { margin: 12px 0 0; padding: 8px 10px; border-radius: 12px; background: #f8fafc; font-size: 11px; font-weight: 600; color: #475569; }
    .dark .avc__note { background: rgba(255,255,255,.05); color: #cbd5e1; }
    .avc__actions { display: flex; gap: 8px; margin-top: 12px; }
    .avc__btn { height: 44px; border: 0; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; font-weight: 800; cursor: pointer; transition: transform .1s ease; }
    .avc__btn:active { transform: scale(.96); }
    .avc__btn .material-symbols-outlined { font-size: 18px; }
    .avc__btn--edit { flex: 1; background: #191d24; color: #fff; box-shadow: 0 4px 12px rgba(25,29,36,.22); }
    .avd { display: flex; flex-direction: column; gap: 12px; }
    .avd__hero { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 18px 14px; border-radius: 24px; background: #191d24; color: #fff; box-shadow: 0 6px 16px -8px rgba(0,0,0,.3); }
    .avd__hero > span:first-child { font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.6); }
    .avd__hero strong { font-size: 30px; font-weight: 800; line-height: 1.1; }
    .avd__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .avd__box { display: flex; flex-direction: column; gap: 3px; padding: 12px; border-radius: 16px; background: #fff; border: 1px solid rgba(255,255,255,.9); box-shadow: 0 4px 14px -6px rgba(15,23,42,.07); }
    .avd__box small, .avd__note small { font-size: 9px; font-weight: 800; letter-spacing: .06em; color: #94a3b8; }
    .avd__box strong { font-size: 13px; font-weight: 800; color: #0f172a; }
    .avd__note { padding: 12px; border-radius: 16px; background: #fff; border: 1px solid rgba(255,255,255,.9); box-shadow: 0 4px 14px -6px rgba(15,23,42,.07); }
    .avd__note p { margin: 4px 0 0; min-height: 36px; font-size: 12px; font-weight: 600; color: #334155; }
    .avd__note em { color: #94a3b8; }
    .avd__close { flex: 1; background: #fff; color: #475569; box-shadow: 0 4px 14px -6px rgba(15,23,42,.1); }
    .dark .avd__box, .dark .avd__note, .dark .avd__close { background: #1d222a; border-color: rgba(255,255,255,.07); }
    .dark .avd__box strong, .dark .avd__note p { color: #e2e8f0; }
    .avc__btn--del { width: 44px; background: #fee2e2; color: #dc2626; }
    .avans-fab.hidden { display: none; }
    .avans-fab .material-symbols-outlined { font-size: 30px; }
    .avans-fab:active { transform: scale(.94); }
    body.modal-open .avans-fab { display: none; }
</style>
