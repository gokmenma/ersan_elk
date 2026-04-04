<?php
/**
 * Mobil Yardım ve Destek Sayfası
 * Yetkiye göre yönetici veya kullanıcı görünümü sunar.
 */

use App\Service\Gate;
use App\Helper\Security;

// Yetkileri kontrol et
$isAdmin = Gate::allows('admin_destek_talebi') || Gate::isSuperAdmin();
$isApprover = Gate::allows('destek_talebi_onaylama');

// Sayfa Başlığı
$pageTitle = $isAdmin ? 'Destek Talepleri (Yönetim)' : 'Yardım ve Destek';

// Filtreler
$statusFilter = $_GET['status'] ?? '';
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-to-br from-indigo-700 to-indigo-500 text-white px-4 pt-6 pb-14 rounded-b-3xl relative overflow-hidden shadow-lg">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-extrabold leading-tight tracking-tight">
                <?= $pageTitle ?>
            </h2>
            <p class="text-white/80 text-sm mt-1 font-medium">
                <?= $isAdmin ? 'Tüm personel talepleri' : 'Destek taleplerim ve yanıtlar' ?>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="refreshTickets()" class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white active:scale-95 transition-transform border border-white/10">
                <span class="material-symbols-outlined text-[22px]">refresh</span>
            </button>
            <?php if (!$isAdmin): ?>
                <button onclick="openNewTicketModal()" class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white active:scale-95 transition-transform border border-white/10">
                    <span class="material-symbols-outlined text-[22px]">add</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="px-4 mt-[-36px] relative z-10 space-y-4 pb-6">

    <!-- Stats Summary (Admin & User different) -->
    <div id="stats-container" class="grid grid-cols-3 gap-2">
        <div class="bg-white dark:bg-card-dark p-3 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 text-center">
            <span id="stat-total" class="block text-xl font-black text-indigo-600 dark:text-indigo-400">0</span>
            <span class="text-[9px] uppercase font-bold tracking-wider text-slate-400">Toplam</span>
        </div>
        <div class="bg-white dark:bg-card-dark p-3 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 text-center">
            <span id="stat-pending" class="block text-xl font-black text-amber-500">0</span>
            <span class="text-[9px] uppercase font-bold tracking-wider text-slate-400">Bekleyen</span>
        </div>
        <div class="bg-white dark:bg-card-dark p-3 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 text-center">
            <span id="stat-resolved" class="block text-xl font-black text-emerald-500">0</span>
            <span class="text-[9px] uppercase font-bold tracking-wider text-slate-400">Çözüldü</span>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="relative">
        <span class="material-symbols-outlined absolute left-4 top-3.5 text-slate-400">search</span>
        <input type="text" id="ticket-search" oninput="debounceSearch()" class="w-full pl-12 pr-4 py-4 bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-2xl shadow-sm text-sm font-semibold text-slate-800 dark:text-white focus:border-indigo-500 focus:ring-0 outline-none transition-all" placeholder="Talep konusu veya No ile ara...">
    </div>

    <!-- Status Filters -->
    <div class="flex gap-2 p-1 bg-white dark:bg-card-dark rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar">
        <button onclick="setFilter('')" class="filter-btn flex-none py-2 px-4 rounded-lg text-xs font-bold transition-all bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400" data-status="">
            Tümü
        </button>
        <button onclick="setFilter('acik')" class="filter-btn flex-none py-2 px-4 rounded-lg text-xs font-bold transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800" data-status="acik">
            Açık
        </button>
        <button onclick="setFilter('yanitlandi')" class="filter-btn flex-none py-2 px-4 rounded-lg text-xs font-bold transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800" data-status="yanitlandi">
            Yanıtlandı
        </button>
        <button onclick="setFilter('personel_yaniti')" class="filter-btn flex-none py-2 px-4 rounded-lg text-xs font-bold transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800" data-status="personel_yaniti">
            Personel Yanıtı
        </button>
        <button onclick="setFilter('kapali')" class="filter-btn flex-none py-2 px-4 rounded-lg text-xs font-bold transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800" data-status="kapali">
            Kapatıldı
        </button>
    </div>

    <!-- Ticket List -->
    <div id="tickets-list" class="space-y-3">
        <!-- Will be filled with AJAX -->
        <div class="flex justify-center py-20">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
        </div>
    </div>
    
    <!-- Load More -->
    <div id="load-more-container" class="hidden py-1 pb-6 text-center">
        <button onclick="loadMoreTickets()" class="px-8 py-4 bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-2xl text-[11px] font-black text-slate-500 shadow-sm active:scale-95 transition-all uppercase tracking-widest">
            Daha Fazla Yükle
        </button>
    </div>

