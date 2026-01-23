<?php
/**
 * Personel PWA - İzin Sayfası
 * İzin listesi ve yeni izin talebi
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header
        class="bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 px-4 py-4 sticky top-0 z-30">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">İzin İşlemleri</h1>
                <p class="text-sm text-slate-500">İzinlerinizi yönetin</p>
            </div>
            <button onclick="Modal.open('izin-modal')" class="btn-primary flex items-center gap-2 px-4 py-2.5 text-sm">
                <span class="material-symbols-outlined text-lg">add</span>
                <span>Yeni</span>
            </button>
        </div>
    </header>

    <!-- Stats Summary -->
    <section class="px-4 py-4 bg-slate-50 dark:bg-background-dark">
        <div class="grid grid-cols-3 gap-3">
            <div class="card p-3 text-center relative overflow-hidden cursor-pointer" onclick="showHakedisDetay()">
                <div class="absolute top-0 right-0 w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-full -mr-4 -mt-4">
                </div>
                <div
                    class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-blue-600 text-lg">event_available</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="kalan-izin">0</p>
                <p class="text-[10px] text-slate-500">Kalan İzin</p>
            </div>

            <div class="card p-3 text-center relative overflow-hidden">
                <div
                    class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-red-600 text-lg">medical_services</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="hastalik-izni">0</p>
                <p class="text-[10px] text-slate-500">Hastalık İzni</p>
            </div>

            <div class="card p-3 text-center relative overflow-hidden">
                <div
                    class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-amber-600 text-lg">pending_actions</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="bekleyen-izin">0</p>
                <p class="text-[10px] text-slate-500">Bekleyen</p>
            </div>
        </div>
    </section>

    <!-- Filter Tabs -->
    <div
        class="px-4 py-3 bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 overflow-x-auto sticky top-[73px] z-20">
        <div class="flex gap-2 min-w-max">
            <button onclick="filterIzinler('all')"
                class="filter-btn active px-4 py-2 text-sm font-semibold rounded-full bg-primary text-white"
                data-filter="all">
                Tümü
            </button>
            <button onclick="filterIzinler('onaylandi')"
                class="filter-btn px-4 py-2 text-sm font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
                data-filter="onaylandi">
                Onaylanan
            </button>
            <button onclick="filterIzinler('beklemede')"
                class="filter-btn px-4 py-2 text-sm font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
                data-filter="beklemede">
                Bekleyen
            </button>
            <button onclick="filterIzinler('reddedildi')"
                class="filter-btn px-4 py-2 text-sm font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
                data-filter="reddedildi">
                Reddedilen
            </button>
        </div>
    </div>

    <!-- İzin List -->
    <div class="flex-1 px-4 py-4">
        <div class="flex flex-col gap-3" id="izin-list">
            <!-- İzin items will be loaded here -->
            <div class="shimmer h-24 rounded-xl"></div>
            <div class="shimmer h-24 rounded-xl"></div>
        </div>
    </div>
</div>

<!-- Yeni İzin Talebi Modal -->
<div id="izin-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 max-h-[85vh] overflow-y-auto">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-2xl">edit_calendar</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Yeni İzin Talebi</h3>
            </div>
            <button onclick="Modal.close('izin-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <form id="izin-form" class="flex flex-col gap-4">
            <div>
                <label class="form-label">İzin Türü</label>
                <select name="izin_tipi" class="form-input form-select" required>
                    <option value="">Yükleniyor...</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Başlangıç Tarihi</label>
                    <input type="date" name="baslangic_tarihi" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Bitiş Tarihi</label>
                    <input type="date" name="bitis_tarihi" class="form-input" required>
                </div>
            </div>

            <div>
                <label class="form-label">Açıklama</label>
                <textarea name="aciklama" class="form-input min-h-[80px]"
                    placeholder="İzin talebiniz hakkında kısa bir açıklama..."></textarea>
            </div>

            <!-- Info Box -->
            <div class="bg-primary/5 border border-primary/20 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary">info</span>
                    <div>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Hatırlatma</p>
                        <p class="text-xs text-slate-500 mt-1">3 günden uzun süreli izin talepleri en az 2 hafta önceden
                            bildirilmelidir.</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-2">
                <button type="button" onclick="Modal.close('izin-modal')"
                    class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                    İptal
                </button>
                <button type="submit" class="flex-1 btn-primary py-3">
                    Talebi Gönder
                </button>
            </div>
        </form>
    </div>
</div>

<!-- İzin Detay Modal -->
<div id="izin-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">İzin Detayı</h3>
            <button onclick="Modal.close('izin-detay-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div id="izin-detay-content">
            <!-- Content will be loaded dynamically -->
        </div>

        <div class="flex gap-3 mt-6" id="izin-detay-actions">
            <!-- Action buttons -->
        </div>
    </div>
</div>

<!-- Hakediş Detay Bottom Sheet -->
<div id="hakedis-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 h-[60vh] flex flex-col">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-4 flex-shrink-0">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">İzin Hakediş Detayları</h3>
            <button onclick="Modal.close('hakedis-detay-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-4 flex-shrink-0">
            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-xl text-center">
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-1">Toplam</p>
                <p class="text-lg font-bold text-blue-700 dark:text-blue-300" id="sheet-toplam">0</p>
            </div>
            <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-xl text-center">
                <p class="text-xs text-red-600 dark:text-red-400 mb-1">Kullanılan</p>
                <p class="text-lg font-bold text-red-700 dark:text-red-300" id="sheet-kullanilan">0</p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-xl text-center">
                <p class="text-xs text-green-600 dark:text-green-400 mb-1">Kalan</p>
                <p class="text-lg font-bold text-green-700 dark:text-green-300" id="sheet-kalan">0</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div id="hakedis-list" class="space-y-2">
                <!-- Dynamic Content -->
            </div>
        </div>
    </div>
</div>

<script>
    let currentFilter = 'all';
    let izinlerData = [];
    let izinTurleri = [];
    let hakedisData = null;

    document.addEventListener('DOMContentLoaded', function () {
        loadIzinTurleri();
        loadIzinStats();
        loadIzinler();

        // İzin form submit
        document.getElementById('izin-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await submitIzinTalebi(this);
        });
    });

    async function loadIzinTurleri() {
        try {
            const response = await API.request('getIzinTurleri');
            if (response.success) {
                izinTurleri = response.data;
                const select = document.querySelector('select[name="izin_tipi"]');

                // Mevcut seçenekleri temizle
                select.innerHTML = '<option value="">Seçiniz...</option>';

                // İzin türlerini ekle
                izinTurleri.forEach(tur => {
                    const option = document.createElement('option');
                    option.value = tur.id;
                    option.textContent = tur.tur_adi;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('İzin türleri yüklenemedi:', error);
            const select = document.querySelector('select[name="izin_tipi"]');
            select.innerHTML = '<option value="">Hata oluştu</option>';
        }
    }

    async function loadIzinStats() {
        try {
            const response = await API.request('getIzinStats');
            if (response.success) {
                hakedisData = response.data;
                document.getElementById('kalan-izin').textContent = response.data.kalan_izin + ' Gün';
                document.getElementById('hastalik-izni').textContent = response.data.hastalik_izni + ' Gün';
                document.getElementById('bekleyen-izin').textContent = response.data.bekleyen;
            }
        } catch (error) {
            console.error('Stats load error:', error);
        }
    }

    async function showHakedisDetay() {
        if (!hakedisData) return;

        document.getElementById('sheet-toplam').textContent = hakedisData.toplam_hakedis + ' Gün';
        document.getElementById('sheet-kullanilan').textContent = hakedisData.kullanilan_izin + ' Gün';
        document.getElementById('sheet-kalan').textContent = hakedisData.kalan_izin + ' Gün';

        const container = document.getElementById('hakedis-list');

        // Kullanılan izinleri yıllara göre gruplamak için izinleri çek
        try {
            const response = await API.request('getIzinlerByYear');
            const izinlerByYear = response.success ? response.data : {};

            if (hakedisData.detay && hakedisData.detay.length > 0) {
                // FIFO Logic: Calculate how much leave was used from each year
                const allLeaves = izinlerByYear['all'] || [];
                const totalUsed = allLeaves.reduce((sum, izin) => sum + parseFloat(izin.toplam_gun || 0), 0);

                // Distribute used leaves across years using FIFO
                let remainingUsed = totalUsed;
                const yearUsage = [];

                for (let i = 0; i < hakedisData.detay.length; i++) {
                    const yearDetail = hakedisData.detay[i];
                    const yearEntitlement = yearDetail.hakedis_gun;

                    if (remainingUsed > 0) {
                        const usedFromThisYear = Math.min(remainingUsed, yearEntitlement);
                        const remaining = yearEntitlement - usedFromThisYear;
                        yearUsage.push({
                            year: yearDetail.yil,
                            entitlement: yearEntitlement,
                            used: usedFromThisYear,
                            remaining: remaining
                        });
                        remainingUsed -= usedFromThisYear;
                    } else {
                        yearUsage.push({
                            year: yearDetail.yil,
                            entitlement: yearEntitlement,
                            used: 0,
                            remaining: yearEntitlement
                        });
                    }
                }

                container.innerHTML = hakedisData.detay.map((item, index) => {
                    const usage = yearUsage[index];

                    return `
                        <div class="card overflow-hidden">
                            <button onclick="toggleYearAccordion(${index})" 
                                class="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-primary">calendar_today</span>
                                    </div>
                                    <div class="text-left">
                                        <p class="font-bold text-slate-900 dark:text-white">${item.yil}. Yıl</p>
                                        <p class="text-xs text-slate-500">${new Date(item.hakedis_tarihi).toLocaleDateString('tr-TR')}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-xs text-slate-500">Hak: ${usage.entitlement} | Kullanılan: ${usage.used} | Kalan: ${usage.remaining}</p>
                                    </div>
                                    <span class="material-symbols-outlined text-slate-400 accordion-icon" id="accordion-icon-${index}">expand_more</span>
                                </div>
                            </button>
                            <div id="accordion-${index}" class="accordion-content hidden">
                                <div class="px-4 pb-3 border-t border-slate-100 dark:border-slate-800">
                                    ${usage.used > 0 && allLeaves.length > 0 ? `
                                        <div class="mt-3 overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-xs text-slate-500 border-b border-slate-200 dark:border-slate-700">
                                                        <th class="text-left py-2 px-2 font-semibold">İzin Türü</th>
                                                        <th class="text-left py-2 px-2 font-semibold">Tarih</th>
                                                        <th class="text-center py-2 px-2 font-semibold">Süre (Gün)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${allLeaves.map(izin => `
                                                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                                                            <td class="py-2.5 px-2 text-slate-900 dark:text-white font-medium">${izin.izin_tipi_adi || 'Yıllık İzin'}</td>
                                                            <td class="py-2.5 px-2 text-slate-600 dark:text-slate-400">
                                                                <div class="flex flex-col">
                                                                    <span class="text-xs">Başlangıç: ${izin.baslangic}</span>
                                                                    <span class="text-xs">Bitiş: ${izin.bitis}</span>
                                                                </div>
                                                            </td>
                                                            <td class="py-2.5 px-2 text-center font-bold text-primary">${izin.toplam_gun}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    ` : `
                                        <div class="mt-2 py-3 text-center">
                                            <p class="text-xs text-slate-500">Bu yıl için kullanılan izin bulunmuyor</p>
                                        </div>
                                    `}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = '<div class="text-center py-8 text-slate-500">Hakediş detayı bulunamadı.</div>';
            }
        } catch (error) {
            console.error('Hakediş detay yükleme hatası:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Veriler yüklenirken hata oluştu.</div>';
        }

        Modal.open('hakedis-detay-modal');
    }

    function toggleYearAccordion(index) {
        const content = document.getElementById(`accordion-${index}`);
        const icon = document.getElementById(`accordion-icon-${index}`);

        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.textContent = 'expand_less';
        } else {
            content.classList.add('hidden');
            icon.textContent = 'expand_more';
        }
    }

    async function loadIzinler() {
        const container = document.getElementById('izin-list');

        try {
            const response = await API.request('getIzinler');

            if (response.success && response.data.length > 0) {
                izinlerData = response.data;
                renderIzinler();
            } else {
                container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">calendar_today</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Henüz izin kaydı yok</p>
                    <p class="text-sm text-slate-500">Yeni izin talebi oluşturabilirsiniz.</p>
                </div>
            `;
            }
        } catch (error) {
            console.error('İzin load error:', error);
            container.innerHTML = '<p class="text-center text-slate-500 py-8">Veriler yüklenemedi</p>';
        }
    }

    function renderIzinler() {
        const container = document.getElementById('izin-list');
        let filtered = izinlerData;

        if (currentFilter !== 'all') {
            filtered = izinlerData.filter(i => i.durum === currentFilter);
        }

        if (filtered.length === 0) {
            container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-symbols-outlined">filter_list</span>
                </div>
                <p class="text-slate-600 dark:text-slate-400 font-medium">Kayıt bulunamadı</p>
                <p class="text-sm text-slate-500">Bu filtreye uygun izin bulunmuyor.</p>
            </div>
        `;
            return;
        }

        container.innerHTML = filtered.map(izin => `
        <div class="card p-4" onclick="showIzinDetay(${izin.id})">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl ${izin.renk} flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-xl">${izin.ikon}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-bold text-sm text-slate-900 dark:text-white">${izin.izin_tipi_text}</p>
                            <p class="text-xs text-slate-500 mt-0.5">${izin.baslangic} - ${izin.bitis}</p>
                        </div>
                        <span class="badge ${getStatusBadge(izin.durum)} flex-shrink-0">${izin.durum_text}</span>
                    </div>
                    <div class="flex items-center gap-4 mt-3">
                        <div class="flex items-center gap-1 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            <span>${izin.toplam_gun} Gün</span>
                        </div>
                        <div class="flex items-center gap-1 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-sm">calendar_today</span>
                            <span>${izin.talep_tarihi}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    }

    function filterIzinler(filter) {
        currentFilter = filter;

        // Update filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-primary', 'text-white');
            btn.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-400');
        });

        const activeBtn = document.querySelector(`[data-filter="${filter}"]`);
        activeBtn.classList.add('active', 'bg-primary', 'text-white');
        activeBtn.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-400');

        renderIzinler();
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'onaylandi':
            case 'onaylandı':
                return 'badge-success bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
            case 'beklemede': return 'badge-warning';
            case 'reddedildi': return 'badge-danger';
            case 'iptal_edildi': return 'badge-gray bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
            default: return 'badge-gray';
        }
    }

    async function showIzinDetay(id) {
        Modal.open('izin-detay-modal');

        const izin = izinlerData.find(i => parseInt(i.id) === parseInt(id));
        if (!izin) return;

        document.getElementById('izin-detay-content').innerHTML = `
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-12 h-12 rounded-xl ${izin.renk} flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">${izin.ikon}</span>
                </div>
                <div>
                    <p class="font-bold text-slate-900 dark:text-white">${izin.izin_tipi_text}</p>
                    <span class="badge ${getStatusBadge(izin.durum)}">${izin.durum_text}</span>
                </div>
            </div>
            
            ${izin.red_nedeni ? `
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 p-3 rounded-xl">
                <p class="text-xs text-red-500 mb-1 font-medium">Red Nedeni</p>
                <p class="text-sm text-red-700 dark:text-red-400">${izin.red_nedeni}</p>
            </div>
            ` : ''}
            
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                    <p class="text-xs text-slate-500 mb-1">Başlangıç</p>
                    <p class="font-semibold text-slate-900 dark:text-white">${izin.baslangic}</p>
                </div>
                <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                    <p class="text-xs text-slate-500 mb-1">Bitiş</p>
                    <p class="font-semibold text-slate-900 dark:text-white">${izin.bitis}</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 py-3 border-t border-b border-slate-100 dark:border-slate-800">
                <span class="material-symbols-outlined text-slate-400">schedule</span>
                <span class="text-slate-600 dark:text-slate-400">Toplam Süre:</span>
                <span class="font-bold text-slate-900 dark:text-white ml-auto">${izin.toplam_gun} Gün</span>
            </div>
            
            ${izin.aciklama ? `
            <div>
                <p class="text-xs text-slate-500 mb-1">Açıklama</p>
                <p class="text-sm text-slate-700 dark:text-slate-300">${izin.aciklama}</p>
            </div>
            ` : ''}
        </div>
    `;

        // Action buttons based on status
        const actionsContainer = document.getElementById('izin-detay-actions');
        if (izin.durum === 'beklemede') {
            actionsContainer.innerHTML = `
            <button onclick="cancelIzin(${izin.id})" class="flex-1 py-3 bg-red-50 dark:bg-red-900/20 text-red-600 font-semibold rounded-xl">
                İptal Et
            </button>
            <button onclick="Modal.close('izin-detay-modal')" class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        `;
        } else {
            actionsContainer.innerHTML = `
            <button onclick="Modal.close('izin-detay-modal')" class="w-full py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        `;
        }
    }

    async function submitIzinTalebi(form) {
        const formData = Form.serialize(form);

        if (!Form.validate(form)) {
            Toast.show('Lütfen gerekli alanları doldurun', 'error');
            return;
        }

        // Kalan izin kontrolü
        const baslangic = formData.baslangic_tarihi;
        const bitis = formData.bitis_tarihi;

        if (!baslangic || !bitis) {
            Toast.show('Lütfen tarihleri seçiniz', 'error');
            return;
        }

        // Gün sayısını hesapla
        const diff = new Date(bitis) - new Date(baslangic);
        const toplamGun = Math.round(diff / (1000 * 60 * 60 * 24)) + 1;

        if (toplamGun <= 0) {
            Toast.show('Bitiş tarihi başlangıç tarihinden önce olamaz', 'error');
            return;
        }

        // Kalan izin kontrolü
        if (hakedisData && toplamGun > hakedisData.kalan_izin) {
            Toast.show(
                `Yetersiz izin hakkı! Talebiniz: ${toplamGun} gün, Kalan izniniz: ${hakedisData.kalan_izin} gün`,
                'error'
            );
            return;
        }

        try {
            const response = await API.request('createIzinTalebi', formData);

            if (response.success) {
                Toast.show('İzin talebiniz başarıyla gönderildi', 'success');
                Modal.close('izin-modal');
                form.reset();
                loadIzinler();
                loadIzinStats();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    async function cancelIzin(id) {
        const isConfirmed = await Alert.confirm(
            'İptal Et',
            'Bu izin talebini iptal etmek istediğinize emin misiniz?',
            'Evet, İptal Et',
            'Vazgeç'
        );

        if (!isConfirmed) return;

        try {
            const response = await API.request('cancelIzinTalebi', { id: id });

            if (response.success) {
                Toast.show('İzin talebi iptal edildi', 'success');
                Modal.close('izin-detay-modal');
                loadIzinler();
                loadIzinStats();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }
</script>