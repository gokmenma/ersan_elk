<?php
/**
 * Personel PWA - Bordro Sayfası
 * Bordro listesi ve avans talebi
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header <?php
    /**
     * Personel PWA - Bordro Sayfası
     * Bordro listesi ve avans talebi
     */
    ?> <div
        class="flex flex-col min-h-screen">
        <!-- Header -->
        <header
            class="bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 px-4 py-4 sticky top-0 z-30">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Bordrolar</h1>
                    <p class="text-sm text-slate-500">Kazançlarınızı inceleyin</p>
                </div>
                <button onclick="openNewAvansModal()" class="btn-primary flex items-center gap-2 px-4 py-2.5 text-sm">
                    <span class="material-symbols-outlined text-lg">add_card</span>
                    <span>Avans</span>
                </button>
            </div>
        </header>

        <!-- Stats Summary -->
        <section class="px-4 py-4 bg-slate-50 dark:bg-background-dark">
            <div class="grid grid-cols-3 gap-3">
                <div class="card p-3 text-center">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Yıllık Net</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white" id="yearly-net">0 ₺</p>
                    <span class="badge badge-success text-[10px]">+5.2%</span>
                </div>
                <div class="card p-3 text-center">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Avans Limit</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white" id="advance-limit">0 ₺</p>
                    <span class="text-[10px] text-slate-400">Kullanılabilir</span>
                </div>
                <div class="card p-3 text-center">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Bekleyen</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white" id="pending-requests">0</p>
                    <span class="badge badge-primary text-[10px]">Talep</span>
                </div>
            </div>
        </section>

        <!-- Tab Navigation -->
        <div
            class="px-4 py-2 bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 sticky top-[73px] z-20">
            <div class="flex gap-2">
                <!-- <button onclick="changeTab('bordro')" class="tab-btn active px-4 py-2 text-sm font-semibold rounded-lg"
                    data-tab="bordro">
                    Bordrolar
                </button> -->
                <button onclick="changeTab('avans')"
                    class="tab-btn px-4 py-2 text-sm font-semibold rounded-lg active text-slate-500" data-tab="avans">
                    Avans Talepleri
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 px-4 py-4">
            <!-- Bordro List -->
            <!--  <div id="bordro-tab" class="tab-content">
                <div class="flex flex-col gap-3" id="bordro-list">
                     Bordro items will be loaded here 
                    <div class="shimmer h-20 rounded-xl"></div>
                    <div class="shimmer h-20 rounded-xl"></div>
                    <div class="shimmer h-20 rounded-xl"></div>
                </div>
            </div>-->

            <!-- Avans List -->
            <div id="avans-tab" class="tab-content">
                <div class="flex flex-col gap-3" id="avans-list">
                    <!-- Avans items will be loaded here -->
                </div>
            </div>
        </div>
</div>