</div>

<!-- New Ticket Modal (Bottom Sheet for better mobile UX) -->
<div id="new-ticket-sheet" class="fixed inset-0 z-[100] pointer-events-none">
    <div id="new-ticket-overlay" class="absolute inset-0 bg-slate-900/60 opacity-0 transition-opacity duration-300" onclick="closeNewTicketModal()"></div>
    <div id="new-ticket-content" class="absolute bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl transform translate-y-full transition-transform duration-300 shadow-2xl flex flex-col max-h-[90vh]">
        <div class="pt-4 pb-2 flex justify-center shrink-0">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        <div class="px-5 pb-8 overflow-y-auto no-scrollbar">
            <div class="flex items-center justify-between mb-5 sticky top-0 bg-white dark:bg-card-dark py-1">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Yeni Destek Talebi</h3>
                <button onclick="closeNewTicketModal()" class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
            
            <form id="new-ticket-form" class="space-y-4">
                <input type="hidden" name="action" value="create-ticket">
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Konu</label>
                    <input type="text" name="konu" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border-2 border-slate-100 dark:border-slate-700 rounded-2xl text-sm font-semibold text-slate-800 dark:text-white focus:border-indigo-500 focus:ring-0 outline-none transition-colors" placeholder="Talebinizi kısaca özetleyin">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Kategori</label>
                        <select name="kategori" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border-2 border-slate-100 dark:border-slate-700 rounded-2xl text-sm font-semibold text-slate-800 dark:text-white focus:border-indigo-500 focus:ring-0 outline-none transition-colors appearance-none">
                            <option value="Genel">Genel</option>
                            <option value="Teknik Sorun">Teknik Sorun</option>
                            <option value="Hata Bildirimi">Hata Bildirimi</option>
                            <option value="İşleyiş/Süreç">İşleyiş/Süreç</option>
                            <option value="Diğer">Diğer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Öncelik</label>
                        <select name="oncelik" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border-2 border-slate-100 dark:border-slate-700 rounded-2xl text-sm font-semibold text-slate-800 dark:text-white focus:border-indigo-500 focus:ring-0 outline-none transition-colors appearance-none">
                            <option value="dusuk">Düşük</option>
                            <option value="orta" selected>Orta</option>
                            <option value="yuksek">Yüksek</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Mesajınız</label>
                    <textarea name="mesaj" required rows="4" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border-2 border-slate-100 dark:border-slate-700 rounded-2xl text-sm font-semibold text-slate-800 dark:text-white focus:border-indigo-500 focus:ring-0 outline-none transition-colors" placeholder="Detaylı açıklama yapmanız size daha hızlı yardımcı olmamızı sağlar..."></textarea>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 ml-1">Dosya Eki (Opsiyonel)</label>
                    <input type="file" name="dosya" id="new-ticket-file" class="hidden" onchange="updateFileName(this)">
                    <button type="button" onclick="document.getElementById('new-ticket-file').click()" class="w-full flex items-center justify-between gap-3 p-4 bg-slate-50 dark:bg-slate-800 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl text-sm font-semibold text-slate-500 transition-colors hover:border-indigo-400">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">attach_file</span>
                            <span id="file-name-display">Dosya Seç</span>
                        </div>
                        <span class="material-symbols-outlined text-slate-300">upload</span>
                    </button>
                    <p class="text-[10px] text-slate-400 mt-2 ml-1">JPG, PNG veya WEBP formatında görsel ekleyebilirsiniz.</p>
                </div>

                <button type="submit" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-base font-bold shadow-lg shadow-indigo-600/30 transition-all active:scale-95 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">send</span>
                    Talebi Oluştur
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Ticket Detail Bottom Sheet -->
<div id="ticket-detail-sheet" class="fixed inset-0 z-[110] pointer-events-none">
    <div id="ticket-detail-overlay" class="absolute inset-0 bg-slate-900/60 opacity-0 transition-opacity duration-300" onclick="closeTicketDetail()"></div>
    <div id="ticket-detail-content" class="absolute bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl transform translate-y-full transition-transform duration-300 shadow-2xl flex flex-col h-[90vh]">
        <div class="pt-4 pb-2 flex justify-center shrink-0">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        <div id="ticket-detail-body" class="px-5 pb-6 overflow-y-auto no-scrollbar flex-grow">
            <!-- AJAX content -->
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    refreshTickets();
    
    // Close dropdown on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#reply-actions-trigger').length) {
            $('#reply-actions-menu').addClass('hidden');
        }
    });
});

