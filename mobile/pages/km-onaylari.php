<?php
/**
 * Mobil KM Onay Sayfası - Server Side Paginated
 */

use App\Model\AracKmBildirimModel;
use App\Helper\Helper;
use App\Service\Gate;

$KmBildirim = new AracKmBildirimModel();

$initialShow = $_GET['show'] ?? 'pending';
if (!in_array($initialShow, ['pending', 'unreported', 'approved', 'rejected'])) {
    $initialShow = 'pending';
}

$hasEditPermission = Gate::allows('onaylikm_duzenle') || Gate::isSuperAdmin();
$initialCounts = $KmBildirim->getReportCounts();
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-to-br from-cyan-600 to-cyan-400 text-white px-4 pt-6 pb-14 rounded-b-3xl relative overflow-hidden shadow-lg">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex justify-between items-center">
        <div>
            <h2 id="header-page-title" class="text-2xl font-extrabold leading-tight tracking-tight">
                KM Onayları
            </h2>
            <p id="header-page-subtitle" class="text-white/80 text-sm mt-1 font-medium">Bekleyen kilometre bildirimleri</p>
        </div>
        <div class="flex gap-3">
            <div class="text-center">
                <div class="bg-white/20 rounded-xl px-4 py-2 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span id="header-pending-count" class="block text-2xl font-black"><?= $initialCounts['pending'] ?></span>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-white/90">Bekliyor</span>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="px-4 mt-[-36px] relative z-10 space-y-4 pb-6">
    <!-- Filter Tabs -->
    <div class="flex gap-2 p-1 bg-white dark:bg-card-dark rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar">
        <button onclick="setTab('pending')" data-tab="pending" class="km-tab-btn shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all <?= $initialShow === 'pending' ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400' : 'text-slate-500' ?>">
            <span class="material-symbols-outlined text-[16px]">schedule</span>
            Bekleyen
            <span id="tab-badge-pending" class="bg-cyan-500 text-white text-[9px] px-1.5 py-0.5 rounded-full ml-1 <?= $initialCounts['pending'] > 0 ? '' : 'hidden' ?>"><?= $initialCounts['pending'] ?></span>
        </button>
        <button onclick="setTab('unreported')" data-tab="unreported" class="km-tab-btn shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all <?= $initialShow === 'unreported' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'text-slate-500' ?>">
            <span class="material-symbols-outlined text-[16px]">error</span>
            Yapmayanlar
            <span id="tab-badge-unreported" class="bg-amber-500 text-white text-[9px] px-1.5 py-0.5 rounded-full ml-1 <?= $initialCounts['unreported'] > 0 ? '' : 'hidden' ?>"><?= $initialCounts['unreported'] ?></span>
        </button>
        <button onclick="setTab('approved')" data-tab="approved" class="km-tab-btn shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all <?= $initialShow === 'approved' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'text-slate-500' ?>">
            <span class="material-symbols-outlined text-[16px]">check_circle</span>
            Onaylanan
            <span id="tab-badge-approved" class="bg-emerald-600 text-white text-[9px] px-1.5 py-0.5 rounded-full ml-1 <?= $initialCounts['approved'] > 0 ? '' : 'hidden' ?>"><?= $initialCounts['approved'] ?></span>
        </button>
        <button onclick="setTab('rejected')" data-tab="rejected" class="km-tab-btn shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all <?= $initialShow === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'text-slate-500' ?>">
            <span class="material-symbols-outlined text-[16px]">cancel</span>
            Reddedilen
            <span id="tab-badge-rejected" class="bg-rose-500 text-white text-[9px] px-1.5 py-0.5 rounded-full ml-1 <?= $initialCounts['rejected'] > 0 ? '' : 'hidden' ?>"><?= $initialCounts['rejected'] ?></span>
        </button>
    </div>

    <!-- Search input -->
    <div class="relative">
        <input type="text" id="km-search-input" oninput="debounceSearch()" placeholder="Personel adı veya plaka ile ara..." 
            class="w-full h-11 pl-10 pr-4 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-medium text-slate-800 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-cyan-500 transition-all shadow-sm">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">search</span>
    </div>

    <!-- List Container -->
    <div id="km-reports-container" class="space-y-4">
        <!-- Dynamic content via JS -->
    </div>

    <!-- Sayfalama Kontrolleri -->
    <div id="km-pagination-container" class="flex items-center justify-between pt-2 pb-6 px-1 hidden">
        <button id="prev-page-btn" onclick="prevPage()" class="h-10 px-4 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1 shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined text-base">chevron_left</span> Önceki
        </button>
        <div class="text-center">
            <span id="page-info-text" class="text-xs font-black text-slate-600 dark:text-slate-300">Sayfa 1 / 1</span>
            <p id="total-info-text" class="text-[10px] font-bold text-slate-400">0 Kayıt</p>
        </div>
        <button id="next-page-btn" onclick="nextPage()" class="h-10 px-4 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1 shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
            Sonraki <span class="material-symbols-outlined text-base">chevron_right</span>
        </button>
    </div>
