<?php
/**
 * Personel PWA - Yardım ve Destek Sayfası
 */
?>
<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 px-4 py-4 sticky top-0 z-30">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="?page=ana-sayfa" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600">arrow_back</span>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Yardım & Destek</h1>
                    <p class="text-sm text-slate-500">Destek taleplerinizi yönetin</p>
                </div>
            </div>
            <button id="open-ticket-button" onclick="openNewTicketModal()" class="w-12 h-12 rounded-2xl bg-primary text-white flex items-center justify-center shadow-lg shadow-primary/20 active:scale-95 transition-all">
                <span class="material-symbols-outlined">add</span>
            </button>
        </div>
    </header>

    <!-- Stats -->
    <section class="px-4 py-6">
        <div class="grid grid-cols-3 gap-3">
            <div class="card p-3 text-center bg-blue-50/50 dark:bg-blue-900/10 border-blue-100 dark:border-blue-800">
                <p class="text-xs text-blue-600 dark:text-blue-400 font-bold mb-1">AÇIK</p>
                <p class="text-2xl font-black text-blue-700 dark:text-blue-300" id="stat-bekleyen">0</p>
            </div>
            <div class="card p-3 text-center bg-emerald-50/50 dark:bg-emerald-900/10 border-emerald-100 dark:border-emerald-800">
                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-bold mb-1">YANIT</p>
                <p class="text-2xl font-black text-emerald-700 dark:text-emerald-300" id="stat-yanitlanan">0</p>
            </div>
            <div class="card p-3 text-center bg-slate-50/50 dark:bg-slate-900/10 border-slate-200 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-400 font-bold mb-1">KAPALI</p>
                <p class="text-2xl font-black text-slate-600 dark:text-slate-300" id="stat-kapali">0</p>
            </div>
        </div>
    </section>

    <!-- Ticket List -->
    <section class="px-4 pb-20 flex-1">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Taleplerim</h2>
        <div id="ticket-list" class="flex flex-col gap-4">
            <!-- Loading -->
            <div class="shimmer h-24 rounded-2xl"></div>
            <div class="shimmer h-24 rounded-2xl"></div>
        </div>
    </section>
</div>

<!-- New Ticket Modal -->
<div id="new-ticket-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle mb-4"></div>
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Yeni Destek Talebi</h3>
            <button onclick="Modal.close('new-ticket-modal')" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-500">close</span>
            </button>
        </div>

        <form id="new-ticket-form" class="flex flex-col gap-4">
            <input type="hidden" name="action" value="create-ticket">
            
            <div class="form-group">
                <label class="form-label">Konu</label>
                <input type="text" name="konu" class="form-input" placeholder="Talebinizi özetleyin" required>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" class="form-input">
                        <option value="Genel">Genel</option>
                        <option value="Hata">Hata Bildirimi</option>
                        <option value="Öneri">Öneri</option>
                        <option value="İstek">İstek</option>
                        <option value="Muhasebe">Muhasebe/Bordro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Öncelik</label>
                    <select name="oncelik" class="form-input">
                        <option value="dusuk">Düşük</option>
                        <option value="orta" selected>Orta</option>
                        <option value="yuksek">Yüksek</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Açıklama</label>
                <textarea name="mesaj" class="form-input min-h-[120px]" placeholder="Sorununuzu veya talebinizi detaylandırın" required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Dosya Ekle (Resim)</label>
                <div class="relative">
                    <input type="file" name="dosya" id="ticket-file" accept="image/*" class="hidden" onchange="updateFileName(this)">
                    <label for="ticket-file" class="flex items-center gap-3 p-4 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-slate-500 cursor-pointer hover:border-primary transition-all">
                        <span class="material-symbols-outlined">add_a_photo</span>
                        <span id="file-name-label">Resim Seçin</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-primary py-4 mt-2">
                Talep Oluştur
            </button>
        </form>
    </div>
</div>

<script>
let activeTicketCount = 0;

function parseJsonSafe(text) {
    try {
        return JSON.parse(text);
    } catch (e) {
        return { success: false, message: 'Geçersiz sunucu yanıtı.' };
    }
}

async function postJson(url, data) {
    const body = Object.keys(data)
        .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(data[key]))
        .join('&');

    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body
    });
    const text = await response.text();
    return parseJsonSafe(text);
}