let currentStatusFilter = '';
let searchQuery = '';
let currentPage = 1;
const limit = 20;
let hasMore = true;
const API_URL = '../views/yardim/api.php';
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

function setFilter(status) {
    currentStatusFilter = status;
    $('.filter-btn').removeClass('bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400').addClass('text-slate-500');
    $(`.filter-btn[data-status="${status}"]`).addClass('bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 active-filter').removeClass('text-slate-500');
    currentPage = 1;
    refreshTickets();
}

let searchTimer;
function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchQuery = $('#ticket-search').val().trim();
        currentPage = 1;
        refreshTickets();
    }, 500);
}

function refreshTickets() {
    if (currentPage === 1) {
        $('#tickets-list').html(`<div class="flex justify-center py-20"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div></div>`);
        $('#load-more-container').addClass('hidden');
    }
    
    const action = isAdmin ? 'get-tickets-admin' : 'get-tickets-pwa';
    
    $.post(API_URL, { 
        action: action, 
        status: currentStatusFilter,
        search: searchQuery,
        page: currentPage,
        limit: limit
    }, function(res) {
        if (res.success) {
            if (currentPage === 1) {
                renderTickets(res.tickets, false);
            } else {
                renderTickets(res.tickets, true);
            }
            
            updateStats(res.stats);
            
            // Show/hide load more
            hasMore = res.tickets && res.tickets.length >= limit;
            if (hasMore) {
                $('#load-more-container').removeClass('hidden');
            } else {
                $('#load-more-container').addClass('hidden');
            }
        } else {
            if (currentPage === 1) {
                $('#tickets-list').html(`<div class="bg-red-50 text-red-600 p-4 rounded-2xl text-center font-bold text-sm border border-red-100">${res.message}</div>`);
            } else {
                Alert.error('Hata', res.message);
            }
        }
    });
}

function loadMoreTickets() {
    if (!hasMore) return;
    currentPage++;
    refreshTickets();
}

function updateStats(stats) {
    if (!stats) return;
    $('#stat-total').text(stats.toplam || 0);
    $('#stat-pending').text(stats.bekleyen || 0);
    $('#stat-resolved').text(stats.yanitlanan || 0);
}