</div>

<!-- Image View Modal -->
<div id="km-img-modal" class="fixed inset-0 z-[200] bg-black/95 hidden opacity-0 transition-opacity flex flex-col items-center justify-center" onclick="closeKmImage()">
    <button class="absolute top-6 right-6 w-12 h-12 rounded-full bg-white/10 text-white flex items-center justify-center backdrop-blur-md">
        <span class="material-symbols-outlined">close</span>
    </button>
    <div class="w-full h-full p-4 flex items-center justify-center">
        <img id="km-modal-img" src="" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl">
    </div>
    <div class="absolute bottom-10 left-0 right-0 text-center text-white p-4">
        <h3 id="km-modal-title" class="text-xl font-black mb-1"></h3>
        <p id="km-modal-date" class="text-white/60 text-sm font-medium"></p>
    </div>
</div>

<!-- KM Düzelt ve Onayla Modalı -->
<div id="km-edit-modal" class="fixed inset-0 z-[200] bg-slate-900/60 backdrop-blur-sm hidden opacity-0 transition-opacity flex items-center justify-center p-4">
    <div class="bg-white dark:bg-card-dark rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300">
        <!-- Başlık -->
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
            <h3 class="font-black text-sm text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-amber-500 text-lg">edit_note</span> KM Düzelt ve Onayla
            </h3>
            <button onclick="closeKmEditModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center active:scale-90 transition-transform">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
        <!-- İçerik -->
        <div class="p-5">
            <form id="km-edit-form" onsubmit="submitKmEdit(event)">
                <input type="hidden" id="edit_km_id" name="id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Yeni KM Değeri</label>
                        <input type="number" id="edit_km_value" name="km" required 
                            class="w-full h-11 px-3 border border-slate-200 dark:border-slate-800 rounded-xl text-sm font-bold bg-transparent dark:text-white focus:outline-none focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Açıklama / Not</label>
                        <textarea id="edit_km_aciklama" name="aciklama" placeholder="Değişiklik nedenini yazabilirsiniz..." rows="3"
                            class="w-full p-3 border border-slate-200 dark:border-slate-800 rounded-xl text-sm bg-transparent dark:text-white focus:outline-none focus:border-cyan-500 resize-none"></textarea>
                    </div>
                </div>
                
                <div class="flex gap-2.5 mt-6">
                    <button type="button" onclick="closeKmEditModal()" 
                        class="flex-1 h-11 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition-all">
                        Vazgeç
                    </button>
                    <button type="submit" 
                        class="flex-[2] h-11 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-sm shadow-amber-500/20 transition-all flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px] filled">check_circle</span> Onayla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentTab = '<?= $initialShow ?>';
let currentPage = 1;
let totalPages = 1;
let searchQuery = '';
let searchTimer = null;
const perPage = 15;

const titles = {
    pending: { title: "KM Onayları", subtitle: "Bekleyen kilometre bildirimleri" },
    unreported: { title: "Bildirim Yapmayanlar", subtitle: "Bugün KM bildirimi yapmayan personeller" },
    approved: { title: "Onaylanan KM'ler", subtitle: "Daha önce onaylanmış kayıtlar" },
    rejected: { title: "Reddedilen KM'ler", subtitle: "İşleme alınmayan bildirimler" }
};

