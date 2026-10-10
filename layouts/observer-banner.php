<?php

use App\Service\ObserverMode;

if (!ObserverMode::isActive()) {
    return;
}
$observer = ObserverMode::data();
?>
<div class="observer-mode-banner" role="status">
    <div class="observer-mode-copy">
        <span class="observer-pulse-dot"></span>
        <i class="bx bx-show font-size-18 text-white"></i>
        <span class="observer-text">
            <strong><?= htmlspecialchars((string) ($observer['target_name'] ?? 'Kullanıcı'), ENT_QUOTES, 'UTF-8') ?></strong>
            kullanıcısını salt okunur görüntülüyorsunuz.
        </span>
        <span class="badge observer-firm-badge">Firma #<?= (int) ($_SESSION['firma_id'] ?? 0) ?></span>
    </div>
    <form method="post" action="/observer-mode.php" class="m-0 d-flex align-items-center">
        <input type="hidden" name="action" value="stop">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($observer['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn btn-sm btn-light fw-semibold observer-stop-btn shadow-sm">
            <i class="bx bx-exit me-1"></i> Gözlemi Bitir
        </button>
    </form>
</div>
<style>
.observer-mode-banner {
    position: fixed;
    top: 70px;
    right: 0;
    left: 250px;
    height: 38px;
    min-height: 38px;
    padding: 0 16px;
    background: linear-gradient(90deg, #6366f1 0%, #7c3aed 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    box-shadow: 0 2px 10px rgba(99, 102, 241, 0.25);
    z-index: 988;
    transition: left 0.2s cubic-bezier(0.4, 0, 0.2, 1), top 0.2s ease, width 0.2s ease;
    box-sizing: border-box;
}

.observer-mode-copy {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.observer-mode-copy strong {
    font-weight: 700;
    color: #ffffff;
}

.observer-pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #34d399;
    box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7);
    animation: observerPulse 2s infinite;
    flex-shrink: 0;
}

@keyframes observerPulse {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 6px rgba(52, 211, 153, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(52, 211, 153, 0);
    }
}

.observer-firm-badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
}

.observer-stop-btn {
    font-size: 12px;
    padding: 3px 10px;
    border-radius: 6px;
    background-color: #ffffff;
    color: #4f46e5 !important;
    border: none;
    display: inline-flex;
    align-items: center;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.observer-stop-btn:hover {
    background-color: #f8fafc;
    color: #4338ca !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

/* Sidebar Daraltıldığında (Mini Sidebar) */
body[data-sidebar-size="sm"] .observer-mode-banner {
    left: 60px !important;
}

/* Sayfa İçeriği Padding Ayarları - Butonların ve Başlıkların Üzerini Kapatmasını Önler */
body:not(:has(#quick-favorites-bar)) .main-content .page-content,
body.observer-mode-active:not(:has(#quick-favorites-bar)) .main-content .page-content,
body:has(.observer-mode-banner):not(:has(#quick-favorites-bar)) .main-content .page-content,
body:has(.observer-mode-banner):not(:has(#quick-favorites-bar)) .page-content {
    padding-top: 122px !important;
}

/* Sık Kullanılanlar Çubuğu Varken Konumlandırma */
body:has(#quick-favorites-bar) .observer-mode-banner {
    top: 112px !important;
}

body:has(#quick-favorites-bar) .main-content .page-content,
body.observer-mode-active:has(#quick-favorites-bar) .main-content .page-content,
body:has(#quick-favorites-bar):has(.observer-mode-banner) .main-content .page-content,
body:has(#quick-favorites-bar):has(.observer-mode-banner) .page-content {
    padding-top: 164px !important;
}

/* macOS Dark Teması Uyumluluğu */
[data-theme-preset="macos-dark"] body:not(:has(#quick-favorites-bar)) .observer-mode-banner {
    top: calc(var(--mac-window-gap, 6px) + 60px + var(--mac-window-gap, 6px)) !important;
}
[data-theme-preset="macos-dark"] body:not(:has(#quick-favorites-bar)) .main-content .page-content {
    padding-top: calc(var(--mac-window-gap, 6px) + 60px + var(--mac-window-gap, 6px) + 38px + 14px) !important;
}

[data-theme-preset="macos-dark"] body:has(#quick-favorites-bar) .observer-mode-banner {
    top: calc(var(--mac-window-gap, 6px) + 60px + var(--mac-window-gap, 6px) + 42px + var(--mac-window-gap, 6px)) !important;
}
[data-theme-preset="macos-dark"] body:has(#quick-favorites-bar) .main-content .page-content {
    padding-top: calc(var(--mac-window-gap, 6px) + 60px + var(--mac-window-gap, 6px) + 42px + var(--mac-window-gap, 6px) + 38px + 14px) !important;
}

/* Mobil Cihazlar (< 992px) */
@media (max-width: 991.98px) {
    .observer-mode-banner {
        left: 0 !important;
        top: 70px !important;
        padding: 0 10px;
    }
    .observer-mode-copy {
        font-size: 11.5px;
    }
    .observer-mode-copy strong {
        max-width: 100px;
        display: inline-block;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: bottom;
    }
    .observer-firm-badge {
        display: none;
    }
}
</style>
<script>
document.addEventListener('click', function (event) {
  var el = event.target.closest('button, a, input[type="submit"], .durum-degistir');
  if (!el || el.closest('.observer-mode-banner')) return;
  var text = ((el.textContent || '') + ' ' + (el.title || '')).toLocaleLowerCase('tr-TR');
  if (/(ekle|düzenle|sil|kaydet|onayla|reddet|içe aktar|yükle|yayınla|güncelle)/.test(text)) {
    event.preventDefault(); event.stopImmediatePropagation();
    if (window.Swal) Swal.fire('Salt Okunur Mod', 'Gözlem modunda veri değiştirilemez.', 'info');
  }
}, true);
</script>
