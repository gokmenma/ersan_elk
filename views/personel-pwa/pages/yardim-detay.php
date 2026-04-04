<?php
/**
 * Personel PWA - Yardım ve Destek Detay Sayfası
 */
use App\Helper\Security;

$encryptedId = $_GET['id'] ?? '';
$id = Security::decrypt($encryptedId);
$id = is_numeric($id) ? (int) $id : 0;
?>
<div class="flex flex-col h-screen bg-slate-50 dark:bg-card-dark">
    <!-- Header -->
    <header class="bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 px-4 py-4 sticky top-0 z-30">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 overflow-hidden">
                <a href="?page=yardim" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-slate-600">arrow_back</span>
                </a>
                <div class="overflow-hidden">
                    <h1 class="text-sm font-bold text-slate-900 dark:text-white truncate" id="ticket-subject">Yükleniyor...</h1>
                    <p class="text-[10px] text-slate-500 font-medium tracking-widest" id="ticket-ref-no">#000000</p>
                </div>
            </div>
            <div id="ticket-status-badge">
                <div class="shimmer w-16 h-6 rounded-lg"></div>
            </div>
        </div>
    </header>

    <!-- Chat Area -->
    <div id="chat-messages" class="flex-1 overflow-y-auto px-4 py-6 space-y-6 bg-slate-50 dark:bg-card-dark pb-44">
        <div class="shimmer h-20 rounded-2xl w-3/4"></div>
        <div class="shimmer h-20 rounded-2xl w-3/4 self-end"></div>
        <div class="shimmer h-20 rounded-2xl w-3/4"></div>
    </div>

    <!-- Bottom Action Bar -->
    <div class="fixed bottom-[72px] left-0 right-0 p-4 bg-white/80 dark:bg-card-dark/80 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 z-40 pb-4">
        <form id="reply-form" class="flex flex-col gap-3">
            <input type="hidden" name="action" value="add-message">
            <input type="hidden" name="bilet_id" value="<?php echo $id; ?>">
            
            <div class="relative flex items-end gap-2 bg-slate-100 dark:bg-slate-800 rounded-2xl p-2 pr-1 transition-all focus-within:ring-2 focus-within:ring-primary/20 focus-within:bg-white dark:focus-within:bg-slate-900 border border-transparent focus-within:border-primary/30">
                <button type="button" onclick="document.getElementById('reply-file').click()" class="w-10 h-10 rounded-xl bg-white dark:bg-slate-700 flex items-center justify-center flex-shrink-0 shadow-sm active:scale-90 transition-all">
                    <span class="material-symbols-outlined text-slate-500 text-xl" id="file-icon">attach_file</span>
                </button>
                <input type="file" name="dosya" id="reply-file" accept="image/*" class="hidden" onchange="updateFileStatus(this)">
                
                <textarea name="mesaj" id="reply-message" class="bg-transparent border-none focus:ring-0 w-full text-slate-900 dark:text-white text-sm py-2 px-1 resize-none" rows="1" placeholder="Mesajınızı yazın..." oninput="autoResize(this)" required></textarea>
                
                <button type="submit" class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center flex-shrink-0 shadow-lg shadow-primary/20 active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-xl">send</span>
                </button>
            </div>
            <div id="file-status" class="hidden px-2 py-1 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600 text-xs">image</span>
                <span id="file-name" class="text-[10px] text-emerald-700 dark:text-emerald-400 font-bold truncate">Resim Eklendi</span>
                <button type="button" onclick="clearFile()" class="ml-auto text-emerald-700 hover:text-red-500"><span class="material-symbols-outlined text-xs">close</span></button>
            </div>
        </form>
        <div id="closed-ticket-message" class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-900/30 dark:bg-rose-950/20 dark:text-rose-300">
            Bu destek talebi kapatıldı. Yeni mesaj gönderemezsiniz.
        </div>
        <div id="waiting-admin-message" class="hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-300">
            Yeni mesaj göndermek için yönetici yanıtını beklemelisiniz.
        </div>
        <button id="close-ticket-button" type="button" onclick="closeTicket()" class="mt-3 hidden w-full rounded-2xl bg-rose-500 px-4 py-3 text-sm font-bold text-white shadow-sm active:scale-95 transition-all">
            Talebi Kapat
        </button>
    </div>
</div>

<style>
.message-card { max-width: min(85%, 22rem); padding: 1rem; border-radius: 1rem; position: relative; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); width: fit-content; word-break: break-word; }
.message-mine { align-self: flex-end; background-color: var(--primary); color: white; border-bottom-right-radius: 0; box-shadow: 0 4px 6px -1px rgba(var(--primary-rgb), 0.1); }
.message-others { align-self: flex-start; background-color: white; color: #1e293b; border-bottom-left-radius: 0; border: 1px solid #e2e8f0; }
.dark .message-others { background-color: #1e1e1e; color: white; border-color: rgba(30, 41, 59, 0.5); }
.message-meta { font-size: 9px; margin-top: 0.5rem; opacity: 0.6; font-weight: 500; letter-spacing: -0.025em; }
.message-attachment { margin-top: 0.75rem; border-radius: 0.75rem; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.2); cursor: pointer; }
</style>

<script>
const biletId = <?php echo $id; ?>;
let currentTicketStatus = '';

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

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = (textarea.scrollHeight) + 'px';
}