function renderTickets(tickets, append = false) {
    if ((!tickets || tickets.length === 0) && !append) {
        $('#tickets-list').html(`
            <div class="bg-white dark:bg-card-dark rounded-2xl p-8 text-center border border-slate-100 dark:border-slate-800 shadow-sm">
                <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                    <span class="material-symbols-outlined text-3xl">inbox</span>
                </div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Kayıt Bulunamadı</h3>
                <p class="text-xs text-slate-500 mt-1">Görünüşe göre aradığınız kriterlere uygun henüz bir talep yok.</p>
            </div>
        `);
        return;
    }

    let html = '';
    tickets.forEach(ticket => {
        let statusBadge = '';
        let statusColor = '';

        switch(ticket.durum) {
            case 'acik': statusBadge = 'Açık'; statusColor = 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'; break;
            case 'yanitlandi': statusBadge = 'Yanıtlandı'; statusColor = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'; break;
            case 'personel_yaniti': statusBadge = 'Yanıtınız'; statusColor = 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'; break;
            case 'kapali': statusBadge = 'Kapatıldı'; statusColor = 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'; break;
            default: statusBadge = ticket.durum || '-'; statusColor = 'bg-slate-100 text-slate-500';
        }

        const refNo = ticket.ref_no || '#' + ticket.id;
        const owner = isAdmin ? `
            <div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-50 dark:border-slate-800/50">
                <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-md ${ticket.is_mine ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20'}">
                    <span class="material-symbols-outlined text-[12px] font-bold">${ticket.is_mine ? 'person' : 'badge'}</span>
                    <span class="text-[9px] font-black tracking-widest uppercase">${ticket.is_mine ? 'Benim' : 'Personel'}</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200 font-display">${ticket.personel_adi || 'Bilinmeyen'}</span>
                    <span class="text-[10px] text-slate-400 font-medium">${ticket.departman || ''}</span>
                </div>
                <span class="text-[10px] text-slate-400 ml-auto">${ticket.kategori || ''}</span>
            </div>
        ` : '';

        const priorityBadge = ticket.oncelik === 'yuksek' ? '<span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>' : '';

        html += `
            <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 active:scale-[0.98] transition-all cursor-pointer" onclick="viewTicketDetail(${ticket.id})">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-1.5">
                        ${priorityBadge}
                        <span class="text-[10px] font-black tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-500 px-1.5 py-0.5 rounded leading-none pt-1">${refNo}</span>
                    </div>
                    <span class="text-[10px] font-bold ${statusColor} px-2 py-0.5 rounded-full">${statusBadge.toUpperCase()}</span>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-white line-clamp-1 mb-1 font-display uppercase tracking-tight">${ticket.konu || 'Konu Yok'}</h4>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-slate-400 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">schedule</span>
                        ${formatDate(ticket.guncelleme_tarihi)}
                    </span>
                    ${!isAdmin && ticket.durum !== 'kapali' ? `
                         <span class="material-symbols-outlined text-slate-300 text-lg">chevron_right</span>
                    ` : ''}
                </div>
                ${owner}
            </div>
        `;
    });

    if (append) {
        $('#tickets-list').append(html);
    } else {
        $('#tickets-list').html(html);
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Modal Handlers
function openNewTicketModal() {
    const sheet = document.getElementById('new-ticket-sheet');
    const overlay = document.getElementById('new-ticket-overlay');
    const content = document.getElementById('new-ticket-content');
    
    sheet.classList.remove('pointer-events-none');
    overlay.classList.add('opacity-100');
    content.classList.remove('translate-y-full');
}

function closeNewTicketModal() {
    const sheet = document.getElementById('new-ticket-sheet');
    const overlay = document.getElementById('new-ticket-overlay');
    const content = document.getElementById('new-ticket-content');
    
    overlay.classList.remove('opacity-100');
    content.classList.add('translate-y-full');
    setTimeout(() => {
        sheet.classList.add('pointer-events-none');
    }, 300);
}

function updateFileName(input) {
    const name = input.files[0] ? input.files[0].name : 'Dosya Seç';
    $('#file-name-display').text(name);
}

$('#new-ticket-form').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    Loading.show();
    $.ajax({
        url: API_URL,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            Loading.hide();
            if (res.success) {
                Alert.success('Başarılı', res.message);
                closeNewTicketModal();
                $('#new-ticket-form')[0].reset();
                $('#file-name-display').text('Dosya Seç');
                refreshTickets();
            } else {
                Alert.error('Hata', res.message);
            }
        },
        error: function() {
            Loading.hide();
            Alert.error('Sistem Hatası', 'Talebiniz alınırken bir hata oluştu.');
        }
    });
});

