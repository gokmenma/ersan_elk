<?php
/**
 * Personel PWA - Zimmetler Sayfası
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <?php pwaPageHeader([
        'eyebrow' => 'ENVANTER',
        'title' => 'Zimmetler',
        'subtitle' => 'Üzerinizdeki demirbaşlar',
        'actions' => '<button type="button" onclick="exportZimmetToExcel()" class="pwa-circle-btn" aria-label="Excel\'e Aktar" title="Excel\'e Aktar"><span class="material-symbols-outlined">download</span></button>',
    ]); ?>

    <div class="px-4 pb-3">
        <div class="gt-sekmeler" role="tablist">
            <button type="button" id="zt-sekme-zimmet" class="gt-sekme active" role="tab" onclick="setZimmetSekme('zimmet')">
                <span class="material-symbols-outlined">inventory_2</span>Zimmetler
            </button>
            <button type="button" id="zt-sekme-gecici" class="gt-sekme" role="tab" onclick="setZimmetSekme('gecici')">
                <span class="material-symbols-outlined">swap_horiz</span>Geçici Teslim
                <span id="zt-gelen-rozet" class="gt-rozet hidden">0</span>
            </button>
        </div>
    </div>

    <div id="zt-panel-zimmet" class="flex flex-col">
    <section class="px-4 pb-3">
        <div class="pwa-hero-dark">
            <div class="pwa-hero-dark__top">
                <div>
                    <span class="pwa-hero-dark__eyebrow">ZİMMET MERKEZİ</span>
                    <h2 class="pwa-hero-dark__value"><span id="zimmet-toplam-count">0</span> aktif zimmet</h2>
                    <p class="pwa-hero-dark__sub">Kategoriye dokunarak listeyi filtreleyin</p>
                </div>
                <span class="pwa-hero-dark__icon"><span class="material-symbols-outlined">inventory_2</span></span>
            </div>
            <div class="pwa-hero-dark__stats">
                <div id="card-arac" onclick="toggleZimmetCategory('arac')" role="button" tabindex="0" class="pwa-hero-dark__stat pwa-hero-dark__stat--tile">
                    <span class="material-symbols-outlined">directions_car</span>
                    <strong id="arac-zimmet-count">0</strong>
                    <small>Araçlar</small>
                </div>
                <div id="card-tech" onclick="toggleZimmetCategory('tech')" role="button" tabindex="0" class="pwa-hero-dark__stat pwa-hero-dark__stat--tile">
                    <span class="material-symbols-outlined">devices</span>
                    <strong id="tech-zimmet-count">0</strong>
                    <small>Teknoloji</small>
                </div>
                <div id="card-sayac" onclick="showSayacDetay()" role="button" tabindex="0" class="pwa-hero-dark__stat pwa-hero-dark__stat--tile">
                    <span class="material-symbols-outlined">speed</span>
                    <strong id="sayac-zimmet-count">0</strong>
                    <small>Sayaçlar</small>
                </div>
                <div id="card-aparat" onclick="showAparatDetay()" role="button" tabindex="0" class="pwa-hero-dark__stat pwa-hero-dark__stat--tile">
                    <span class="material-symbols-outlined">build</span>
                    <strong id="aparat-zimmet-count">0</strong>
                    <small>Aparatlar</small>
                </div>
            </div>
        </div>

        <!-- Filter Info / Reset -->
        <div id="filter-status" class="hidden mt-3 flex items-center justify-between px-2">
            <span class="text-xs text-slate-500 font-medium" id="filter-text">Seçili kategori gösteriliyor</span>
            <button onclick="toggleZimmetCategory('all')"
                class="text-xs text-primary font-bold flex items-center gap-1">
                Tümünü Göster <span class="material-symbols-outlined text-sm">refresh</span>
            </button>
        </div>
    </section>

    <!-- Zimmet List -->
    <div class="flex-1 px-4 py-4">
        <div class="flex flex-col gap-3" id="zimmet-list">
            <!-- Shimmer Loading -->
            <div class="shimmer h-24 rounded-2xl"></div>
            <div class="shimmer h-24 rounded-2xl"></div>
            <div class="shimmer h-24 rounded-2xl"></div>
        </div>
    </div>
    </div>

    <section id="zt-panel-gecici" class="px-4 pt-1 pb-6 hidden">
        <div class="gt-baslik">
            <div>
                <h3>Geçici Teslim İşlemleri</h3>
                <p>Personeller arası geçici teslimler · asıl zimmetinizi değiştirmez</p>
            </div>
        </div>
        <div id="gt-bos" class="hidden gt-bos">
            <span class="material-symbols-outlined">swap_horiz</span>
            <strong>Geçici teslim işlemi yok</strong>
            <p>Üzerinizdeki araçları buradan başka bir personele geçici olarak devredebilirsiniz.</p>
        </div>
        <div id="gt-gelen" class="flex flex-col gap-3"></div>
        <div id="gt-uzerimdekiler" class="flex flex-col gap-3"></div>
        <div id="gt-baskasinda" class="flex flex-col gap-3"></div>
        <details id="gt-gecmis-kutu" class="gt-gecmis hidden">
            <summary>
                <span>Geçici teslim geçmişi</span>
                <span class="material-symbols-outlined">expand_more</span>
            </summary>
            <div id="gt-gecmis" class="flex flex-col gap-2 pt-2"></div>
        </details>
    </section>

</div>

<!-- Sayaç Detay Modal -->
<div id="sayac-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 flex flex-col max-h-[80vh]">
        <div class="modal-handle"></div>
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Sayaç Zimmetleri</h3>
            <button onclick="Modal.close('sayac-detay-modal')"
                class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div
                class="bg-emerald-50 dark:bg-emerald-900/20 p-4 rounded-2xl border border-emerald-100 dark:border-emerald-800/50 flex flex-col items-center">
                <span class="text-3xl font-black text-emerald-600" id="sayac-sifir-val">0</span>
                <span class="text-[10px] font-bold text-emerald-600/70 uppercase tracking-widest mt-1">Sıfır
                    Sayaç</span>
            </div>
            <div
                class="bg-rose-50 dark:bg-rose-900/20 p-4 rounded-2xl border border-rose-100 dark:border-rose-800/50 flex flex-col items-center">
                <span class="text-3xl font-black text-rose-600" id="sayac-hurda-val">0</span>
                <span class="text-[10px] font-bold text-rose-600/70 uppercase tracking-widest mt-1">Hurda Sayaç</span>
            </div>
        </div>
        <button onclick="Modal.close('sayac-detay-modal'); toggleZimmetCategory('sayac')"
            class="w-full py-3.5 bg-primary text-white font-bold rounded-2xl shadow-lg shadow-primary/20 transition-all active:scale-95 mb-3">
            Listeyi Görüntüle
        </button>
        <button onclick="Modal.close('sayac-detay-modal')"
            class="w-full py-3 bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold rounded-2xl">Kapat</button>
    </div>
</div>

<!-- Aparat Detay Modal -->
<div id="aparat-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 flex flex-col max-h-[90vh]">
        <div class="modal-handle"></div>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Aparat Detayları</h3>
                <p class="text-xs text-slate-500" id="aparat-total-info">Toplam 0 aparat zimmetli</p>
            </div>
            <button onclick="Modal.close('aparat-detay-modal')"
                class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto pr-1">
            <div id="aparat-detail-list" class="space-y-3">
                <!-- Aparatlar buraya gelecek -->
            </div>
        </div>
        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button onclick="Modal.close('aparat-detay-modal'); toggleZimmetCategory('aparat')"
                class="w-full py-3.5 mb-3 bg-primary text-white font-bold rounded-2xl">Filtrele ve Listele</button>
            <button onclick="Modal.close('aparat-detay-modal')"
                class="w-full py-3.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold rounded-2xl">Kapat</button>
        </div>
    </div>
</div>

<!-- Zimmet Detay Modal (Existing) -->
<div id="zimmet-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 flex flex-col max-h-[90vh]">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Zimmet Geçmişi</h3>
            <button onclick="Modal.close('zimmet-detay-modal')"
                class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center transition-colors hover:bg-slate-200">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto pr-1">
            <div id="zimmet-hareket-list" class="space-y-4">
                <!-- Hareketler buraya gelecek -->
            </div>
        </div>

        <div id="zimmet-devret-satiri" class="hidden mt-4">
            <button type="button" id="btn-zimmet-devret" class="pwa-btn pwa-btn--primary w-full">
                <span class="material-symbols-outlined text-lg">swap_horiz</span>Geçici Devret
            </button>
        </div>

        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex gap-3">
            <button id="btn-zimmet-talep-ac"
                class="flex-1 py-3.5 bg-primary/10 text-primary font-bold rounded-2xl transition-all active:scale-95 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">edit_note</span>
                Talep Bildir
            </button>
            <button onclick="Modal.close('zimmet-detay-modal')"
                class="flex-1 py-3.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold rounded-2xl transition-all active:scale-95">
                Kapat
            </button>
        </div>
    </div>
</div>

<div id="gecici-devret-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content pwa-sheet" style="display: flex !important; flex-direction: column !important; max-height: min(92dvh, 780px) !important; overflow: hidden !important; padding: 0 !important;">
        <div class="pwa-sheet__handle"></div>
        <div class="pwa-sheet__head">
            <span class="pwa-sheet__icon"><span class="material-symbols-outlined">swap_horiz</span></span>
            <div class="flex-1 min-w-0">
                <h3>Geçici Devret</h3>
                <p id="gt-devret-plaka">-</p>
            </div>
            <button type="button" onclick="Modal.close('gecici-devret-modal')" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="gecici-devret-form" class="flex flex-col flex-1 min-h-0">
            <input type="hidden" name="zimmet_token" id="gt-devret-zimmet">
            <input type="hidden" name="alan_token" id="gt-devret-alan">
            <div class="flex-1 overflow-y-auto pwa-sheet__body" style="overscroll-behavior-y: contain;">
                <section class="pwa-sheet-card">
                    <h2 class="pwa-card-title"><span class="material-symbols-outlined">person_search</span>Teslim Edilecek Personel</h2>
                    <input type="search" id="gt-personel-ara" class="pwa-input" placeholder="İsim ile ara..." autocomplete="off">
                    <div id="gt-personel-liste" class="gt-personel-liste"></div>
                    <p class="gt-ipucu">Seçtiğiniz personel onayladığında araç onun geçici zimmetine geçer. Asıl zimmet kaydı değişmez.</p>
                </section>

                <section class="pwa-sheet-card">
                    <h2 class="pwa-card-title"><span class="material-symbols-outlined">tune</span>Detaylar</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="gt-alan-etiket" for="gt-devret-km">Güncel KM</label>
                            <input type="number" name="teslim_km" id="gt-devret-km" inputmode="numeric" min="0" class="pwa-input" placeholder="Opsiyonel">
                        </div>
                        <div>
                            <label class="gt-alan-etiket" for="gt-devret-plan">Geri Alma (Plan)</label>
                            <input type="date" name="planlanan_iade_tarihi" id="gt-devret-plan" class="pwa-input">
                        </div>
                    </div>
                    <div>
                        <label class="gt-alan-etiket" for="gt-devret-aciklama">Açıklama</label>
                        <textarea name="aciklama" id="gt-devret-aciklama" class="pwa-input" placeholder="Teslim nedeni, araç durumu vb."></textarea>
                    </div>
                </section>
            </div>

            <div class="pwa-sheet__footer">
                <button type="button" onclick="Modal.close('gecici-devret-modal')" class="pwa-btn pwa-btn--ghost">İptal</button>
                <button type="submit" class="pwa-btn pwa-btn--primary" id="gt-devret-gonder">Onaya Gönder</button>
            </div>
        </form>
    </div>
</div>

<div id="gt-karar-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content pwa-sheet" style="display: flex !important; flex-direction: column !important; max-height: min(92dvh, 780px) !important; overflow: hidden !important; padding: 0 !important;">
        <div class="pwa-sheet__handle"></div>
        <div class="pwa-sheet__head">
            <span class="pwa-sheet__icon" id="gt-karar-ikon"><span class="material-symbols-outlined">task_alt</span></span>
            <div class="flex-1 min-w-0">
                <h3 id="gt-karar-baslik">Teslim Aldınız mı?</h3>
                <p id="gt-karar-alt">-</p>
            </div>
            <button type="button" onclick="Modal.close('gt-karar-modal')" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="gt-karar-form" class="flex flex-col flex-1 min-h-0">
            <input type="hidden" id="gt-karar-token">
            <input type="hidden" id="gt-karar-mod">
            <div class="flex-1 overflow-y-auto pwa-sheet__body" style="overscroll-behavior-y: contain;">
                <section class="pwa-sheet-card" id="gt-karar-ozet"></section>

                <section class="pwa-sheet-card" id="gt-karar-onay-alani">
                    <h2 class="pwa-card-title"><span class="material-symbols-outlined">speed</span>Güncel KM <small style="font-weight:600;text-transform:none;letter-spacing:0">(Opsiyonel)</small></h2>
                    <input type="number" id="gt-karar-km" inputmode="numeric" min="0" class="pwa-input" placeholder="Aracın şu anki KM'si">
                </section>

                <section class="pwa-sheet-card hidden" id="gt-karar-red-alani">
                    <h2 class="pwa-card-title"><span class="material-symbols-outlined">notes</span>Red Gerekçesi</h2>
                    <textarea id="gt-karar-neden" class="pwa-input" placeholder="Neden kabul etmiyorsunuz?"></textarea>
                </section>
            </div>

            <div class="pwa-sheet__footer">
                <button type="button" onclick="Modal.close('gt-karar-modal')" class="pwa-btn pwa-btn--ghost">Vazgeç</button>
                <button type="submit" class="pwa-btn pwa-btn--primary" id="gt-karar-gonder">Teslim Aldım</button>
            </div>
        </form>
    </div>
</div>

<!-- Zimmet Talep Modal -->
<div id="zimmet-talep-modal" class="modal-overlay" style="z-index: 1000;">
    <div class="modal-content p-6 pt-3 flex flex-col max-h-[90vh]">
        <div class="modal-handle"></div>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">edit_note</span>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Talep Bildir</h3>
            </div>
            <button onclick="Modal.close('zimmet-talep-modal')"
                class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto pr-1">
            <form id="zimmet-talep-form" class="space-y-5">
                <input type="hidden" name="action" value="createTalepBildirimi">
                <input type="hidden" name="asset_id" id="talep-asset-id">
                <input type="hidden" name="asset_name" id="talep-asset-name">
                <input type="hidden" name="asset_type" id="talep-asset-type">

                <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">SEÇİLİ ZİMMET</p>
                    <p class="font-bold text-slate-900 dark:text-white" id="talep-asset-display-name">-</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">TALEP TÜRÜ</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="selectZimmetKategori(this, 'ariza')"
                            class="zimmet-kategori-btn p-3 rounded-2xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-600 dark:text-slate-400 flex flex-col items-center gap-1 transition-all active:scale-95">
                            <span class="material-symbols-outlined text-rose-500">construction</span>
                            Arıza
                        </button>
                        <button type="button" onclick="selectZimmetKategori(this, 'servis')"
                            class="zimmet-kategori-btn p-3 rounded-2xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-600 dark:text-slate-400 flex flex-col items-center gap-1 transition-all active:scale-95">
                            <span class="material-symbols-outlined text-blue-500">settings</span>
                            Servis
                        </button>
                        <button type="button" onclick="selectZimmetKategori(this, 'bakim')"
                            class="zimmet-kategori-btn p-3 rounded-2xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-600 dark:text-slate-400 flex flex-col items-center gap-1 transition-all active:scale-95">
                            <span class="material-symbols-outlined text-emerald-500">verified</span>
                            Bakım
                        </button>
                        <button type="button" onclick="selectZimmetKategori(this, 'diger')"
                            class="zimmet-kategori-btn p-3 rounded-2xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-600 dark:text-slate-400 flex flex-col items-center gap-1 transition-all active:scale-95">
                            <span class="material-symbols-outlined text-slate-500">more_horiz</span>
                            Diğer
                        </button>
                    </div>
                    <input type="hidden" name="kategori" id="zimmet-kategori-input" required>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">AÇIKLAMA</label>
                    <textarea name="aciklama" rows="3"
                        class="w-full p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                        placeholder="Talep detaylarını buraya yazın..." required></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" id="btn-zimmet-talep-gonder"
                        class="w-full py-4 bg-primary text-white font-bold rounded-2xl shadow-lg shadow-primary/20 transition-all active:scale-[0.98] flex items-center justify-center gap-2">
                        <span>Talebi Gönder</span>
                        <span class="material-symbols-outlined text-lg">send</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .zk-kart { background: #fff; border-radius: 22px; padding: 14px 14px 12px; border: 1px solid rgba(15, 23, 42, .06); box-shadow: 0 6px 18px rgba(15, 23, 42, .05); cursor: pointer; transition: transform .15s; }
    .zk-kart:active { transform: scale(.985); }
    .zk-ust { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .zk-ana { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .zk-yazi { display: flex; flex-direction: column; min-width: 0; }
    .zk-yazi b { font-size: 14px; font-weight: 800; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .zk-alt { font-size: 11px; font-weight: 600; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .zk-ikon { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; background: #f1f5f9; color: #475569; }
    .zk-ikon .material-symbols-outlined { font-size: 20px; }
    .zk-plaka { display: inline-flex; align-items: center; flex-shrink: 0; border: 1.5px solid #1e293b; border-radius: 7px; background: #fff; padding: 1.5px; box-shadow: 0 1px 3px rgba(0, 0, 0, .1); }
    .zk-tr { background: #0284c7; color: #fff; font: 900 9px sans-serif; padding: 3px 5px; border-radius: 4px; letter-spacing: .02em; }
    .zk-plaka-text { font: 900 14px 'Roboto Condensed', sans-serif; letter-spacing: .05em; color: #0f172a; padding: 1px 7px 1px 6px; line-height: 1.1; }
    .zk-durum { flex-shrink: 0; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: #f1f5f9; color: #64748b; }
    .zk-durum--amber { background: #fef3c7; color: #b45309; }
    .zk-durum--emerald { background: #dcfce7; color: #16a34a; }
    .zk-durum--rose { background: #ffe4e6; color: #be123c; }
    .zk-meta { display: flex; align-items: center; gap: 20px; margin-top: 12px; padding: 9px 12px; border-radius: 14px; background: #f8fafc; }
    .zk-meta > div { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
    .zk-meta small { font-size: 9px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #94a3b8; }
    .zk-meta b { font-size: 12px; font-weight: 800; color: #1e293b; }
    .zk-gecici { margin-top: 10px; width: 100%; min-height: 40px; border-radius: 999px; display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; font-weight: 700; }
    .zk-gecici .material-symbols-outlined { font-size: 18px; }
    .zk-gecici--btn { background: #191d24; color: #fff; font-weight: 800; transition: transform .15s; }
    .zk-gecici--btn:active { transform: scale(.97); }
    .zk-gecici--bilgi { background: #e0f2fe; color: #0369a1; }
    .zk-gecici--bekle { background: #fffbeb; color: #92400e; border: 1px dashed #fcd34d; }
    .dark .zk-gecici--btn { background: #f1f5f9; color: #0f172a; }
    .dark .zk-gecici--bilgi { background: rgba(14, 165, 233, .15); color: #7dd3fc; }
    .dark .zk-gecici--bekle { background: rgba(245, 158, 11, .1); color: #fcd34d; border-color: rgba(245, 158, 11, .4); }
    .zk-git { margin-left: auto; font-size: 20px; color: #94a3b8; }
    .dark .zk-kart { background: #1e2229; border-color: rgba(255, 255, 255, .06); box-shadow: none; }
    .dark .zk-yazi b, .dark .zk-meta b { color: #f1f5f9; }
    .dark .zk-ikon { background: #2a2f38; color: #cbd5e1; }
    .dark .zk-meta { background: rgba(255, 255, 255, .04); }
    .dark .zk-alt { color: #94a3b8; }
    .dark .zk-durum--amber { background: rgba(245, 158, 11, .18); color: #fcd34d; }
    .dark .zk-durum--emerald { background: rgba(16, 185, 129, .18); color: #6ee7b7; }
    .dark .zk-durum--rose { background: rgba(244, 63, 94, .18); color: #fda4af; }
    .dark .zk-durum { background: #2a2f38; color: #cbd5e1; }
    .gt-sekmeler { display: flex; gap: 6px; padding: 5px; border-radius: 999px; background: rgba(15, 23, 42, .06); }
    .gt-sekme { flex: 1; min-height: 44px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 800; color: #64748b; transition: background .2s, color .2s; }
    .gt-sekme .material-symbols-outlined { font-size: 18px; }
    .gt-sekme.active { background: #191d24; color: #fff; box-shadow: 0 4px 12px rgba(15, 23, 42, .18); }
    .gt-rozet { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #f59e0b; color: #fff; font-size: 11px; font-weight: 800; display: inline-grid; place-items: center; }
    .gt-bos { text-align: center; padding: 36px 16px; color: #64748b; }
    .gt-bos .material-symbols-outlined { font-size: 40px; color: #94a3b8; }
    .gt-bos strong { display: block; margin-top: 6px; font-size: 14px; color: #334155; }
    .gt-bos p { font-size: 12px; margin-top: 2px; }
    .dark .gt-sekmeler { background: rgba(255, 255, 255, .06); }
    .dark .gt-sekme { color: #94a3b8; }
    .dark .gt-sekme.active { background: #f1f5f9; color: #0f172a; }
    .dark .gt-bos strong { color: #e2e8f0; }
    .gt-baslik { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 2px 10px; }
    .gt-baslik h3 { font-size: 15px; font-weight: 800; color: #0f172a; }
    .gt-baslik p { font-size: 11px; color: #64748b; margin-top: 1px; }
    .gt-kart { background: #fff; border-radius: 22px; padding: 14px; border: 1px solid rgba(15, 23, 42, .06); box-shadow: 0 6px 18px rgba(15, 23, 42, .06); }
    .gt-kart--gelen { border-color: #fcd34d; background: #fffbeb; }
    .gt-kart--bilgi { background: #f8fafc; box-shadow: none; }
    .gt-kart__ust { display: flex; align-items: flex-start; gap: 12px; }
    .gt-alt { font-size: 11px; color: #64748b; line-height: 1.45; }
    .gt-alt strong { color: #334155; }
    .gt-etiket { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; background: #e0f2fe; color: #0369a1; white-space: nowrap; }
    .gt-etiket--asil { background: #fef3c7; color: #b45309; }
    .gt-etiket--bekle { background: #fef3c7; color: #b45309; }
    .gt-etiket--kapali { background: #f1f5f9; color: #64748b; }
    .gt-etiket--red { background: #ffe4e6; color: #be123c; }
    .gt-etiket--ok { background: #d1fae5; color: #047857; }
    .gt-not { margin-top: 8px; font-size: 12px; color: #475569; background: rgba(255, 255, 255, .7); border-radius: 12px; padding: 8px 10px; }
    .gt-aksiyonlar { display: flex; gap: 8px; margin-top: 12px; }
    .gt-btn { flex: 1; min-height: 44px; border-radius: 999px; font-size: 13px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: transform .15s; }
    .gt-btn:active { transform: scale(.97); }
    .gt-btn .material-symbols-outlined { font-size: 18px; }
    .gt-btn--ana { background: #191d24; color: #fff; }
    .gt-btn--onay { background: #059669; color: #fff; }
    .gt-btn--red { background: #fff; color: #be123c; border: 1px solid #fecdd3; }
    .gt-btn--ikincil { background: #f1f5f9; color: #475569; }
    .gt-bekleyen { margin-top: 10px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #92400e; background: #fffbeb; border: 1px dashed #fcd34d; border-radius: 14px; padding: 8px 10px; }
    .gt-gecmis { background: #fff; border-radius: 22px; padding: 12px 14px; border: 1px solid rgba(15, 23, 42, .06); }
    .gt-gecmis summary { list-style: none; display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 700; color: #334155; cursor: pointer; min-height: 32px; }
    .gt-gecmis summary::-webkit-details-marker { display: none; }
    .gt-gecmis[open] summary .material-symbols-outlined { transform: rotate(180deg); }
    .gt-gecmis-satir { display: flex; justify-content: space-between; gap: 10px; padding: 9px 0; border-top: 1px solid #f1f5f9; }
    .gt-gecmis-plaka { font-size: 14px; font-weight: 800; color: #1e293b; }
    .dark .gt-gecmis-plaka { color: #f1f5f9; }
    .gt-ipucu { font-size: 11px; font-weight: 600; color: #64748b; line-height: 1.45; }
    .gt-alan-etiket { display: block; margin: 0 0 6px 4px; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
    .dark .gt-ipucu, .dark .gt-alan-etiket { color: #94a3b8; }
    #gt-karar-ozet .gt-alt { margin-top: 2px; }
    .gt-uyari { display: flex; gap: 8px; font-size: 12px; color: #075985; background: #f0f9ff; border-radius: 14px; padding: 10px 12px; }
    .gt-uyari .material-symbols-outlined { font-size: 18px; }
    .gt-personel-liste { max-height: 230px; overflow-y: auto; border-radius: 18px; background: #f1f4f8; }
    .gt-personel { width: 100%; text-align: left; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 11px 14px; min-height: 48px; border-bottom: 1px solid rgba(15, 23, 42, .06); font-size: 14px; font-weight: 600; color: #0f172a; }
    .gt-personel small { display: block; font-size: 11px; font-weight: 500; color: #94a3b8; }
    .gt-personel.secili { background: #e0f2fe; color: #0369a1; }
    .gt-personel .material-symbols-outlined { visibility: hidden; color: #0369a1; }
    .gt-personel.secili .material-symbols-outlined { visibility: visible; }
    .dark .gt-baslik h3, .dark .gt-personel { color: #f1f5f9; }
    .dark .gt-kart, .dark .gt-gecmis { background: #1e2229; border-color: rgba(255, 255, 255, .06); box-shadow: none; }
    .dark .gt-kart--gelen { background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .4); }
    .dark .gt-kart--bilgi { background: #191d24; }
    .dark .gt-alt strong, .dark .gt-gecmis summary { color: #cbd5e1; }
    .dark .gt-not { background: rgba(255, 255, 255, .05); color: #cbd5e1; }
    .dark .gt-btn--red { background: transparent; border-color: rgba(244, 63, 94, .4); color: #fb7185; }
    .dark .gt-btn--ikincil { background: #2a2f38; color: #cbd5e1; }
    .dark .gt-btn--ana { background: #f1f5f9; color: #0f172a; }
    .dark .gt-bekleyen { background: rgba(245, 158, 11, .1); color: #fcd34d; border-color: rgba(245, 158, 11, .4); }
    .dark .gt-gecmis-satir { border-color: rgba(255, 255, 255, .06); }
    .dark .gt-uyari { background: rgba(14, 165, 233, .12); color: #7dd3fc; }
    .dark .gt-personel-liste { background: rgba(255, 255, 255, .05); }
    .dark .gt-personel { border-color: #2a2f38; }
    .dark .gt-personel.secili { background: rgba(14, 165, 233, .18); color: #7dd3fc; }
    .zimmet-kategori-btn.active {
        background: rgba(var(--primary-rgb), 0.1);
        border-color: var(--primary);
        color: var(--primary);
    }
</style>

<script>
    let allZimmetler = [];
    let currentZimmetFilter = 'all';
    let zimmetGorunenSayi = 40;

    document.addEventListener('DOMContentLoaded', function () {
        loadZimmetler();
        loadGeciciTeslimler();
        if (new URLSearchParams(location.search).get('tab') === 'gecici') setZimmetSekme('gecici');
    });

    function setZimmetSekme(sekme) {
        const gecici = sekme === 'gecici';
        document.getElementById('zt-panel-zimmet').classList.toggle('hidden', gecici);
        document.getElementById('zt-panel-gecici').classList.toggle('hidden', !gecici);
        document.getElementById('zt-sekme-zimmet').classList.toggle('active', !gecici);
        document.getElementById('zt-sekme-gecici').classList.toggle('active', gecici);
    }

    let gtPersoneller = null;
    const gtEsc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function zkGeciciSatiri(z) {
        if (z.gecici_kimde) {
            return `<div class="zk-gecici zk-gecici--bilgi"><span class="material-symbols-outlined">person_pin_circle</span><span>Şu an <b>${gtEsc(z.gecici_kimde)}</b> kullanıyor</span></div>`;
        }
        if (z.gecici_bekleyen) {
            return `<div class="zk-gecici zk-gecici--bekle"><span class="material-symbols-outlined">hourglass_top</span><span><b>${gtEsc(z.gecici_bekleyen)}</b> onayı bekleniyor</span></div>`;
        }
        return `<button type="button" class="zk-gecici zk-gecici--btn" data-zimmet-token="${gtEsc(z.zimmet_token)}" data-plaka="${gtEsc(z.demirbas_adi)}" onclick="event.stopPropagation(); openGeciciDevret(this)"><span class="material-symbols-outlined">swap_horiz</span>Geçici Devret</button>`;
    }

    const zkPlaka = (p) => `<span class="zk-plaka"><span class="zk-tr">TR</span><span class="zk-plaka-text">${gtEsc(p)}</span></span>`;

    async function loadGeciciTeslimler() {
        const response = await API.request('getGeciciTeslimler', {}, false);
        if (!response.success) return;
        renderGeciciTeslimler(response.data || {});
    }

    function renderGeciciTeslimler(d) {
        const gelen = d.gelen || [];
        const uzerimde = d.uzerimdekiler || [];
        const baskasinda = d.baskasindakiler || [];
        const gecmis = d.gecmis || [];
        document.getElementById('gt-bos').classList.toggle('hidden', !!(gelen.length || uzerimde.length || baskasinda.length || gecmis.length));
        const rozet = document.getElementById('zt-gelen-rozet');
        rozet.textContent = gelen.length;
        rozet.classList.toggle('hidden', gelen.length === 0);

        gtGelenHaritasi = Object.fromEntries(gelen.map((t) => [t.token, t]));
        document.getElementById('gt-gelen').innerHTML = gelen.map((t) => `
            <div class="gt-kart gt-kart--gelen">
                <div class="gt-kart__ust">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            ${zkPlaka(t.plaka)}
                            <span class="gt-etiket gt-etiket--bekle">Size gelen</span>
                        </div>
                        <div class="gt-alt"><strong>${gtEsc(t.karsi_personel_adi)}</strong> aracı size ${t.asila_donus ? 'geri' : 'geçici olarak'} teslim etmek istiyor.</div>
                        <div class="gt-alt">${gtEsc(t.marka_model || '')} · ${gtEsc(t.talep_tarihi)}${t.teslim_km ? ' · ' + gtEsc(t.teslim_km) + ' km' : ''}</div>
                    </div>
                </div>
                ${t.aciklama ? `<div class="gt-not">"${gtEsc(t.aciklama)}"</div>` : ''}
                <div class="gt-aksiyonlar">
                    <button type="button" class="gt-btn gt-btn--red" onclick="openGtKarar('reddet', '${gtEsc(t.token)}')"><span class="material-symbols-outlined">close</span>Reddet</button>
                    <button type="button" class="gt-btn gt-btn--onay" onclick="openGtKarar('onayla', '${gtEsc(t.token)}')"><span class="material-symbols-outlined">check</span>Teslim Aldım</button>
                </div>
            </div>`).join('');

        document.getElementById('gt-uzerimdekiler').innerHTML = uzerimde.map((a) => `
            <div class="gt-kart">
                <div class="gt-kart__ust">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            ${zkPlaka(a.plaka)}
                            <span class="gt-etiket ${a.rol === 'asil' ? 'gt-etiket--asil' : ''}">${a.rol === 'asil' ? 'Asıl zimmetim' : 'Geçici zimmetim'}</span>
                        </div>
                        <div class="gt-alt">${gtEsc(a.marka_model || '')}</div>
                        ${a.rol === 'gecici' ? `<div class="gt-alt">Asıl sahibi: <strong>${gtEsc(a.asil_personel_adi)}</strong> · Teslim eden: <strong>${gtEsc(a.veren_personel_adi)}</strong></div>
                        <div class="gt-alt">Teslim alma: ${gtEsc(a.teslim_alma_tarihi)}${a.planlanan_iade ? ' · Plan: ' + gtEsc(a.planlanan_iade) : ''}</div>` : ''}
                    </div>
                </div>
                ${a.bekleyen_talep ? `
                    <div class="gt-bekleyen"><span class="material-symbols-outlined text-base">hourglass_top</span>
                        <span class="flex-1"><strong>${gtEsc(a.bekleyen_talep.alan_personel_adi)}</strong> onayı bekleniyor</span>
                    </div>
                    <div class="gt-aksiyonlar">
                        <button type="button" class="gt-btn gt-btn--ikincil" onclick="geriCekGeciciTeslim('${gtEsc(a.bekleyen_talep.token)}')"><span class="material-symbols-outlined">undo</span>Talebi Geri Çek</button>
                    </div>` : `
                    <div class="gt-aksiyonlar">
                        <button type="button" class="gt-btn gt-btn--ana" data-zimmet-token="${gtEsc(a.zimmet_token)}" data-plaka="${gtEsc(a.plaka)}" data-varsayilan-token="${a.rol === 'gecici' ? gtEsc(a.veren_token || '') : ''}" data-varsayilan-ad="${a.rol === 'gecici' ? gtEsc(a.veren_personel_adi || '') : ''}" onclick="openGeciciDevret(this)"><span class="material-symbols-outlined">swap_horiz</span>${a.rol === 'gecici' ? (a.veren_token ? 'Geri Ver / Devret' : 'Devret') : 'Geçici Devret'}</button>
                    </div>`}
            </div>`).join('');

        document.getElementById('gt-baskasinda').innerHTML = baskasinda.map((a) => `
            <div class="gt-kart gt-kart--bilgi">
                <div class="gt-kart__ust">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            ${zkPlaka(a.plaka)}
                            <span class="gt-etiket gt-etiket--kapali">Başkasında</span>
                        </div>
                        <div class="gt-alt">Asıl zimmetiniz şu an <strong>${gtEsc(a.kimde)}</strong> adlı personelde.</div>
                        <div class="gt-alt">Teslim: ${gtEsc(a.teslim_tarihi)}${a.planlanan_iade ? ' · Plan: ' + gtEsc(a.planlanan_iade) : ''}</div>
                    </div>
                </div>
            </div>`).join('');

        const etiketSinifi = { aktif: '', iade_edildi: 'gt-etiket--kapali', tamamlandi: 'gt-etiket--ok', reddedildi: 'gt-etiket--red', iptal: 'gt-etiket--red' };
        document.getElementById('gt-gecmis-kutu').classList.toggle('hidden', !gecmis.length);
        document.getElementById('gt-gecmis').innerHTML = gecmis.map((h) => `
            <div class="gt-gecmis-satir">
                <div class="min-w-0">
                    <div class="gt-gecmis-plaka">${gtEsc(h.plaka)} <span class="gt-alt">${h.yon === 'gelen' ? '← ' : '→ '}${gtEsc(h.karsi_personel_adi)}</span></div>
                    <div class="gt-alt">${gtEsc(h.teslim_tarihi || h.talep_tarihi)}${h.iade_tarihi ? ' – ' + gtEsc(h.iade_tarihi) : ''}</div>
                    ${h.red_nedeni ? `<div class="gt-alt">${gtEsc(h.red_nedeni)}</div>` : ''}
                </div>
                <span class="gt-etiket ${etiketSinifi[h.durum] ?? ''}" style="align-self:flex-start">${gtEsc(h.durum_text)}</span>
            </div>`).join('');
    }

    async function openGeciciDevret(btn) {
        const d = btn.dataset;
        const form = document.getElementById('gecici-devret-form');
        form.reset();
        document.getElementById('gt-devret-zimmet').value = d.zimmetToken;
        document.getElementById('gt-devret-alan').value = d.varsayilanToken || '';
        document.getElementById('gt-devret-plaka').textContent = d.plaka;
        document.getElementById('gt-personel-ara').value = '';
        form.querySelector('[name="planlanan_iade_tarihi"]').min = new Date().toISOString().slice(0, 10);
        Modal.open('gecici-devret-modal');

        if (gtPersoneller === null) {
            document.getElementById('gt-personel-liste').innerHTML = '<div class="p-4 text-center text-xs text-slate-400">Yükleniyor...</div>';
            const response = await API.request('getGeciciTeslimPersonelleri', {}, false);
            gtPersoneller = response.success ? (response.data || []) : [];
        }
        if (d.varsayilanToken && !gtPersoneller.some((p) => p.token === d.varsayilanToken)) {
            gtPersoneller.unshift({ token: d.varsayilanToken, adi_soyadi: d.varsayilanAd || 'Teslim eden personel', departman: '' });
        }
        renderGtPersoneller('');
        const secili = document.querySelector('#gt-personel-liste .gt-personel.secili');
        if (secili) secili.scrollIntoView({ block: 'nearest' });
    }

    function renderGtPersoneller(arama) {
        const secili = document.getElementById('gt-devret-alan').value;
        const q = arama.toLocaleLowerCase('tr-TR').trim();
        const liste = gtPersoneller
            .filter((p) => !q || p.adi_soyadi.toLocaleLowerCase('tr-TR').includes(q))
            .sort((a, b) => (b.token === secili) - (a.token === secili));
        document.getElementById('gt-personel-liste').innerHTML = liste.length
            ? liste.slice(0, 100).map((p) => `
                <button type="button" class="gt-personel ${p.token === secili ? 'secili' : ''}" data-token="${gtEsc(p.token)}">
                    <span>${gtEsc(p.adi_soyadi)}<small>${gtEsc(p.departman)}</small></span>
                    <span class="material-symbols-outlined">check_circle</span>
                </button>`).join('')
            : '<div class="p-4 text-center text-xs text-slate-400">Personel bulunamadı.</div>';
    }

    document.getElementById('gt-personel-ara').addEventListener('input', (e) => renderGtPersoneller(e.target.value));
    document.getElementById('gt-personel-liste').addEventListener('click', (e) => {
        const btn = e.target.closest('.gt-personel');
        if (!btn) return;
        document.getElementById('gt-devret-alan').value = btn.dataset.token;
        document.querySelectorAll('#gt-personel-liste .gt-personel').forEach((b) => b.classList.toggle('secili', b === btn));
    });

    document.getElementById('gecici-devret-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!document.getElementById('gt-devret-alan').value) {
            Toast.show('Teslim edeceğiniz personeli seçiniz.', 'warning');
            return;
        }
        const btn = document.getElementById('gt-devret-gonder');
        btn.disabled = true;
        const response = await API.request('createGeciciTeslimTalebi', Object.fromEntries(new FormData(this).entries()));
        btn.disabled = false;
        if (response.success) {
            Modal.close('gecici-devret-modal');
            Alert.success('Talep Gönderildi', response.message);
            loadGeciciTeslimler();
            loadZimmetler();
        } else {
            Alert.error('Hata', response.message || 'Talep gönderilemedi.');
        }
    });

    let gtGelenHaritasi = {};

    function openGtKarar(mod, token) {
        const t = gtGelenHaritasi[token];
        if (!t) return;
        const onay = mod === 'onayla';
        document.getElementById('gt-karar-token').value = token;
        document.getElementById('gt-karar-mod').value = mod;
        document.getElementById('gt-karar-baslik').textContent = onay ? 'Teslim Aldınız mı?' : 'Talebi Reddet';
        document.getElementById('gt-karar-alt').textContent = t.plaka;
        document.getElementById('gt-karar-ikon').innerHTML = `<span class="material-symbols-outlined">${onay ? 'task_alt' : 'block'}</span>`;
        document.getElementById('gt-karar-ikon').style.background = onay ? '' : '#e11d48';
        document.getElementById('gt-karar-onay-alani').classList.toggle('hidden', !onay);
        document.getElementById('gt-karar-red-alani').classList.toggle('hidden', onay);
        const btn = document.getElementById('gt-karar-gonder');
        btn.textContent = onay ? 'Teslim Aldım' : 'Reddet';
        btn.style.background = onay ? '#059669' : '#e11d48';
        document.getElementById('gt-karar-km').value = t.teslim_km || '';
        document.getElementById('gt-karar-neden').value = '';
        document.getElementById('gt-karar-ozet').innerHTML = `
            <div class="flex items-center justify-between gap-2">${zkPlaka(t.plaka)}<span class="zk-alt">${gtEsc(t.marka_model || '')}</span></div>
            <div class="gt-alt"><strong>${gtEsc(t.karsi_personel_adi)}</strong> aracı size ${t.asila_donus ? 'geri' : 'geçici olarak'} teslim etmek istiyor.</div>
            <div class="gt-alt">${gtEsc(t.talep_tarihi)}${t.teslim_km ? ' · ' + gtEsc(t.teslim_km) + ' km' : ''}</div>
            ${t.aciklama ? `<div class="gt-not">"${gtEsc(t.aciklama)}"</div>` : ''}`;
        Modal.open('gt-karar-modal');
    }

    document.getElementById('gt-karar-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const token = document.getElementById('gt-karar-token').value;
        const onay = document.getElementById('gt-karar-mod').value === 'onayla';
        const neden = document.getElementById('gt-karar-neden').value.trim();
        if (!onay && !neden) {
            Toast.show('Red gerekçesi giriniz.', 'warning');
            return;
        }
        const btn = document.getElementById('gt-karar-gonder');
        btn.disabled = true;
        const response = await API.request('cevaplaGeciciTeslimTalebi', onay
            ? { token, karar: 'onayla', teslim_km: document.getElementById('gt-karar-km').value }
            : { token, karar: 'reddet', red_nedeni: neden });
        btn.disabled = false;
        if (response.success) {
            Modal.close('gt-karar-modal');
            onay ? Alert.success('Onaylandı', response.message) : Toast.show(response.message, 'success');
            loadGeciciTeslimler();
            loadZimmetler();
        } else {
            Alert.error('Hata', response.message || 'İşlem yapılamadı.');
        }
    });

    async function geriCekGeciciTeslim(token) {
        if (!(await Alert.confirm('Talebi geri çek', 'Gönderdiğiniz teslim talebi iptal edilecek.', 'Geri Çek'))) return;
        const response = await API.request('geriCekGeciciTeslimTalebi', { token });
        if (response.success) {
            Toast.show(response.message, 'success');
            loadGeciciTeslimler();
        } else {
            Alert.error('Hata', response.message || 'İşlem yapılamadı.');
        }
    }

    async function loadZimmetler() {
        const container = document.getElementById('zimmet-list');

        try {
            const response = await API.request('getZimmetler');

            if (response.success) {
                allZimmetler = response.data;
                calculateCategoryStats();
                renderZimmetler();
            }
        } catch (error) {
            console.error('Zimmetler yüklenemedi:', error);
            container.innerHTML = '<div class="card p-8 text-center text-rose-500">Veriler yüklenirken bir hata oluştu.</div>';
        }
    }

    function calculateCategoryStats() {
        // Aktif zimmetleri filtrele (üzerinde olanlar)
        const aktifler = allZimmetler.filter(z => z.durum === 'teslim');

        // 1. Araçlar
        const araclar = aktifler.filter(z => z.type === 'arac');
        document.getElementById('arac-zimmet-count').textContent = araclar.length;

        // 2. Teknoloji (Telefon, Tablet, Yazıcı, Bilgisayar)
        const techTerms = ['telefon', 'tablet', 'yazıcı', 'yazici', 'bilgisayar', 'laptop', 'ekran', 'monitör', 'terminal', 'el terminali'];
        const tech = aktifler.filter(z => 
            z.type === 'demirbas' && 
            techTerms.some(term => (z.kategori || '').toLowerCase().includes(term) || (z.demirbas_adi || '').toLowerCase().includes(term))
        );
        document.getElementById('tech-zimmet-count').textContent = tech.length;

        // 3. Sayaçlar
        const sayaclar = aktifler.filter(z => (z.kategori || '').toLowerCase().includes('sayaç') || (z.kategori || '').toLowerCase().includes('sayac'));
        document.getElementById('sayac-zimmet-count').textContent = sayaclar.length;

        // 4. Aparatlar
        const aparatlar = aktifler.filter(z => (z.kategori || '').toLowerCase().includes('aparat'));
        // Aparatlarda miktar toplamı önemli olabilir
        const aparatTotal = aparatlar.reduce((sum, item) => sum + (parseInt(item.miktar) || 0), 0);
        document.getElementById('aparat-zimmet-count').textContent = aparatTotal;
        document.getElementById('zimmet-toplam-count').textContent = aktifler.length;
    }

    function toggleZimmetCategory(category) {
        currentZimmetFilter = category;
        zimmetGorunenSayi = 40;
        
        // UI Reset
        const cards = ['arac', 'tech', 'sayac', 'aparat'];
        cards.forEach(c => {
            const el = document.getElementById(`card-${c}`);
            if (el) el.classList.remove('border-primary/50');
        });

        const filterStatus = document.getElementById('filter-status');
        const filterText = document.getElementById('filter-text');

        if (category === 'all') {
            filterStatus.classList.add('hidden');
        } else {
            filterStatus.classList.remove('hidden');
            const el = document.getElementById(`card-${category}`);
            if (el) el.classList.add('border-primary/50');
            
            const names = {
                'arac': 'Araçlar',
                'tech': 'Teknoloji Ürünleri',
                'sayac': 'Sayaçlar',
                'aparat': 'Aparatlar'
            };
            filterText.textContent = `${names[category] || category} filtresi aktif`;
        }

        renderZimmetler();
    }

    function showSayacDetay() {
        const aktifler = allZimmetler.filter(z => z.durum === 'teslim');
        const sayaclar = aktifler.filter(z => (z.kategori || '').toLowerCase().includes('sayaç') || (z.kategori || '').toLowerCase().includes('sayac'));
        
        let sifir = 0;
        let hurda = 0;

        sayaclar.forEach(z => {
            const name = (z.demirbas_adi || '').toLowerCase();
            const miktar = parseInt(z.miktar) || 0;
            
            if (name.includes('sıfır') || name.includes('sifir')) {
                sifir += miktar;
            } else if (name.includes('hurda')) {
                hurda += miktar;
            } else {
                // Diğerleri (belirtilmemişse sıfır kabul edilebilir veya name bazlı bakılabilir)
                sifir += miktar; 
            }
        });

        document.getElementById('sayac-sifir-val').textContent = sifir;
        document.getElementById('sayac-hurda-val').textContent = hurda;
        
        Modal.open('sayac-detay-modal');
    }

    function showAparatDetay() {
        const aktifler = allZimmetler.filter(z => z.durum === 'teslim');
        const aparatlar = aktifler.filter(z => (z.kategori || '').toLowerCase().includes('aparat'));
        
        const totalAmount = aparatlar.reduce((sum, item) => sum + (parseInt(item.miktar) || 0), 0);
        document.getElementById('aparat-total-info').textContent = `Zimmetinizde toplam ${totalAmount} aparat bulunuyor.`;

        const listContainer = document.getElementById('aparat-detail-list');
        
        if (aparatlar.length === 0) {
            listContainer.innerHTML = '<div class="text-center py-6 text-slate-500">Zimmetli aparat bulunamadı.</div>';
        } else {
            listContainer.innerHTML = aparatlar.map(z => `
                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <div class="flex flex-col">
                        <span class="font-bold text-slate-900 dark:text-white text-sm">${z.demirbas_adi}</span>
                        <span class="text-[10px] text-slate-500">${z.marka_model || 'Marka Belirtilmemiş'}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-400">×</span>
                        <span class="text-lg font-black text-primary">${z.miktar}</span>
                    </div>
                </div>
            `).join('');
        }

        Modal.open('aparat-detay-modal');
    }

    function renderZimmetler() {
        const container = document.getElementById('zimmet-list');
        let filtered = allZimmetler;

        // Önce durum filtresi (tüm kategoriler için aktifleri gösterelim kategori seçiliyse)
        if (currentZimmetFilter !== 'all') {
            filtered = allZimmetler.filter(z => z.durum === 'teslim');
            
            if (currentZimmetFilter === 'arac') {
                filtered = filtered.filter(z => z.type === 'arac');
            } else if (currentZimmetFilter === 'tech') {
                const terms = ['telefon', 'tablet', 'yazıcı', 'yazici', 'bilgisayar', 'laptop', 'ekran', 'monitör', 'terminal', 'el terminali'];
                filtered = filtered.filter(z => 
                    z.type === 'demirbas' && 
                    terms.some(term => (z.kategori || '').toLowerCase().includes(term) || (z.demirbas_adi || '').toLowerCase().includes(term))
                );
            } else if (currentZimmetFilter === 'sayac') {
                filtered = filtered.filter(z => (z.kategori || '').toLowerCase().includes('sayaç') || (z.kategori || '').toLowerCase().includes('sayac'));
            } else if (currentZimmetFilter === 'aparat') {
                filtered = filtered.filter(z => (z.kategori || '').toLowerCase().includes('aparat'));
            }
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-slate-400 text-4xl">inventory_2</span>
                    </div>
                    <h3 class="text-slate-900 dark:text-white font-bold">Zimmet Bulunamadı</h3>
                    <p class="text-slate-500 text-sm mt-1">Bu kategoride kayıtlı herhangi bir aktif zimmet bulunmuyor.</p>
                </div>
            `;
            return;
        }

        const gorunen = filtered.slice(0, zimmetGorunenSayi);
        const kalan = filtered.length - gorunen.length;
        container.innerHTML = gorunen.map(z => {
            const arac = z.type === 'arac';
            const km = z.teslim_km ? Number(z.teslim_km).toLocaleString('tr-TR') + ' km' : '-';
            const baslik = arac
                ? `${zkPlaka(z.demirbas_adi)}<span class="zk-alt">${gtEsc(z.marka_model || '')}</span>`
                : `<span class="zk-ikon"><span class="material-symbols-outlined">${z.durum === 'teslim' ? 'inventory_2' : 'history'}</span></span>
                   <span class="zk-yazi"><b>${gtEsc(z.demirbas_adi)}</b><span class="zk-alt">${gtEsc([z.kategori, z.marka_model].filter(Boolean).join(' · '))}</span></span>`;
            const ikinci = arac
                ? `<div><small>Teslim KM</small><b>${gtEsc(km)}</b></div>`
                : `<div><small>${z.seri_no ? 'Seri No' : 'Miktar'}</small><b>${gtEsc(z.seri_no || (z.miktar + ' Adet'))}</b></div>`;
            return `
            <article class="zk-kart" data-ad="${gtEsc(z.demirbas_adi)}" onclick="showZimmetHareketleri(${Number(z.id)}, this.dataset.ad, '${gtEsc(z.type)}')">
                <div class="zk-ust">
                    <div class="zk-ana">${baslik}</div>
                    <span class="zk-durum zk-durum--${gtEsc(z.durum_color)}">${gtEsc(z.durum_text)}</span>
                </div>
                <div class="zk-meta">
                    <div><small>Teslim Tarihi</small><b>${gtEsc(z.teslim_tarihi)}</b></div>
                    ${ikinci}
                    <span class="material-symbols-outlined zk-git">chevron_right</span>
                </div>
                ${arac && z.durum === 'teslim' ? zkGeciciSatiri(z) : ''}
            </article>`;
        }).join('') + (kalan > 0 ? `<button type="button" onclick="zimmetDahaFazla()" class="pwa-btn pwa-btn--ghost w-full">Daha fazla göster (${kalan})</button>` : '');
    }

    function zimmetDahaFazla() {
        zimmetGorunenSayi += 40;
        renderZimmetler();
    }

    async function showZimmetHareketleri(zimmetId, demirbasAdi, type = 'demirbas') {
        const listContainer = document.getElementById('zimmet-hareket-list');
        listContainer.innerHTML = '<div class="flex justify-center py-8"><div class="shimmer w-full h-32 rounded-2xl"></div></div>';

        // Talep butonunu ayarla
        const btnTalep = document.getElementById('btn-zimmet-talep-ac');
        if (btnTalep) {
            btnTalep.onclick = () => openZimmetTalepModal(zimmetId, demirbasAdi, type);
        }

        const satir = document.getElementById('zimmet-devret-satiri');
        const zz = allZimmetler.find((z) => z.type === 'arac' && Number(z.id) === Number(zimmetId));
        const devredilebilir = type === 'arac' && zz && zz.durum === 'teslim' && !zz.gecici_kimde && !zz.gecici_bekleyen;
        satir.classList.toggle('hidden', !devredilebilir);
        if (devredilebilir) {
            const b = document.getElementById('btn-zimmet-devret');
            b.dataset.zimmetToken = zz.zimmet_token;
            b.dataset.plaka = zz.demirbas_adi;
            b.dataset.varsayilanToken = '';
            b.dataset.varsayilanAd = '';
            b.onclick = () => { Modal.close('zimmet-detay-modal'); openGeciciDevret(b); };
        }

        Modal.open('zimmet-detay-modal');

        try {
            const response = await API.request('getZimmetHareketleri', { zimmet_id: zimmetId, type: type });

            if (response.success) {
                const hareketler = response.data;

                if (hareketler.length === 0) {
                    listContainer.innerHTML = '<div class="text-center py-8 text-slate-500">Hareket kaydı bulunamadı.</div>';
                    return;
                }

                listContainer.innerHTML = hareketler.map((h, index) => `
                    <div class="relative pl-8 pb-2 last:pb-0">
                        <!-- Timeline Line -->
                        ${index !== hareketler.length - 1 ? '<div class="absolute left-[11px] top-7 bottom-0 w-0.5 bg-slate-100 dark:bg-slate-800"></div>' : ''}
                        
                        <!-- Timeline Dot -->
                        <div class="absolute left-0 top-1.5 w-6 h-6 rounded-full border-4 border-white dark:border-card-dark bg-${h.tip === 'zimmet' ? 'amber' : (h.tip === 'iade' ? 'emerald' : 'rose')}-500 z-10 shadow-sm"></div>
                        
                        <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-${h.tip === 'zimmet' ? 'amber' : (h.tip === 'iade' ? 'emerald' : 'rose')}-600">
                                    ${h.tip === 'zimmet' ? 'TESLİM ALINDI' : (h.tip === 'iade' ? 'İADE EDİLDİ' : h.tip.toUpperCase())}
                                </span>
                                <span class="text-[10px] font-medium text-slate-400 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-lg border border-slate-100 dark:border-slate-700">
                                    ${h.tarih}
                                </span>
                            </div>
                            
                            <div class="flex items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-sm text-slate-400">inventory_2</span>
                                <span class="text-sm text-slate-700 dark:text-slate-300 font-medium">Miktar: ${h.miktar} Adet</span>
                            </div>
                            
                            ${h.aciklama ? `
                                <div class="bg-white dark:bg-card-dark p-3 rounded-xl border border-slate-100 dark:border-slate-800 mb-2">
                                    <p class="text-xs text-slate-500 leading-relaxed italic">"${h.aciklama}"</p>
                                </div>
                            ` : ''}
                            
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="material-symbols-outlined text-xs text-slate-400">person</span>
                                <span class="text-[10px] text-slate-400 font-medium">İşlem Yapan: ${h.islem_yapan || '-'}</span>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                listContainer.innerHTML = `<div class="card p-4 text-center text-rose-500">${response.message}</div>`;
            }
        } catch (error) {
            console.error('Hareketler yüklenemedi:', error);
            listContainer.innerHTML = '<div class="card p-4 text-center text-rose-500">Hareketler yüklenirken bir hata oluştu.</div>';
        }
    }

    function exportZimmetToExcel() {
        if (allZimmetler.length === 0) {
            Toast.show('Dışa aktarılacak veri bulunamadı.', 'warning');
            return;
        }

        let html = `
            <table border="1">
                <thead>
                    <tr style="background-color: #f3f4f6;">
                        <th>Tür</th>
                        <th>Demirbaş No / Plaka</th>
                        <th>Demirbaş Adı</th>
                        <th>Kategori</th>
                        <th>Marka/Model</th>
                        <th>Seri No</th>
                        <th>Miktar</th>
                        <th>Teslim Tarihi</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
        `;

        allZimmetler.forEach(z => {
            html += `
                <tr>
                    <td>${z.type === 'arac' ? 'Araç' : 'Demirbaş'}</td>
                    <td>${z.demirbas_no}</td>
                    <td>${z.demirbas_adi}</td>
                    <td>${z.kategori}</td>
                    <td>${z.marka_model || '-'}</td>
                    <td>${z.seri_no || '-'}</td>
                    <td>${z.miktar}</td>
                    <td>${z.teslim_tarihi}</td>
                    <td>${z.durum_text}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';

        const blob = new Blob(['\ufeff', html], {
            type: 'application/vnd.ms-excel'
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Zimmetler_${new Date().toLocaleDateString().replace(/\./g, '-')}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        Toast.show('Excel dosyası indiriliyor...', 'success');
    }

    // Zimmet Talep Fonksiyonları
    function openZimmetTalepModal(id, name, type) {
        document.getElementById('talep-asset-id').value = id;
        document.getElementById('talep-asset-name').value = name;
        document.getElementById('talep-asset-type').value = type;
        document.getElementById('talep-asset-display-name').textContent = name;
        
        // Formu temizle
        document.getElementById('zimmet-talep-form').reset();
        document.getElementById('zimmet-kategori-input').value = '';
        document.querySelectorAll('.zimmet-kategori-btn').forEach(btn => btn.classList.remove('active'));
        
        // Eğer araçsa varsayılan olarak 'servis' seçelim
        if (type === 'arac') {
            const servisBtn = Array.from(document.querySelectorAll('.zimmet-kategori-btn')).find(b => b.onclick.toString().includes('servis'));
            if (servisBtn) selectZimmetKategori(servisBtn, 'servis');
        } else {
            // Demirbaşsa 'ariza' seçelim
            const arizaBtn = Array.from(document.querySelectorAll('.zimmet-kategori-btn')).find(b => b.onclick.toString().includes('ariza'));
            if (arizaBtn) selectZimmetKategori(arizaBtn, 'ariza');
        }

        Modal.open('zimmet-talep-modal');
    }

    function selectZimmetKategori(btn, value) {
        document.querySelectorAll('.zimmet-kategori-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('zimmet-kategori-input').value = value;
    }

    // Form submit listener
    document.getElementById('zimmet-talep-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const btn = document.getElementById('btn-zimmet-talep-gonder');
        const originalHtml = btn.innerHTML;
        
        const kategori = formData.get('kategori');
        if (!kategori) {
            Toast.show('Lütfen bir talep türü seçin.', 'warning');
            return;
        }

        const assetName = formData.get('asset_name');
        const assetType = formData.get('asset_type') === 'arac' ? 'Araç' : 'Demirbaş';
        const aciklama = formData.get('aciklama');
        
        // Konum bilgisini alalım (PWA standartlarında genelde istenir)
        let konum = 'Zimmetler Sayfası';
        
        try {
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin">refresh</span>';
            btn.disabled = true;

            // Talep başlığını ve açıklamasını özelleştirelim
            const kategoriMap = {
                'ariza': 'Arıza Bildirimi',
                'servis': 'Servis Talebi',
                'bakim': 'Bakım Talebi',
                'diger': 'Talep'
            };
            
            const baslik = `${kategoriMap[kategori]} - ${assetName}`;
            const fullAciklama = `[${assetType}: ${assetName}] ${aciklama}`;

            const response = await API.request('createTalepBildirimi', {
                kategori: kategori === 'bakim' || kategori === 'servis' ? 'istek' : kategori, // API'nin desteklediği kategorilere map et
                baslik: baslik,
                aciklama: fullAciklama,
                konum: konum,
                oncelik: 'orta'
            });

            if (response.success) {
                Modal.close('zimmet-talep-modal');
                Modal.close('zimmet-detay-modal');
                Alert.success('Başarılı', 'Talebiniz başarıyla oluşturuldu.');
            } else {
                throw new Error(response.message || 'Bir hata oluştu');
            }
        } catch (error) {
            console.error('Talep gönderme hatası:', error);
            Alert.error('Hata', error.message || 'Talep gönderilirken bir sorun oluştu.');
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    });
</script>