<!-- Avans Talebi Modal -->
<div id="avans-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-2xl">account_balance_wallet</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Avans Talebi</h3>
            </div>
            <button onclick="Modal.close('avans-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <form id="avans-form" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="avans-id">
            <div>
                <label class="form-label">Talep Edilen Tutar</label>
                <div class="relative">
                    <span
                        class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-600 dark:text-slate-400">₺</span>
                    <input type="number" name="tutar" id="avans-tutar" class="form-input pl-10" placeholder="0.00"
                        required>
                </div>
                <p class="text-xs text-slate-500 mt-1">Maksimum limit: <span id="max-limit">2.000,00 ₺</span></p>
            </div>

            <div>
                <label class="form-label">Geri Ödeme Seçeneği</label>
                <select name="odeme_sekli" class="form-input form-select">
                    <option value="tek">Gelecek Maaştan (Tek Seferde)</option>
                    <option value="2">2 Taksit</option>
                    <option value="3">3 Taksit</option>
                </select>
            </div>

            <div>
                <label class="form-label">Talep Nedeni</label>
                <textarea name="aciklama" class="form-input min-h-[100px]"
                    placeholder="Lütfen avans talebinizin nedenini açıklayın..."></textarea>
            </div>

            <div class="flex items-start gap-3 bg-slate-50 dark:bg-slate-800 p-4 rounded-xl">
                <input type="checkbox" id="onay" name="onay"
                    class="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary" required>
                <label for="onay" class="text-xs text-slate-600 dark:text-slate-400">
                    Bu tutarın, seçilen geri ödeme seçeneğine göre gelecekteki bordrolarımdan otomatik olarak
                    düşüleceğini anlıyorum.
                </label>
            </div>

            <div class="flex gap-3 mt-2">
                <button type="button" onclick="Modal.close('avans-modal')"
                    class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                    İptal
                </button>
                <button type="submit" class="flex-1 btn-primary py-3" id="avans-submit-btn">
                    Talebi Gönder
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bordro Detay Modal -->
<div id="bordro-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="bordro-modal-title">Bordro Detayı</h3>
            <button onclick="Modal.close('bordro-detay-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div id="bordro-detay-content">
            <!-- Content will be loaded dynamically -->
        </div>

        <div class="flex gap-3 mt-6">
            <button onclick="downloadBordro()" class="flex-1 btn-secondary flex items-center justify-center gap-2 py-3">
                <span class="material-symbols-outlined">download</span>
                PDF İndir
            </button>
            <button onclick="Modal.close('bordro-detay-modal')"
                class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        </div>
    </div>
</div>