// Detail View Handlers
function viewTicketDetail(ticketId) {
    const sheet = document.getElementById('ticket-detail-sheet');
    const overlay = document.getElementById('ticket-detail-overlay');
    const content = document.getElementById('ticket-detail-content');
    const body = document.getElementById('ticket-detail-body');
    
    body.innerHTML = `<div class="flex justify-center py-20"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div></div>`;
    
    sheet.classList.remove('pointer-events-none');
    overlay.classList.add('opacity-100');
    content.classList.remove('translate-y-full');
    
    $.post(API_URL, { action: 'get-ticket-details', bilet_id: ticketId }, function(res) {
        if (res.success) {
            renderTicketDetail(res.ticket);
        } else {
            body.innerHTML = `<div class="bg-red-50 text-red-600 p-4 rounded-2xl text-center font-bold text-sm h-full flex items-center justify-center">${res.message}</div>`;
        }
    });
}

function closeTicketDetail() {
    const sheet = document.getElementById('ticket-detail-sheet');
    const overlay = document.getElementById('ticket-detail-overlay');
    const content = document.getElementById('ticket-detail-content');
    
    overlay.classList.remove('opacity-100');
    content.classList.add('translate-y-full');
    setTimeout(() => {
        sheet.classList.add('pointer-events-none');
    }, 300);
}