document.addEventListener('DOMContentLoaded', function() {
    loadReports(1);
});

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function numberFormat(num) {
    if (isNaN(num)) return '0';
    return Number(num).toLocaleString('tr-TR');
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}.${month}.${year}`;
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    const hours = String(d.getHours()).padStart(2, '0');
    const mins = String(d.getMinutes()).padStart(2, '0');
    return `${hours}:${mins}`;
}

function setTab(status) {
    currentTab = status;
    currentPage = 1;
    
    // Update tab UI buttons
    document.querySelectorAll('.km-tab-btn').forEach(btn => {
        const tab = btn.getAttribute('data-tab');
        btn.className = 'km-tab-btn shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all text-slate-500';
        
        if (tab === currentTab) {
            if (tab === 'pending') btn.className += ' bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400';
            if (tab === 'unreported') btn.className += ' bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
            if (tab === 'approved') btn.className += ' bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
            if (tab === 'rejected') btn.className += ' bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        }
    });

    // Update headers
    if (titles[currentTab]) {
        document.getElementById('header-page-title').textContent = titles[currentTab].title;
        document.getElementById('header-page-subtitle').textContent = titles[currentTab].subtitle;
    }

    loadReports(1);
}

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchQuery = document.getElementById('km-search-input').value.trim();
        currentPage = 1;
        loadReports(1);
    }, 400);
}

function prevPage() {
    if (currentPage > 1) {
        loadReports(currentPage - 1);
    }
}

function nextPage() {
    if (currentPage < totalPages) {
        loadReports(currentPage + 1);
    }
}

function loadReports(page) {
    currentPage = page;
    const container = document.getElementById('km-reports-container');
    const paginationContainer = document.getElementById('km-pagination-container');
    
    // Skeleton / Loading state
    container.innerHTML = `
        <div class="flex justify-center items-center py-16">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-cyan-600"></div>
        </div>
    `;

    $.ajax({
        url: '../views/arac-takip/api.php',
        type: 'POST',
        data: {
            action: 'get-mobile-km-onaylari',
            status: currentTab,
            page: currentPage,
            perPage: perPage,
            search: searchQuery
        },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                updateCounts(res.counts);
                renderReports(res.reports);
                updatePagination(res.pagination);
            } else {
                container.innerHTML = `
                    <div class="bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 p-4 rounded-2xl text-center font-bold text-xs border border-rose-100 dark:border-rose-800">
                        ${escapeHtml(res.message || 'Veriler yüklenirken hata oluştu')}
                    </div>
                `;
                paginationContainer.classList.add('hidden');
            }
        },
        error: function() {
            container.innerHTML = `
                <div class="bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 p-4 rounded-2xl text-center font-bold text-xs border border-rose-100 dark:border-rose-800">
                    Bağlantı hatası oluştu
                </div>
            `;
            paginationContainer.classList.add('hidden');
        }
    });
}

function updateCounts(counts) {
    if (!counts) return;
    document.getElementById('header-pending-count').textContent = counts.pending || 0;
    
    const setBadge = (id, count) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = count;
            if (count > 0) el.classList.remove('hidden');
            else el.classList.add('hidden');
        }
    };

    setBadge('tab-badge-pending', counts.pending || 0);
    setBadge('tab-badge-unreported', counts.unreported || 0);
    setBadge('tab-badge-approved', counts.approved || 0);
    setBadge('tab-badge-rejected', counts.rejected || 0);
}

function renderReports(reports) {
    const container = document.getElementById('km-reports-container');
    
    if (!reports || reports.length === 0) {
        container.innerHTML = `
            <div class="bg-white dark:bg-card-dark rounded-2xl p-8 text-center border border-dashed border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800 text-slate-300 dark:text-slate-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-4xl">speed</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white">Bildirim Bulunmuyor</h3>
                <p class="text-xs text-slate-500 mt-1">Bu arama veya kategoride gösterilecek kayıt yok.</p>
            </div>
        `;
        return;
    }

    let html = '';

    if (currentTab === 'unreported') {
        reports.forEach(r => {
            const firstChar = escapeHtml((r.personel_adi || 'P').charAt(0).toUpperCase());
            html += `
                <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center text-amber-600 font-bold text-sm">
                            ${firstChar}
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-700 dark:text-white">${escapeHtml(r.personel_adi)}</h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">${escapeHtml(r.plaka)} • Bildirim Yapmadı</p>
                        </div>
                    </div>
                    <button onclick="sendReminder('${escapeHtml(r.personel_id)}')" class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center active:scale-95 transition-all">
                        <span class="material-symbols-outlined text-[20px]">notifications_active</span>
                    </button>
                </div>
            `;
        });
    } else {
        reports.forEach(r => {
            const imgUrl = r.resim_yolu ? '../' + r.resim_yolu : '../assets/images/no-image.png';
            const personelResim = r.personel_resim ? '../' + r.personel_resim : '';
            const firstChar = escapeHtml((r.personel_adi || 'P').charAt(0).toUpperCase());
            const turText = r.tur === 'sabah' ? 'Sabah Bildirimi' : 'Akşam Bildirimi';
            const formattedKm = numberFormat(r.bitis_km);
            const dateFmt = formatDate(r.tarih);
            
            let statusActionHtml = '';
            if (currentTab === 'pending') {
                statusActionHtml = `
                    <div class="flex gap-2 mt-4">
                        <button onclick="rejectKm(${r.id})" class="flex-1 h-10 bg-rose-50 hover:bg-rose-100 text-rose-600 dark:bg-rose-900/20 dark:text-rose-400 rounded-xl text-[11px] font-bold transition-all flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">close</span> Red
                        </button>
                        <button onclick="editAndApproveKm(${r.id}, ${r.bitis_km})" class="flex-1 h-10 bg-amber-50 hover:bg-amber-100 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400 rounded-xl text-[11px] font-bold transition-all flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">edit</span> Düzelt
                        </button>
                        <button onclick="approveKm(${r.id})" class="flex-[1.5] h-10 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-[11px] font-bold shadow-sm shadow-cyan-500/20 transition-all flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-[16px] filled">check_circle</span> Onayla
                        </button>
                    </div>
                `;
            } else {
                const isApproved = currentTab === 'approved';
                const statusBadgeClass = isApproved 
                    ? 'bg-green-50 text-green-600 dark:bg-green-900/20 border border-green-100 dark:border-green-800' 
                    : 'bg-red-50 text-red-600 dark:bg-red-900/20 border border-red-100 dark:border-red-800';
                const iconName = isApproved ? 'check_circle' : 'cancel';
                const statusLabel = isApproved ? 'ONAYLANDI' : 'REDDEDİLDİ';
                const approver = escapeHtml(r.onaylayan_adi || 'Sistem');

                statusActionHtml = `
                    <div class="flex items-center justify-between pt-3 mt-3 border-t border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[14px] text-slate-400">person_check</span>
                            <span class="text-[10px] text-slate-400 font-medium">${approver}</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold ${statusBadgeClass}">
                            <span class="material-symbols-outlined text-[12px] filled">${iconName}</span>
                            ${statusLabel}
                        </span>
                    </div>
                `;
            }

            let aciklamaHtml = '';
            if (r.aciklama) {
                aciklamaHtml = `
                    <div class="bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800 mt-3">
                        <p class="text-[11px] text-slate-500 italic leading-relaxed line-clamp-2">"${escapeHtml(r.aciklama)}"</p>
                    </div>
                `;
            }

            let avatarHtml = '';
            if (personelResim) {
                avatarHtml = `<img src="${escapeHtml(personelResim)}" class="w-10 h-10 rounded-full object-cover border border-slate-100 dark:border-slate-800">`;
            } else {
                avatarHtml = `<div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 font-bold text-xs">${firstChar}</div>`;
            }

            html += `
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden km-report-card" data-id="${r.id}">
                    <div class="relative h-48 bg-slate-100 dark:bg-slate-900 overflow-hidden cursor-pointer active:opacity-90" onclick="viewKmImage('${escapeHtml(imgUrl)}', '${escapeHtml(r.plaka)}')">
                        <img src="${escapeHtml(imgUrl)}" class="w-full h-full object-cover" alt="KM Görseli">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                        <div class="absolute bottom-3 left-3 right-3 flex justify-between items-end">
                            <div>
                                <span class="bg-white/20 backdrop-blur-md text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider border border-white/20">
                                    ${turText}
                                </span>
                                <h3 class="text-white font-black text-lg leading-tight mt-1">${escapeHtml(r.plaka)}</h3>
                            </div>
                            <div class="text-right">
                                <p class="text-white/70 text-[10px] font-bold">${dateFmt}</p>
                                <p class="text-cyan-400 font-black text-xl leading-none">${formattedKm} <span class="text-[10px]">KM</span></p>
                            </div>
                        </div>
                        <div class="absolute top-3 right-3">
                            <div class="w-8 h-8 rounded-full bg-black/30 backdrop-blur-md flex items-center justify-center text-white">
                                <span class="material-symbols-outlined text-[18px]">zoom_in</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="flex items-center gap-3">
                            ${avatarHtml}
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] text-slate-400 font-bold uppercase leading-none mb-1">Bildiren Personel</p>
                                <p class="text-xs font-black text-slate-700 dark:text-white truncate">${escapeHtml(r.personel_adi)}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">ARAÇ</p>
                                <p class="text-[11px] font-black text-slate-600 dark:text-slate-400">${escapeHtml(r.marka || '')} ${escapeHtml(r.model || '')}</p>
                            </div>
                        </div>

                        ${aciklamaHtml}
                        ${statusActionHtml}
                    </div>
                </div>
            `;
        });
    }

    container.innerHTML = html;
}

