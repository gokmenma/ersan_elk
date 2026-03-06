<!-- =====================================================
     Yönetici Canlı Destek Chat Widget (LinkedIn Style)
     Tüm sayfalarda görünen popup chat penceresi
     ===================================================== -->
<?php
// Canlı destek ayarı kontrolü
$_destekSettingsModel = new \App\Model\SettingsModel();
$_canliDestekAktif = $_destekSettingsModel->getSettings('canli_destek_aktif');
if ($_canliDestekAktif !== '1') {
    // Canlı destek kapalı - widget gösterme
    return;
}

// Yetkili kullanıcı kontrolü
$_yetkiliKullanicilar = $_destekSettingsModel->getSettings('canli_destek_yetkili_kullanicilar') ?? '';
if (!empty($_yetkiliKullanicilar)) {
    $_yetkiliIds = array_filter(explode(',', $_yetkiliKullanicilar));
    $_currentUserId = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;
    if (!in_array((string) $_currentUserId, $_yetkiliIds)) {
        // Bu kullanıcı yetkili değil - widget gösterme
        return;
    }
}

// Mesai dışındaysa widgetı gizle
$_destekModel = new \App\Model\DestekModel();
if (!$_destekModel->isWorkingHours()) {
    // Sadece mevcut açık/beklemede olan işlemler için açık kalsın mı? Yok, yönetici tarafı isteniyor:
    // "yönetici tarafında da mesai saatleri dışında chat butonu gizlensin"
    return;
}
?>