function renderTicketDetail(ticket) {
    const body = $('#ticket-detail-body');
    
    let statusLabel = '';
    let statusClass = '';
    switch(ticket.durum) {
        case 'acik': statusLabel = 'Açık'; statusClass = 'bg-amber-100 text-amber-700'; break;
        case 'yanitlandi': statusLabel = 'Yanıtlandı'; statusClass = 'bg-emerald-100 text-emerald-700'; break;
        case 'personel_yaniti': statusLabel = 'Yanıtınız Bekleniyor'; statusClass = 'bg-indigo-100 text-indigo-700'; break;
        case 'kapali': statusLabel = 'Kapatıldı'; statusClass = 'bg-slate-100 text-slate-500'; break;
    }

    let messagesHtml = '';
    if (ticket.messages && ticket.messages.length > 0) {
        ticket.messages.forEach(msg => {
            const isMe = msg.gonderen_tip === (isAdmin ? 'yonetici' : 'personel');
            const senderName = msg.gonderen_tip === 'yonetici' ? 'Destek Ekibi' : (ticket.personel_adi || 'Siz');
            const msgClass = isMe ? 'bg-indigo-600 text-white rounded-tr-none ml-auto' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-white rounded-tl-none mr-auto';
            const metaClass = isMe ? 'text-right' : 'text-left';
            
            let fileAttachment = '';
            if (msg.dosya_yolu) {
                fileAttachment = `<div class="mt-2 pt-2 border-t ${isMe ? 'border-white/20' : 'border-slate-200 dark:border-slate-700'}">
                    <a href="../${msg.dosya_yolu}" target="_blank" class="flex items-center gap-2 text-[10px] font-bold ${isMe ? 'text-white/80' : 'text-indigo-600'}">
                        <span class="material-symbols-outlined text-[14px]">attach_file</span>
                        Eki Görüntüle
                    </a>
                </div>`;
            }

            messagesHtml += `
                <div class="mb-4 max-w-[85%] ${isMe ? 'ml-auto' : 'mr-auto'}">
                    <p class="text-[9px] font-bold text-slate-400 mb-1 uppercase tracking-wider ${metaClass}">${senderName} • ${formatDate(msg.created_at)}</p>
                    <div class="p-3 rounded-2xl text-xs font-medium ${msgClass} shadow-sm">
                        ${msg.mesaj}
                        ${fileAttachment}
                    </div>
                </div>
            `;
        });
    }

    let actionButton = '';
    if (ticket.durum !== 'kapali') {
        if (isAdmin || ticket.can_reply) {
            actionButton = `
                <div class="px-3 py-4 bg-white dark:bg-card-dark border-t border-slate-100 dark:border-slate-800 shrink-0 sticky bottom-0 mt-auto">
                    <form id="reply-form" class="space-y-4">
                        <div class="w-full">
                            <textarea id="reply-message" rows="3" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border-2 border-slate-100 dark:border-slate-700 rounded-3xl text-[14px] font-semibold text-slate-800 dark:text-white focus:border-indigo-500 focus:ring-0 outline-none transition-all no-scrollbar" placeholder="Mesajınızı yazın..." style="max-height: 150px"></textarea>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <!-- Hidden File Input -->
                            <input type="file" id="reply-file" class="hidden" onchange="updateReplyFileName(this)">
                            
                            <!-- Clip (Attachment) Button -->
                            <button type="button" onclick="$('#reply-file').click()" class="w-12 h-12 bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 rounded-2xl flex items-center justify-center active:scale-95 transition-all border border-slate-100 dark:border-slate-700 shadow-sm relative">
                                <span class="material-symbols-outlined text-[22px]">attach_file</span>
                                <div id="reply-file-dot" class="absolute top-3 right-3 w-2 h-2 bg-indigo-500 rounded-full hidden border-2 border-white dark:border-slate-800"></div>
                            </button>
                            
                            <!-- More (Actions) Button -->
                            <div class="relative">
                                <button type="button" id="reply-actions-trigger" onclick="event.stopPropagation(); $('#reply-actions-menu').toggleClass('hidden')" class="w-12 h-12 bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 rounded-2xl flex items-center justify-center active:scale-95 transition-all border border-slate-100 dark:border-slate-700 shadow-sm">
                                    <span class="material-symbols-outlined text-[22px]">more_vert</span>
                                </button>
                                
                                <div id="reply-actions-menu" class="absolute bottom-full left-0 mb-3 w-64 bg-white dark:bg-card-dark rounded-[2.5rem] shadow-2xl border border-slate-100 dark:border-slate-800 py-3 hidden z-[120] animate-in fade-in slide-in-from-bottom-3 duration-200 overflow-hidden">
                                    <div class="px-4 py-2 border-b border-slate-50 dark:border-slate-800 mb-1">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">İşlemler</p>
                                    </div>
                                    ${isAdmin ? `
                                        ${ticket.durum !== 'isleme_alindi' && ticket.durum !== 'cozuldu' ? `
                                            <button type="button" onclick="updateTicketStatus(${ticket.id}, 'isleme_alindi')" class="w-full text-left px-5 py-4 text-[13px] font-bold text-slate-700 dark:text-slate-200 flex items-center gap-3 active:bg-indigo-50 dark:active:bg-indigo-900/20 active:text-indigo-600 transition-colors">
                                                <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-500">
                                                    <span class="material-symbols-outlined text-[20px]">pending_actions</span>
                                                </div>
                                                İşleme Al
                                            </button>
                                        ` : ''}
                                        ${ticket.durum !== 'cozuldu' ? `
                                            <button type="button" onclick="updateTicketStatus(${ticket.id}, 'cozuldu')" class="w-full text-left px-5 py-4 text-[13px] font-bold text-slate-700 dark:text-slate-200 flex items-center gap-3 active:bg-indigo-50 dark:active:bg-indigo-900/20 active:text-indigo-600 transition-colors">
                                                <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-500">
                                                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                                                </div>
                                                Çözüldü Olarak İşaretle
                                            </button>
                                        ` : ''}
                                    ` : ''}
                                    <button type="button" onclick="updateTicketStatus(${ticket.id}, 'kapali')" class="w-full text-left px-5 py-4 text-[13px] font-bold text-red-600 flex items-center gap-3 active:bg-red-50 dark:active:bg-red-900/20 transition-colors">
                                        <div class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-red-500">
                                            <span class="material-symbols-outlined text-[20px]">cancel</span>
                                        </div>
                                        Talebi Kapat
                                    </button>
                                    <button type="button" onclick="closeTicketDetail()" class="w-full text-left px-5 py-4 text-[13px] font-bold text-slate-500 flex items-center gap-3 active:bg-slate-50 dark:active:bg-slate-800 transition-colors mt-1 border-t border-slate-50 dark:border-slate-800 pt-3">
                                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                            <span class="material-symbols-outlined text-[20px]">close</span>
                                        </div>
                                        Detayı Kapat
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Send Message Button -->
                            <button type="button" onclick="sendReply(${ticket.id})" class="flex-1 h-12 bg-indigo-600 text-white rounded-[1.25rem] shadow-xl shadow-indigo-600/20 active:scale-[0.98] transition-all flex items-center justify-center gap-2 uppercase tracking-widest font-black text-[13px]">
                                <span>MESAJI GÖNDER</span>
                                <span class="material-symbols-outlined text-[20px]">send</span>
                            </button>
                        </div>
                    </form>
                </div>
            `;
        } else {
             actionButton = `
                <div class="px-5 pb-5">
                    <div class="p-5 bg-slate-50 dark:bg-slate-800 rounded-3xl border border-dashed border-slate-200 dark:border-slate-700 text-center mb-4 shadow-inner">
                        <p class="text-[11px] font-bold text-slate-400 italic">Destek ekibinin yanıtı bekleniyor...</p>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="updateTicketStatus(${ticket.id}, 'kapali')" class="flex-1 py-4 bg-red-50 text-red-600 rounded-2xl font-black uppercase tracking-widest text-[10px] active:scale-[0.95] transition-all flex items-center justify-center gap-2 border border-red-100 shadow-sm">
                            <span class="material-symbols-outlined text-[18px]">lock</span>
                            Talebi Kapat
                        </button>
                        <button type="button" onclick="closeTicketDetail()" class="flex-1 py-4 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-2xl font-bold uppercase tracking-widest text-[10px] active:scale-[0.95] transition-all border border-slate-200 dark:border-slate-700">
                            Detayı Kapat
                        </button>
                    </div>
                </div>
             `;
        }
    } else {
        let closureInfo = 'Bu Talep Kapatılmıştır';
        if (ticket.kapatan_adi && ticket.kapatma_tarihi) {
            closureInfo = `<div class="flex flex-col items-center gap-1">
                <span>Bu Talep Kapatılmıştır</span>
                <span class="text-[9px] font-medium normal-case opacity-70">${ticket.kapatan_adi} tarafından ${formatDate(ticket.kapatma_tarihi)} tarihinde kapatıldı.</span>
            </div>`;
        }
        actionButton = `
            <div class="px-5 pb-5">
                <div class="p-5 text-center text-xs font-bold text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-800/50 uppercase tracking-widest flex items-center justify-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-sm">lock</span>
                    ${closureInfo}
                </div>
                <button type="button" onclick="closeTicketDetail()" class="w-full py-4 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-2xl font-bold uppercase tracking-widest text-[10px]">
                    Detayı Kapat
                </button>
            </div>
        `;
    }

    const firstMsg = ticket.messages && ticket.messages[0] ? ticket.messages[0].mesaj : '';
    const attachmentsCount = ticket.messages ? ticket.messages.filter(m => m.dosya_yolu).length : 0;

    body.html(`
        <div class="flex flex-col h-full overflow-hidden">
            <div class="shrink-0 mb-6 sticky top-0 bg-white dark:bg-card-dark z-20 pt-1">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-500 px-2 py-0.5 rounded leading-none pt-1.5 uppercase tracking-widest">${ticket.ref_no || '#' + ticket.id}</span>
                    <span class="text-[10px] font-bold ${statusClass} px-2.5 py-1 rounded-full uppercase tracking-wide shadow-sm">${statusLabel}</span>
                </div>
                <h3 class="text-xl font-bold text-slate-900 dark:text-white leading-tight font-display mb-2 uppercase tracking-tight">${ticket.konu || 'Konu Yok'}</h3>
                
                <div class="flex items-center gap-4 text-slate-400">
                    <div class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">grid_view</span>
                        <span class="text-[10px] font-bold">${ticket.kategori || 'Genel'}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">flag</span>
                        <span class="text-[10px] font-bold capitalize">${ticket.oncelik || 'Orta'}</span>
                    </div>
                    <div class="flex items-center gap-1 ml-auto">
                        <span class="material-symbols-outlined text-sm">attach_file</span>
                        <span class="text-[10px] font-bold">${attachmentsCount} Dosya</span>
                    </div>
                </div>
                <div class="h-px bg-slate-100 dark:bg-slate-800/60 w-full mt-4"></div>
            </div>

            <div class="flex-grow overflow-y-auto no-scrollbar pb-2">
                <div class="messages-container">
                    ${messagesHtml}
                </div>
            </div>

            ${actionButton}
        </div>
    `);

    // Auto-resize textarea
    const tx = document.getElementById('reply-message');
    if (tx) {
        tx.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
}

function updateReplyFileName(input) {
    if (input.files && input.files[0]) {
        $('#reply-file-dot').removeClass('hidden');
    } else {
        $('#reply-file-dot').addClass('hidden');
    }
}

function sendReply(ticketId) {
    const msg = $('#reply-message').val().trim();
    const file = document.getElementById('reply-file').files[0];
    
    if (!msg && !file) return;
    
    const formData = new FormData();
    formData.append('action', 'add-message');
    formData.append('bilet_id', ticketId);
    formData.append('mesaj', msg);
    if (file) {
        formData.append('dosya', file);
    }
    
    Loading.show();
    $.ajax({
        url: API_URL,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            Loading.hide();
            if (res.success) {
                // Clear inputs
                $('#reply-message').val('');
                $('#reply-file').val('');
                $('#reply-file-dot').addClass('hidden');
                
                // Re-fetch detail
                $.post(API_URL, { action: 'get-ticket-details', bilet_id: ticketId }, function(resDet) {
                    if (resDet.success) {
                        renderTicketDetail(resDet.ticket);
                        // Scroll to bottom
                        setTimeout(() => {
                            const container = document.querySelector('.messages-container').parentElement;
                            container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                        }, 100);
                    }
                });
            } else {
                Alert.error('Hata', res.message);
            }
        },
        error: function() {
            Loading.hide();
            Alert.error('Sistem Hatası', 'Mesaj gönderilirken bir hata oluştu.');
        }
    });
}

