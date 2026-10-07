<?php

use App\Service\ObserverMode;

if (!ObserverMode::isActive()) {
    return;
}
$observer = ObserverMode::data();
?>
<div class="observer-mode-banner" role="status">
    <div class="observer-mode-copy">
        <i class="bx bx-show"></i>
        <strong><?= htmlspecialchars((string) ($observer['target_name'] ?? 'Kullanıcı'), ENT_QUOTES, 'UTF-8') ?></strong>
        kullanıcısını salt okunur görüntülüyorsunuz.
        <span>Firma #<?= (int) ($_SESSION['firma_id'] ?? 0) ?></span>
    </div>
    <form method="post" action="/observer-mode.php" class="m-0">
        <input type="hidden" name="action" value="stop">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($observer['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn btn-sm btn-light fw-semibold"><i class="bx bx-exit me-1"></i>Gözlemi Bitir</button>
    </form>
</div>
<style>
.observer-mode-banner{position:fixed;top:70px;right:0;left:250px;z-index:1035;min-height:44px;padding:7px 18px;background:#7c3aed;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 3px 12px rgba(76,29,149,.25)}
.observer-mode-copy{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.observer-mode-copy>i{font-size:20px}.observer-mode-copy>span{opacity:.8;font-size:12px}
body[data-sidebar-size="sm"] .observer-mode-banner{left:60px}body:has(.observer-mode-banner) .page-content{padding-top:118px!important}
@media(max-width:991.98px){.observer-mode-banner{left:0;top:70px}.observer-mode-copy{font-size:12px}}
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