<style>
    /* ===== Admin Chat Widget - LinkedIn Style ===== */
    :root {
        --achat-primary: var(--bs-primary, #135bec);
        --achat-primary-dark: var(--bs-primary, #0d47c1);
        --achat-primary-rgb: var(--bs-primary-rgb, 19, 91, 236);
        --achat-success: #22c55e;
        --achat-danger: #ef4444;
        --achat-bg: #ffffff;
        --achat-bg-hover: #f8f9fa;
        --achat-text: #1e293b;
        --achat-text-muted: #64748b;
        --achat-border: #e2e8f0;
        --achat-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        --achat-radius: 12px;
    }

    /* Floating Chat Toggle Button */
    .achat-toggle-btn {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, var(--achat-primary), var(--achat-primary-dark));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 9998;
        box-shadow: 0 4px 16px rgba(19, 91, 236, 0.35);
        transition: all 0.3s ease;
        font-size: 24px;
    }

    .achat-toggle-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 24px rgba(19, 91, 236, 0.45);
    }

    .achat-toggle-btn .badge-count {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 20px;
        height: 20px;
        background: var(--achat-danger);
        color: white;
        font-size: 11px;
        font-weight: 700;
        border-radius: 10px;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
        border: 2px solid white;
    }

    .achat-toggle-btn .badge-count.show {
        display: flex;
    }

    /* Main Panel (LinkedIn Messaging Style) */
    .achat-panel {
        position: fixed;
        bottom: 0;
        right: 24px;
        width: 380px;
        height: 500px;
        background: var(--achat-bg);
        border-radius: var(--achat-radius) var(--achat-radius) 0 0;
        box-shadow: var(--achat-shadow);
        z-index: 9999;
        display: none;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--achat-border);
        border-bottom: none;
        transition: all 0.3s ease;
    }

    .achat-panel.show {
        display: flex;
    }

    .achat-panel.minimized {
        height: 52px;
    }

    /* Panel Header */
    .achat-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        background: linear-gradient(135deg, var(--achat-primary), var(--achat-primary-dark));
        cursor: pointer;
        flex-shrink: 0;
        user-select: none;
    }

    .achat-panel-header h4 {
        color: white;
        font-size: 15px;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .achat-panel-header h4 .bx {
        font-size: 20px;
    }

    .achat-panel-header .achat-badge {
        background: var(--achat-danger);
        color: white;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }

    .achat-header-actions {
        display: flex;
        gap: 4px;
    }

    .achat-header-actions button {
        width: 30px;
        height: 30px;
        border: none;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 16px;
        transition: background 0.2s;
    }

    .achat-header-actions button:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    /* Conversation List */
    .achat-conv-list {
        flex: 1;
        overflow-y: auto;
    }

    .achat-conv-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.15s;
        border-bottom: 1px solid var(--achat-border);
        position: relative;
    }

    .achat-conv-item:hover {
        background: var(--achat-bg-hover);
    }

    .achat-conv-item.unread {
        background: #eff6ff;
    }

    .achat-conv-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
        position: relative;
    }

    .achat-conv-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .achat-conv-avatar .initial {
        font-size: 16px;
        font-weight: 700;
        color: var(--achat-primary);
    }

    .achat-conv-avatar .online-dot {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 10px;
        height: 10px;
        background: var(--achat-success);
        border-radius: 50%;
        border: 2px solid white;
    }

    .achat-conv-info {
        flex: 1;
        min-width: 0;
    }

    .achat-conv-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--achat-text);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .achat-conv-preview {
        font-size: 12px;
        color: var(--achat-text-muted);
        margin: 2px 0 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .achat-conv-item.unread .achat-conv-preview {
        font-weight: 600;
        color: var(--achat-text);
    }

    .achat-conv-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
    }

    .achat-conv-time {
        font-size: 11px;
        color: var(--achat-text-muted);
        white-space: nowrap;
    }

    .achat-conv-unread-badge {
        width: 20px;
        height: 20px;
        background: var(--achat-primary);
        color: white;
        font-size: 11px;
        font-weight: 700;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Empty state */
    .achat-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
        flex: 1;
    }

    .achat-empty .bx {
        font-size: 48px;
        color: #cbd5e1;
        margin-bottom: 12px;
    }

    .achat-empty p {
        color: var(--achat-text-muted);
        font-size: 14px;
        margin: 0;
    }

    /* ===== Individual Chat Windows ===== */
    .achat-chat-window {
        position: fixed;
        bottom: 0;
        width: 340px;
        height: 450px;
        background: var(--achat-bg);
        border-radius: var(--achat-radius) var(--achat-radius) 0 0;
        box-shadow: var(--achat-shadow);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--achat-border);
        border-bottom: none;
        transition: all 0.3s ease;
    }

    .achat-chat-window.minimized {
        height: 52px;
    }

    /* Chat Window Header */
    .achat-chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: linear-gradient(135deg, var(--achat-primary), var(--achat-primary-dark));
        cursor: pointer;
        flex-shrink: 0;
    }

    .achat-chat-header-info {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .achat-chat-header-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .achat-chat-header-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .achat-chat-header-avatar .initial {
        font-size: 13px;
        font-weight: 700;
        color: white;
    }

    .achat-chat-header-name {
        font-size: 14px;
        font-weight: 600;
        color: white;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .achat-chat-header-dept {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
    }

    .achat-chat-header-actions {
        display: flex;
        gap: 2px;
    }

    .achat-chat-header-actions button {
        width: 28px;
        height: 28px;
        border: none;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 16px;
        transition: background 0.2s;
    }

    .achat-chat-header-actions button:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    /* Chat Messages Area */
    .achat-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        background: #f8f9fa;
    }

    .achat-msg {
        display: flex;
        flex-direction: column;
        max-width: 80%;
        animation: achatMsgIn 0.25s ease;
    }

    @keyframes achatMsgIn {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .achat-msg.outgoing {
        align-self: flex-end;
    }

    .achat-msg.incoming {
        align-self: flex-start;
    }

    .achat-msg.system {
        align-self: center;
        max-width: 90%;
    }

    .achat-msg-bubble {
        padding: 8px 12px;
        border-radius: 14px;
        font-size: 13px;
        line-height: 1.45;
        word-break: break-word;
    }

    .achat-msg.outgoing .achat-msg-bubble {
        background: linear-gradient(135deg, var(--achat-primary), #3b82f6);
        color: white;
        border-bottom-right-radius: 4px;
    }

    .achat-msg.incoming .achat-msg-bubble {
        background: white;
        color: var(--achat-text);
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
    }

    .achat-msg.system .achat-msg-bubble {
        background: rgba(100, 116, 139, 0.1);
        color: var(--achat-text-muted);
        font-size: 11px;
        text-align: center;
        padding: 6px 14px;
    }

    .achat-msg-time {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 2px;
        padding: 0 4px;
    }

    .achat-msg.outgoing .achat-msg-time {
        text-align: right;
    }

    .achat-msg.incoming .achat-msg-time {
        text-align: left;
    }

    .achat-msg-image {
        max-width: 200px;
        border-radius: 10px;
        cursor: pointer;
    }

    /* Chat Input */
    .achat-chat-input-area {
        padding: 10px 12px;
        border-top: 1px solid var(--achat-border);
        flex-shrink: 0;
        background: white;
    }

    .achat-chat-input-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #f1f5f9;
        border-radius: 20px;
        padding: 3px 5px;
    }

    .achat-chat-input-wrap:focus-within {
        box-shadow: 0 0 0 2px rgba(19, 91, 236, 0.2);
    }

    .achat-chat-input {
        flex: 1;
        border: none;
        background: transparent;
        outline: none;
        font-size: 13px;
        padding: 6px 4px;
        color: var(--achat-text);
    }

    .achat-chat-input::placeholder {
        color: #94a3b8;
    }

    .achat-chat-input-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.15s;
    }

    .achat-chat-img-btn {
        background: transparent;
        color: #64748b;
        font-size: 18px;
    }

    .achat-chat-img-btn:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .achat-chat-send-btn {
        background: linear-gradient(135deg, var(--achat-primary), #3b82f6);
        color: white;
        font-size: 16px;
    }

    .achat-chat-send-btn:hover {
        transform: scale(1.05);
    }

    /* Status Actions */
    .achat-status-bar {
        display: flex;
        gap: 6px;
        padding: 6px 12px;
        background: #f8f9fa;
        border-top: 1px solid var(--achat-border);
    }

    .achat-status-bar button {
        flex: 1;
        padding: 4px 8px;
        border: 1px solid var(--achat-border);
        background: white;
        border-radius: 6px;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .achat-status-bar button:hover {
        border-color: var(--achat-primary);
        color: var(--achat-primary);
    }

    .achat-status-bar .btn-close-conv {
        color: var(--achat-danger);
        border-color: var(--achat-danger);
    }

    /* Sound notification ping */
    @keyframes achatPing {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.2);
        }

        100% {
            transform: scale(1);
        }
    }

    /* Filter buttons */
    .achat-filter-btn {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 2px 12px;
        font-size: 12px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.15s;
    }

    .achat-filter-btn:hover {
        background: #e2e8f0;
    }

    .achat-filter-btn.active {
        background: var(--achat-primary);
        color: #fff;
        border-color: var(--achat-primary);
    }

    /* Status badges */
    .achat-status-badge {
        display: inline-block;
        font-size: 10px;
        padding: 1px 7px;
        border-radius: 10px;
        font-weight: 600;
    }

    .achat-status-badge.acik {
        background: #dcfce7;
        color: #16a34a;
    }

    .achat-status-badge.beklemede {
        background: #fef3c7;
        color: #d97706;
    }

    .achat-status-badge.cozuldu {
        background: #dbeafe;
        color: #2563eb;
    }

    .achat-status-badge.kapali {
        background: #f1f5f9;
        color: #94a3b8;
    }

    /* Delete button */
    .achat-conv-delete {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 22px;
        height: 22px;
        border: none;
        background: transparent;
        color: #94a3b8;
        border-radius: 50%;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: all 0.15s;
    }

    .achat-conv-delete:hover {
        background: #fee2e2;
        color: #ef4444;
    }

    .achat-conv-item:hover .achat-conv-delete {
        display: flex;
    }

    .achat-conv-item {
        position: relative;
    }

    /* Disabled input */
    .achat-input-disabled {
        background: #f8f9fa;
        pointer-events: none;
        opacity: 0.6;
    }

    .achat-input-disabled .achat-input-hint {
        text-align: center;
        font-size: 12px;
        color: #94a3b8;
        padding: 8px;
    }

    .achat-read-icon {
        font-size: 15px;
        color: #94a3b8;
        vertical-align: middle;
        transition: color 0.3s ease;
    }

    .achat-read-icon.done-all {
        color: #3b82f6;
        /* Okundu tik */
    }
</style>

<!-- Chat Toggle Button -->
<button class="achat-toggle-btn" onclick="AdminChat.togglePanel()" title="Canlı Destek">
    <i class='bx bx-support'></i>
    <span class="badge-count" id="achat-total-badge">0</span>
</button>

<!-- Main Conversations Panel -->
<div class="achat-panel" id="achat-panel">
    <div class="achat-panel-header" onclick="AdminChat.toggleMinimize()">
        <h4>
            <i class='bx bx-support'></i>
            Canlı Destek
            <span class="achat-badge" id="achat-header-badge" style="display:none">0</span>
        </h4>
        <div class="achat-header-actions">
            <div class="achat-status-dropdown" style="position:relative; display:inline-block;">
                <button onclick="event.stopPropagation(); AdminChat.toggleStatusMenu()" title="Durum Değiştir"
                    style="background:none; border:none; cursor:pointer; padding:4px; display:flex; align-items:center; gap:4px;">
                    <span id="achat-admin-status-dot"
                        style="width:10px; height:10px; border-radius:50%; background:#22c55e; border:2px solid #fff; display:inline-block;"></span>
                </button>
                <div id="achat-status-menu"
                    style="display:none; position:absolute; top:100%; right:0; background:#fff; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.15); padding:4px; min-width:140px; z-index:10;">
                    <div onclick="event.stopPropagation(); AdminChat.setAdminStatus('cevrimici')"
                        style="display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; color:#1e293b; transition:background .15s;"
                        onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                        <span style="width:8px; height:8px; border-radius:50%; background:#22c55e;"></span> Çevrimiçi
                    </div>
                    <div onclick="event.stopPropagation(); AdminChat.setAdminStatus('mesgul')"
                        style="display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; color:#1e293b; transition:background .15s;"
                        onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                        <span style="width:8px; height:8px; border-radius:50%; background:#f59e0b;"></span> Meşgul
                    </div>
                    <div onclick="event.stopPropagation(); AdminChat.setAdminStatus('cevrimdisi')"
                        style="display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; color:#1e293b; transition:background .15s;"
                        onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                        <span style="width:8px; height:8px; border-radius:50%; background:#94a3b8;"></span> Çevrimdışı
                    </div>
                </div>
            </div>
            <button onclick="event.stopPropagation(); AdminChat.refreshConversations()" title="Yenile">
                <i class='bx bx-refresh'></i>
            </button>
            <button onclick="event.stopPropagation(); AdminChat.closePanel()" title="Kapat">
                <i class='bx bx-x'></i>
            </button>
        </div>
    </div>
    <!-- Search & Filter -->
    <div style="padding:8px 12px; border-bottom:1px solid #e5e7eb;">
        <input type="text" id="achat-search" class="form-control form-control-sm" placeholder="Konuşma ara..."
            style="border-radius:20px; font-size:13px; padding-left:32px; background: #f1f5f9 url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2214%22 height=%2214%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2394a3b8%22 stroke-width=%222%22><circle cx=%2211%22 cy=%2211%22 r=%228%22/><path d=%22m21 21-4.35-4.35%22/></svg>') no-repeat 10px center;"
            oninput="AdminChat.filterConversations(this.value)">
        <div style="display:flex; gap:4px; margin-top:6px;">
            <button class="achat-filter-btn active" data-filter="aktif" onclick="AdminChat.setFilter('aktif')">
                Aktif
            </button>
            <button class="achat-filter-btn" data-filter="tumu" onclick="AdminChat.setFilter('tumu')">
                Tümü
            </button>
            <button class="achat-filter-btn" data-filter="kapali" onclick="AdminChat.setFilter('kapali')">
                Kapalı
            </button>
        </div>
    </div>
    <div class="achat-conv-list" id="achat-conv-list">
        <div class="achat-empty">
            <i class='bx bx-message-square-dots'></i>
            <p>Henüz destek talebi yok</p>
        </div>
    </div>
</div>

<!-- Chat Windows Container -->
<div id="achat-windows-container"></div>

<script>     /**      * Admin Chat Controller - LinkedIn Style      */
    const AdminChat = {
        panelOpen: false,
        panelMinimized: false,
        openWindows: {},
        pollInterval: null,
        lastCheck: null,
        windowPositions: [],
        seenMessageIds: new Set(),
        isFirstPoll: true,
        currentFilter: 'aktif',
        searchQuery: '',
        allConversations: [],
        adminStatus: 'cevrimici',
        statusMenuOpen: false,

        init() {
            this.loadSeenMessages();
            // Kayıtlı durum yükle
            this.loadAdminStatus();
            // Sunucu zamanını kullan (JS/PHP zaman farkını önlemek için)
            this.lastCheck = '<?php echo date("Y-m-d H:i:s"); ?>';
            this.startPolling();

            // Status menu dışına tıklanınca kapat
            document.addEventListener('click', (e) => {
                if (this.statusMenuOpen && !e.target.closest('.achat-status-dropdown')) {
                    document.getElementById('achat-status-menu').style.display = 'none';
                    this.statusMenuOpen = false;
                }
            });
        },

        /**
         * Bildirim sesi çal (Web Audio API)
         */
        playSound() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;

                const ctx = new AudioContext();
                const osc = ctx.createOscillator();
                const gainNode = ctx.createGain();

                osc.connect(gainNode);
                gainNode.connect(ctx.destination);

                osc.type = "sine";
                osc.frequency.setValueAtTime(800, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1200, ctx.currentTime + 0.1);

                gainNode.gain.setValueAtTime(0.3, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);

                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.1);
            } catch (e) {
                console.error("Sound play error:", e);
            }
        },

        // ===== Admin Status =====
        toggleStatusMenu() {
            const menu = document.getElementById('achat-status-menu');
            this.statusMenuOpen = !this.statusMenuOpen;
            menu.style.display = this.statusMenuOpen ? 'block' : 'none';
        },

        async setAdminStatus(status) {
            const statusColors = { cevrimici: '#22c55e', mesgul: '#f59e0b', cevrimdisi: '#94a3b8' };
            this.adminStatus = status;

            // Dot güncelle
            const dot = document.getElementById('achat-admin-status-dot');
            if (dot) dot.style.background = statusColors[status] || '#22c55e';

            // Menüyü kapat
            document.getElementById('achat-status-menu').style.display = 'none';
            this.statusMenuOpen = false;

            // API'ye kaydet
            try {
                await this.apiRequest('set-admin-status', { status: status });
            } catch (e) {
                console.error('Status update error:', e);
            }

            if (typeof Toastify !== 'undefined') {
                const labels = { cevrimici: 'Çevrimiçi', mesgul: 'Meşgul', cevrimdisi: 'Çevrimdışı' };
                const msg = `Durum: ${labels[status] || status}`;
                const safeMsg = typeof msg === 'string' ? msg : String(msg || '');
                Toastify({
                    text: safeMsg,
                    duration: 2000,
                    gravity: "top",
                    position: "right",
                    style: { background: statusColors[status] || '#22c55e' }
                }).showToast();
            }
        },

        async loadAdminStatus() {
            try {
                const response = await this.apiRequest('get-admin-status');
                if (response.status === 'success' && response.admin_status) {
                    const statusColors = { cevrimici: '#22c55e', mesgul: '#f59e0b', cevrimdisi: '#94a3b8' };
                    this.adminStatus = response.admin_status;
                    const dot = document.getElementById('achat-admin-status-dot');
                    if (dot) dot.style.background = statusColors[response.admin_status] || '#22c55e';
                }
            } catch (e) { }
        },

        loadSeenMessages() {
            // localStorage'dan seen mesajları yükle (sekmeler arası paylaşım)
            try {
                const stored = localStorage.getItem('achat_seen_msgs');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (Array.isArray(parsed)) {
                        this.seenMessageIds = new Set(parsed);
                    }
                }
            } catch (e) { }
        },

        saveSeenMessages() {
            try {
                // Set'i array'e çevirip son 150 kaydı sakla
                const arr = Array.from(this.seenMessageIds);
                const toSave = arr.length > 150 ? arr.slice(-150) : arr;
                localStorage.setItem('achat_seen_msgs', JSON.stringify(toSave));
            } catch (e) { }
        },

        // ===== Panel Controls =====
        togglePanel() {
            if (this.panelOpen) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        },

        openPanel() {
            this.panelOpen = true;
            this.panelMinimized = false;
            document.getElementById('achat-panel').classList.add('show');
            document.getElementById('achat-panel').classList.remove('minimized');
            this.refreshConversations();
        },

        closePanel() {
            this.panelOpen = false;
            document.getElementById('achat-panel').classList.remove('show');
        },

        toggleMinimize() {
            this.panelMinimized = !this.panelMinimized;
            document.getElementById('achat-panel').classList.toggle('minimized', this.panelMinimized);
        },

        // ===== Conversations =====
        async refreshConversations() {
            try {
                const response = await this.apiRequest('get-all-conversations');
                if (response.status === 'success') {
                    this.allConversations = response.conversations || [];
                    this.applyFilterAndSearch();
                }
            } catch (e) {
                console.error('Refresh conversations error:', e);
            }
        },

        setFilter(filter) {
            this.currentFilter = filter;
            document.querySelectorAll('.achat-filter-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.filter === filter);
            });
            this.applyFilterAndSearch();
        },

        filterConversations(query) {
            this.searchQuery = query.toLowerCase().trim();
            this.applyFilterAndSearch();
        },

        applyFilterAndSearch() {
            let filtered = this.allConversations;

            // Filtre uygula
            if (this.currentFilter === 'aktif') {
                filtered = filtered.filter(c => c.durum === 'acik' || c.durum === 'beklemede');
            } else if (this.currentFilter === 'kapali') {
                filtered = filtered.filter(c => c.durum === 'cozuldu' || c.durum === 'kapali');
            }

            // Arama uygula
            if (this.searchQuery) {
                filtered = filtered.filter(c => {
                    const name = (c.personel_adi || '').toLowerCase();
                    const preview = (c.son_mesaj_onizleme || '').toLowerCase();
                    return name.includes(this.searchQuery) || preview.includes(this.searchQuery);
                });
            }

            this.renderConversations(filtered);
        },

        renderConversations(conversations) {
            const list = document.getElementById('achat-conv-list');
            if (!list) return;

            if (!conversations || conversations.length === 0) {
                const emptyMsg = this.currentFilter === 'aktif'
                    ? 'Aktif destek talebi yok'
                    : (this.searchQuery ? 'Sonuç bulunamadı' : 'Henüz destek talebi yok');
                list.innerHTML = `
                <div class="achat-empty">
                    <i class='bx bx-message-square-dots'></i>
                    <p>${emptyMsg}</p>
                </div>`;
                return;
            }

            const statusLabels = { acik: 'Açık', beklemede: 'Beklemede', cozuldu: 'Çözüldü', kapali: 'Kapalı' };

            let html = '';
            conversations.forEach(conv => {
                const unread = parseInt(conv.okunmamis_yonetici) || 0;
                const initial = (conv.personel_adi || '?').charAt(0).toUpperCase();
                const timeStr = conv.son_mesaj_zamani ? this.formatTime(conv.son_mesaj_zamani) : '';
                const preview = conv.son_mesaj_onizleme || 'Yeni konuşma';
                const avatarImg = conv.resim_yolu
                    ? `<img src="${conv.resim_yolu}" alt="">`
                    : `<span class="initial">${initial}</span>`;
                const statusClass = conv.durum || 'acik';
                const statusLabel = statusLabels[statusClass] || statusClass;
                const isClosed = (conv.durum === 'cozuldu' || conv.durum === 'kapali');

                html += `
                <div class="achat-conv-item ${unread > 0 ? 'unread' : ''}" 
                     onclick="AdminChat.openChatWindow(${conv.id})">
                    <button class="achat-conv-delete" onclick="event.stopPropagation(); AdminChat.deleteConversation(${conv.id})" title="Sil">
                        <i class='bx bx-trash'></i>
                    </button>
                    <div class="achat-conv-avatar">
                        ${avatarImg}
                        ${!isClosed ? '<span class="online-dot"></span>' : ''}
                    </div>
                    <div class="achat-conv-info">
                        <p class="achat-conv-name">${this.escapeHtml(conv.personel_adi || 'Bilinmeyen')}</p>
                        <p class="achat-conv-preview">${this.escapeHtml(preview)}</p>
                    </div>
                    <div class="achat-conv-meta">
                        <span class="achat-status-badge ${statusClass}">${statusLabel}</span>
                        <span class="achat-conv-time">${timeStr}</span>
                        ${unread > 0 ? `<span class="achat-conv-unread-badge">${unread}</span>` : ''}
                    </div>
                </div>`;
            });

            list.innerHTML = html;
        },

        async deleteConversation(konusmaId) {
            const result = await Swal.fire({
                title: 'Konuşmayı Sil',
                text: 'Bu konuşmayı silmek istediğinize emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'Vazgeç',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            });
            if (!result.isConfirmed) return;
            try {
                await this.apiRequest('delete-conversation', { konusma_id: konusmaId });
                this.closeChatWindow(konusmaId);
                this.allConversations = this.allConversations.filter(c => c.id != konusmaId);
                this.applyFilterAndSearch();

                if (typeof Toastify !== 'undefined') {
                    const safeMsg = typeof 'Konuşma silindi' === 'string' ? 'Konuşma silindi' : String('Konuşma silindi');
                    Toastify({
                        text: safeMsg,
                        duration: 2000,
                        gravity: "top",
                        position: "right",
                        style: { background: "#ef4444" }
                    }).showToast();
                }
            } catch (e) {
                console.error('Delete conversation error:', e);
            }
        },

        updateBadges(count) {
            const totalBadge = document.getElementById('achat-total-badge');
            const headerBadge = document.getElementById('achat-header-badge');

            if (totalBadge) {
                if (count > 0) {
                    totalBadge.textContent = count;
                    totalBadge.classList.add('show');
                } else {
                    totalBadge.classList.remove('show');
                }
            }

            if (headerBadge) {
                if (count > 0) {
                    headerBadge.textContent = count;
                    headerBadge.style.display = 'inline-block';
                } else {
                    headerBadge.style.display = 'none';
                }
            }
        },

        // ===== Chat Windows =====
        async openChatWindow(konusmaId) {
            // Zaten açık mı?
            if (this.openWindows[konusmaId]) {
                const win = document.getElementById(`achat-win-${konusmaId}`);
                if (win) {
                    win.classList.remove('minimized');
                    this.openWindows[konusmaId].minimized = false;
                }
                return;
            }

            // Mesajları yükle
            try {
                const response = await this.apiRequest('get-messages', { konusma_id: konusmaId });
                console.log('Chat window response:', response);
                if (response.status !== 'success') {
                    console.error('Failed to open chat:', response);
                    return;
                }

                const conv = response.conversation;
                if (!conv) {
                    console.error('Conversation not found for id:', konusmaId);
                    return;
                }
                const messages = response.messages || [];

                // Pencere pozisyonu hesapla
                const panelRight = 24 + 390; // panel genişliği + sağ boşluk
                const winWidth = 350;
                const existingCount = Object.keys(this.openWindows).length;
                const rightPos = panelRight + (existingCount * (winWidth + 10));

                // Pencere oluştur
                this.createChatWindow(konusmaId, conv, messages, rightPos);

                if (response.opponent_last_read_id) {
                    this.markMessagesAsReadUI(konusmaId, response.opponent_last_read_id);
                }

                // Kaydet
                this.openWindows[konusmaId] = {
                    lastMessageId: messages.length > 0 ? messages[messages.length - 1].id : 0,
                    minimized: false
                };

                // Konuşmayı listede güncelle
                this.refreshConversations();
            } catch (e) {
                console.error('Open chat window error:', e);
            }
        },

        createChatWindow(konusmaId, conv, messages, rightPos) {
            const container = document.getElementById('achat-windows-container');
            const initial = (conv.personel_adi || '?').charAt(0).toUpperCase();
            const avatarContent = conv.resim_yolu
                ? `<img src="${conv.resim_yolu}" alt="">`
                : `<span class="initial">${initial}</span>`;
            const isClosed = (conv.durum === 'cozuldu' || conv.durum === 'kapali');

            const win = document.createElement('div');
            win.className = 'achat-chat-window';
            win.id = `achat-win-${konusmaId}`;
            win.style.right = rightPos + 'px';

            let messagesHtml = '';
            messages.forEach(msg => {
                messagesHtml += this.renderMessage(msg, konusmaId);
            });

            // Status bar - sadece açık konuşmalar için
            const statusBarHtml = isClosed ? '' : `
            <div class="achat-status-bar">
                <button onclick="AdminChat.updateConvStatus(${konusmaId}, 'cozuldu')">
                    <i class='bx bx-check'></i> Çözüldü
                </button>
                <button class="btn-close-conv" onclick="AdminChat.updateConvStatus(${konusmaId}, 'kapali')">
                    <i class='bx bx-x'></i> Kapat
                </button>
            </div>`;

            // Input area - kapalı konuşmalarda pasif
            const inputAreaHtml = isClosed ? `
            <div class="achat-chat-input-area achat-input-disabled">
                <div class="achat-input-hint">
                    <i class='bx bx-lock-alt'></i> Bu konuşma ${conv.durum === 'cozuldu' ? 'çözüldü' : 'kapatıldı'}
                </div>
            </div>` : `
            <div class="achat-chat-input-area">
                <div class="achat-chat-input-wrap">
                    <button class="achat-chat-input-btn achat-chat-img-btn" 
                            onclick="document.getElementById('achat-img-${konusmaId}').click()" title="Resim Gönder">
                        <i class='bx bx-image'></i>
                    </button>
                    <input type="text" class="achat-chat-input" id="achat-input-${konusmaId}" 
                           placeholder="Mesaj yazın..." 
                           onkeypress="if(event.key==='Enter')AdminChat.sendMessage(${konusmaId})">
                    <button class="achat-chat-input-btn achat-chat-send-btn" 
                            onclick="AdminChat.sendMessage(${konusmaId})" title="Gönder">
                        <i class='bx bx-send'></i>
                    </button>
                </div>
                <input type="file" id="achat-img-${konusmaId}" accept="image/*" style="display:none"
                       onchange="AdminChat.sendImageFile(${konusmaId}, this)">
            </div>`;

            win.innerHTML = `
            <div class="achat-chat-header" onclick="AdminChat.toggleWindowMinimize(${konusmaId})">
                <div class="achat-chat-header-info">
                    <div class="achat-chat-header-avatar">${avatarContent}</div>
                    <div style="min-width:0">
                        <p class="achat-chat-header-name">${this.escapeHtml(conv.personel_adi || 'Bilinmeyen')}</p>
                        <p class="achat-chat-header-dept">${this.escapeHtml(conv.departman || '')}</p>
                    </div>
                </div>
                <div class="achat-chat-header-actions">
                    <button onclick="event.stopPropagation(); AdminChat.toggleWindowMinimize(${konusmaId})" title="Küçült">
                        <i class='bx bx-minus'></i>
                    </button>
                    <button onclick="event.stopPropagation(); AdminChat.closeChatWindow(${konusmaId})" title="Kapat">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            </div>
            <div class="achat-chat-messages" id="achat-msgs-${konusmaId}">
                ${messagesHtml}
            </div>
            ${statusBarHtml}
            ${inputAreaHtml}`;

            container.appendChild(win);

            // Scroll aşağı
            this.scrollToBottom(konusmaId);

            // Focus (sadece açık konuşmalarda)
            if (!isClosed) {
                setTimeout(() => {
                    document.getElementById(`achat-input-${konusmaId}`)?.focus();
                }, 300);
            }
        },

        renderMessage(msg, konusmaId) {
            let type = 'incoming';
            if (msg.gonderen_tip === 'yonetici') type = 'outgoing';
            else if (msg.gonderen_tip === 'sistem') type = 'system';

            let content = '';
            if (msg.dosya_url && msg.dosya_tip && msg.dosya_tip.startsWith('image/')) {
                content = `
                <div class="achat-msg-bubble">
                    <img src="${msg.dosya_url}" class="achat-msg-image" 
                         onclick="window.open('${msg.dosya_url}', '_blank')" alt="Resim">
                    ${msg.mesaj && msg.mesaj !== '📷 Resim' ? `<div style="margin-top:4px">${this.escapeHtml(msg.mesaj)}</div>` : ''}
                </div>`;
            } else {
                content = `<div class="achat-msg-bubble">${this.escapeHtml(msg.mesaj)}</div>`;
            }

            const time = msg.created_at ? this.formatTime(msg.created_at) : '';
            let readIcon = '';
            if (type === 'outgoing') {
                if (msg.okundu == 1) {
                    readIcon = `<i class='bx bx-check-double achat-read-icon done-all'></i>`;
                } else {
                    readIcon = `<i class='bx bx-check achat-read-icon'></i>`;
                }
            }

            return `
            <div class="achat-msg ${type}" data-msg-id="${msg.id || ''}">
                ${content}
                ${time || readIcon ? `
                <div style="display:flex; justify-content:flex-end; align-items:center; gap:2px; margin-top:2px;">
                    ${time ? `<span class="achat-msg-time" style="margin-top:0;">${time}</span>` : ''}
                    ${readIcon}
                </div>` : ''}
            </div>`;
        },

        // ===== Send Message =====
        markMessagesAsReadUI(konusmaId, lastReadId) {
            const container = document.getElementById(`achat-msgs-${konusmaId}`);
            if (!container) return;
            const msgs = container.querySelectorAll('.achat-msg.outgoing');
            msgs.forEach(msgEl => {
                const id = parseInt(msgEl.dataset.msgId || "0");
                if (id > 0 && id <= lastReadId) {
                    const icon = msgEl.querySelector('.achat-read-icon');
                    if (icon && !icon.classList.contains('bx-check-double')) {
                        icon.className = "bx bx-check-double achat-read-icon done-all";
                    }
                }
            });
        },

        disableChatWindowInput(konusmaId, reason) {
            const win = document.getElementById(`achat-win-${konusmaId}`);
            if (!win) return;

            // Remove status bar buttons
            const statusBar = win.querySelector('.achat-status-bar');
            if (statusBar) {
                statusBar.remove();
            }

            // Replace input area with disabled message
            const inputArea = win.querySelector('.achat-chat-input-area');
            if (inputArea) {
                inputArea.className = 'achat-chat-input-area achat-input-disabled';
                inputArea.innerHTML = `
                <div class="achat-input-hint">
                    <i class='bx bx-lock-alt'></i> Bu konuşma ${reason}
                </div>`;
            }
        },
        async sendMessage(konusmaId) {
            const input = document.getElementById(`achat-input-${konusmaId}`);
            const mesaj = input?.value?.trim();
            if (!mesaj) return;

            input.value = '';

            // Optimistic UI
            const msgContainer = document.getElementById(`achat-msgs-${konusmaId}`);
            const tempId = "temp-" + Date.now();
            if (msgContainer) {
                msgContainer.insertAdjacentHTML('beforeend', `
                <div class="achat-msg outgoing" data-msg-id="${tempId}">
                    <div class="achat-msg-bubble">${this.escapeHtml(mesaj)}</div>
                    <div style="display:flex; justify-content:flex-end; align-items:center; gap:2px; margin-top:2px;">
                        <span class="achat-msg-time" style="margin-top:0;">Az önce</span>
                        <i class='bx bx-check achat-read-icon'></i>
                    </div>
                </div>`);
                this.scrollToBottom(konusmaId);
            }

            try {
                const response = await this.apiRequest('send-message', {
                    konusma_id: konusmaId,
                    mesaj: mesaj
                });
                if (response.status === 'success' && response.message_id) {
                    const el = document.querySelector(`.achat-msg[data-msg-id="${tempId}"]`);
                    if (el) el.dataset.msgId = response.message_id;
                }
            } catch (e) {
                console.error('Send message error:', e);
            }
        },

        async sendImageFile(konusmaId, input) {
            const file = input.files?.[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'send-image');
            formData.append('konusma_id', konusmaId);
            formData.append('image', file);

            try {
                const response = await fetch('views/destek/api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    const msgContainer = document.getElementById(`achat-msgs-${konusmaId}`);
                    if (msgContainer) {
                        const tempImgId = "temp-" + Date.now();
                        msgContainer.insertAdjacentHTML('beforeend', `
                        <div class="achat-msg outgoing" data-msg-id="${tempImgId}">
                            <div class="achat-msg-bubble">
                                <img src="${result.file_url}" class="achat-msg-image" alt="Resim" onclick="window.open('${result.file_url}', '_blank')">
                            </div>
                            <div style="display:flex; justify-content:flex-end; align-items:center; gap:2px; margin-top:2px;">
                                <span class="achat-msg-time" style="margin-top:0;">Az önce</span>
                                <i class='bx bx-check achat-read-icon'></i>
                            </div>
                        </div>`);
                        this.scrollToBottom(konusmaId);

                        if (result.message_id) {
                            setTimeout(() => {
                                const el = document.querySelector(`.achat-msg[data-msg-id="${tempImgId}"]`);
                                if (el) el.dataset.msgId = result.message_id;
                            }, 50);
                        }
                    }
                }
            } catch (e) {
                console.error('Image upload error:', e);
            }

            input.value = '';
        },

        // ===== Window Controls =====
        toggleWindowMinimize(konusmaId) {
            const win = document.getElementById(`achat-win-${konusmaId}`);
            if (!win || !this.openWindows[konusmaId]) return;

            this.openWindows[konusmaId].minimized = !this.openWindows[konusmaId].minimized;
            win.classList.toggle('minimized', this.openWindows[konusmaId].minimized);
        },

        closeChatWindow(konusmaId) {
            const win = document.getElementById(`achat-win-${konusmaId}`);
            if (win) win.remove();
            delete this.openWindows[konusmaId];
            this.repositionWindows();
        },

        repositionWindows() {
            const panelRight = 24 + 390;
            const winWidth = 350;
            let i = 0;
            for (const id in this.openWindows) {
                const win = document.getElementById(`achat-win-${id}`);
                if (win) {
                    win.style.right = (panelRight + (i * (winWidth + 10))) + 'px';
                    i++;
                }
            }
        },

        async updateConvStatus(konusmaId, durum) {
            try {
                await this.apiRequest('update-status', {
                    konusma_id: konusmaId,
                    durum: durum
                });
                this.closeChatWindow(konusmaId);
                this.refreshConversations();

                if (typeof Toastify !== 'undefined') {
                    Toastify({
                        text: durum === 'cozuldu' ? 'Konuşma çözüldü olarak işaretlendi' : 'Konuşma kapatıldı',
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#34c38f"
                    }).showToast();
                }
            } catch (e) {
                console.error('Update status error:', e);
            }
        },

        // ===== Polling =====
        startPolling() {
            this.stopPolling();
            this.poll(); // Hemen çalıştır
            this.pollInterval = setInterval(() => this.poll(), 3000);
        },

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        async poll() {
            try {
                const response = await this.apiRequest('check-new-messages', {
                    last_check: this.lastCheck
                });

                if (response.status !== 'success') return;

                // Badge güncelle
                this.updateBadges(response.total_unread || 0);

                // Konuşma listesi güncelle
                if (this.panelOpen) {
                    this.allConversations = response.conversations || [];
                    this.applyFilterAndSearch();
                }

                // Diğer sekmelerde görülen mesajları yakalamak için poll başında senkronize et
                this.loadSeenMessages();

                // Yeni mesajlar - sadece daha önce gösterilmemiş olanları bildir
                if (response.new_messages && response.new_messages.length > 0) {
                    let hasNew = false;
                    response.new_messages.forEach(nm => {
                        // Unique key oluştur (mesaj ID veya konusma+zaman)
                        const msgKey = nm.id ? `msg_${nm.id}` : `conv_${nm.konusma_id}_${nm.created_at}`;

                        // Bu mesaj daha önce işlendi mi?
                        if (this.seenMessageIds.has(msgKey)) return;
                        this.seenMessageIds.add(msgKey);
                        this.saveSeenMessages();

                        if (!this.isFirstPoll) {
                            hasNew = true;
                            const konusmaId = nm.konusma_id;

                            // Panel kapalıysa veya pencere kapalıysa bildirim göster
                            if (!this.openWindows[konusmaId]) {
                                this.showNotification(nm);
                            }
                        }
                    });

                    if (hasNew) {
                        this.playSound();
                    }
                }

                // İlk poll tamamlandı
                this.isFirstPoll = false;

                // Açık pencerelerdeki mesajları da güncelle
                for (const konusmaId in this.openWindows) {
                    await this.pollWindowMessages(parseInt(konusmaId));
                }

                // Zaman güncelle
                this.lastCheck = response.server_time || new Date().toISOString().replace('T', ' ').substring(0, 19);

                // Seen set'i çok büyümesini engelle (son 150 mesajı tut)
                if (this.seenMessageIds.size > 150) {
                    const arr = Array.from(this.seenMessageIds);
                    this.seenMessageIds = new Set(arr.slice(-100));
                    this.saveSeenMessages();
                }

            } catch (e) {
                // Sessiz
            }
        },

        async pollWindowMessages(konusmaId) {
            if (!this.openWindows[konusmaId]) return;

            try {
                const response = await this.apiRequest('poll-messages', {
                    konusma_id: konusmaId,
                    after_id: this.openWindows[konusmaId].lastMessageId || 0
                });

                if (response.status === 'success') {
                    if (response.opponent_last_read_id) {
                        this.markMessagesAsReadUI(konusmaId, response.opponent_last_read_id);
                    }

                    if (response.messages?.length > 0) {
                        const msgContainer = document.getElementById(`achat-msgs-${konusmaId}`);
                        if (!msgContainer) return;

                        response.messages.forEach(msg => {
                            if (msg.gonderen_tip !== 'yonetici' && !msgContainer.querySelector(`[data-msg-id="${msg.id}"]`)) {
                                msgContainer.insertAdjacentHTML('beforeend', this.renderMessage(msg, konusmaId));
                                if (msg.id > this.openWindows[konusmaId].lastMessageId) {
                                    this.openWindows[konusmaId].lastMessageId = msg.id;
                                }

                                if (msg.gonderen_tip === 'sistem' && msg.mesaj) {
                                    const lowerMsg = msg.mesaj.toLowerCase();
                                    if (lowerMsg.includes('kapatıldı') || lowerMsg.includes('çözüldü')) {
                                        this.disableChatWindowInput(konusmaId, lowerMsg.includes('çözüldü') ? 'çözüldü' : 'kapatıldı');
                                    }
                                }
                            }
                        });

                        this.scrollToBottom(konusmaId);
                    }
                }
            } catch (e) {
                // Sessiz
            }
        },

        showNotification(msg) {
            if (typeof Toastify !== 'undefined') {
                Toastify({
                    text: `<strong>${msg.personel_adi || 'Personel'}</strong><br>${msg.mesaj?.substring(0, 60)}`,
                    duration: 5000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim() || "#135bec",
                    escapeMarkup: false,
                    onClick: () => {
                        this.openPanel();
                        this.openChatWindow(msg.konusma_id);
                    }
                }).showToast();
            }
        },

        // ===== Helpers =====
        async apiRequest(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            for (const [key, value] of Object.entries(data)) {
                formData.append(key, value);
            }

            const response = await fetch('views/destek/api.php', {
                method: 'POST',
                body: formData
            });

            return await response.json();
        },

        scrollToBottom(konusmaId) {
            const container = document.getElementById(`achat-msgs-${konusmaId}`);
            if (container) {
                setTimeout(() => { container.scrollTop = container.scrollHeight; }, 50);
            }
        },

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        formatTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString.replace(' ', 'T'));
            const now = new Date();
            const diffMs = now - date;

            if (diffMs < 60000) return 'Az önce';

            const h = date.getHours().toString().padStart(2, '0');
            const m = date.getMinutes().toString().padStart(2, '0');

            if (date.toDateString() === now.toDateString()) return `${h}:${m}`;

            const yesterday = new Date(now);
            yesterday.setDate(yesterday.getDate() - 1);
            if (date.toDateString() === yesterday.toDateString()) return `Dün ${h}:${m}`;

            return `${date.getDate().toString().padStart(2, '0')}.${(date.getMonth() + 1).toString().padStart(2, '0')} ${h}:${m}`;
        }
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        AdminChat.init();
    });

    // Visiblity API
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            AdminChat.stopPolling();
        } else {
            AdminChat.startPolling();
        }
    });
</script>