async function postFormData(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        body: formData
    });
    const text = await response.text();
    return parseJsonSafe(text);
}

function updateFileName(input) {
    const label = document.getElementById('file-name-label');
    if (input.files && input.files[0]) {
        label.textContent = input.files[0].name;
        label.classList.add('text-primary', 'font-bold');
    } else {
        label.textContent = 'Resim Seçin';
        label.classList.remove('text-primary', 'font-bold');
    }
}

function openNewTicketModal() {
    if (activeTicketCount >= 2) {
        Toast.show('Aynı anda en fazla 2 açık destek talebiniz olabilir.', 'error');
        return;
    }
    Modal.open('new-ticket-modal');
}

function updateCreateButtonState() {
    const button = document.getElementById('open-ticket-button');
    if (!button) {
        return;
    }

    if (activeTicketCount >= 2) {
        button.classList.add('opacity-50');
        button.classList.remove('bg-primary');
        button.classList.add('bg-slate-400');
        button.setAttribute('title', 'Önce mevcut açık taleplerinizden birini kapatın');
    } else {
        button.classList.remove('opacity-50');
        button.classList.remove('bg-slate-400');
        button.classList.add('bg-primary');
        button.removeAttribute('title');
    }
}

async function loadTickets() {
    const res = await postJson('api.php', { action: 'get-tickets-pwa' });
    if (res.success) {
        renderTickets(res.tickets || []);
        activeTicketCount = Number(res.active_ticket_count || 0);
        document.getElementById('stat-bekleyen').textContent = res.stats?.bekleyen || 0;
        document.getElementById('stat-yanitlanan').textContent = res.stats?.yanitlanan || 0;
        document.getElementById('stat-kapali').textContent = res.stats?.kapali || 0;
        updateCreateButtonState();
    }
}

function renderTickets(tickets) {
    const container = document.getElementById('ticket-list');
    if(tickets.length === 0) {
        container.innerHTML = `
            <div class="empty-state py-10">
                <div class="empty-state-icon mb-4">
                    <span class="material-symbols-outlined text-5xl opacity-20">support_agent</span>
                </div>
                <p class="text-slate-500 text-center font-medium">Henüz bir destek talebiniz bulunmuyor.</p>
            </div>
        `;
        return;
    }

    let html = '';
    tickets.forEach(ticket => {
        let statusClass = 'bg-slate-100 text-slate-600';
        let statusText = ticket.durum.toUpperCase();
        
        if(ticket.durum === 'acik') { statusClass = 'bg-amber-100 text-amber-700'; statusText = 'AÇIK'; }
        if(ticket.durum === 'yanitlandi') { statusClass = 'bg-emerald-100 text-emerald-700'; statusText = 'YANITLANDI'; }
        if(ticket.durum === 'personel_yaniti') { statusClass = 'bg-blue-100 text-blue-700'; statusText = 'BEKLİYOR'; }
        if(ticket.durum === 'kapali') { statusClass = 'bg-slate-100 text-slate-500'; statusText = 'KAPALI'; }

        html += `
            <a href="?page=yardim-detay&id=${ticket.encrypted_id || ticket.id}" class="card p-4 active:scale-[0.98] transition-all">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 tracking-widest uppercase">#${ticket.ref_no}</span>
                        <h3 class="font-bold text-slate-900 dark:text-white leading-tight mt-0.5">${ticket.konu}</h3>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black ${statusClass}">${statusText}</span>
                </div>
                <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/50">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400 text-sm">category</span>
                        <span class="text-xs text-slate-500 font-medium">${ticket.kategori}</span>
                    </div>
                    <span class="text-[10px] text-slate-400 font-medium">${ticket.guncelleme_tarihi}</span>
                </div>
            </a>
        `;
    });
    container.innerHTML = html;
}

document.getElementById('new-ticket-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new window.FormData(this);
    
    Toast.show('Gönderiliyor...', 'info');
    const res = await postFormData('api.php', formData);
    if (res.success) {
        document.getElementById('new-ticket-form').reset();
        Modal.close('new-ticket-modal');
        loadTickets();
        Toast.show('Talebiniz başarıyla oluşturuldu.', 'success');
    } else {
        Toast.show(res.message || 'İşlem sırasında bir hata oluştu.', 'error');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    loadTickets().catch(function() {
        Toast.show('Talepler yüklenirken bir hata oluştu.', 'error');
    });
});
</script>
