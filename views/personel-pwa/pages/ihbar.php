<?php
/**
 * Personel PWA - İhbar Sayfası
 * Kaçak Su ihbarı bildirimi ve takibi
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <?php pwaPageHeader([
        'eyebrow' => 'KAÇAK KONTROL',
        'title' => 'İhbarlar',
        'subtitle' => 'Kaçak Su ihbarlarını takip edin',
        'actions' => '<button type="button" onclick="openYeniIhbarModal()" class="pwa-circle-btn pwa-circle-btn--primary" aria-label="İhbar Yap" title="İhbar Yap"><span class="material-symbols-outlined">add_circle</span></button>',
    ]); ?>

    <div id="ihbar-transfer-panel" class="ihb-transfer" aria-live="polite"></div>
    <!-- Tab Navigation -->
    <div
        class="px-4 pt-4 pb-1 pwa-toolbar">
        <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
            <button onclick="changeIhbarTab('gelen')"
                class="ihbar-tab-btn active flex-1 py-2 text-sm font-semibold rounded-md transition-all" data-tab="gelen">
                Gelen İhbarlar
            </button>
            <button onclick="changeIhbarTab('bildirdiklerim')"
                class="ihbar-tab-btn flex-1 py-2 text-sm font-medium rounded-md text-slate-500 transition-all"
                data-tab="bildirdiklerim">
                Bildirdiklerim
            </button>
        </div>
    </div>

    <!-- Özet Kartı -->
    <div class="ihb-section">
        <div class="ihb-hero" id="ihbar-summary">
            <div class="ihb-hero__top">
                <div>
                    <div class="ihb-hero__total" id="ihb-sum-toplam">0</div>
                    <div class="ihb-hero__label" id="ihb-sum-label">toplam ihbar</div>
                </div>
                <div class="ihb-hero__rate">
                    <strong id="ihb-sum-oran">%0</strong>
                    <span>Olumlu Başarı</span>
                </div>
            </div>
            <div class="ihb-hero__bar">
                <span id="ihb-seg-devam" style="background:#38bdf8;width:0"></span>
                <span id="ihb-seg-olumlu" style="background:#34d399;width:0"></span>
                <span id="ihb-seg-olumsuz" style="background:#f87171;width:0"></span>
            </div>
            <div class="ihb-hero__grid">
                <div><small><i style="background:#94a3b8"></i>Yeni</small><b id="ihb-sum-yeni">0</b></div>
                <div><small><i style="background:#38bdf8"></i>Devam</small><b id="ihb-sum-devam">0</b></div>
                <div><small><i style="background:#34d399"></i>Olumlu</small><b id="ihb-sum-olumlu">0</b></div>
                <div><small><i style="background:#f87171"></i>Olumsuz</small><b id="ihb-sum-olumsuz">0</b></div>
            </div>
        </div>
    </div>

    <!-- Filtre & Arama Alanı -->
    <div class="ihb-section">
      <div class="ihb-filter-card">
        <!-- Arama Çubuğu & Detaylı Filtre Butonu -->
        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">search</span>
                <input type="text" id="ihbar-search-input" oninput="onIhbarSearch(this.value)"
                    placeholder="İlçe, mahalle veya açıklamada ara..."
                    class="w-full pl-9 pr-8 py-2 text-xs font-medium rounded-xl bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all shadow-sm">
                <button type="button" id="ihbar-search-clear" onclick="clearIhbarSearch()" style="display:none;"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 flex items-center">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <button type="button" onclick="toggleIhbarAdvancedFilter()" id="ihbar-filter-toggle-btn"
                class="relative px-3 py-2 rounded-xl bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 shadow-sm flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-800 transition-all active:scale-95"
                title="Detaylı Filtre">
                <span class="material-symbols-outlined text-lg">tune</span>
                <span id="ihbar-active-filter-badge" class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-600 rounded-full border-2 border-white dark:border-card-dark hidden"></span>
            </button>
        </div>

        <!-- Hızlı Durum Çipleri (Pills) -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs -mx-1 px-1">
            <button type="button" onclick="setIhbarDurumFilter('all')" data-durum="all"
                class="ihbar-durum-chip active shrink-0 px-3 py-1.5 rounded-xl font-bold transition-all shadow-sm">
                Tümü <span class="ml-1 opacity-80 font-semibold" id="chip-count-all">0</span>
            </button>
            <button type="button" onclick="setIhbarDurumFilter('olumlu')" data-durum="olumlu"
                class="ihbar-durum-chip shrink-0 px-3 py-1.5 rounded-xl font-semibold transition-all">
                <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block mr-1"></span>Olumlu <span class="ml-1 opacity-80 text-[11px]" id="chip-count-olumlu">0</span>
            </button>
            <button type="button" onclick="setIhbarDurumFilter('olumsuz')" data-durum="olumsuz"
                class="ihbar-durum-chip shrink-0 px-3 py-1.5 rounded-xl font-semibold transition-all">
                <span class="w-2 h-2 rounded-full bg-rose-500 inline-block mr-1"></span>Olumsuz <span class="ml-1 opacity-80 text-[11px]" id="chip-count-olumsuz">0</span>
            </button>
            <button type="button" onclick="setIhbarDurumFilter('yonlendirildi')" data-durum="yonlendirildi"
                class="ihbar-durum-chip shrink-0 px-3 py-1.5 rounded-xl font-semibold transition-all">
                <span class="w-2 h-2 rounded-full bg-blue-500 inline-block mr-1"></span>Yönlendirildi <span class="ml-1 opacity-80 text-[11px]" id="chip-count-yonlendirildi">0</span>
            </button>
            <button type="button" onclick="setIhbarDurumFilter('islemde')" data-durum="islemde"
                class="ihbar-durum-chip shrink-0 px-3 py-1.5 rounded-xl font-semibold transition-all">
                <span class="w-2 h-2 rounded-full bg-amber-500 inline-block mr-1"></span>İşlemde <span class="ml-1 opacity-80 text-[11px]" id="chip-count-islemde">0</span>
            </button>
            <button type="button" onclick="setIhbarDurumFilter('yeni')" data-durum="yeni"
                class="ihbar-durum-chip shrink-0 px-3 py-1.5 rounded-xl font-semibold transition-all">
                <span class="w-2 h-2 rounded-full bg-slate-400 inline-block mr-1"></span>Yeni <span class="ml-1 opacity-80 text-[11px]" id="chip-count-yeni">0</span>
            </button>
        </div>

        <!-- Açılır Gelişmiş Filtre Paneli -->
        <div id="ihbar-advanced-filter-panel" class="hidden bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <div class="flex items-center justify-between pb-1 border-b border-slate-100 dark:border-slate-800">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-red-600">tune</span>
                    Detaylı Filtreleme
                </span>
                <button type="button" onclick="resetAllIhbarFilters()" class="text-[11px] font-semibold text-red-600 hover:text-red-700">
                    Sıfırla
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">İlçe</label>
                    <select id="ihbar-ilce-filter" onchange="onIhbarIlceChange(this.value)"
                        class="w-full px-2.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white">
                        <option value="">Tüm İlçeler</option>
                        <option value="Afşin">Afşin</option>
                        <option value="Andırın">Andırın</option>
                        <option value="Çağlayancerit">Çağlayancerit</option>
                        <option value="Dulkadiroğlu">Dulkadiroğlu</option>
                        <option value="Ekinözü">Ekinözü</option>
                        <option value="Elbistan">Elbistan</option>
                        <option value="Göksun">Göksun</option>
                        <option value="Nurhak">Nurhak</option>
                        <option value="Onikişubat">Onikişubat</option>
                        <option value="Pazarcık">Pazarcık</option>
                        <option value="Türkoğlu">Türkoğlu</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">İhbar Türü</label>
                    <select id="ihbar-tur-filter" onchange="onIhbarTurChange(this.value)"
                        class="w-full px-2.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white">
                        <option value="">Tüm Türler</option><option>Abonesiz</option><option>Kaçak</option><option>Pasif</option><option>Usülsüz</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Başlangıç Tarihi</label>
                    <input type="date" id="ihbar-bas-tarih" onchange="onIhbarDateChange()"
                        class="w-full px-2.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Bitiş Tarihi</label>
                    <input type="date" id="ihbar-bit-tarih" onchange="onIhbarDateChange()"
                        class="w-full px-2.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white">
                </div>
            </div>
        </div>

      </div>
        <!-- Filtre Sonuç Bilgi Satırı -->
        <div class="flex items-center justify-between text-[11px] text-slate-500 px-2 pt-3" id="ihbar-filter-summary">
            <span id="ihbar-count-text">Yükleniyor...</span>
            <button type="button" id="ihbar-clear-all-link" onclick="resetAllIhbarFilters()" class="text-red-600 font-semibold hidden hover:underline">
                Filtreleri Temizle
            </button>
        </div>
    </div>

    <!-- İhbar List -->
    <div class="flex-1 px-4 pt-1 pb-28">
        <div class="ihb-list" id="ihbar-list">
            <div class="shimmer h-24 rounded-3xl"></div>
            <div class="shimmer h-24 rounded-3xl"></div>
            <div class="shimmer h-24 rounded-3xl"></div>
        </div>
    </div>
</div>

<!-- İhbar Detay Modal -->
<div id="ihbar-detay-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content"
        style="display: flex !important; flex-direction: column !important; max-height: 85vh !important; overflow: hidden !important; padding: 0 !important;">
        <div class="px-6 pt-3 pb-2 flex-shrink-0 bg-white dark:bg-card-dark z-10 border-b border-transparent">
            <div class="modal-handle mb-4"></div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">İhbar Detayı</h3>
                <button onclick="Modal.close('ihbar-detay-modal')"
                    class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600">close</span>
                </button>
            </div>
        </div>
        <div class="p-6 pt-4 overflow-y-auto flex-1 pb-10" style="overscroll-behavior-y: contain;">
            <div id="ihbar-detay-content"></div>
            <button onclick="Modal.close('ihbar-detay-modal')"
                class="w-full mt-6 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        </div>
    </div>
</div>

<style>
    .ihb-transfer:empty { display: none; }
    .ihb-transfer { padding: 12px 16px 0; }
    .ihb-section { padding: 16px 16px 0; }
    .ihb-list { display: flex; flex-direction: column; gap: 14px; }

    .ihb-hero { background: #191d24; color: #fff; border-radius: 28px; padding: 20px; box-shadow: 0 8px 20px -10px rgba(0, 0, 0, .35); }
    .ihb-hero__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .ihb-hero__total { font-size: 40px; font-weight: 800; line-height: 1; }
    .ihb-hero__label { font-size: 12px; color: rgba(255, 255, 255, .6); margin-top: 6px; font-weight: 600; }
    .ihb-hero__rate { background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .08); border-radius: 16px; padding: 8px 12px; text-align: center; }
    .ihb-hero__rate strong { display: block; font-size: 16px; font-weight: 800; color: #34d399; }
    .ihb-hero__rate span { font-size: 9px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: rgba(255, 255, 255, .6); }
    .ihb-hero__bar { display: flex; gap: 4px; height: 8px; margin: 18px 0 16px; }
    .ihb-hero__bar span { display: block; height: 100%; border-radius: 999px; min-width: 0; transition: width .3s ease; }
    .ihb-hero__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .ihb-hero__grid > div { background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .08); border-radius: 16px; padding: 10px 6px; text-align: center; }
    .ihb-hero__grid small { display: flex; align-items: center; justify-content: center; gap: 4px; font-size: 10px; font-weight: 700; color: rgba(255, 255, 255, .6); }
    .ihb-hero__grid small i { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .ihb-hero__grid b { display: block; font-size: 18px; font-weight: 800; margin-top: 4px; }

    .ihb-filter-card { background: #fff; border-radius: 24px; padding: 14px; display: flex; flex-direction: column; gap: 12px; box-shadow: 0 6px 16px -10px rgba(15, 23, 42, .18); border: 1px solid rgba(255, 255, 255, .9); }
    .dark .ihb-filter-card { background: #1a2130; border-color: #334155; }
    .ihb-filter-card .ihbar-durum-chip { border-radius: 999px; padding: 7px 12px; }

    .ihb-card { background: #fff; border-radius: 24px; padding: 16px; box-shadow: 0 6px 16px -10px rgba(15, 23, 42, .18); border: 1px solid rgba(255, 255, 255, .9); display: flex; flex-direction: column; gap: 10px; cursor: pointer; transition: transform .15s ease; }
    .ihb-card:active { transform: scale(.985); }
    .dark .ihb-card { background: #1a2130; border-color: #334155; }
    .ihb-card__head { display: flex; align-items: center; gap: 10px; }
    .ihb-card__icon { width: 36px; height: 36px; border-radius: 12px; background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ihb-card__icon .material-symbols-outlined { font-size: 20px; }
    .ihb-card__title { flex: 1; min-width: 0; font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.25; }
    .ihb-card__title span { font-weight: 600; color: #64748b; }
    .dark .ihb-card__title { color: #fff; }
    .ihb-card__desc { font-size: 12px; color: #64748b; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .ihb-card__tags { display: flex; flex-wrap: wrap; gap: 6px; }
    .ihb-tag { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 10px; font-weight: 700; background: #f1f5f9; color: #475569; }
    .ihb-tag--tur { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
    .ihb-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 10px; border-top: 1px solid #f1f5f9; font-size: 11px; }
    .dark .ihb-card__foot { border-top-color: #334155; }
    .ihb-card__foot .ekip { color: var(--primary); font-weight: 700; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ihb-card__foot .tarih { color: #94a3b8; flex-shrink: 0; margin-left: auto; }
    .ihb-edit-btn { width: 30px; height: 30px; border-radius: 10px; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

    .ihbar-tab-btn.active {
        background: white;
        color: var(--primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .dark .ihbar-tab-btn.active {
        background: #1a2130;
    }

    .ihbar-durum-chip {
        background: #ffffff;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .dark .ihbar-durum-chip {
        background: #1e293b;
        color: #94a3b8;
        border-color: #334155;
    }

    .ihbar-durum-chip.active {
        background: #dc2626 !important;
        color: #ffffff !important;
        border-color: #dc2626 !important;
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
    }

    .ihbar-durum-chip.active span {
        color: #ffffff !important;
    }

    #pwa-full-modal.ihbar-form-modal > .fixed.top-0,
    #pwa-full-modal.ihbar-form-modal > button.fixed {
        display: none !important;
    }

    #pwa-full-modal.ihbar-form-modal {
        top: auto !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: min(100%, 480px);
        height: min(92dvh, 780px);
        margin: 0 auto;
        border-radius: 28px 28px 0 0;
        overflow: hidden;
        background: #e6eaef;
        box-shadow:
            0 -100vh 0 100vh rgba(22, 25, 32, .48),
            0 -18px 44px rgba(22, 25, 32, .24);
    }

    .dark #pwa-full-modal.ihbar-form-modal {
        background: #14171c;
    }

    #pwa-full-modal.ihbar-form-modal #pwa-full-modal-content {
        padding-bottom: 0 !important;
        scroll-padding-bottom: 7rem;
    }

    .ihbar-sheet-handle {
        width: 40px;
        height: 5px;
        margin: 10px auto 0;
        border-radius: 999px;
        background: rgba(22, 25, 32, .18);
    }

    .dark .ihbar-sheet-handle {
        background: rgba(255, 255, 255, .22);
    }

    .ihbar-sheet-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px 14px;
    }

    .ihbar-sheet-head__icon {
        flex: none;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--primary);
        color: #fff;
        box-shadow: 0 10px 18px -8px rgba(var(--primary-rgb, 19, 91, 236), .6);
    }

    .ihbar-sheet-head h1 {
        margin: 0;
        font-size: 20px;
        line-height: 1.15;
        font-weight: 800;
        color: #161920;
    }

    .ihbar-sheet-head p {
        margin: 2px 0 0;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
    }

    .dark .ihbar-sheet-head h1 { color: #e8ecf2; }
    .dark .ihbar-sheet-head p { color: #94a3b8; }

    .ihbar-sheet-body {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 0 16px 20px;
    }

    .ihbar-card {
        padding: 18px 16px;
        border-radius: 28px;
        background: #fff;
        border: 1px solid rgba(255, 255, 255, .9);
        box-shadow: 0 18px 36px -24px rgba(22, 25, 32, .3);
    }

    .dark .ihbar-card {
        background: #1d222a;
        border-color: rgba(255, 255, 255, .07);
    }

    .ihbar-card + .ihbar-card { margin-top: 0; }

    .ihbar-card__title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 14px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
    }

    .ihbar-card__title .material-symbols-outlined { font-size: 18px; color: var(--primary); }
    .dark .ihbar-card__title { color: #94a3b8; }

    .ihbar-fields { display: flex; flex-direction: column; gap: 14px; }

    .ihbar-field label {
        display: block;
        margin: 0 0 6px 2px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }

    .ihbar-field label small {
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
    }

    .dark .ihbar-field label { color: #cbd5e1; }

    .ihbar-field .req { color: #e11d48; }

    .ihbar-input {
        display: block;
        width: 100%;
        min-height: 52px;
        padding: 13px 16px;
        border-radius: 18px;
        border: 2px solid transparent;
        background: #f1f4f8;
        color: #161920;
        font-size: 15px;
        font-weight: 700;
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .ihbar-input::placeholder { font-weight: 600; color: #94a3b8; }

    .ihbar-input:focus {
        background: #fff;
        border-color: var(--primary);
        box-shadow: 0 0 0 5px rgba(var(--primary-rgb, 19, 91, 236), .14);
    }

    select.ihbar-input {
        appearance: none;
        -webkit-appearance: none;
        padding-right: 44px;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 14px center;
        background-size: 20px;
    }

    textarea.ihbar-input {
        min-height: 104px;
        resize: none;
        line-height: 1.45;
    }

    .dark .ihbar-input {
        background: rgba(255, 255, 255, .06);
        color: #f1f5f9;
    }

    .dark .ihbar-input:focus {
        background: rgba(var(--primary-rgb, 19, 91, 236), .12);
    }

    .ihbar-konum-btn {
        width: 100%;
        min-height: 52px;
        padding: 0 16px;
        border: 0;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 800;
        color: var(--primary);
        background: rgba(var(--primary-rgb, 19, 91, 236), .1);
        transition: transform .15s ease, background .2s ease;
    }

    .ihbar-konum-btn:active { transform: scale(.98); }
    .ihbar-konum-btn:disabled { opacity: .6; }

    .ihbar-konum-note {
        margin: 8px 4px 0;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
    }

    .dark .ihbar-konum-note { color: #94a3b8; }

    .ihbar-upload-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .ihbar-upload-tile {
        min-height: 88px;
        padding: 12px 8px;
        border-radius: 22px;
        border: 2px dashed rgba(100, 116, 139, .35);
        background: #f6f8fb;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 800;
        color: #475569;
        cursor: pointer;
        transition: transform .15s ease, border-color .2s ease, background .2s ease;
    }

    .ihbar-upload-tile:active { transform: scale(.97); }
    .ihbar-upload-tile:hover { border-color: var(--primary); }

    .ihbar-upload-tile .material-symbols-outlined { font-size: 26px; color: var(--primary); }
    .ihbar-upload-tile--muted .material-symbols-outlined { color: #94a3b8; }

    .dark .ihbar-upload-tile {
        background: rgba(255, 255, 255, .04);
        border-color: rgba(255, 255, 255, .16);
        color: #cbd5e1;
    }

    .ihbar-progress {
        margin-top: 12px;
        padding: 12px 14px;
        border-radius: 20px;
        background: rgba(var(--primary-rgb, 19, 91, 236), .08);
        border: 1px solid rgba(var(--primary-rgb, 19, 91, 236), .18);
    }

    .ihbar-form-footer {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 135;
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 10px;
        margin: 0;
        padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
        background: linear-gradient(to top, #e6eaef 62%, rgba(230, 234, 239, 0));
    }

    .dark .ihbar-form-footer {
        background: linear-gradient(to top, #14171c 62%, rgba(20, 23, 28, 0));
    }

    .ihbar-btn {
        min-height: 52px;
        border-radius: 999px;
        border: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 800;
        transition: transform .15s ease, opacity .2s ease;
    }

    .ihbar-btn:active { transform: scale(.97); }
    .ihbar-btn:disabled { opacity: .6; }

    .ihbar-btn--ghost {
        background: #fff;
        color: #161920;
        border: 1px solid rgba(255, 255, 255, .9);
        box-shadow: 0 8px 18px -10px rgba(22, 25, 32, .3);
    }

    .dark .ihbar-btn--ghost {
        background: #262c36;
        color: #e8ecf2;
        border-color: rgba(255, 255, 255, .1);
    }

    .ihbar-btn--primary {
        background: var(--primary);
        color: #fff;
        box-shadow: 0 14px 24px -12px rgba(var(--primary-rgb, 19, 91, 236), .7);
    }
</style>

<script>
    let currentIhbarTab = 'gelen';
    let ihbarBildirdiklerimData = [];
    let ihbarGelenData = [];
    const IHBAR_MAX_FOTO = <?= (int) \App\Model\IhbarModel::MAX_FOTO ?>;
    const IHBAR_MAX_VIDEO = <?= (int) \App\Model\IhbarModel::MAX_VIDEO ?>;
    const IHBAR_VIDEO_MAX_SURE = <?= (int) \App\Model\IhbarModel::VIDEO_MAX_SURE ?>;
    const IHBAR_VIDEO_MAX_BYTE = <?= (int) \App\Model\IhbarModel::VIDEO_MAX_BYTE ?>;
    let ihbarSeciliFotolar = [];
    let ihbarSeciliVideolar = [];
    let ihbarEditId = null;
    let ihbarEditToken = null;
    let ihbarMevcutFotograflar = [];

    async function ihbarSayfayiBaslat() {
        try {
            if (window.OfflineQueue) {
                OfflineQueue.mountPanel('ihbar', 'ihbar-transfer-panel', { onDelete: async uuid => {
                    const confirmed = await Alert.confirm('Cihazdan kaldır', 'Gönderilmemiş fotoğraf ve videolar cihazdan kaldırılacak. Sunucuya ulaşmış kayıtlar bu işlemle silinmez. Devam edilsin mi?', 'Kaldır', 'Vazgeç');
                    if (confirmed) await OfflineQueue.sil(uuid);
                } });
            }
        } catch (e) {
            console.warn('İhbar transfer panel başlatılamadı:', e);
        }

        window.addEventListener('kuyruk-degisti', function (e) {
            if (e.detail && e.detail.sebep === 'gonderim' && e.detail.gonderildi) loadIhbarlar();
        });

        window.addEventListener('online', function () {
            loadIhbarlar();
        });

        loadIhbarlar().then(ihbarDerinBaglantiyiAc).catch(function (err) {
            console.error('İhbar yükleme hatası:', err);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ihbarSayfayiBaslat);
    } else {
        ihbarSayfayiBaslat();
    }

    function ihbarDerinBaglantiyiAc() {
        const parametreler = new URLSearchParams(window.location.search);
        const ihbarId = parseInt(parametreler.get('ihbar_id') || '0', 10);
        if (!ihbarId) return;

        const tip = parametreler.get('tip') === 'bildirdiklerim' ? 'bildirdiklerim' : 'gelen';
        if (tip !== currentIhbarTab) {
            changeIhbarTab(tip);
        }
        showIhbarDetay(ihbarId, tip);

        parametreler.delete('ihbar_id');
        parametreler.delete('tip');
        const kalan = parametreler.toString();
        history.replaceState(null, '', window.location.pathname + (kalan ? '?' + kalan : ''));
    }

    async function loadIhbarlar() {
        const cacheKey = 'ihbar-listeler';
        try {
            const [ownRes, incomingRes] = await Promise.allSettled([
                API.request('listIhbarlarim'),
                API.request('listGelenIhbarlar')
            ]);

            let fetchedAny = false;
            if (ownRes.status === 'fulfilled' && ownRes.value && ownRes.value.success) {
                ihbarBildirdiklerimData = ownRes.value.data || [];
                fetchedAny = true;
            }
            if (incomingRes.status === 'fulfilled' && incomingRes.value && incomingRes.value.success) {
                ihbarGelenData = incomingRes.value.data || [];
                fetchedAny = true;
            }

            if (fetchedAny) {
                if (window.OfflineQueue) {
                    OfflineQueue.referansKaydet(cacheKey, { own: ihbarBildirdiklerimData, incoming: ihbarGelenData }).catch(() => {});
                }
                renderIhbarlar();
                return;
            }
            throw new Error('İhbar listesi alınamadı.');
        } catch (error) {
            console.warn('İhbar listesi yüklenemedi, önbellek deneniyor:', error);
            let cached = null;
            try {
                cached = window.OfflineQueue ? await OfflineQueue.referansOku(cacheKey) : null;
            } catch (e) {}

            if (cached) {
                ihbarBildirdiklerimData = cached.own || [];
                ihbarGelenData = cached.incoming || [];
                renderIhbarlar();
            } else {
                const listEl = document.getElementById('ihbar-list');
                if (listEl) {
                    listEl.innerHTML = `
                        <div class="p-8 text-center bg-white dark:bg-card-dark rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm my-2">
                            <p class="text-sm font-bold text-slate-800 dark:text-white">Liste Yüklenemedi</p>
                            <p class="text-xs text-slate-400 mt-1 mb-3">İnternet bağlantınızı kontrol edip tekrar deneyin.</p>
                            <button type="button" onclick="loadIhbarlar()" class="btn-primary px-4 py-2 text-xs">Tekrar Dene</button>
                        </div>`;
                }
            }
        }
    }

    function changeIhbarTab(tab) {
        currentIhbarTab = tab;

        document.querySelectorAll('.ihbar-tab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.classList.add('text-slate-500');
        });

        const activeBtn = document.querySelector(`[data-tab="${tab}"]`);
        activeBtn.classList.add('active');
        activeBtn.classList.remove('text-slate-500');

        renderIhbarlar();
    }

    function ihbarDurumBadge(durum) {
        switch (durum) {
            case 'yeni': return 'badge-gray';
            case 'yonlendirildi': return 'badge-primary';
            case 'islemde': return 'badge-warning';
            case 'olumlu': return 'badge-success';
            case 'olumsuz': return 'badge-danger';
            default: return 'badge-gray';
        }
    }

    function ihbarDurumText(durum) {
        const map = { yeni: 'Yeni', yonlendirildi: 'Yönlendirildi', islemde: 'İşlemde', olumlu: 'Olumlu', olumsuz: 'Olumsuz' };
        return map[durum] || durum;
    }

    // Filtre Değişkenleri
    let filterSearch = '';
    let filterDurum = 'all';
    let filterIlce = '';
    let filterTur = '';
    let filterBasTarih = '';
    let filterBitTarih = '';
    let isAdvancedFilterOpen = false;

    function onIhbarSearch(val) {
        filterSearch = (val || '').trim();
        const clearBtn = document.getElementById('ihbar-search-clear');
        if (clearBtn) {
            clearBtn.style.display = filterSearch ? 'flex' : 'none';
        }
        renderIhbarlar();
    }

    function clearIhbarSearch() {
        const input = document.getElementById('ihbar-search-input');
        if (input) input.value = '';
        onIhbarSearch('');
    }

    function setIhbarDurumFilter(durum) {
        filterDurum = durum || 'all';
        document.querySelectorAll('.ihbar-durum-chip').forEach(chip => {
            if (chip.getAttribute('data-durum') === filterDurum) {
                chip.classList.add('active');
            } else {
                chip.classList.remove('active');
            }
        });
        renderIhbarlar();
    }

    function toggleIhbarAdvancedFilter() {
        isAdvancedFilterOpen = !isAdvancedFilterOpen;
        const panel = document.getElementById('ihbar-advanced-filter-panel');
        if (panel) {
            panel.classList.toggle('hidden', !isAdvancedFilterOpen);
        }
    }

    function onIhbarIlceChange(val) {
        filterIlce = val || '';
        checkAdvancedFilterBadge();
        renderIhbarlar();
    }

    function onIhbarTurChange(val) {
        filterTur = val || '';
        checkAdvancedFilterBadge();
        renderIhbarlar();
    }

    function onIhbarDateChange() {
        filterBasTarih = document.getElementById('ihbar-bas-tarih')?.value || '';
        filterBitTarih = document.getElementById('ihbar-bit-tarih')?.value || '';
        checkAdvancedFilterBadge();
        renderIhbarlar();
    }

    function checkAdvancedFilterBadge() {
        const badge = document.getElementById('ihbar-active-filter-badge');
        const hasAdvanced = Boolean(filterIlce || filterTur || filterBasTarih || filterBitTarih);
        if (badge) {
            badge.classList.toggle('hidden', !hasAdvanced);
        }
    }

    function resetAllIhbarFilters() {
        filterSearch = '';
        filterDurum = 'all';
        filterIlce = '';
        filterTur = '';
        filterBasTarih = '';
        filterBitTarih = '';

        const searchInput = document.getElementById('ihbar-search-input');
        if (searchInput) searchInput.value = '';
        const searchClear = document.getElementById('ihbar-search-clear');
        if (searchClear) searchClear.style.display = 'none';

        const ilceSelect = document.getElementById('ihbar-ilce-filter');
        if (ilceSelect) ilceSelect.value = '';
        const turSelect = document.getElementById('ihbar-tur-filter');
        if (turSelect) turSelect.value = '';

        const basInput = document.getElementById('ihbar-bas-tarih');
        if (basInput) basInput.value = '';

        const bitInput = document.getElementById('ihbar-bit-tarih');
        if (bitInput) bitInput.value = '';

        document.querySelectorAll('.ihbar-durum-chip').forEach(chip => {
            chip.classList.toggle('active', chip.getAttribute('data-durum') === 'all');
        });

        checkAdvancedFilterBadge();
        renderIhbarlar();
    }

    function updateIhbarChipCounts(rawData) {
        const counts = {
            all: rawData ? rawData.length : 0,
            olumlu: 0,
            olumsuz: 0,
            yonlendirildi: 0,
            islemde: 0,
            yeni: 0
        };

        if (Array.isArray(rawData)) {
            rawData.forEach(item => {
                const d = (item.durum || '').toLowerCase();
                if (counts[d] !== undefined) {
                    counts[d]++;
                }
            });
        }

        updateIhbarSummary(rawData);

        Object.keys(counts).forEach(k => {
            const el = document.getElementById('chip-count-' + k);
            if (el) el.textContent = counts[k];
        });
    }

    function getFilteredIhbarlar() {
        const rawData = currentIhbarTab === 'gelen' ? ihbarGelenData : ihbarBildirdiklerimData;
        updateIhbarChipCounts(rawData);

        if (!Array.isArray(rawData)) return [];

        return rawData.filter(item => {
            if (filterDurum !== 'all' && (item.durum || '').toLowerCase() !== filterDurum) {
                return false;
            }

            if (filterIlce && (item.ilce || '').toLocaleLowerCase('tr-TR') !== filterIlce.toLocaleLowerCase('tr-TR')) {
                return false;
            }
            if (filterTur && (item.ihbar_turu || '') !== filterTur) return false;

            if (filterSearch) {
                const searchLower = filterSearch.toLocaleLowerCase('tr-TR');
                const textPool = [
                    item.ihbar_turu || '',
                    item.ilce || '',
                    item.mahalle || '',
                    item.aciklama || '',
                    item.telefon || '',
                    item.komsu_abone_no || '',
                    item.atanan_ekip_adi || ''
                ].join(' ').toLocaleLowerCase('tr-TR');

                if (!textPool.includes(searchLower)) {
                    return false;
                }
            }

            if (filterBasTarih || filterBitTarih) {
                if (!item.tarih) return false;
                const match = item.tarih.match(/^(\d{2})\.(\d{2})\.(\d{4})/);
                if (match) {
                    const itemDateISO = `${match[3]}-${match[2]}-${match[1]}`;
                    if (filterBasTarih && itemDateISO < filterBasTarih) {
                        return false;
                    }
                    if (filterBitTarih && itemDateISO > filterBitTarih) {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    function renderIhbarlar() {
        const container = document.getElementById('ihbar-list');
        const rawData = currentIhbarTab === 'gelen' ? ihbarGelenData : ihbarBildirdiklerimData;
        const filteredData = getFilteredIhbarlar();

        const countText = document.getElementById('ihbar-count-text');
        const clearAllLink = document.getElementById('ihbar-clear-all-link');
        const hasActiveFilters = Boolean(filterSearch || (filterDurum !== 'all') || filterIlce || filterTur || filterBasTarih || filterBitTarih);

        if (countText) {
            const rawCount = Array.isArray(rawData) ? rawData.length : 0;
            if (hasActiveFilters) {
                countText.innerHTML = `<span class="font-bold text-slate-700 dark:text-slate-300">${filteredData.length}</span> / ${rawCount} ihbar listeleniyor`;
            } else {
                countText.innerHTML = `Toplam <span class="font-bold text-slate-700 dark:text-slate-300">${filteredData.length}</span> ihbar`;
            }
        }

        if (clearAllLink) {
            clearAllLink.style.display = hasActiveFilters ? 'inline-block' : 'none';
        }

        if (!rawData || rawData.length === 0) {
            container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-symbols-outlined">campaign</span>
                </div>
                <p class="text-slate-600 dark:text-slate-400 font-medium">Kayıt bulunamadı</p>
            </div>`;
            return;
        }

        if (filteredData.length === 0) {
            container.innerHTML = `
            <div class="p-8 text-center bg-white dark:bg-card-dark rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm my-2">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                    <span class="material-symbols-outlined text-2xl">search_off</span>
                </div>
                <p class="text-sm font-bold text-slate-800 dark:text-white">Kriterlere Uygun İhbar Bulunamadı</p>
                <p class="text-xs text-slate-400 mt-1 mb-4">Arama veya filtre kriterlerinizi değiştirerek tekrar deneyebilirsiniz.</p>
                <button type="button" onclick="resetAllIhbarFilters()"
                    class="px-4 py-2 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 text-xs font-bold rounded-xl border border-red-200 dark:border-red-900/50 hover:bg-red-100 transition-colors">
                    Filtreleri Temizle
                </button>
            </div>`;
            return;
        }

        container.innerHTML = filteredData.map(ihbar => `
        <div class="ihb-card" onclick="showIhbarDetay(${Number(ihbar.id)}, '${currentIhbarTab}')">
            <div class="ihb-card__head">
                <span class="ihb-card__icon"><span class="material-symbols-outlined">location_on</span></span>
                <p class="ihb-card__title">${ihbEsc(ihbar.ilce || '-')} <span>· ${ihbEsc(ihbar.mahalle || '-')}</span></p>
                <span class="badge ${ihbarDurumBadge(ihbar.durum)}">${ihbEsc(ihbarDurumText(ihbar.durum))}</span>
            </div>
            ${ihbar.aciklama ? `<p class="ihb-card__desc">${ihbEsc(ihbar.aciklama)}</p>` : ''}
            ${(ihbar.ihbar_turu || ihbar.komsu_abone_no) ? `<div class="ihb-card__tags">
                ${ihbar.ihbar_turu ? `<span class="ihb-tag ihb-tag--tur">${ihbEsc(ihbar.ihbar_turu)}</span>` : ''}
                ${ihbar.komsu_abone_no ? `<span class="ihb-tag">Abone: ${ihbEsc(ihbar.komsu_abone_no)}</span>` : ''}
            </div>` : ''}
            <div class="ihb-card__foot">
                ${ihbar.atanan_ekip_adi ? `<span class="ekip">${ihbEsc(ihbar.atanan_ekip_adi)}</span>` : ''}
                <span class="tarih">${ihbEsc(ihbar.tarih || '')}</span>
                ${(currentIhbarTab === 'bildirdiklerim' && ihbar.duzenlenebilir) ? `
                <button type="button" onclick="event.stopPropagation(); startEditIhbar('${ihbEsc(ihbar.edit_token)}');" class="ihb-edit-btn" aria-label="Düzenle">
                    <span class="material-symbols-outlined text-[16px]">edit</span>
                </button>` : ''}
            </div>
        </div>`).join('');
    }

    function ihbEsc(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    }

    function updateIhbarSummary(rawData) {
        const list = Array.isArray(rawData) ? rawData : [];
        const sayi = d => list.filter(i => (i.durum || '').toLowerCase() === d).length;
        const toplam = list.length;
        const yeni = sayi('yeni');
        const devam = sayi('yonlendirildi') + sayi('islemde');
        const olumlu = sayi('olumlu');
        const olumsuz = sayi('olumsuz');
        const sonuclanan = olumlu + olumsuz;
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('ihb-sum-toplam', toplam);
        set('ihb-sum-yeni', yeni);
        set('ihb-sum-devam', devam);
        set('ihb-sum-olumlu', olumlu);
        set('ihb-sum-olumsuz', olumsuz);
        set('ihb-sum-oran', '%' + (sonuclanan ? Math.round(olumlu / sonuclanan * 100) : 0));
        const yuzde = v => (toplam ? (v / toplam * 100) : 0) + '%';
        [['ihb-seg-devam', devam], ['ihb-seg-olumlu', olumlu], ['ihb-seg-olumsuz', olumsuz]].forEach(([id, v]) => {
            const el = document.getElementById(id);
            if (el) el.style.width = yuzde(v);
        });
    }

    async function startEditIhbar(token) {
        const ihbar = ihbarBildirdiklerimData.find(i => i.edit_token === token && i.duzenlenebilir);
        if (!ihbar) return;

        try {
            const response = await API.request('ihbarDetay', { id: ihbar.id });
            if (response.success) {
                openYeniIhbarModal({ ...ihbar, ...response.data });
            } else {
                openYeniIhbarModal(ihbar);
            }
        } catch (error) {
            console.error('İhbar detay hatası:', error);
            openYeniIhbarModal(ihbar);
        }
    }

    function openYeniIhbarModal(editData = null) {
        ihbarSeciliFotolar = [];
        ihbarSeciliVideolar = [];
        ihbarEditId = editData ? Number(editData.id) : null;
        ihbarEditToken = editData?.edit_token || null;
        ihbarMevcutFotograflar = editData?.fotograflar || [];
        document.getElementById('pwa-full-modal')?.classList.add('ihbar-form-modal');

        showPwaFullModal({
            html: `
                <div class="ihbar-sheet-handle"></div>
                <div class="ihbar-sheet-head">
                    <span class="ihbar-sheet-head__icon"><span class="material-symbols-outlined">${editData ? 'edit_note' : 'campaign'}</span></span>
                    <div>
                        <h1>${editData ? 'İhbarı Güncelle' : 'İhbar Bildir'}</h1>
                        <p>${editData ? 'İhbar sonuçlandırılmadan önce bilgileri düzenleyebilirsiniz.' : 'Kaçak Su kullanımı ihbarınızı bildirin.'}</p>
                    </div>
                </div>

                <form id="ihbar-form" class="ihbar-sheet-body">
                    <section class="ihbar-card">
                        <h2 class="ihbar-card__title"><span class="material-symbols-outlined">assignment</span>İhbar Bilgileri</h2>
                        <div class="ihbar-fields">
                            <div class="ihbar-field">
                                <label>İhbar Türü <span class="req">*</span></label>
                                <select name="ihbar_turu" class="ihbar-input" required>
                                    <option value="">Tür seçin...</option>
                                    ${['Abonesiz', 'Kaçak', 'Pasif', 'Usülsüz'].map(tur => `<option value="${tur}" ${editData?.ihbar_turu === tur ? 'selected' : ''}>${tur}</option>`).join('')}
                                </select>
                            </div>
                            <div class="ihbar-field">
                                <label>İlçe <span class="req">*</span></label>
                                <select name="ilce" class="ihbar-input" required>
                                    <option value="">İlçe seçin...</option>
                                    ${['Afşin', 'Andırın', 'Çağlayancerit', 'Dulkadiroğlu', 'Ekinözü', 'Elbistan', 'Göksun', 'Nurhak', 'Onikişubat', 'Pazarcık', 'Türkoğlu']
                                        .map(ilce => `<option value="${ilce}" ${editData?.ilce === ilce ? 'selected' : ''}>${ilce}</option>`).join('')}
                                </select>
                            </div>
                            <div class="ihbar-field">
                                <label>Mahalle <span class="req">*</span></label>
                                <input type="text" name="mahalle" class="ihbar-input" placeholder="Mahalle" required value="${editData?.mahalle ?? ''}">
                            </div>
                            <div class="ihbar-field">
                                <label>Komşu / Abone Telefonu</label>
                                <input type="tel" name="telefon" class="ihbar-input" placeholder="05XX XXX XX XX" value="${editData?.telefon ?? ''}">
                            </div>
                            <div class="ihbar-field">
                                <label>Komşu Abone No</label>
                                <input type="text" name="komsu_abone_no" class="ihbar-input" placeholder="Abone numarasını yazın" value="${editData?.komsu_abone_no ?? ''}">
                            </div>
                        </div>
                    </section>

                    <section class="ihbar-card">
                        <h2 class="ihbar-card__title"><span class="material-symbols-outlined">location_on</span>Konum Bilgisi <span class="req" style="color:#e11d48">*</span></h2>
                        <input type="hidden" name="konum_lat" id="ihbar-konum-lat" value="${editData?.konum_lat ?? ''}">
                        <input type="hidden" name="konum_lng" id="ihbar-konum-lng" value="${editData?.konum_lng ?? ''}">
                        <input type="hidden" name="konum_dogruluk" id="ihbar-konum-dogruluk" value="${editData?.konum_dogruluk ?? ''}">
                        <button type="button" id="ihbar-konum-btn" onclick="ihbarKonumAl()" class="ihbar-konum-btn">
                            <span class="material-symbols-outlined">my_location</span>
                            <span id="ihbar-konum-text">${editData?.konum_lat && editData?.konum_lng ? 'Konum eklendi · Yenilemek için dokunun' : 'Mevcut Konumumu Ekle'}</span>
                        </button>
                        <p id="ihbar-konum-durum" class="ihbar-konum-note">
                            ${editData?.konum_lat && editData?.konum_lng ? `${Number(editData.konum_lat).toFixed(6)}, ${Number(editData.konum_lng).toFixed(6)}` : 'Konum izni zorunludur ve bu ihbar için kullanılacaktır.'}
                        </p>
                    </section>

                    <section class="ihbar-card">
                        <h2 class="ihbar-card__title"><span class="material-symbols-outlined">notes</span>Açıklama <span style="color:#e11d48">*</span></h2>
                        <textarea name="aciklama" class="ihbar-input" placeholder="İhbar detaylarını yazınız..." required>${editData?.aciklama ?? ''}</textarea>
                    </section>

                    <section class="ihbar-card">
                        <h2 class="ihbar-card__title"><span class="material-symbols-outlined">photo_camera</span>Fotoğraf Ekle <span style="color:#e11d48">*</span> <small style="font-weight:600;text-transform:none;letter-spacing:0">(en fazla ${IHBAR_MAX_FOTO} adet)</small></h2>
                        ${editData && ihbarMevcutFotograflar.length > 0 ? `
                        <div class="mb-3">
                            <p class="text-xs text-slate-500 mb-1">Yüklü dosyalar</p>
                            <div class="flex flex-wrap gap-2">
                                ${ihbarMevcutFotograflar.map(f => f.medya_tipi === 'video'
                                    ? `<div class="w-16 h-16 rounded-2xl border border-slate-200 dark:border-slate-700 flex items-center justify-center bg-slate-100"><span class="material-symbols-outlined text-slate-400">movie</span></div>`
                                    : `<img src="${f.kucuk_url || f.url}" class="w-16 h-16 object-cover rounded-2xl border border-slate-200 dark:border-slate-700">`).join('')}
                            </div>
                        </div>` : ''}
                        <input type="file" id="ihbar-foto-input" accept="image/*" multiple class="hidden">
                        <input type="file" id="ihbar-foto-camera-input" accept="image/*" capture="environment" class="hidden">
                        <div class="ihbar-upload-grid">
                            <div class="ihbar-upload-tile" onclick="document.getElementById('ihbar-foto-camera-input').click()">
                                <span class="material-symbols-outlined">photo_camera</span>
                                <span>Kameradan Çek</span>
                            </div>
                            <div class="ihbar-upload-tile ihbar-upload-tile--muted" onclick="document.getElementById('ihbar-foto-input').click()">
                                <span class="material-symbols-outlined">photo_library</span>
                                <span>Galeriden Seç</span>
                            </div>
                        </div>
                        <div id="ihbar-foto-preview" class="flex flex-wrap gap-2 mt-3"></div>
                    </section>

                    <section class="ihbar-card" style="margin-bottom:5.5rem">
                        <h2 class="ihbar-card__title"><span class="material-symbols-outlined">videocam</span>Video Ekle <small style="font-weight:600;text-transform:none;letter-spacing:0">(en fazla ${IHBAR_MAX_VIDEO} adet, ${IHBAR_VIDEO_MAX_SURE} sn)</small></h2>
                        <input type="file" id="ihbar-video-input" accept="video/*" class="hidden">
                        <input type="file" id="ihbar-video-camera-input" accept="video/*" capture="environment" class="hidden">
                        <div class="ihbar-upload-grid">
                            <div class="ihbar-upload-tile" onclick="document.getElementById('ihbar-video-camera-input').click()">
                                <span class="material-symbols-outlined">videocam</span>
                                <span>Kamera ile Çek</span>
                            </div>
                            <div class="ihbar-upload-tile ihbar-upload-tile--muted" onclick="document.getElementById('ihbar-video-input').click()">
                                <span class="material-symbols-outlined">video_library</span>
                                <span>Galeriden Seç</span>
                            </div>
                        </div>

                        <div id="ihbar-video-progress-container" class="ihbar-progress hidden">
                            <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">
                                <span id="ihbar-video-progress-text" class="flex items-center gap-1.5 truncate">
                                    <svg class="w-4 h-4 text-primary animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Video Yükleniyor...</span>
                                </span>
                                <span id="ihbar-video-progress-percent" class="text-primary font-extrabold text-xs ml-2">0%</span>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3 overflow-hidden p-0.5">
                                <div id="ihbar-video-progress-bar" class="bg-primary h-2 rounded-full transition-all duration-150 w-0"></div>
                            </div>
                            <div class="flex justify-between items-center text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 font-mono">
                                <span id="ihbar-video-progress-name" class="truncate max-w-[160px]">video.mp4</span>
                                <span id="ihbar-video-progress-detail">0.0 MB / 0.0 MB</span>
                            </div>
                        </div>

                        <div id="ihbar-video-preview" class="flex flex-wrap gap-2 mt-3"></div>
                    </section>
                </form>

                <div class="ihbar-form-footer">
                    <button type="button" onclick="closePwaFullModal()" class="ihbar-btn ihbar-btn--ghost">Kapat</button>
                    <button type="button" onclick="document.getElementById('ihbar-form').requestSubmit()" id="ihbar-submit-btn" class="ihbar-btn ihbar-btn--primary">
                        <span class="material-symbols-outlined text-[18px]">${editData ? 'save' : 'send'}</span>
                        <span id="ihbar-submit-text">${editData ? 'Güncelle' : 'Kaydet'}</span>
                    </button>
                </div>
            `,
            onOpen: () => {
                const fotoInput = document.getElementById('ihbar-foto-input');
                const fotoCamInput = document.getElementById('ihbar-foto-camera-input');

                const handleIhbarFotoChange = function () {
                    const yeniDosyalar = Array.from(this.files);
                    const mevcutFoto = ihbarMevcutFotograflar.filter(f => f.medya_tipi !== 'video').length;
                    const toplamMevcut = mevcutFoto + ihbarSeciliFotolar.length;
                    if (toplamMevcut + yeniDosyalar.length > IHBAR_MAX_FOTO) {
                        Alert.warning('Sınır Aşıldı', `Toplamda en fazla ${IHBAR_MAX_FOTO} fotoğraf olabilir.`);
                        this.value = '';
                        return;
                    }

                    yeniDosyalar.forEach(file => {
                        const name = file.name || '';
                        const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
                        const isImageMime = file.type ? file.type.startsWith('image/') : false;
                        const isSupportedExt = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'jfif'].includes(ext);

                        if (!isImageMime && !isSupportedExt && file.type && !file.type.includes('octet-stream')) {
                            Alert.error('Hata', 'Sadece görsel dosyası (JPG, PNG, WEBP vb.) yükleyebilirsiniz.');
                            return;
                        }
                        ihbarSeciliFotolar.push(file);
                    });

                    this.value = '';
                    renderIhbarFotoPreview();
                };

                if (fotoInput) fotoInput.addEventListener('change', handleIhbarFotoChange);
                if (fotoCamInput) fotoCamInput.addEventListener('change', handleIhbarFotoChange);

                const videoInput = document.getElementById('ihbar-video-input');
                const videoCamInput = document.getElementById('ihbar-video-camera-input');

                const handleIhbarVideoChange = async function () {
                    const dosya = this.files[0];
                    this.value = '';
                    if (!dosya) return;

                    const mevcutVideo = ihbarMevcutFotograflar.filter(f => f.medya_tipi === 'video').length;
                    if (mevcutVideo + ihbarSeciliVideolar.length >= IHBAR_MAX_VIDEO) {
                        return Alert.warning('Sınır Aşıldı', `Toplamda en fazla ${IHBAR_MAX_VIDEO} video olabilir.`);
                    }

                    const progressBox = document.getElementById('ihbar-video-progress-container');
                    if (progressBox) progressBox.classList.remove('hidden');

                    const textEl = document.getElementById('ihbar-video-progress-text');
                    const percentEl = document.getElementById('ihbar-video-progress-percent');
                    const barEl = document.getElementById('ihbar-video-progress-bar');
                    const nameEl = document.getElementById('ihbar-video-progress-name');
                    const detailEl = document.getElementById('ihbar-video-progress-detail');

                    const mbSize = (dosya.size / (1024 * 1024)).toFixed(1);
                    if (textEl) textEl.innerHTML = `<svg class="w-4 h-4 text-indigo-600 animate-spin flex-shrink-0 inline me-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Video İşleniyor...`;
                    if (percentEl) percentEl.textContent = '50%';
                    if (barEl) barEl.style.width = '50%';
                    if (nameEl) nameEl.textContent = dosya.name;
                    if (detailEl) detailEl.textContent = `${mbSize} MB Hazırlanıyor...`;

                    try {
                        const vSonuc = await OfflineQueue.videoIncele(dosya, IHBAR_VIDEO_MAX_SURE, IHBAR_VIDEO_MAX_BYTE);
                        ihbarSeciliVideolar.push(vSonuc);
                        renderIhbarVideoPreview();
                        ihbarVideoProgressDurumGuncelle();
                        if (vSonuc.sikistirildi) {
                            const tasarruf = Math.round((1 - (vSonuc.yeniBoyut / vSonuc.hamBoyut)) * 100);
                            Alert.success('Video Sıkıştırıldı', `Video boyutu ${(vSonuc.hamBoyut / 1048576).toFixed(1)} MB -> ${(vSonuc.yeniBoyut / 1048576).toFixed(1)} MB seviyesine düşürüldü (%${tasarruf} tasarruf).`);
                        }
                    } catch (hata) {
                        ihbarVideoProgressDurumGuncelle();
                        Alert.warning('Video Eklenemedi', hata.message);
                    }
                };

                if (videoInput) videoInput.addEventListener('change', handleIhbarVideoChange);
                if (videoCamInput) videoCamInput.addEventListener('change', handleIhbarVideoChange);

                document.getElementById('ihbar-form').addEventListener('submit', async function (e) {
                    e.preventDefault();
                    await submitYeniIhbar(this);
                });
            }
        });
    }

    function renderIhbarVideoPreview() {
        const preview = document.getElementById('ihbar-video-preview');
        if (!preview) return;

        preview.innerHTML = ihbarSeciliVideolar.map((v, idx) => {
            const kapak = v.kapak
                ? `<img src="${v.kapak}" class="w-20 h-20 object-cover rounded-xl">`
                : `<div class="w-20 h-20 rounded-xl bg-slate-100 flex items-center justify-center">
                       <span class="material-symbols-outlined text-slate-400">movie</span></div>`;
            const sikistirmaRozeti = v.sikistirildi
                ? `<span class="absolute top-1 left-1 bg-emerald-600 text-white text-[10px] font-bold rounded px-1 flex items-center gap-0.5" title="Sıkıştırıldı">
                       <span class="material-symbols-outlined text-[10px]">compress</span>OPT
                   </span>`
                : '';
            return `
            <div class="relative inline-block">
                ${kapak}
                ${sikistirmaRozeti}
                <span class="absolute bottom-1 right-1 text-white text-xs rounded px-1" style="background:rgba(0,0,0,.7)">${OfflineQueue.sureBicimle(v.sure)}</span>
                <button type="button" onclick="window.removeIhbarVideo(${idx})" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>`;
        }).join('');
    }

    function ihbarVideoProgressDurumGuncelle() {
        const progressBox = document.getElementById('ihbar-video-progress-container');
        if (!progressBox) return;

        if (!ihbarSeciliVideolar || ihbarSeciliVideolar.length === 0) {
            progressBox.classList.add('hidden');
            return;
        }

        progressBox.classList.remove('hidden');

        const textEl = document.getElementById('ihbar-video-progress-text');
        const percentEl = document.getElementById('ihbar-video-progress-percent');
        const barEl = document.getElementById('ihbar-video-progress-bar');
        const nameEl = document.getElementById('ihbar-video-progress-name');
        const detailEl = document.getElementById('ihbar-video-progress-detail');

        let toplamBoyutByte = 0;
        ihbarSeciliVideolar.forEach(v => {
            const b = v.yeniBoyut || v.hamBoyut || (v.dosya ? v.dosya.size : 0);
            toplamBoyutByte += b;
        });

        const mbSize = (toplamBoyutByte / (1024 * 1024)).toFixed(1);
        const sonVideo = ihbarSeciliVideolar[ihbarSeciliVideolar.length - 1];
        const sonAd = sonVideo && sonVideo.dosya ? sonVideo.dosya.name : 'video.mp4';

        if (textEl) textEl.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse me-1"></span>${ihbarSeciliVideolar.length} Video Hazır (Gönderim Bekleniyor)`;
        if (percentEl) percentEl.textContent = '100%';
        if (barEl) barEl.style.width = '100%';
        if (nameEl) nameEl.textContent = ihbarSeciliVideolar.length === 1 ? sonAd : `${ihbarSeciliVideolar.length} adet video`;
        if (detailEl) detailEl.textContent = `${mbSize} MB (Form Gönderilince Yüklenecek)`;
    }

    window.removeIhbarVideo = function (idx) {
        ihbarSeciliVideolar.splice(idx, 1);
        renderIhbarVideoPreview();
        ihbarVideoProgressDurumGuncelle();
    };

    function renderIhbarFotoPreview() {
        const preview = document.getElementById('ihbar-foto-preview');
        if (!preview) return;

        preview.innerHTML = ihbarSeciliFotolar.map((file, idx) => {
            const url = URL.createObjectURL(file);
            return `
            <div class="relative inline-block">
                <img src="${url}" class="w-20 h-20 object-cover rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <button type="button" onclick="window.removeIhbarFoto(${idx})" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center shadow-md active:scale-95 transition-transform" title="Sil">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>`;
        }).join('');
    }

    window.removeIhbarFoto = function (idx) {
        ihbarSeciliFotolar.splice(idx, 1);
        renderIhbarFotoPreview();
    };

    function ihbarKonumAl() {
        const button = document.getElementById('ihbar-konum-btn');
        const text = document.getElementById('ihbar-konum-text');
        const durum = document.getElementById('ihbar-konum-durum');

        if (!navigator.geolocation) {
            Alert.error('Konum Alınamadı', 'Cihazınız konum paylaşımını desteklemiyor.');
            return;
        }

        button.disabled = true;
        text.textContent = 'Konum alınıyor...';
        durum.textContent = 'Lütfen konum izni isteğini onaylayın.';

        navigator.geolocation.getCurrentPosition(position => {
            const { latitude, longitude, accuracy } = position.coords;
            document.getElementById('ihbar-konum-lat').value = latitude.toFixed(7);
            document.getElementById('ihbar-konum-lng').value = longitude.toFixed(7);
            document.getElementById('ihbar-konum-dogruluk').value = accuracy.toFixed(2);
            text.textContent = 'Konum Eklendi';
            durum.textContent = `${latitude.toFixed(6)}, ${longitude.toFixed(6)} · Yaklaşık ${Math.round(accuracy)} m doğruluk`;
            button.disabled = false;
        }, error => {
            const messages = {
                1: 'Konum izni verilmedi. Telefon ayarlarından konum iznini açabilirsiniz.',
                2: 'Cihaz konumu belirleyemedi. Lütfen tekrar deneyin.',
                3: 'Konum alınırken zaman aşımı oluştu.'
            };
            text.textContent = 'Mevcut Konumumu Ekle';
            durum.textContent = messages[error.code] || 'Konum alınamadı.';
            button.disabled = false;
            Alert.warning('Konum Alınamadı', durum.textContent);
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 30000
        });
    }

    async function submitYeniIhbar(form) {
        const btn = document.getElementById('ihbar-submit-btn');
        const btnText = document.getElementById('ihbar-submit-text');
        const isEdit = !!ihbarEditId;

        const ilceVal = form.querySelector('[name=ilce]')?.value?.trim() || '';
        const turVal = form.querySelector('[name=ihbar_turu]')?.value?.trim() || '';
        const mahalleVal = form.querySelector('[name=mahalle]')?.value?.trim() || '';
        const aciklamaVal = form.querySelector('[name=aciklama]')?.value?.trim() || '';
        const latVal = form.querySelector('[name=konum_lat]')?.value?.trim() || '';
        const lngVal = form.querySelector('[name=konum_lng]')?.value?.trim() || '';

        if (!turVal) {
            Alert.warning('Eksik Bilgi', 'Lütfen ihbar türünü seçiniz.');
            form.querySelector('[name=ihbar_turu]')?.focus();
            return;
        }
        if (!ilceVal) {
            Alert.warning('Eksik Bilgi', 'Lütfen ilçe seçiniz.');
            form.querySelector('[name=ilce]')?.focus();
            return;
        }

        if (!mahalleVal) {
            Alert.warning('Eksik Bilgi', 'Lütfen mahalle alanını doldurunuz.');
            form.querySelector('[name=mahalle]')?.focus();
            return;
        }

        if (!aciklamaVal) {
            Alert.warning('Eksik Bilgi', 'Lütfen açıklama alanını doldurunuz.');
            form.querySelector('[name=aciklama]')?.focus();
            return;
        }

        if (!latVal || !lngVal) {
            Alert.warning('Konum Gerekli', 'Lütfen "Mevcut Konumumu Ekle" butonuna dokunarak konumunuzu ekleyiniz.');
            return;
        }

        const mevcutFotoSayisi = ihbarMevcutFotograflar.filter(f => f.medya_tipi !== 'video').length;
        if (ihbarSeciliFotolar.length === 0 && (!isEdit || mevcutFotoSayisi === 0)) {
            Alert.warning('Fotoğraf Gerekli', 'Lütfen en az 1 adet ihbar fotoğrafı ekleyiniz.');
            return;
        }

        btn.disabled = true;
        btnText.innerText = isEdit ? 'GÜNCELLENİYOR...' : 'GÖNDERİLİYOR...';

        try {
            if (!window.OfflineQueue) throw new Error('Telefon kayıt altyapısı yüklenemedi. Sayfayı yeniden açın.');
            const alanlar = {
                ihbar_turu: turVal, ilce: ilceVal, mahalle: mahalleVal, aciklama: aciklamaVal,
                telefon: form.querySelector('[name=telefon]')?.value?.trim() || '',
                komsu_abone_no: form.querySelector('[name=komsu_abone_no]')?.value?.trim() || '',
                konum_lat: latVal, konum_lng: lngVal,
                konum_dogruluk: form.querySelector('[name=konum_dogruluk]')?.value || ''
            };
            if (isEdit) alanlar.edit_token = ihbarEditToken;
            const fotolar = [];
            for (const file of ihbarSeciliFotolar) {
                const foto = await OfflineQueue.fotografKucult(file, 1600, 0.75);
                fotolar.push({ alan: 'fotograflar[]', ad: foto.ad, tip: foto.tip, blob: foto.blob, cekim: foto.cekim || '' });
            }
            // New reports retain the mandatory first photo in the main request.
            const anaFotolar = isEdit && mevcutFotoSayisi > 0 ? [] : fotolar.splice(0, 1);
            await OfflineQueue.ekle(isEdit ? 'updateIhbar' : 'createIhbar', alanlar, anaFotolar,
                { ilce: ilceVal, mahalle: mahalleVal }, { dosyalar: fotolar, videolar: ihbarSeciliVideolar });
            closePwaFullModal();
            OfflineQueue.flush().catch(() => {});
            await Alert.success('Telefona kaydedildi', 'Bildirim, fotoğraf ve videolar cihazınıza kaydedildi. Gönderim durumunu bu sayfadan takip edebilirsiniz.');
        } catch (error) {
            console.error('İhbar gönderim hatası:', error);
            const mesaj = (error && error.name === 'QuotaExceededError')
                ? 'Telefonunuzda yeterli depolama alanı bulunamadı. Lütfen cihazınızda yer açıp tekrar deneyin.'
                : ((error && error.message) || 'Telefon depolamasına yazılamadı.') + ' Formunuz açık kaldı; lütfen tekrar deneyin.';
            Alert.error('Kaydedilemedi', mesaj);
        } finally {
            btn.disabled = false;
            btnText.innerText = isEdit ? 'GÜNCELLE' : 'İHBARI GÖNDER';
        }
    }

    async function showIhbarDetay(id, tip) {
        Modal.open('ihbar-detay-modal');
        const content = document.getElementById('ihbar-detay-content');
        content.innerHTML = '<div class="text-center py-8"><span class="material-symbols-outlined animate-spin text-3xl text-primary">refresh</span></div>';

        try {
            const response = await API.request('ihbarDetay', { id: id }, false);
            if (!response.success) {
                content.innerHTML = `<p class="text-center text-red-500 py-4">${response.message || 'Kayıt bulunamadı'}</p>`;
                return;
            }

            const d = response.data;
            const kapaliMi = (d.durum === 'olumlu' || d.durum === 'olumsuz');
            const buAtandiMi = tip === 'gelen';

            const fotoHtml = (d.fotograflar || []).map(f =>
                f.medya_tipi === 'video'
                    ? `<div class="relative inline-block cursor-pointer" onclick="window.open('${f.url}', '_blank')">
                           ${f.kucuk_var
                               ? `<img src="${f.kucuk_url}" loading="lazy" class="w-20 h-20 object-cover rounded-xl">`
                               : `<div class="w-20 h-20 rounded-xl bg-slate-100 flex items-center justify-center"><span class="material-symbols-outlined text-slate-400">movie</span></div>`}
                           <span class="material-symbols-outlined absolute inset-0 m-auto w-fit h-fit text-white" style="text-shadow:0 0 6px rgba(0,0,0,.8)">play_circle</span>
                           ${f.sure_saniye ? `<span class="absolute bottom-1 right-1 text-white text-xs rounded px-1" style="background:rgba(0,0,0,.7)">${OfflineQueue.sureBicimle(f.sure_saniye)}</span>` : ''}
                       </div>`
                    : `<img src="${f.kucuk_url || f.url}" loading="lazy" onclick="window.open('${f.url}', '_blank')" class="w-20 h-20 object-cover rounded-xl cursor-pointer">`
            ).join('') || '<p class="text-xs text-slate-400">Fotoğraf yok</p>';

            const tarihceHtml = (d.tarihce || []).map(t => `
                <div class="flex items-start gap-3 mb-3">
                    <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-primary text-sm">history</span>
                    </div>
                    <div>
                        <p class="text-sm text-slate-700 dark:text-slate-300">${t.aciklama}</p>
                        <p class="text-xs text-slate-400">${t.ekleyen_adi || ''} • ${t.tarih}</p>
                    </div>
                </div>`).join('');

            content.innerHTML = `
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <p class="font-bold text-slate-900 dark:text-white">${d.ilce || '-'} / ${d.mahalle || '-'}</p>
                        <span class="badge ${ihbarDurumBadge(d.durum)}">${ihbarDurumText(d.durum)}</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                        <p class="text-xs text-slate-500 mb-1">Açıklama</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300">${d.aciklama || '-'}</p>
                    </div>
                    ${d.telefon ? `<p class="text-xs text-slate-500">Telefon: <span class="text-slate-800 dark:text-slate-200 font-medium">${d.telefon}</span></p>` : ''}
                    ${d.komsu_abone_no ? `<p class="text-xs text-slate-500">Komşu Abone No: <span class="text-slate-800 dark:text-slate-200 font-medium">${d.komsu_abone_no}</span></p>` : ''}
                    ${d.konum_lat && d.konum_lng ? `
                        <a href="https://www.google.com/maps?q=${d.konum_lat},${d.konum_lng}" target="_blank"
                            class="w-full p-3 rounded-xl border border-primary/20 bg-primary/5 text-primary text-sm font-semibold flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">map</span>Konumu Haritada Aç
                        </a>` : ''}
                    <div>
                        <p class="text-xs text-slate-500 mb-2">Fotoğraflar</p>
                        <div class="flex flex-wrap gap-2">${fotoHtml}</div>
                    </div>
                    ${d.durum === 'olumlu' ? `<div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm text-green-700 dark:text-green-300"><strong>Tutanak No:</strong> ${d.tutanak_no || '-'}</div>` : ''}
                    ${d.durum === 'olumsuz' ? `<div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-300"><strong>Olumsuz Sebep:</strong> ${d.olumsuz_sebep || '-'}</div>` : ''}

                    ${buAtandiMi ? `
                    ${!kapaliMi ? `
                    <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                        <p class="text-xs text-slate-500 mb-2">Not Ekle (Tutanak vb.)</p>
                        <div class="flex gap-2">
                            <input type="text" id="ihbar-not-input" class="form-input flex-1" placeholder="Not yazın...">
                            <button type="button" onclick="ihbarNotEkle(${d.id}, '${tip}')" class="btn-primary px-4">Ekle</button>
                        </div>
                    </div>` : ''}
                    <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                        <p class="text-xs text-slate-500 mb-2">${kapaliMi ? 'Sonucu Yeniden Düzenle' : 'Sonuçlandır'}</p>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <button type="button" onclick="ihbarSonucSec(this, 'olumlu')" class="ihbar-sonuc-btn py-2 rounded-xl border ${d.durum === 'olumlu' ? 'active border-primary text-primary' : 'border-slate-200 dark:border-slate-700'} text-sm font-medium">✅ Olumlu</button>
                            <button type="button" onclick="ihbarSonucSec(this, 'olumsuz')" class="ihbar-sonuc-btn py-2 rounded-xl border ${d.durum === 'olumsuz' ? 'active border-primary text-primary' : 'border-slate-200 dark:border-slate-700'} text-sm font-medium">❌ Olumsuz</button>
                        </div>
                        <input type="hidden" id="ihbar-sonuc-durum" value="${d.durum === 'olumlu' || d.durum === 'olumsuz' ? d.durum : ''}">
                        <input type="text" id="ihbar-tutanak-no" class="form-input ${d.durum === 'olumlu' ? '' : 'hidden'} mb-2" placeholder="Tutanak No" value="${d.tutanak_no || ''}">
                        <textarea id="ihbar-olumsuz-sebep" class="form-input ${d.durum === 'olumsuz' ? '' : 'hidden'} mb-2" placeholder="Olumsuz sebebi">${d.olumsuz_sebep || ''}</textarea>
                        <button type="button" onclick="ihbarSonuclandir(${d.id}, '${tip}')" class="w-full btn-primary py-3">${kapaliMi ? 'Sonucu Güncelle' : 'Sonuçlandır'}</button>
                    </div>` : ''}

                    <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                        <p class="text-xs text-slate-500 mb-3">İşlem Tarihçesi</p>
                        ${tarihceHtml || '<p class="text-xs text-slate-400">Kayıt yok</p>'}
                    </div>
                </div>`;
        } catch (error) {
            console.error('İhbar detay hatası:', error);
            content.innerHTML = '<p class="text-center text-red-500 py-4">Bir hata oluştu.</p>';
        }
    }

    function ihbarSonucSec(btn, durum) {
        document.querySelectorAll('.ihbar-sonuc-btn').forEach(b => b.classList.remove('active', 'border-primary', 'text-primary'));
        btn.classList.add('active', 'border-primary', 'text-primary');
        document.getElementById('ihbar-sonuc-durum').value = durum;
        document.getElementById('ihbar-tutanak-no').classList.toggle('hidden', durum !== 'olumlu');
        document.getElementById('ihbar-olumsuz-sebep').classList.toggle('hidden', durum !== 'olumsuz');
    }

    async function ihbarNotEkle(id, tip) {
        const input = document.getElementById('ihbar-not-input');
        const not = input.value.trim();
        if (!not) {
            Alert.warning('Uyarı', 'Lütfen bir not yazın.');
            return;
        }

        const response = await API.request('ihbarNotEkle', { id: id, aciklama: not });
        if (response.success) {
            input.value = '';
            showIhbarDetay(id, tip);
        } else {
            Alert.error('Hata', response.message || 'Bir hata oluştu.');
        }
    }

    async function ihbarSonuclandir(id, tip) {
        const durum = document.getElementById('ihbar-sonuc-durum').value;
        if (!durum) {
            Alert.warning('Uyarı', 'Lütfen sonuç seçiniz.');
            return;
        }

        const tutanakNo = document.getElementById('ihbar-tutanak-no').value.trim();
        const sebep = document.getElementById('ihbar-olumsuz-sebep').value.trim();

        if (durum === 'olumlu' && !tutanakNo) {
            Alert.warning('Uyarı', 'Tutanak numarası girilmelidir.');
            return;
        }
        if (durum === 'olumsuz' && !sebep) {
            Alert.warning('Uyarı', 'Olumsuz sebebi girilmelidir.');
            return;
        }

        const response = await API.request('ihbarSonuclandir', { id: id, durum: durum, tutanak_no: tutanakNo, sebep: sebep });
        if (response.success) {
            Modal.close('ihbar-detay-modal');
            await Alert.success('Başarılı', response.message || 'İhbar sonuçlandırıldı.');
            loadIhbarlar();
        } else {
            Alert.error('Hata', response.message || 'Bir hata oluştu.');
        }
    }
</script>