function updatePagination(p) {
    const container = document.getElementById('km-pagination-container');
    if (!p || p.total === 0) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');
    currentPage = p.page;
    totalPages = p.totalPages;

    document.getElementById('page-info-text').textContent = `Sayfa ${p.page} / ${p.totalPages}`;
    document.getElementById('total-info-text').textContent = `${numberFormat(p.total)} Kayıt`;

    const prevBtn = document.getElementById('prev-page-btn');
    const nextBtn = document.getElementById('next-page-btn');

    prevBtn.disabled = (currentPage <= 1);
    nextBtn.disabled = (currentPage >= totalPages);
}

function editAndApproveKm(id, currentKm) {
    document.getElementById('edit_km_id').value = id;
    document.getElementById('edit_km_value').value = currentKm;
    document.getElementById('edit_km_aciklama').value = '';
    
    const modal = document.getElementById('km-edit-modal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('.transform').classList.remove('scale-95');
        modal.querySelector('.transform').classList.add('scale-100');
    }, 10);
}

function closeKmEditModal() {
    const modal = document.getElementById('km-edit-modal');
    modal.classList.add('opacity-0');
    modal.querySelector('.transform').classList.remove('scale-100');
    modal.querySelector('.transform').classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function submitKmEdit(e) {
    e.preventDefault();
    const id = document.getElementById('edit_km_id').value;
    const km = document.getElementById('edit_km_value').value;
    const aciklama = document.getElementById('edit_km_aciklama').value;
    
    closeKmEditModal();
    performKmAction('km-onay-duzelt-onayla', { id: id, km: km, aciklama: aciklama });
}

function viewKmImage(url, title) {
    const modal = document.getElementById('km-img-modal');
    const img = document.getElementById('km-modal-img');
    const titleEl = document.getElementById('km-modal-title');
    
    img.src = url;
    titleEl.textContent = title;
    
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.remove('opacity-0'), 10);
}

