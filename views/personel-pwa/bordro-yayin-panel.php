<?php
if (!isset($personel_id) || !$personel_id) { http_response_code(403); exit; }
?>
<section id="resmi-bordro-panel" aria-label="Resmî bordrolarım">
    <div class="card overflow-hidden border border-slate-100 dark:border-slate-800 shadow-sm mb-4">
        <div class="p-4 flex items-center gap-3 bg-gradient-to-r from-primary/10 to-transparent">
            <div class="w-11 h-11 rounded-2xl bg-primary text-white flex items-center justify-center shadow-lg shadow-primary/20"><span class="material-symbols-outlined">account_balance_wallet</span></div>
            <div class="min-w-0"><h2 class="font-bold text-slate-900 dark:text-white">Resmî Bordrolarım</h2><p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Dökümünüzü inceleyin ve okuma beyanınızı kaydedin.</p></div>
        </div>
    </div>
    <div id="resmi-bordro-mesaj" role="status" class="hidden mb-3 rounded-2xl px-4 py-3 text-sm font-medium"></div>
    <div id="resmi-bordro-liste" class="space-y-3" aria-live="polite"></div>
</section>
<div id="resmi-bordro-modal" class="modal-overlay" style="z-index: 200;" aria-hidden="true">
    <div class="modal-content p-0 overflow-hidden max-h-[92vh] flex flex-col">
        <div class="modal-handle mt-3"></div>
        <div class="flex items-center justify-between px-5 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div><p class="text-[11px] uppercase tracking-wider font-bold text-primary">Resmî alacak dökümü</p><h3 id="resmi-bordro-modal-baslik" class="text-lg font-bold text-slate-900 dark:text-white">Bordro detayı</h3></div>
            <button type="button" id="resmi-bordro-kapat" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500" aria-label="Kapat"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div id="resmi-bordro-detay" class="overflow-y-auto px-5 py-4 pb-8"></div>
    </div>
</div>
<script>window.bordroYayinCsrf = <?= json_encode(\App\Helper\Security::csrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/bordro-yayin.js?v=<?= filemtime(__DIR__ . '/assets/js/bordro-yayin.js') ?>"></script>
