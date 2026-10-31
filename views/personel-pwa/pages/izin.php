<?php
/**
 * Personel PWA - İzin Sayfası
 * İzin listesi ve yeni izin talebi
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <?php pwaPageHeader([
        'eyebrow' => 'İZİN YÖNETİMİ',
        'title' => 'İzin İşlemleri',
        'subtitle' => 'İzinlerinizi yönetin',
        'actions' => '<button type="button" onclick="openNewIzinModal()" class="pwa-circle-btn pwa-circle-btn--primary" aria-label="Yeni İzin" title="Yeni İzin"><span class="material-symbols-outlined">add</span></button>',
    ]); ?>

    <!-- Stats Summary -->
    <section class="px-4 pt-1 pb-4">
        <div class="grid grid-cols-3 gap-3 pwa-stat-strip">
            <div class="card p-3 text-center relative overflow-hidden cursor-pointer" onclick="showHakedisDetay()">
                <div class="absolute top-0 right-0 w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-full -mr-4 -mt-4">
                </div>
                <div
                    class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-blue-600 text-lg">event_available</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="kalan-izin">0</p>
                <p class="text-[10px] text-slate-500">Kalan İzin</p>
            </div>

            <div class="card p-3 text-center relative overflow-hidden">
                <div
                    class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-red-600 text-lg">medical_services</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="hastalik-izni">0</p>
                <p class="text-[10px] text-slate-500">Hastalık İzni</p>
            </div>

            <div class="card p-3 text-center relative overflow-hidden">
                <div
                    class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-amber-600 text-lg">pending_actions</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="bekleyen-izin">0</p>
                <p class="text-[10px] text-slate-500">Bekleyen</p>
            </div>
        </div>
    </section>

    <!-- View Toggle & Month Selector -->
    <div
        class="px-4 pwa-toolbar izn-toolbar">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-3">
                <!-- Month/Week Selector -->
                <div id="month-selector" class="flex items-center gap-2" style="display: none;">
                    <button onclick="prevPeriod()"
                        class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">chevron_left</span>
                    </button>
                    <span id="period-label"
                        class="text-sm font-semibold text-slate-900 dark:text-white min-w-[120px] text-center">Şubat
                        2026</span>
                    <button onclick="nextPeriod()"
                        class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">chevron_right</span>
                    </button>
                </div>

                <!-- Spacer if month selector is hidden -->
                <div class="flex-1" id="month-selector-spacer"></div>

                <!-- View Toggle -->
                <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg flex-shrink-0">
                    <button onclick="setView('calendar')" id="btn-calendar"
                        class="view-btn px-3 py-1.5 text-xs font-medium rounded-md text-slate-600 dark:text-slate-400">
                        Takvim
                    </button>
                    <button onclick="setView('list')" id="btn-list"
                        class="view-btn px-3 py-1.5 text-xs font-semibold rounded-md bg-white dark:bg-card-dark shadow-sm">
                        Liste
                    </button>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div id="filter-tabs" class="flex gap-2 overflow-x-auto pb-1 min-w-0" style="display: flex;">
                <button onclick="filterIzinler('all')"
                    class="filter-btn active flex-shrink-0 px-4 py-1.5 text-xs font-semibold rounded-full bg-primary text-white"
                    data-filter="all">
                    Tümü
                </button>
                <button onclick="filterIzinler('onaylandi')"
                    class="filter-btn flex-shrink-0 px-4 py-1.5 text-xs font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
                    data-filter="onaylandi">
                    Onaylanan
                </button>
                <button onclick="filterIzinler('beklemede')"
                    class="filter-btn flex-shrink-0 px-4 py-1.5 text-xs font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
                    data-filter="beklemede">
                    Bekleyen
                </button>
                <button onclick="filterIzinler('reddedildi')"
                    class="filter-btn flex-shrink-0 px-4 py-1.5 text-xs font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
                    data-filter="reddedildi">
                    Reddedilen
                </button>
            </div>

            <!-- Calendar Stats (Visible in Calendar View) -->
            <div id="calendar-stats" class="izn-stats" style="display: none;">
                <div class="izn-stat izn-stat--blue">
                    <span>Çalışılan</span>
                    <b id="stat-calisilan">-</b>
                </div>
                <div class="izn-stat izn-stat--amber">
                    <span>Ücretsiz</span>
                    <b id="stat-ucretsiz">-</b>
                </div>
                <div class="izn-stat izn-stat--green">
                    <span>Ücretli</span>
                    <b id="stat-ucretli">-</b>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar View -->
    <div id="calendar-view" class="px-4 pb-28 swipe-ignore" style="display: none;">
    <div class="pwa-cal-card">
        <!-- Week Days Header -->
        <div class="grid grid-cols-7 gap-1 mb-2">
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Pzt</div>
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Sal</div>
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Çar</div>
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Per</div>
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Cum</div>
            <div class="text-center text-[10px] font-semibold text-red-500 py-1">Cmt</div>
            <div class="text-center text-[10px] font-semibold text-red-500 py-1">Paz</div>
        </div>
        <!-- Calendar Grid -->
        <div id="calendar-grid" class="grid grid-cols-7 gap-1">
            <!-- Days will be rendered here -->
        </div>

        <!-- Calendar Legend -->
        <div class="flex flex-wrap items-center justify-center gap-4 mt-6 px-2">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-green-500"></span>
                <span class="text-[11px] text-slate-600 dark:text-slate-400 font-medium">Onaylanan</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                <span class="text-[11px] text-slate-600 dark:text-slate-400 font-medium">Bekleyen</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-400"></span>
                <span class="text-[11px] text-slate-600 dark:text-slate-400 font-medium">Reddedilen</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-slate-400"></span>
                <span class="text-[11px] text-slate-600 dark:text-slate-400 font-medium">İptal Edilen</span>
            </div>
        </div>
    </div>
    </div>

    <div id="izn-range-bar" class="izn-range-bar" aria-live="polite">
        <div class="izn-range-bar__text">
            <b id="izn-range-title">-</b>
            <span id="izn-range-sub">-</span>
        </div>
        <button type="button" onclick="clearIzinRange()" class="izn-range-bar__clear" aria-label="Seçimi temizle">
            <span class="material-symbols-outlined">close</span>
        </button>
        <button type="button" onclick="openIzinFromRange()" class="izn-range-bar__go">İzin Talep Et</button>
    </div>

    <!-- İzin List View -->
    <div id="list-view" class="flex-1 px-4 pt-2 pb-28">
        <div class="izn-list" id="izin-list">
            <!-- İzin items will be loaded here -->
            <div class="shimmer h-24 rounded-3xl"></div>
            <div class="shimmer h-24 rounded-3xl"></div>
        </div>
    </div>
</div>

<!-- Yeni İzin Talebi Modal -->
<div id="izin-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content pwa-sheet flex flex-col" style="max-height: min(92dvh, 720px);">
        <div class="pwa-sheet__handle"></div>

        <div class="pwa-sheet__head">
            <span class="pwa-sheet__icon"><span class="material-symbols-outlined">edit_calendar</span></span>
            <div class="flex-1 min-w-0">
                <h3>Yeni İzin Talebi</h3>
                <p id="izin-modal-aralik">Tarih aralığını seçin</p>
            </div>
            <button type="button" onclick="Modal.close('izin-modal')" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="izin-form" class="flex flex-col flex-1 min-h-0">
            <div class="flex-1 overflow-y-auto pwa-sheet__body">
                <section class="pwa-sheet-card">
                    <div class="pwa-field">
                        <label>İzin Türü</label>
                        <select name="izin_tipi" class="pwa-input" required>
                            <option value="">Yükleniyor...</option>
                        </select>
                    </div>

                    <div id="hastalik-izni-uyari" class="bg-amber-500/10 border border-amber-500/30 rounded-2xl transition-all" style="display: none; padding: 16px 18px;">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-2xl flex-shrink-0 mt-0.5">medical_services</span>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">Önemli Bilgilendirme (Sağlık Raporu)</p>
                                <p class="text-xs text-amber-900/85 dark:text-amber-200/90 mt-1.5 leading-relaxed">
                                    Hastalık izni talebi oluşturabilmeniz için hekim veya sağlık kurulu tarafından verilmiş geçerli bir <strong>sağlık raporunuzun bulunması gerekmektedir</strong>. Raporunuz olmadan hastalık izni seçilmemelidir.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="pwa-field">
                            <label>Başlangıç Tarihi</label>
                            <input type="date" name="baslangic_tarihi" class="pwa-input" required>
                        </div>
                        <div class="pwa-field">
                            <label>Bitiş Tarihi</label>
                            <input type="date" name="bitis_tarihi" class="pwa-input" required>
                        </div>
                    </div>

                    <div class="pwa-field">
                        <label>Açıklama</label>
                        <textarea name="aciklama" class="pwa-input" placeholder="İzin talebiniz hakkında kısa bir açıklama..."></textarea>
                    </div>
                </section>
            </div>

            <div class="pwa-sheet__footer">
                <button type="button" onclick="Modal.close('izin-modal')" class="pwa-btn pwa-btn--ghost">İptal</button>
                <button type="submit" id="izin-submit-btn" class="pwa-btn pwa-btn--primary">
                    <span class="material-symbols-outlined" style="font-size:18px">send</span>Talebi Gönder
                </button>
            </div>
        </form>
    </div>
</div>

<!-- İzin Detay Modal -->
<div id="izin-detay-modal" class="modal-overlay">
    <div class="modal-content pwa-sheet p-6 pt-0">
        <div class="pwa-sheet__handle"></div>

        <div class="pwa-sheet__head" style="padding-left:0;padding-right:0">
            <span class="pwa-sheet__icon"><span class="material-symbols-outlined">event_note</span></span>
            <div class="flex-1 min-w-0"><h3>İzin Detayı</h3></div>
            <button type="button" onclick="Modal.close('izin-detay-modal')" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div id="izin-detay-content">
            <!-- Content will be loaded dynamically -->
        </div>

        <div class="flex gap-3 mt-4" id="izin-detay-actions">
            <!-- Action buttons -->
        </div>
    </div>
</div>

<!-- Hakediş Detay Bottom Sheet -->
<div id="hakedis-detay-modal" class="modal-overlay">
    <div class="modal-content pwa-sheet p-6 pt-0 h-[60vh] flex flex-col">
        <div class="pwa-sheet__handle"></div>

        <div class="pwa-sheet__head flex-shrink-0" style="padding-left:0;padding-right:0">
            <span class="pwa-sheet__icon"><span class="material-symbols-outlined">event_available</span></span>
            <div class="flex-1 min-w-0"><h3>İzin Hakediş Detayları</h3></div>
            <button type="button" onclick="Modal.close('hakedis-detay-modal')" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-4 flex-shrink-0">
            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-xl text-center">
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-1">Toplam</p>
                <p class="text-lg font-bold text-blue-700 dark:text-blue-300" id="sheet-toplam">0</p>
            </div>
            <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-xl text-center">
                <p class="text-xs text-red-600 dark:text-red-400 mb-1">Kullanılan</p>
                <p class="text-lg font-bold text-red-700 dark:text-red-300" id="sheet-kullanilan">0</p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-xl text-center">
                <p class="text-xs text-green-600 dark:text-green-400 mb-1">Kalan</p>
                <p class="text-lg font-bold text-green-700 dark:text-green-300" id="sheet-kalan">0</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div id="hakedis-list" class="space-y-2">
                <!-- Dynamic Content -->
            </div>
        </div>
    </div>
</div>

<style>
    .izn-toolbar { padding-bottom: 16px !important; }
    .izn-list { display: flex; flex-direction: column; gap: 14px; }
    .izn-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .izn-stat { background: #fff; border-radius: 20px; padding: 12px 8px; text-align: center; border: 1px solid rgba(255, 255, 255, .9); box-shadow: 0 6px 16px -10px rgba(15, 23, 42, .18); }
    .dark .izn-stat { background: #1a2130; border-color: #334155; }
    .izn-stat span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
    .izn-stat b { display: block; font-size: 22px; font-weight: 800; line-height: 1; margin-top: 6px; }
    .izn-stat--blue b { color: #2563eb; }
    .izn-stat--amber b { color: #d97706; }
    .izn-stat--green b { color: #059669; }
    .izn-card { background: #fff; border-radius: 24px; padding: 16px; display: flex; flex-direction: column; gap: 12px; cursor: pointer; box-shadow: 0 6px 16px -10px rgba(15, 23, 42, .18); border: 1px solid rgba(255, 255, 255, .9); transition: transform .15s ease; }
    .izn-card:active { transform: scale(.985); }
    .dark .izn-card { background: #1a2130; border-color: #334155; }
    .izn-card__head { display: flex; align-items: center; gap: 12px; }
    .izn-card__icon { width: 40px; height: 40px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .izn-card__title { flex: 1; min-width: 0; font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.25; }
    .dark .izn-card__title { color: #fff; }
    .izn-card__title small { display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-top: 2px; }
    .izn-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 10px; border-top: 1px solid #f1f5f9; font-size: 11px; color: #94a3b8; }
    .dark .izn-card__foot { border-top-color: #334155; }
    .izn-card__foot span { display: inline-flex; align-items: center; gap: 4px; }
    .izn-card__foot .material-symbols-outlined { font-size: 14px; }

    .izn-day--sel { background: var(--primary) !important; }
    .izn-day--sel span { color: #fff !important; font-weight: 800; }
    .izn-day--range { background: rgba(var(--primary-rgb, 19, 91, 236), .16) !important; border-radius: 0 !important; }
    .izn-day--range span { color: var(--primary) !important; font-weight: 800; }
    .izn-day--start { border-radius: 12px 0 0 12px !important; }
    .izn-day--end { border-radius: 0 12px 12px 0 !important; }
    .izn-day--start.izn-day--end { border-radius: 12px !important; }

    .izn-range-bar { position: fixed; left: 50%; bottom: calc(92px + env(safe-area-inset-bottom)); z-index: 60; width: min(calc(100% - 24px), 456px); display: flex; align-items: center; gap: 8px; padding: 10px 10px 10px 18px; border-radius: 999px; background: #191d24; color: #fff; box-shadow: 0 18px 32px -14px rgba(22, 25, 32, .6); transform: translate(-50%, 140%); opacity: 0; pointer-events: none; transition: transform .28s ease, opacity .28s ease; }
    .izn-range-bar.is-on { transform: translate(-50%, 0); opacity: 1; pointer-events: auto; }
    .izn-range-bar__text { flex: 1; min-width: 0; line-height: 1.2; }
    .izn-range-bar__text b { display: block; font-size: 13px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .izn-range-bar__text span { display: block; font-size: 11px; font-weight: 600; color: rgba(255, 255, 255, .62); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .izn-range-bar__clear { flex: none; width: 36px; height: 36px; border: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, .12); color: #fff; }
    .izn-range-bar__clear .material-symbols-outlined { font-size: 18px; }
    .izn-range-bar__go { flex: none; height: 44px; padding: 0 18px; border: 0; border-radius: 999px; background: var(--primary); color: #fff; font-size: 13px; font-weight: 800; }

</style>

<script>
    let currentFilter = 'all';
    let izinlerData = [];
    let izinTurleri = [];
    let hakedisData = null;
    const iseGirisTarihi = '<?= $personel->ise_giris_tarihi ?? "" ?>';
    const istenCikisTarihi = '<?= $personel->isten_cikis_tarihi ?? "" ?>';
    const personelMedeniDurum = '<?= htmlspecialchars($personel->medeni_durum ?? "", ENT_QUOTES, "UTF-8") ?>';

    // Calendar State
    let currentView = 'calendar';
    let currentDate = new Date();
    currentDate.setDate(1);
    const aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    const gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

    document.addEventListener('DOMContentLoaded', function () {
        loadIzinTurleri();
        loadIzinStats();
        loadIzinler();
        updatePeriodLabel();
        setView('calendar');

        // İzin türü değişiminde bilgilendirme ve uyarı kontrolleri
        const selectIzinTipi = document.querySelector('#izin-form select[name="izin_tipi"]');
        if (selectIzinTipi) {
            selectIzinTipi.addEventListener('change', function () {
                updateIzinTuruUyarilari(this.value, true);
            });
        }

        // İzin form submit
        document.getElementById('izin-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await submitIzinTalebi(this);
        });
    });

    function setView(view) {
        currentView = view;

        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.remove('bg-white', 'dark:bg-card-dark', 'shadow-sm');
            btn.classList.add('text-slate-600', 'dark:text-slate-400');
        });

        const activeBtn = document.getElementById(`btn-${view}`);
        activeBtn.classList.add('bg-white', 'dark:bg-card-dark', 'shadow-sm');
        activeBtn.classList.remove('text-slate-600', 'dark:text-slate-400');

        if (view === 'calendar') {
            document.getElementById('list-view').style.display = 'none';
            document.getElementById('filter-tabs').style.display = 'none';
            document.getElementById('month-selector-spacer').style.display = 'none';
            document.getElementById('calendar-view').style.display = 'block';
            document.getElementById('month-selector').style.display = 'flex';
            document.getElementById('calendar-stats').style.display = 'flex';
            renderCalendar();
        } else {
            document.getElementById('calendar-view').style.display = 'none';
            document.getElementById('month-selector').style.display = 'none';
            document.getElementById('calendar-stats').style.display = 'none';
            document.getElementById('month-selector-spacer').style.display = 'block';
            document.getElementById('filter-tabs').style.display = 'flex';
            document.getElementById('list-view').style.display = 'block';
            renderIzinler();
        }
    }

    function updatePeriodLabel() {
        const label = `${aylar[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
        document.getElementById('period-label').textContent = label;
    }

    function prevPeriod() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        updatePeriodLabel();
        renderCalendar();
    }

    function nextPeriod() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        updatePeriodLabel();
        renderCalendar();
    }

    function parseDateCustom(str) {
        if (!str) return null;
        const parts = str.split('.');
        if (parts.length === 3) {
            return new Date(parts[2], parseInt(parts[1]) - 1, parts[0]);
        }
        return new Date(str);
    }

    let izinRangeStart = null;
    let izinRangeEnd = null;

    function izinTarihEtiketi(str) {
        const d = new Date(str + 'T00:00:00');
        return `${d.getDate()} ${aylar[d.getMonth()]}`;
    }

    function izinGunSayisi(a, b) {
        return Math.round((new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000) + 1;
    }

    function izinGunDolu(dateStr) {
        const t = new Date(dateStr + 'T00:00:00').getTime();
        return izinlerData.some(izin => {
            const bas = parseDateCustom(izin.baslangic);
            const bit = parseDateCustom(izin.bitis);
            if (!bas || !bit) return false;
            bas.setHours(0, 0, 0, 0);
            bit.setHours(0, 0, 0, 0);
            return t >= bas.getTime() && t <= bit.getTime();
        });
    }

    function izinAralikCakisiyor(a, b) {
        const d = new Date(a + 'T00:00:00');
        const end = new Date(b + 'T00:00:00');
        while (d <= end) {
            const str = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            if (izinGunDolu(str)) return true;
            d.setDate(d.getDate() + 1);
        }
        return false;
    }

    function pickIzinDay(dateStr) {
        if (izinRangeStart && !izinRangeEnd) {
            if (dateStr < izinRangeStart) {
                izinRangeStart = dateStr;
            } else if (dateStr === izinRangeStart) {
                izinRangeEnd = dateStr;
            } else if (izinAralikCakisiyor(izinRangeStart, dateStr)) {
                Toast.show('Seçtiğiniz aralıkta mevcut bir izin var', 'warning');
                izinRangeStart = dateStr;
            } else {
                izinRangeEnd = dateStr;
            }
        } else {
            izinRangeStart = dateStr;
            izinRangeEnd = null;
        }
        renderCalendar();
        updateIzinRangeBar();
    }

    function updateIzinRangeBar() {
        const bar = document.getElementById('izn-range-bar');
        if (!bar) return;
        if (!izinRangeStart) {
            bar.classList.remove('is-on');
            return;
        }
        const title = document.getElementById('izn-range-title');
        const sub = document.getElementById('izn-range-sub');
        if (izinRangeEnd && izinRangeEnd !== izinRangeStart) {
            title.textContent = `${izinTarihEtiketi(izinRangeStart)} – ${izinTarihEtiketi(izinRangeEnd)}`;
            sub.textContent = `${izinGunSayisi(izinRangeStart, izinRangeEnd)} gün seçildi`;
        } else if (izinRangeEnd) {
            title.textContent = izinTarihEtiketi(izinRangeStart);
            sub.textContent = '1 gün seçildi';
        } else {
            title.textContent = izinTarihEtiketi(izinRangeStart);
            sub.textContent = 'Bitiş gününe dokunun; tek gün için buton';
        }
        bar.classList.add('is-on');
    }

    function clearIzinRange() {
        izinRangeStart = null;
        izinRangeEnd = null;
        updateIzinRangeBar();
        renderCalendar();
    }

    function openIzinFromRange() {
        if (!izinRangeStart) return;
        openNewIzinModal(izinRangeStart, izinRangeEnd || izinRangeStart);
    }

    function openNewIzinModal(dateStr = '', endStr = '') {
        if (dateStr) {
            document.querySelector('#izin-form input[name="baslangic_tarihi"]').value = dateStr;
            document.querySelector('#izin-form input[name="bitis_tarihi"]').value = endStr || dateStr;
        }
        const aralikEl = document.getElementById('izin-modal-aralik');
        if (aralikEl) {
            aralikEl.textContent = dateStr
                ? `${izinTarihEtiketi(dateStr)}${endStr && endStr !== dateStr ? ' – ' + izinTarihEtiketi(endStr) + ' · ' + izinGunSayisi(dateStr, endStr) + ' gün' : ' · 1 gün'}`
                : 'Tarih aralığını seçin';
        }
        const currentIzinTipi = document.querySelector('#izin-form select[name="izin_tipi"]')?.value || '';
        updateIzinTuruUyarilari(currentIzinTipi, false);
        Modal.open('izin-modal');
    }

    function renderCalendar() {
        const grid = document.getElementById('calendar-grid');
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startOffset = (firstDay.getDay() + 6) % 7; // Pazartesi = 0

        let html = '';

        for (let i = 0; i < startOffset; i++) {
            html += `<div class="aspect-square p-1"></div>`;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let day = 1; day <= lastDay.getDate(); day++) {
            const currentCellDate = new Date(year, month, day);
            currentCellDate.setHours(0, 0, 0, 0);
            const isToday = currentCellDate.getTime() === today.getTime();
            const isPast = currentCellDate < today;
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            const dayIzinler = izinlerData.filter(izin => {
                const startDate = parseDateCustom(izin.baslangic);
                const endDate = parseDateCustom(izin.bitis);
                if (startDate && endDate) {
                    startDate.setHours(0, 0, 0, 0);
                    endDate.setHours(0, 0, 0, 0);
                    return currentCellDate >= startDate && currentCellDate <= endDate;
                }
                return false;
            });

            let dayClass = 'bg-slate-50 dark:bg-slate-800';
            let textClass = 'text-slate-600 dark:text-slate-400';
            let opacityClass = '';

            if (isPast) {
                dayClass = 'bg-slate-100/50 dark:bg-slate-800/50';
                textClass = 'text-slate-400 dark:text-slate-600';
                opacityClass = 'opacity-50';
            }

            if (isToday) {
                dayClass = 'bg-primary/10 ring-2 ring-primary';
                textClass = 'text-primary font-bold';
                opacityClass = '';
            }

            let mainIzin = dayIzinler.length > 0 ? dayIzinler[0] : null;
            let onclickAction = '';

            if (mainIzin) {
                textClass = 'text-white font-bold';
                let baseClass = 'bg-green-500';
                if (mainIzin.durum === 'beklemede') baseClass = 'bg-amber-500';
                else if (mainIzin.durum === 'reddedildi') baseClass = 'bg-red-400';
                else if (mainIzin.durum === 'iptal_edildi') baseClass = 'bg-slate-400';

                if (isPast && mainIzin.durum === 'onaylandi') {
                    baseClass = 'bg-green-400';
                }
                dayClass = baseClass;
                onclickAction = `showIzinDetay(${mainIzin.id})`;
            } else if (!isPast) {
                onclickAction = `pickIzinDay('${dateStr}')`;
                const rEnd = izinRangeEnd || izinRangeStart;
                if (izinRangeStart && dateStr >= izinRangeStart && dateStr <= rEnd) {
                    const isStart = dateStr === izinRangeStart;
                    const isEnd = dateStr === rEnd;
                    dayClass = (isStart || isEnd) ? 'izn-day--sel' : 'izn-day--range';
                    if (isStart) dayClass += ' izn-day--start';
                    if (isEnd) dayClass += ' izn-day--end';
                    textClass = '';
                }
            }

            html += `
                <div onclick="${onclickAction}" 
                     class="aspect-square p-1 rounded-lg ${dayClass} ${opacityClass} flex flex-col items-center justify-center ${onclickAction ? 'cursor-pointer transition-transform active:scale-95' : ''}">
                    <span class="text-sm ${textClass}">${day}</span>
                    <div class="flex gap-0.5 mt-0.5 max-w-full flex-wrap justify-center overflow-hidden">
                        ${dayIzinler.length > 1 ? '<span class="w-1.5 h-1.5 bg-white rounded-full"></span>'.repeat(Math.min(dayIzinler.length - 1, 3)) : ''}
                    </div>
                </div>
            `;
        }

        grid.innerHTML = html;
        updateMonthStats();
    }

    function updateMonthStats() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        const now = new Date();
        const isCurrentMonth = (year === now.getFullYear() && month === now.getMonth());
        const isPastMonth = (year < now.getFullYear() || (year === now.getFullYear() && month < now.getMonth()));
        
        let totalDays = 0;
        let limitDay = 0;
        
        if (isCurrentMonth) {
            // Saat 17:00'den sonraysa bugünü de dahil et
            const includeToday = now.getHours() >= 17;
            limitDay = includeToday ? now.getDate() : Math.max(0, now.getDate() - 1);
        } else if (isPastMonth) {
            limitDay = daysInMonth;
        } else {
            limitDay = 0;
        }

        // 1. Calculate base worked days (Respecting employment dates)
        let periodStart = new Date(year, month, 1);
        let periodEnd = new Date(year, month, Math.max(1, limitDay));
        periodStart.setHours(0, 0, 0, 0);
        periodEnd.setHours(23, 59, 59, 999);

        // Adjust by Hire Date
        const hireDate = parseDateCustom(iseGirisTarihi);
        if (hireDate) hireDate.setHours(0,0,0,0);
        
        if (hireDate && hireDate > periodStart) {
            periodStart = hireDate;
        }

        // Adjust by Termination Date
        const termDate = parseDateCustom(istenCikisTarihi);
        if (termDate) termDate.setHours(23,59,59,999);

        if (termDate && termDate < periodEnd && termDate.getFullYear() > 1970) {
            periodEnd = termDate;
        }

        // Final total days in this period
        if (periodStart <= periodEnd && limitDay > 0) {
            const diffTime = Math.abs(periodEnd - periodStart);
            totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        } else {
            totalDays = 0;
        }

        // 2. Calculate Leaves in this month up to limitDay (respecting the worked period)
        let unpaidLeaveDays = 0;
        let paidLeaveDays = 0;

        if (totalDays > 0 && izinlerData && izinlerData.length > 0) {
            izinlerData.forEach(izin => {
                // Check if status is approved (case-insensitive and handling Turkish chars)
                const status = (izin.durum || '').toLowerCase();
                const isApproved = status === 'onaylandi' || status === 'onaylandı';
                
                if (!isApproved) return; 

                const start = parseDateCustom(izin.baslangic);
                const end = parseDateCustom(izin.bitis);
                
                if (!start || !end) return;

                start.setHours(0,0,0,0);
                end.setHours(23,59,59,999);

                // Check overlap between leave and the "worked period"
                if (start > periodEnd || end < periodStart) return;

                const overlapStart = start < periodStart ? periodStart : start;
                const overlapEnd = end > periodEnd ? periodEnd : end;

                // Calculate days in overlap
                const diffTime = Math.abs(overlapEnd - overlapStart);
                const overlapDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                // Categorize
                const typeName = (izin.izin_tipi_text || '').toLowerCase();
                if (typeName.includes('ücretsiz') || typeName.includes('ucretsiz')) {
                    unpaidLeaveDays += overlapDays;
                } else {
                    paidLeaveDays += overlapDays;
                }
            });
        }

        // 3. Update UI
        // Worked = Total Days in Period - (Paid + Unpaid Leaves)
        const actualWorked = Math.max(0, totalDays - (paidLeaveDays + unpaidLeaveDays));

        document.getElementById('stat-calisilan').textContent = actualWorked;
        document.getElementById('stat-ucretsiz').textContent = unpaidLeaveDays;
        document.getElementById('stat-ucretli').textContent = paidLeaveDays;
    }

    async function loadIzinTurleri() {
        try {
            const response = await API.request('getIzinTurleri');
            if (response.success) {
                izinTurleri = response.data;
                renderIzinTurleri();
            }
        } catch (error) {
            console.error('İzin türleri yüklenemedi:', error);
            const select = document.querySelector('select[name="izin_tipi"]');
            select.innerHTML = '<option value="">Hata oluştu</option>';
        }
    }

    async function loadIzinStats(showLoading = true) {
        try {
            const response = await API.request('getIzinStats', {}, showLoading);
            if (response.success) {
                hakedisData = response.data;
                renderIzinTurleri();
                document.getElementById('kalan-izin').textContent = response.data.kalan_izin + ' Gün';
                document.getElementById('hastalik-izni').textContent = response.data.hastalik_izni + ' Gün';
                document.getElementById('bekleyen-izin').textContent = response.data.bekleyen;
            }
        } catch (error) {
            console.error('Stats load error:', error);
        }
    }

    function normalizeIzinMetni(value) {
        return String(value || '').toLocaleLowerCase('tr-TR');
    }

    function isMazeretIzinTuru(tur) {
        if (!tur) return false;
        return normalizeIzinMetni(tur.tur_adi).includes('mazeret') || normalizeIzinMetni(tur.kisa_kod) === 'mazeret';
    }

    function isYillikIzinTuru(tur) {
        if (!tur) return false;
        if (tur.is_yillik !== undefined) return Boolean(tur.is_yillik);
        const ad = normalizeIzinMetni(tur.tur_adi);
        const kod = normalizeIzinMetni(tur.kisa_kod);
        return ad.includes('yıllık') || ad.includes('yillik') || kod === 'yillik' || kod === 'yi';
    }

    function isHastalikIzinTuru(tur) {
        if (!tur) return false;
        if (tur.is_hastalik !== undefined) return Boolean(tur.is_hastalik);
        const ad = normalizeIzinMetni(tur.tur_adi);
        const kod = normalizeIzinMetni(tur.kisa_kod);
        return ad.includes('hastalık') || ad.includes('hastalik') || ad.includes('rapor') || kod === 'rp';
    }

    function isBabalikIzinTuru(tur) {
        if (!tur) return false;
        if (tur.is_babalik !== undefined) return Boolean(tur.is_babalik);
        const ad = normalizeIzinMetni(tur.tur_adi);
        const kod = normalizeIzinMetni(tur.kisa_kod);
        return ad.includes('babalık') || ad.includes('babalik') || kod === 'bi';
    }

    function isEvlilikIzinTuru(tur) {
        if (!tur) return false;
        if (tur.is_evlilik !== undefined) return Boolean(tur.is_evlilik);
        const ad = normalizeIzinMetni(tur.tur_adi);
        const kod = normalizeIzinMetni(tur.kisa_kod);
        return ad.includes('evlilik') || kod === 'ei';
    }

    function isHakEdisGerektirenIzinTuru(tur) {
        if (!tur) return false;
        // Yıllık izin talebi bakiye olmasa da onaya sunulabilir.
        if (isYillikIzinTuru(tur) || isEvlilikIzinTuru(tur) || isBabalikIzinTuru(tur)) {
            return false;
        }
        return !isMazeretIzinTuru(tur) && Number(tur.ucretli_mi) === 1;
    }

    function updateIzinTuruUyarilari(selectedId, showToast = true) {
        const hastalikUyariEl = document.getElementById('hastalik-izni-uyari');

        const secilenTur = izinTurleri.find(tur => String(tur.id) === String(selectedId));

        // Hastalık izni bilgilendirmesi
        if (hastalikUyariEl) {
            if (secilenTur && isHastalikIzinTuru(secilenTur)) {
                hastalikUyariEl.style.display = 'block';
                if (showToast) {
                    Toast.show('Hastalık izni seçtiniz. Rapor olmadan hastalık izni seçilmemelidir.', 'warning');
                }
            } else {
                hastalikUyariEl.style.display = 'none';
            }
        }

    }

    function checkHastalikIzniUyarisi(selectedId, showToast = true) {
        updateIzinTuruUyarilari(selectedId, showToast);
    }

    function renderIzinTurleri() {
        const select = document.querySelector('select[name="izin_tipi"]');
        if (!select || !Array.isArray(izinTurleri)) return;

        const seciliDeger = select.value;
        const hakYok = hakedisData && Number(hakedisData.kalan_izin) <= 0;
        const normMedeni = normalizeIzinMetni(personelMedeniDurum);
        select.innerHTML = '<option value="">Seçiniz...</option>';

        izinTurleri.forEach(tur => {
            // Bekar olanlarda babalık izni gelmesin
            if (normMedeni === 'bekar' && isBabalikIzinTuru(tur)) {
                return;
            }
            // Evli olanlarda evlilik izni gelmesin
            if (normMedeni === 'evli' && isEvlilikIzinTuru(tur)) {
                return;
            }

            const option = document.createElement('option');
            option.value = tur.id;
            option.disabled = Boolean(hakYok && isHakEdisGerektirenIzinTuru(tur));
            option.textContent = tur.tur_adi + (option.disabled ? ' (İzin hakedişi yok)' : '');
            select.appendChild(option);
        });

        if ([...select.options].some(option => option.value === seciliDeger && !option.disabled)) {
            select.value = seciliDeger;
        } else {
            select.value = '';
        }

        updateIzinTuruUyarilari(select.value, false);
    }

    async function showHakedisDetay() {
        if (!hakedisData) return;

        document.getElementById('sheet-toplam').textContent = hakedisData.toplam_hakedis + ' Gün';
        document.getElementById('sheet-kullanilan').textContent = hakedisData.kullanilan_izin + ' Gün';
        document.getElementById('sheet-kalan').textContent = hakedisData.kalan_izin + ' Gün';

        const container = document.getElementById('hakedis-list');

        // Kullanılan izinleri yıllara göre gruplamak için izinleri çek
        try {
            const response = await API.request('getIzinlerByYear');
            const izinlerByYear = response.success ? response.data : {};

            if (hakedisData.detay && hakedisData.detay.length > 0) {
                // FIFO Logic: Calculate how much leave was used from each year
                const allLeaves = izinlerByYear['all'] || [];
                const totalUsed = allLeaves.reduce((sum, izin) => sum + parseFloat(izin.toplam_gun || 0), 0);

                // Distribute used leaves across years using FIFO
                let remainingUsed = totalUsed;
                const yearUsage = [];

                for (let i = 0; i < hakedisData.detay.length; i++) {
                    const yearDetail = hakedisData.detay[i];
                    const yearEntitlement = yearDetail.hakedis_gun;

                    if (remainingUsed > 0) {
                        const usedFromThisYear = Math.min(remainingUsed, yearEntitlement);
                        const remaining = yearEntitlement - usedFromThisYear;
                        yearUsage.push({
                            year: yearDetail.yil,
                            entitlement: yearEntitlement,
                            used: usedFromThisYear,
                            remaining: remaining
                        });
                        remainingUsed -= usedFromThisYear;
                    } else {
                        yearUsage.push({
                            year: yearDetail.yil,
                            entitlement: yearEntitlement,
                            used: 0,
                            remaining: yearEntitlement
                        });
                    }
                }

                container.innerHTML = hakedisData.detay.map((item, index) => {
                    const usage = yearUsage[index];

                    return `
                        <div class="card overflow-hidden">
                            <button onclick="toggleYearAccordion(${index})" 
                                class="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-primary">calendar_today</span>
                                    </div>
                                    <div class="text-left">
                                        <p class="font-bold text-slate-900 dark:text-white">${item.yil}. Yıl</p>
                                        <p class="text-xs text-slate-500">${new Date(item.hakedis_tarihi).toLocaleDateString('tr-TR')}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-xs text-slate-500">Hak: ${usage.entitlement} | Kullanılan: ${usage.used} | Kalan: ${usage.remaining}</p>
                                    </div>
                                    <span class="material-symbols-outlined text-slate-400 accordion-icon" id="accordion-icon-${index}">expand_more</span>
                                </div>
                            </button>
                            <div id="accordion-${index}" class="accordion-content hidden">
                                <div class="px-4 pb-3 border-t border-slate-100 dark:border-slate-800">
                                    ${usage.used > 0 && allLeaves.length > 0 ? `
                                        <div class="mt-3 overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-xs text-slate-500 border-b border-slate-200 dark:border-slate-700">
                                                        <th class="text-left py-2 px-2 font-semibold">İzin Türü</th>
                                                        <th class="text-left py-2 px-2 font-semibold">Tarih</th>
                                                        <th class="text-center py-2 px-2 font-semibold">Süre (Gün)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${allLeaves.map(izin => `
                                                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                                                            <td class="py-2.5 px-2 text-slate-900 dark:text-white font-medium">${izin.izin_tipi_adi || 'Yıllık İzin'}</td>
                                                            <td class="py-2.5 px-2 text-slate-600 dark:text-slate-400">
                                                                <div class="flex flex-col">
                                                                    <span class="text-xs">Başlangıç: ${izin.baslangic}</span>
                                                                    <span class="text-xs">Bitiş: ${izin.bitis}</span>
                                                                </div>
                                                            </td>
                                                            <td class="py-2.5 px-2 text-center font-bold text-primary">${izin.toplam_gun}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    ` : `
                                        <div class="mt-2 py-3 text-center">
                                            <p class="text-xs text-slate-500">Bu yıl için kullanılan izin bulunmuyor</p>
                                        </div>
                                    `}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = '<div class="text-center py-8 text-slate-500">Hakediş detayı bulunamadı.</div>';
            }
        } catch (error) {
            console.error('Hakediş detay yükleme hatası:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Veriler yüklenirken hata oluştu.</div>';
        }

        Modal.open('hakedis-detay-modal');
    }

    function toggleYearAccordion(index) {
        const content = document.getElementById(`accordion-${index}`);
        const icon = document.getElementById(`accordion-icon-${index}`);

        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.textContent = 'expand_less';
        } else {
            content.classList.add('hidden');
            icon.textContent = 'expand_more';
        }
    }

    async function loadIzinler(showLoading = true) {
        const container = document.getElementById('izin-list');

        try {
            const response = await API.request('getIzinler', {}, showLoading);

            if (response.success && response.data.length > 0) {
                izinlerData = response.data;
                if (currentView === 'calendar') {
                    renderCalendar();
                } else {
                    renderIzinler();
                }
            } else {
                container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">calendar_today</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Henüz izin kaydı yok</p>
                    <p class="text-sm text-slate-500">Yeni izin talebi oluşturabilirsiniz.</p>
                </div>
            `;
            }
        } catch (error) {
            console.error('İzin load error:', error);
            container.innerHTML = '<p class="text-center text-slate-500 py-8">Veriler yüklenemedi</p>';
        }
    }

    function renderIzinler() {
        const container = document.getElementById('izin-list');
        let filtered = izinGruplari();

        if (currentFilter !== 'all') {
            filtered = filtered.filter(i => i.durum === currentFilter);
        }

        if (filtered.length === 0) {
            container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-symbols-outlined">filter_list</span>
                </div>
                <p class="text-slate-600 dark:text-slate-400 font-medium">Kayıt bulunamadı</p>
                <p class="text-sm text-slate-500">Bu filtreye uygun izin bulunmuyor.</p>
            </div>
        `;
            return;
        }

        container.innerHTML = filtered.map(izin => `
        <div class="izn-card" onclick="showIzinDetay(${izin.id})">
            <div class="izn-card__head">
                <div class="izn-card__icon ${izin.renk}">
                    <span class="material-symbols-outlined text-xl">${izin.ikon}</span>
                </div>
                <p class="izn-card__title">${izin.izin_tipi_text}<small>${izin.baslangic === izin.bitis ? izin.baslangic : izin.baslangic + ' - ' + izin.bitis}</small></p>
                <span class="badge ${getStatusBadge(izin.durum)} flex-shrink-0">${izin.durum_text}</span>
            </div>
            <div class="izn-card__foot">
                <span><span class="material-symbols-outlined">schedule</span>${izin.toplam_gun} Gün</span>
                <span><span class="material-symbols-outlined">calendar_today</span>${izin.talep_tarihi}</span>
            </div>
        </div>
    `).join('');
    }

    function izinTarihiParse(str) {
        const [g, a, y] = String(str).split('.').map(Number);
        return new Date(y, a - 1, g).getTime();
    }

    function izinGruplari() {
        const GUN = 86400000;
        const sirali = [...izinlerData].sort((x, y) => izinTarihiParse(x.baslangic) - izinTarihiParse(y.baslangic) || x.id - y.id);
        const gruplar = [];

        sirali.forEach(izin => {
            const onceki = gruplar.find(g =>
                g.izin_tipi === izin.izin_tipi &&
                g.durum === izin.durum &&
                izinTarihiParse(izin.baslangic) - izinTarihiParse(g.bitis) <= GUN &&
                izinTarihiParse(izin.bitis) >= izinTarihiParse(g.baslangic)
            );
            if (onceki) {
                onceki.ids.push(izin.id);
                if (izinTarihiParse(izin.bitis) > izinTarihiParse(onceki.bitis)) onceki.bitis = izin.bitis;
                onceki.toplam_gun = (Number(onceki.toplam_gun) || 0) + (Number(izin.toplam_gun) || 0);
                if (izin.aciklama && !onceki.aciklama) onceki.aciklama = izin.aciklama;
            } else {
                gruplar.push({ ...izin, ids: [izin.id] });
            }
        });

        return gruplar.sort((x, y) => izinTarihiParse(y.baslangic) - izinTarihiParse(x.baslangic));
    }

    function filterIzinler(filter) {
        currentFilter = filter;

        // Update filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-primary', 'text-white');
            btn.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-400');
        });

        const activeBtn = document.querySelector(`[data-filter="${filter}"]`);
        activeBtn.classList.add('active', 'bg-primary', 'text-white');
        activeBtn.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-400');

        renderIzinler();
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'onaylandi':
            case 'onaylandı':
                return 'badge-success bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
            case 'beklemede': return 'badge-warning';
            case 'reddedildi': return 'badge-danger';
            case 'iptal_edildi': return 'badge-gray bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
            default: return 'badge-gray';
        }
    }

    async function showIzinDetay(id) {
        Modal.open('izin-detay-modal');

        const izin = izinGruplari().find(g => g.ids.some(x => parseInt(x) === parseInt(id)));
        if (!izin) return;

        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const temizle = metin => {
            const t = String(metin || '').trim();
            const half = Math.floor(t.length / 2);
            return (t.length > 20 && t.substring(0, half).trim() === t.substring(half).trim()) ? t.substring(0, half).trim() : t;
        };
        const stepIcons = { talep: 'send', onay: 'check_circle', kismi: 'rule', red: 'cancel', iptal: 'block', bekliyor: 'hourglass_top', sirada: 'schedule' };

        const talepFarkli = !!(izin.talep_baslangic && izin.talep_bitis) && (izin.talep_baslangic !== izin.baslangic || izin.talep_bitis !== izin.bitis) && izin.ids.length === 1;
        const surec = (izin.surec || []).map(a => ({ ...a }));
        if (izin.durum === 'beklemede' && !surec.some(a => a.tip === 'bekliyor' || a.tip === 'kismi')) {
            surec.push({ tip: 'sirada', baslik: 'Yönetici onayı bekleniyor', kisi: null, tarih: null, aciklama: null });
        }
        const surecHtml = surec.map(a => `
            <li class="izd-step izd-step--${esc(a.tip)}">
                <span class="izd-step__dot"><span class="material-symbols-outlined">${stepIcons[a.tip] || 'radio_button_checked'}</span></span>
                <div class="izd-step__body">
                    <p class="izd-step__title">${esc(a.baslik)}</p>
                    ${(a.kisi || a.tarih) ? `<p class="izd-step__meta">${esc([a.kisi, a.tarih].filter(Boolean).join(' • '))}</p>` : ''}
                    ${a.aciklama ? `<p class="izd-step__note">${esc(temizle(a.aciklama))}</p>` : ''}
                </div>
            </li>`).join('');

        document.getElementById('izin-detay-content').innerHTML = `
        <div class="pwa-sheet__body" style="padding:0 0 4px">
            <div class="pwa-sheet-card">
                <div class="izd-hero">
                    <span class="izd-hero__icon"><span class="material-symbols-outlined">${esc(izin.ikon)}</span></span>
                    <div>
                        <p class="izd-hero__title">${esc(izin.izin_tipi_text)}</p>
                        <span class="izd-pill izd-pill--${esc(izin.durum)}">${esc(izin.durum_text)}</span>
                    </div>
                </div>
                <div class="izd-stats">
                    <div class="izd-stat"><small>${talepFarkli ? 'Onaylanan Başlangıç' : 'Başlangıç'}</small><b>${esc(izin.baslangic)}</b></div>
                    <div class="izd-stat"><small>${talepFarkli ? 'Onaylanan Bitiş' : 'Bitiş'}</small><b>${esc(izin.bitis)}</b></div>
                </div>
                ${talepFarkli ? `
                <div class="izd-stat" style="background:rgba(var(--primary-rgb, 19, 91, 236), .08)">
                    <small>Talep Ettiğiniz</small>
                    <b>${esc(izin.talep_baslangic)} - ${esc(izin.talep_bitis)} <span style="font-weight:700;color:#64748b">(${esc(Number(izin.talep_gun))} gün)</span></b>
                </div>` : ''}
                <div class="izd-total">
                    <span class="material-symbols-outlined">schedule</span>${talepFarkli ? 'Onaylanan Süre' : 'Toplam Süre'}
                    <b>${esc(izin.toplam_gun)} Gün</b>
                </div>
                ${izin.aciklama ? `
                <div>
                    <p class="pwa-card-label" style="margin-bottom:6px">Açıklamanız</p>
                    <p class="izd-text">${esc(izin.aciklama)}</p>
                </div>` : ''}
            </div>

            <div class="pwa-sheet-card">
                <p class="pwa-card-title"><span class="material-symbols-outlined">timeline</span>Talep Süreci</p>
                <ol class="izd-steps">${surecHtml}</ol>
            </div>
        </div>
    `;

        // Action buttons based on status
        const actionsContainer = document.getElementById('izin-detay-actions');
        if (izin.durum === 'beklemede') {
            actionsContainer.innerHTML = `
            <button onclick="cancelIzin('${izin.ids.join(',')}')" class="pwa-btn flex-1" style="background:#fee2e2;color:#b91c1c">İptal Et</button>
            <button onclick="Modal.close('izin-detay-modal')" class="pwa-btn pwa-btn--ghost flex-1">Kapat</button>
        `;
        } else {
            actionsContainer.innerHTML = `
            <button onclick="Modal.close('izin-detay-modal')" class="pwa-btn pwa-btn--ghost w-full">Kapat</button>
        `;
        }
    }

    (function () {
        const st = document.createElement('style');
        st.textContent = '@keyframes izin-spin{to{transform:rotate(360deg)}}';
        document.head.appendChild(st);
    })();

    async function submitIzinTalebi(form) {
        const formData = Form.serialize(form);

        if (!Form.validate(form)) {
            Toast.show('Lütfen gerekli alanları doldurun', 'error');
            return;
        }

        // Kalan izin kontrolü
        const baslangic = formData.baslangic_tarihi;
        const bitis = formData.bitis_tarihi;

        if (!baslangic || !bitis) {
            Toast.show('Lütfen tarihleri seçiniz', 'error');
            return;
        }

        // Gün sayısını hesapla
        // Parse date values explicitly instead of relying entirely on native parser which can have timezone issues
        const baslangicD = new Date(baslangic);
        const bitisD = new Date(bitis);
        baslangicD.setHours(0, 0, 0, 0);
        bitisD.setHours(0, 0, 0, 0);

        const diff = bitisD - baslangicD;
        const toplamGun = Math.round(diff / (1000 * 60 * 60 * 24)) + 1;

        if (toplamGun <= 0) {
            Toast.show('Bitiş tarihi başlangıç tarihinden önce olamaz', 'error');
            return;
        }

        // Tek yıllık izin talebi en fazla 20 gün olabilir.
        const secilenIzinTuru = izinTurleri.find(tur => String(tur.id) === String(formData.izin_tipi));

        if (isYillikIzinTuru(secilenIzinTuru) && toplamGun > 20) {
            Toast.show('Yıllık izin talepleri en fazla 20 gün olabilir.', 'error');
            return;
        }

        if (secilenIzinTuru && isHakEdisGerektirenIzinTuru(secilenIzinTuru) && hakedisData && Number(hakedisData.kalan_izin) <= 0) {
            Toast.show('İzin hakedişiniz bulunmadığı için ücretli veya yıllık izin talep edemezsiniz. Lütfen mazeret izni seçiniz.', 'error');
            return;
        }

        /**izin türü mazeret ise açıklama zorunlu olmalıdır */
        if (secilenIzinTuru && isMazeretIzinTuru(secilenIzinTuru) && !formData.aciklama.trim()) {
            Toast.show('Lütfen açıklama giriniz', 'error');
            return;
        }

        // Hastalık izni seçildiyse sağlık raporu teyidi
        if (secilenIzinTuru && isHastalikIzinTuru(secilenIzinTuru)) {
            const onay = await Alert.confirm(
                'Sağlık Raporu Bildirimi',
                'Hastalık izni talebi için geçerli bir sağlık raporunuz olması zorunludur. Rapor olmadan hastalık izni seçilmemelidir. Geçerli bir sağlık raporunuz var mı?',
                'Evet, Raporum Var',
                'Vazgeç'
            );
            if (!onay) {
                return;
            }
        }

        const submitBtn = document.getElementById('izin-submit-btn');
        const submitHtml = submitBtn.innerHTML;
        if (submitBtn.disabled) return;
        submitBtn.disabled = true;
        submitBtn.style.opacity = '.85';
        submitBtn.innerHTML = '<span style="width:18px;height:18px;border:2.5px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;display:inline-block;animation:izin-spin .7s linear infinite"></span>Gönderiliyor...';

        try {
            const response = await API.request('createIzinTalebi', formData, false);

            if (response.success) {
                Toast.show('İzin talebiniz başarıyla gönderildi', 'success');
                Modal.close('izin-modal');
                form.reset();
                clearIzinRange();
                updateIzinTuruUyarilari('', false);
                loadIzinler(false);
                loadIzinStats(false);
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '';
            submitBtn.innerHTML = submitHtml;
        }
    }

    async function cancelIzin(id) {
        const isConfirmed = await Alert.confirm(
            'İptal Et',
            'Bu izin talebini iptal etmek istediğinize emin misiniz?',
            'Evet, İptal Et',
            'Vazgeç'
        );

        if (!isConfirmed) return;

        try {
            let response = { success: true };
            for (const tekId of String(id).split(',')) {
                response = await API.request('cancelIzinTalebi', { id: tekId });
                if (!response.success) break;
            }

            if (response.success) {
                Toast.show('İzin talebi iptal edildi', 'success');
                Modal.close('izin-detay-modal');
                loadIzinler();
                loadIzinStats();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }
</script>