function closeKmImage() {
    const modal = document.getElementById('km-img-modal');
    modal.classList.add('opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function approveKm(id) {
    Alert.confirm('KM Bildirimi Onayı', 'Bu kilometre bildirimini onaylamak istiyor musunuz?', 'Evet, Onayla').then(res => {
        if (res) {
            performKmAction('km-onay-ver', { id: id });
        }
    });
}

function rejectKm(id) {
    Alert.prompt('KM Bildirimi Reddi', 'Reddetme sebebinizi yazın (isteğe bağlı):', 'Reddet', 'Açıklama...').then(reason => {
        if (reason !== false) {
            performKmAction('km-onay-reddet', { id: id, red_nedeni: reason });
        }
    });
}

function performKmAction(action, data) {
    Loading.show();
    $.ajax({
        url: '../views/arac-takip/api.php',
        type: 'POST',
        data: { action: action, ...data },
        success: function(res) {
            Loading.hide();
            try {
                const response = typeof res === 'object' ? res : JSON.parse(res);
                if (response.status === 'success' || response.success) {
                    Toast.show(response.message || 'İşlem başarılı');
                    loadReports(currentPage);
                } else {
                    Alert.error('Hata', response.message || 'Bir hata oluştu');
                }
            } catch (e) {
                Alert.error('Hata', 'Sunucudan geçersiz yanıt alındı');
            }
        },
        error: function() {
            Loading.hide();
            Alert.error('Hata', 'Bağlantı hatası oluştu');
        }
    });
}

function sendReminder(personelId) {
    Loading.show();
    $.ajax({
        url: '../views/arac-takip/api.php',
        type: 'POST',
        data: { action: 'km-hatirlat', personel_id: personelId },
        success: function(res) {
            Loading.hide();
            Toast.show('Hatırlatma gönderildi');
        },
        error: function() {
            Loading.hide();
            Toast.show('Hata oluştu');
        }
    });
}
</script>