function updateTicketStatus(ticketId, status) {
    let msg = 'Bu destek talebini kapatmak istediğinize emin misiniz?';
    if (status === 'isleme_alindi') msg = 'Talebi işleme almak istediğinize emin misiniz?';
    if (status === 'cozuldu') msg = 'Talebi çözüldü olarak işaretlemek istediğinize emin misiniz?';
    
    Alert.confirm('Emin misiniz?', msg).then(ok => {
        if (ok) {
            Loading.show();
            $.post(API_URL, { action: 'update-status', bilet_id: ticketId, durum: status }, function(res) {
                Loading.hide();
                if (res.success) {
                    Alert.success('Başarılı', 'İşlem başarıyla tamamlandı.');
                    // Re-draw or refresh
                    refreshTickets();
                    // If modal is open, re-fetch detail
                    $.post(API_URL, { action: 'get-ticket-details', bilet_id: ticketId }, function(resDet) {
                        if (resDet.success) {
                            renderTicketDetail(resDet.ticket);
                        }
                    });
                } else {
                    Alert.error('Hata', res.message);
                }
            });
        }
    });
}
</script>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    
    .active-filter {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }
    
    .font-display { font-family: 'Roboto Condensed', sans-serif; }
    
    #reply-message {
        transition: height 0.1s ease;
    }

    body.loading-active #loader {
        opacity: 1 !important;
        pointer-events: auto !important;
    }
</style>