<style>
    .tab-btn.active {
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        //Müşteri şimdiilik bordrolar görünmesin dedi
        //loadBordrolar();
        loadAvansTalepleri();
        loadStats();

        // Avans form submit
        document.getElementById('avans-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await submitAvansTalebi(this);
        });
    });

    function changeTab(tab) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.classList.add('text-slate-500');
        });
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
        document.querySelector(`[data-tab="${tab}"]`).classList.remove('text-slate-500');

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
        document.getElementById(`${tab}-tab`).classList.remove('hidden');
    }

    async function loadStats() {
        try {
            const response = await API.request('getBordroStats');
            if (response.success) {
                document.getElementById('yearly-net').textContent = Format.currency(response.data.yearly_net || 0);
                document.getElementById('advance-limit').textContent = Format.currency(response.data.advance_limit || 0);
                document.getElementById('pending-requests').textContent = response.data.pending_requests || 0;
                document.getElementById('max-limit').textContent = Format.currency(response.data.advance_limit || 0);
            }
        } catch (error) {
            console.error('Stats load error:', error);
        }
    }

    async function loadBordrolar() {
        const container = document.getElementById('bordro-list');

        try {
            const response = await API.request('getBordrolar');

            if (response.success && response.data.length > 0) {
                container.innerHTML = response.data.map(bordro => `
                <div class="card p-4 flex items-center gap-4" onclick="showBordroDetay(${bordro.id})">
                    <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary">description</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-sm text-slate-900 dark:text-white">${bordro.donem}</p>
                        <p class="text-xs text-slate-500">Ödeme: ${bordro.odeme_tarihi}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-slate-900 dark:text-white">${Format.currency(bordro.net_tutar)}</p>
                        <span class="badge ${bordro.durum === 'odendi' ? 'badge-success' : 'badge-warning'}">${bordro.durum === 'odendi' ? 'Ödendi' : 'Bekliyor'}</span>
                    </div>
                </div>
            `).join('');
            } else {
                container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Henüz bordro kaydı yok</p>
                    <p class="text-sm text-slate-500">Bordrolarınız burada listelenecek.</p>
                </div>
            `;
            }
        } catch (error) {
            console.error('Bordro load error:', error);
            container.innerHTML = '<p class="text-center text-slate-500 py-8">Veriler yüklenemedi</p>';
        }
    }

    async function loadAvansTalepleri() {
        const container = document.getElementById('avans-list');

        try {
            const response = await API.request('getAvansTalepleri');

            if (response.success && response.data.length > 0) {
                container.innerHTML = response.data.map(avans => `
                <div class="card p-4">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-orange-600">request_quote</span>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-sm text-slate-900 dark:text-white">${Format.currency(avans.tutar)}</p>
                            <p class="text-xs text-slate-500">Talep: ${avans.tarih}</p>
                        </div>
                        <div class="text-right">
                            <span class="badge ${getStatusBadge(avans.durum)}">${avans.durum_text}</span>
                        </div>
                    </div>
                    
                    ${avans.durum === 'beklemede' ? `
                    <div class="flex gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button onclick='editAvans(${JSON.stringify(avans)})' class="flex-1 py-2 text-xs font-semibold text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded-lg flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">edit</span>
                            Düzenle
                        </button>
                        <button onclick="deleteAvans(${avans.id})" class="flex-1 py-2 text-xs font-semibold text-red-600 bg-red-50 dark:bg-red-900/20 rounded-lg flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">delete</span>
                            Sil
                        </button>
                    </div>
                    ` : ''}
                </div>
            `).join('');
            } else {
                container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">request_quote</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Avans talebiniz yok</p>
                    <p class="text-sm text-slate-500">Yeni avans talebi oluşturabilirsiniz.</p>
                </div>
            `;
            }
        } catch (error) {
            console.error('Avans load error:', error);
        }
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'onaylandi': return 'badge-success';
            case 'beklemede': return 'badge-warning';
            case 'reddedildi': return 'badge-danger';
            default: return 'badge-gray';
        }
    }

    async function showBordroDetay(id) {
        // Load bordro details and show modal
        Modal.open('bordro-detay-modal');

        try {
            const response = await API.request('getBordroDetay', { id: id });
            if (response.success) {
                const data = response.data;
                document.getElementById('bordro-modal-title').textContent = data.donem;
                document.getElementById('bordro-detay-content').innerHTML = `
                <div class="flex flex-col gap-4">
                    <div class="flex justify-between items-center py-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Brüt Maaş</span>
                        <span class="font-bold">${Format.currency(data.brut)}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">SGK Primi</span>
                        <span class="font-bold text-red-500">-${Format.currency(data.sgk)}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Gelir Vergisi</span>
                        <span class="font-bold text-red-500">-${Format.currency(data.vergi)}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 bg-primary/5 rounded-lg px-3">
                        <span class="font-bold text-slate-700 dark:text-slate-300">Net Maaş</span>
                        <span class="font-bold text-lg text-primary">${Format.currency(data.net)}</span>
                    </div>
                </div>
            `;
            }
        } catch (error) {
            console.error('Bordro detail error:', error);
        }
    }

    function openNewAvansModal() {
        document.getElementById('avans-form').reset();
        document.getElementById('avans-id').value = '';
        document.getElementById('avans-submit-btn').textContent = 'Talebi Gönder';
        Modal.open('avans-modal');
    }

    function editAvans(avans) {
        const form = document.getElementById('avans-form');
        form.reset();

        document.getElementById('avans-id').value = avans.id;
        document.getElementById('avans-tutar').value = avans.tutar;
        form.querySelector('[name="odeme_sekli"]').value = avans.odeme_sekli || 'tek';
        form.querySelector('[name="aciklama"]').value = avans.aciklama || '';
        form.querySelector('[name="onay"]').checked = true;

        document.getElementById('avans-submit-btn').textContent = 'Güncelle';
        Modal.open('avans-modal');
    }

    async function deleteAvans(id) {
        const isConfirmed = await Alert.confirm(
            'Sil',
            'Bu avans talebini silmek istediğinize emin misiniz?',
            'Evet, Sil',
            'Vazgeç'
        );

        if (!isConfirmed) return;

        try {
            const response = await API.request('deleteAvansTalebi', { id: id });

            if (response.success) {
                Toast.show('Avans talebi silindi', 'success');
                loadAvansTalepleri();
                loadStats();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
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
        }
    }

    function downloadBordro() {
        Toast.show('PDF indiriliyor...', 'success');
        // Implement PDF download
    }
</script>