function updateFileStatus(input) {
    if (input.files && input.files[0]) {
        document.getElementById('file-status').classList.remove('hidden');
        document.getElementById('file-name').textContent = input.files[0].name;
        const fileIcon = document.getElementById('file-icon');
        fileIcon.textContent = 'check';
        fileIcon.classList.add('text-emerald-600');
    }
}

function clearFile() {
    document.getElementById('reply-file').value = '';
    document.getElementById('file-status').classList.add('hidden');
    const fileIcon = document.getElementById('file-icon');
    fileIcon.textContent = 'attach_file';
    fileIcon.classList.remove('text-emerald-600');
}

async function loadTicket() {
    const res = await postJson('api.php', { action: 'get-ticket-details', bilet_id: biletId });
    if (res.success) {
        const ticket = res.ticket;
        currentTicketStatus = ticket.durum;
        document.getElementById('ticket-subject').textContent = ticket.konu;
        document.getElementById('ticket-ref-no').textContent = '#' + ticket.ref_no;
            
        // Status Badge
        let statusClass = 'bg-slate-100 text-slate-600';
        if (ticket.durum === 'acik') statusClass = 'bg-amber-100 text-amber-700';
        if (ticket.durum === 'yanitlandi') statusClass = 'bg-emerald-100 text-emerald-700';
        if (ticket.durum === 'personel_yaniti') statusClass = 'bg-blue-100 text-blue-700';
        if (ticket.durum === 'kapali') statusClass = 'bg-slate-200 text-slate-500';
            
        document.getElementById('ticket-status-badge').innerHTML = `<span class="px-2 py-1 rounded-lg text-[10px] font-black ${statusClass}">${ticket.durum.toUpperCase()}</span>`;

        const closeButton = document.getElementById('close-ticket-button');
        const replyForm = document.getElementById('reply-form');
        const closedMessage = document.getElementById('closed-ticket-message');
        const waitingMessage = document.getElementById('waiting-admin-message');

        if (ticket.durum === 'kapali') {
            closeButton.classList.add('hidden');
            replyForm.classList.add('hidden');
            closedMessage.classList.remove('hidden');
            waitingMessage.classList.add('hidden');
        } else {
            closeButton.classList.remove('hidden');
            closedMessage.classList.add('hidden');
            if (ticket.can_reply) {
                replyForm.classList.remove('hidden');
                waitingMessage.classList.add('hidden');
            } else {
                replyForm.classList.add('hidden');
                waitingMessage.classList.remove('hidden');
            }
        }

        renderMessages(ticket.messages || []);
    } else {
        Toast.show(res.message || 'Bilet yüklenirken bir hata oluştu.', 'error');
        setTimeout(() => window.location.href = '?page=yardim', 2000);
    }
}

function renderMessages(messages) {
    const container = document.getElementById('chat-messages');
    let html = '';
    messages.forEach(msg => {
        const isMine = msg.gonderen_tip === 'personel';
        const cardClass = isMine ? 'message-mine' : 'message-others';
        
        html += `
            <div class="flex flex-col ${isMine ? 'items-end' : 'items-start'}">
                <div class="message-card ${cardClass}">
                    <div class="text-sm font-medium leading-relaxed">${msg.mesaj.replace(/\n/g, '<br>')}</div>
                    ${msg.dosya_yolu ? `
                        <div class="message-attachment" onclick="window.open('${msg.dosya_yolu}', '_blank')">
                            <img src="${msg.dosya_yolu}" class="w-full object-cover">
                        </div>
                    ` : ''}
                    <div class="message-meta">${msg.gonderen_adi} &bull; ${msg.olusturma_tarihi}</div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

async function closeTicket() {
    if (currentTicketStatus === 'kapali') {
        return;
    }

    const confirmed = await Alert.confirm(
        'Talebi Kapat',
        'Bu destek talebini kapatmak istediğinize emin misiniz?',
        'Kapat',
        'Vazgeç'
    );

    if (!confirmed) {
        return;
    }

    const res = await postJson('api.php', { action: 'update-status', bilet_id: biletId, durum: 'kapali' });
    if (res.success) {
        Toast.show('Destek talebi kapatıldı.', 'success');
        loadTicket();
        return;
    }

    Toast.show(res.message || 'Talep kapatılırken bir hata oluştu.', 'error');
}

document.getElementById('reply-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new window.FormData(this);
    
    Toast.show('Gönderiliyor...', 'info');
    const res = await postFormData('api.php', formData);
    if (res.success) {
        document.getElementById('reply-form').reset();
        autoResize(document.getElementById('reply-message'));
        clearFile();
        loadTicket();
        Toast.show('Mesajınız göderildi.', 'success');
    } else {
        Toast.show(res.message || 'Mesaj gönderilirken bir hata oluştu.', 'error');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    loadTicket().catch(function() {
        Toast.show('Bilet yüklenirken bir hata oluştu.', 'error');
    });
});
</script>
