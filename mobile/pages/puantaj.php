<?php
use App\Helper\Security;
use App\Model\TanimlamalarModel;
use App\Model\PersonelModel;
use App\Service\Gate;

// Yetki Kontrolü
$db = (new \App\Core\Db())->getConnection();
$userRoles = !empty($_SESSION['user']->roles) ? explode(',', $_SESSION['user']->roles) : [];
$hasReportPermission = false;

if (!empty($userRoles)) {
    $placeholders = implode(',', array_fill(0, count($userRoles), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_role_permissions WHERE role_id IN ($placeholders) AND permission_id = 61");
    $stmt->execute($userRoles);
    $hasReportPermission = ((int)$stmt->fetchColumn()) > 0;
}

if (!$hasReportPermission && !Gate::allows("puantaj_raporlama") && !Gate::allows("puantaj_yonetim") && !Gate::allows("puantaj/raporlar") && !Gate::allows("puantaj/list")) {
    echo '<div class="px-4 py-12 text-center">
        <div class="inline-flex w-16 h-16 rounded-full bg-rose-50 dark:bg-rose-900/20 text-rose-500 items-center justify-center mb-4">
            <span class="material-symbols-outlined text-3xl">gpp_maybe</span>
        </div>
        <h3 class="font-bold text-slate-800 dark:text-slate-200 text-base mb-1">Yetkisiz Erişim</h3>
        <p class="text-xs text-slate-500 leading-relaxed max-w-xs mx-auto mb-4">Puantaj sayfasını görüntülemek için yetkiniz bulunmamaktadır. Lütfen sistem yöneticinizle irtibata geçin.</p>
        <a href="?p=home" class="inline-flex items-center justify-center px-4 py-2 text-xs font-bold text-white bg-primary rounded-xl active:scale-95 transition-transform shadow-sm shadow-primary/20">Ana Sayfaya Dön</a>
    </div>';
    return;
}

$selectedYear  = date('Y');
$selectedMonth = date('m');
?>

<div class="px-3 pt-2 pb-36 space-y-3">

    <!-- STICKY HEADER & CONTROLS -->
    <div class="sticky top-0 z-30 bg-slate-50 dark:bg-slate-950 -mx-3 px-3 pt-2 pb-2 space-y-2 shadow-md shadow-slate-200/50 dark:shadow-none">
        
        <!-- Header & Ay/Yıl Gezinme -->
        <div class="bg-gradient-to-br from-emerald-600 via-emerald-600 to-teal-700 text-white rounded-2xl p-3.5 shadow-lg flex flex-col gap-3 border border-emerald-500/40 relative overflow-hidden">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
            <div class="flex items-center justify-between relative z-10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 backdrop-blur-md shadow-xs">
                        <span class="material-symbols-outlined text-[22px]">calendar_month</span>
                    </div>
                    <div>
                        <h1 class="font-extrabold text-white text-sm leading-tight tracking-tight">Personel Puantaj</h1>
                        <p class="text-[10px] text-emerald-100 font-medium tracking-wide">Aylık Puantaj ve İzin Yönetimi</p>
                    </div>
                </div>

                <!-- SGK Raporları Butonu (SGK Modalı Bottom Sheet Olarak Açılır) -->
                <button type="button" onclick="openSgkModal()" class="flex items-center gap-1.5 px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white border border-white/30 rounded-xl text-[11px] font-bold active:scale-95 transition-all shadow-xs backdrop-blur-md">
                    <span class="material-symbols-outlined text-[16px]">medical_services</span>
                    <span>SGK</span>
                </button>
            </div>

            <!-- Ay Seçici Gezinme Çubuğu -->
            <div class="flex items-center justify-between gap-1.5 bg-black/20 backdrop-blur-md p-1.5 rounded-xl border border-white/20 relative z-10">
                <button type="button" onclick="changeMonth(-1)" class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 text-white flex items-center justify-center shadow-xs active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-lg">chevron_left</span>
                </button>
                
                <div class="relative flex-1 text-center cursor-pointer">
                    <input type="text" id="monthPickerInput" class="absolute inset-0 opacity-0 w-full h-full cursor-pointer z-10">
                    <span id="currentMonthDisplay" class="font-black text-xs text-white uppercase tracking-wide">
                        <?= mb_strtoupper(date('F Y'), 'UTF-8') ?>
                    </span>
                    <span class="material-symbols-outlined text-[14px] text-emerald-200 align-middle ml-0.5">arrow_drop_down</span>
                </div>

                <button type="button" onclick="changeMonth(1)" class="w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 text-white flex items-center justify-center shadow-xs active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-lg">chevron_right</span>
                </button>
                <button type="button" onclick="resetToCurrentMonth()" class="px-2.5 py-1.5 rounded-lg bg-white text-emerald-800 text-[10px] font-extrabold active:scale-95 transition-transform shadow-sm">
                    BUGÜN
                </button>
            </div>

            <!-- Arama & Filtre Çubuğu -->
            <div class="space-y-2 relative z-10">
                <div class="flex items-center gap-2">
                    <!-- Personel Arama -->
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-emerald-100/80 text-[18px]">search</span>
                        <input type="text" id="puantajSearchInput" placeholder="Personel ara..." class="w-full pl-9 pr-3 py-2 bg-white/20 text-white placeholder-emerald-100/70 border border-white/25 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-white/40 font-medium">
                    </div>

                    <!-- Filtre Accordion Toggle -->
                    <button type="button" onclick="toggleFilterDrawer()" class="w-9 h-9 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 active:scale-95 transition-transform border border-white/25 backdrop-blur-md">
                        <span class="material-symbols-outlined text-[20px]">tune</span>
                    </button>
                </div>

                <!-- Detaylı Filtre Paneli (Gizlenebilir) -->
                <div id="filterDrawer" class="hidden bg-black/25 backdrop-blur-md p-3 rounded-xl border border-white/20 space-y-2.5">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-emerald-100 uppercase mb-1">Departman</label>
                            <select id="filterDepartman" onchange="loadPuantajData()" class="w-full py-1.5 px-2 bg-white/90 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-white/30 rounded-lg text-xs font-medium">
                                <option value="">Tüm Departmanlar</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-emerald-100 uppercase mb-1">Bölge</label>
                            <select id="filterBolge" onchange="loadPuantajData()" class="w-full py-1.5 px-2 bg-white/90 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-white/30 rounded-lg text-xs font-medium">
                                <option value="">Tüm Bölgeler</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="filterIskur" checked onchange="loadPuantajData()" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-white/40">
                            <span class="text-xs font-semibold text-emerald-50">İŞKUR Personelleri Dahil</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ÖZET İSTATİSTİK ŞERİDİ -->
        <div class="grid grid-cols-4 gap-1.5">
            <button type="button" id="cardStatPersonel" onclick="toggleCardFilter('all')" class="bg-white dark:bg-card-dark p-2 rounded-xl border border-slate-100 dark:border-slate-800 text-center shadow-xs active:scale-95 transition-all cursor-pointer">
                <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider">Personel</span>
                <span id="statPersonelCount" class="text-sm font-black text-slate-800 dark:text-white">0</span>
            </button>
            <button type="button" id="cardStatWork" onclick="toggleCardFilter('all')" class="bg-white dark:bg-card-dark p-2 rounded-xl border border-slate-100 dark:border-slate-800 text-center shadow-xs active:scale-95 transition-all cursor-pointer">
                <span class="block text-[9px] font-bold text-blue-500 uppercase tracking-wider">Çalışılan</span>
                <span id="statWorkDays" class="text-sm font-black text-blue-600 dark:text-blue-400">0</span>
            </button>
            <button type="button" id="cardStatLeave" onclick="toggleCardFilter('izinli')" class="bg-white dark:bg-card-dark p-2 rounded-xl border border-slate-100 dark:border-slate-800 text-center shadow-xs active:scale-95 transition-all cursor-pointer relative">
                <span class="block text-[9px] font-bold text-emerald-500 uppercase tracking-wider">İzinli</span>
                <span id="statLeaveDays" class="text-sm font-black text-emerald-600 dark:text-emerald-400">0</span>
            </button>
            <button type="button" id="cardStatSgk" onclick="toggleCardFilter('raporlu')" class="bg-white dark:bg-card-dark p-2 rounded-xl border border-slate-100 dark:border-slate-800 text-center shadow-xs active:scale-95 transition-all cursor-pointer relative">
                <span class="block text-[9px] font-bold text-rose-500 uppercase tracking-wider">Raporlu</span>
                <span id="statSgkDays" class="text-sm font-black text-rose-600 dark:text-rose-400">0</span>
            </button>
        </div>

    </div>

    <!-- MAIN CONTENT CONTAINER (PERSONEL LİSTESİ) -->
    <div id="puantajMainContent" class="relative min-h-[300px]">
        <!-- Loading Spinner -->
        <div id="puantajLoading" class="flex flex-col items-center justify-center py-16 text-slate-400">
            <div class="w-9 h-9 border-3 border-emerald-500/30 border-t-emerald-500 rounded-full animate-spin mb-3"></div>
            <p class="text-xs font-bold uppercase tracking-wider">Puantaj Verileri Yükleniyor...</p>
        </div>

        <!-- PERSONEL LİSTESİ -->
        <div id="personelListContainer" class="hidden space-y-2">
            <!-- Personnel cards rendered dynamically via JS -->
        </div>
    </div>

</div>

<!-- STICKY BOTTOM SAVE ACTION BAR (Alt Menünün Üstünde Yer Alır: bottom-20 z-50) -->
<div id="unsavedBottomBar" class="fixed bottom-20 left-3 right-3 z-50 bg-slate-900/95 dark:bg-slate-800/95 backdrop-blur-md text-white px-4 py-3 rounded-2xl shadow-2xl flex items-center justify-between border border-slate-700/50 transform translate-y-28 opacity-0 transition-all duration-300 pointer-events-none">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center font-black text-xs border border-amber-500/30 shadow-inner">
            <span id="unsavedCount">0</span>
        </div>
        <div>
            <h4 class="font-bold text-xs leading-tight">Kaydedilmemiş Değişiklik</h4>
            <p class="text-[10px] text-slate-400">Değişiklikler henüz kaydedilmedi.</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="discardUnsavedChanges()" class="px-3 py-2 text-xs font-bold text-slate-300 hover:text-white rounded-xl active:scale-95 transition-transform">
            Vazgeç
        </button>
        <!-- Doğrudan Kaydet Butonu -->
        <button type="button" onclick="savePuantajChanges()" class="px-4 py-2 text-xs font-extrabold text-white bg-emerald-500 rounded-xl shadow-md shadow-emerald-500/30 active:scale-95 transition-all flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px]">save</span>
            <span>Kaydet</span>
        </button>
    </div>
</div>

<!-- PERSONEL AY DETAY MODALI (NATIVE BOTTOM SHEET) -->
<div id="personelDetailModalOverlay" class="fixed inset-0 bg-slate-900/60 dark:bg-black/75 z-[60] opacity-0 pointer-events-none transition-opacity duration-300" onclick="closePersonelDetailModal()"></div>

<div id="personelDetailModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[61] transform translate-y-full opacity-0 pointer-events-none transition-all duration-300 shadow-2xl max-h-[92vh] h-[92vh] flex flex-col w-full max-w-lg mx-auto border-t border-slate-200/60 dark:border-slate-800">
    <!-- Drag Handle Bar -->
    <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-0.5 shrink-0"></div>

    <!-- Modal Header -->
    <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white/95 dark:bg-card-dark/95 backdrop-blur-sm z-10 shrink-0">
        <div class="flex items-center gap-3">
            <div id="detailPersonelAvatarContainer"></div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm" id="detailPersonelName">Personel Adı</h3>
                <p class="text-[11px] text-slate-500 font-semibold" id="detailPersonelDept">Departman</p>
            </div>
        </div>
        <button type="button" onclick="closePersonelDetailModal()" class="w-8 h-8 flex items-center justify-center text-slate-400 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>

    <div class="p-4 overflow-y-auto space-y-4 flex-grow pb-16">

        <!-- 1. HIZLI DAMGA (PUANTAJ TÜRLERİ PALETİ - ÜCRETLİ / ÜCRETSİZ SEKMELİ) -->
        <div class="bg-slate-50 dark:bg-slate-900/80 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Hızlı Damga (Puantaj Türleri)</span>
                <span id="modalStampActiveBadge" class="hidden text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 animate-pulse">
                    Damga Modu Aktif
                </span>
            </div>

            <!-- Ücretli / Ücretsiz Sekme Butonları -->
            <div class="flex items-center gap-1.5 p-1 bg-slate-200/70 dark:bg-slate-800 rounded-xl text-[11px] font-bold">
                <button type="button" id="btnPaletteUcretli" onclick="switchPaletteTab('ucretli')" class="flex-1 py-1.5 rounded-lg bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs font-black transition-all">
                    Ücretli
                </button>
                <button type="button" id="btnPaletteUcretsiz" onclick="switchPaletteTab('ucretsiz')" class="flex-1 py-1.5 rounded-lg text-slate-500 dark:text-slate-400 font-bold transition-all">
                    Ücretsiz
                </button>
            </div>
            
            <!-- Dynamic Palette List -->
            <div class="flex gap-1.5 overflow-x-auto hide-scrollbar pb-1 pt-0.5" id="modalTypePaletteContainer">
                <!-- Rendered via JS -->
            </div>

            <!-- Damga Modu Aktif Banner -->
            <div id="modalStampBanner" class="hidden bg-amber-500 text-white p-2.5 rounded-xl flex items-center justify-between shadow-md text-xs font-bold mt-1">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg animate-bounce">touch_app</span>
                    <span>Seçili Statü: <strong id="modalStampTypeName" class="underline">HT - Hafta Tatili</strong></span>
                </div>
                <button type="button" onclick="clearModalStampMode()" class="px-2 py-1 bg-black/20 hover:bg-black/40 rounded-lg text-[10px] font-extrabold uppercase tracking-wide">
                    İptal
                </button>
            </div>
        </div>

        <!-- 2. ANA ODAK NOKTASI: AY İÇİ GÜNLÜK TAKVİM (7 SÜTUNLU TAKVİM) -->
        <div class="bg-white dark:bg-slate-900/50 p-3 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="font-bold text-xs text-slate-900 dark:text-white flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-emerald-500 text-[18px]">calendar_view_month</span>
                    <span>Ay İçi Günlük Takvim</span>
                </h4>
                <span class="text-[10px] text-slate-400 font-semibold">Güne dokunarak düzenleyin</span>
            </div>
            
            <!-- Days Name Header (Pzt - Paz) -->
            <div class="grid grid-cols-7 gap-1 text-center font-bold text-[10px] text-slate-400 py-1 bg-slate-50 dark:bg-slate-800/80 rounded-xl">
                <div>Pzt</div>
                <div>Sal</div>
                <div>Çar</div>
                <div>Per</div>
                <div>Cum</div>
                <div class="text-amber-500">Cmt</div>
                <div class="text-rose-500">Paz</div>
            </div>

            <!-- Days Grid -->
            <div class="grid grid-cols-7 gap-1.5 pt-1" id="detailCalendarGrid">
                <!-- Rendered dynamically via JS -->
            </div>
        </div>

        <!-- 3. EK SEÇENEK: TARİH ARALIĞI STATÜ TANIMLA (KATLANABİLİR KART) -->
        <div class="bg-slate-50 dark:bg-slate-900/80 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            <button type="button" onclick="toggleRangeCard()" class="w-full p-3 flex items-center justify-between font-bold text-xs text-slate-800 dark:text-slate-200">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-500 text-[18px]">date_range</span>
                    <span>Tarih Aralığı İle Statü Tanımla</span>
                </div>
                <span class="material-symbols-outlined text-slate-400 text-lg transition-transform duration-200" id="rangeCardIcon">expand_more</span>
            </button>

            <div id="rangeCardBody" class="hidden p-3 pt-0 border-t border-slate-200/60 dark:border-slate-800 space-y-3">
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Başlangıç Günü</label>
                        <select id="rangeStartDay" class="w-full p-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <!-- Options generated dynamically -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Bitiş Günü</label>
                        <select id="rangeEndDay" class="w-full p-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200">
                            <!-- Options generated dynamically -->
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Statü / İzin Türü</label>
                    <select id="rangeStatusType" class="w-full p-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200">
                        <!-- Options generated dynamically -->
                    </select>
                </div>
                <button type="button" onclick="applyRangeStatus()" class="w-full py-2.5 bg-emerald-500 text-white font-extrabold text-xs rounded-xl shadow-md shadow-emerald-500/20 active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">check_circle</span>
                    <span>Aralığa Statüyü Uygula</span>
                </button>
            </div>
        </div>

    </div>
</div>

<!-- SGK RAPORLARI MODALI (NATIVE BOTTOM SHEET) -->
<div id="sgkModalOverlay" class="fixed inset-0 bg-slate-900/60 dark:bg-black/75 z-[64] opacity-0 pointer-events-none transition-opacity duration-300" onclick="closeSgkModal()"></div>

<div id="sgkModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[65] transform translate-y-full opacity-0 pointer-events-none transition-all duration-300 shadow-2xl max-h-[88vh] h-[88vh] flex flex-col w-full max-w-lg mx-auto border-t border-slate-200/60 dark:border-slate-800">
    <!-- Drag Handle Bar -->
    <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-0.5 shrink-0"></div>

    <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white/95 dark:bg-card-dark/95 backdrop-blur-sm z-10 shrink-0">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-lg">medical_services</span>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">SGK Vizite Raporları</h3>
                <p class="text-[10px] text-slate-500 font-semibold">Onaylı & Bekleyen Hastalık/İstirahat Raporları</p>
            </div>
        </div>
        <button type="button" onclick="closeSgkModal()" class="w-8 h-8 flex items-center justify-center text-slate-400 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>

    <div class="p-4 overflow-y-auto space-y-3 flex-grow pb-12" id="sgkModalBody">
        <!-- Rendered via JS -->
    </div>
</div>


<!-- JAVASCRIPT APP LOGIC -->
<script>
(function() {
    // App State
    let currentYear = parseInt('<?= $selectedYear ?>');
    let currentMonth = parseInt('<?= $selectedMonth ?>');
    
    let rawCalendarData = [];
    let definitions = { ucretli: [], ucretsiz: [], departmanlar: [], bolgeler: [] };
    let typeMapByCode = {};
    let typeMapById = {};
    
    let unsavedChanges = {}; // Key: "personelId_dateStr", Value: { personel_id, type_id, date, baslangic_tarihi, bitis_tarihi }
    let modalStampActiveType = null; // Type object when Stamp Mode is active inside modal
    let currentPaletteTab = 'ucretli'; // 'ucretli' or 'ucretsiz'
    let activeCardFilter = null; // null, 'izinli', or 'raporlu'

    window.toggleCardFilter = function(type) {
        if (type === 'all') {
            activeCardFilter = null;
        } else if (activeCardFilter === type) {
            activeCardFilter = null;
        } else {
            activeCardFilter = type;
        }
        renderPersonelList();
    };

    function updateCardFilterStyles() {
        const cardLeave = document.getElementById('cardStatLeave');
        const cardSgk = document.getElementById('cardStatSgk');

        if (cardLeave) {
            if (activeCardFilter === 'izinli') {
                cardLeave.className = "bg-emerald-50 dark:bg-emerald-950/50 p-2 rounded-xl border-2 border-emerald-500 text-center shadow-md scale-[1.02] transition-all cursor-pointer relative ring-2 ring-emerald-500/30";
            } else {
                cardLeave.className = "bg-white dark:bg-card-dark p-2 rounded-xl border border-slate-100 dark:border-slate-800 text-center shadow-xs active:scale-95 transition-all cursor-pointer relative";
            }
        }

        if (cardSgk) {
            if (activeCardFilter === 'raporlu') {
                cardSgk.className = "bg-rose-50 dark:bg-rose-950/50 p-2 rounded-xl border-2 border-rose-500 text-center shadow-md scale-[1.02] transition-all cursor-pointer relative ring-2 ring-rose-500/30";
            } else {
                cardSgk.className = "bg-white dark:bg-card-dark p-2 rounded-xl border border-slate-100 dark:border-slate-800 text-center shadow-xs active:scale-95 transition-all cursor-pointer relative";
            }
        }
    }

    const monthNamesTR = [
        'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
        'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
    ];

    const dayShortNamesTR = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];

    // SweetAlert2 Custom Styling Config
    const customSwalConfig = {
        customClass: {
            popup: 'rounded-[28px] p-6 shadow-2xl border border-slate-100 dark:border-slate-800 dark:bg-card-dark',
            title: 'text-base font-black text-slate-900 dark:text-white pt-2',
            htmlContainer: 'text-xs text-slate-500 font-medium py-1',
            confirmButton: 'px-5 py-2.5 rounded-2xl font-extrabold text-xs bg-slate-900 dark:bg-emerald-500 text-white shadow-md active:scale-95 transition-transform mx-1',
            cancelButton: 'px-5 py-2.5 rounded-2xl font-extrabold text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 active:scale-95 transition-transform mx-1'
        },
        buttonsStyling: false
    };

    // Resim Yolu Düzeltici Helper
    function getPersonelAvatarUrl(resim) {
        if (!resim || typeof resim !== 'string' || resim.trim() === '' || resim.includes('user-dummy-img')) {
            return '';
        }
        const r = resim.trim();
        if (r.startsWith('http://') || r.startsWith('https://') || r.startsWith('data:')) {
            return r;
        }
        const cleanPath = r.replace(/^(\.\.\/|\/)+/, '');
        return '../' + cleanPath;
    }

    // Departman Renk Paleti ve Resolver'ı
    const deptColorPalette = [
        { bg: 'bg-emerald-50 dark:bg-emerald-950/50', text: 'text-emerald-700 dark:text-emerald-300', border: 'border-emerald-200 dark:border-emerald-800/50' },
        { bg: 'bg-blue-50 dark:bg-blue-950/50',       text: 'text-blue-700 dark:text-blue-300',       border: 'border-blue-200 dark:border-blue-800/50' },
        { bg: 'bg-purple-50 dark:bg-purple-950/50',   text: 'text-purple-700 dark:text-purple-300',   border: 'border-purple-200 dark:border-purple-800/50' },
        { bg: 'bg-amber-50 dark:bg-amber-950/50',     text: 'text-amber-700 dark:text-amber-300',     border: 'border-amber-200 dark:border-amber-800/50' },
        { bg: 'bg-indigo-50 dark:bg-indigo-950/50',   text: 'text-indigo-700 dark:text-indigo-300',   border: 'border-indigo-200 dark:border-indigo-800/50' },
        { bg: 'bg-rose-50 dark:bg-rose-950/50',       text: 'text-rose-700 dark:text-rose-300',       border: 'border-rose-200 dark:border-rose-800/50' },
        { bg: 'bg-teal-50 dark:bg-teal-950/50',       text: 'text-teal-700 dark:text-teal-300',       border: 'border-teal-200 dark:border-teal-800/50' },
        { bg: 'bg-sky-50 dark:bg-sky-950/50',         text: 'text-sky-700 dark:text-sky-300',         border: 'border-sky-200 dark:border-sky-800/50' },
        { bg: 'bg-violet-50 dark:bg-violet-950/50',   text: 'text-violet-700 dark:text-violet-300',   border: 'border-violet-200 dark:border-violet-800/50' },
        { bg: 'bg-pink-50 dark:bg-pink-950/50',       text: 'text-pink-700 dark:text-pink-300',       border: 'border-pink-200 dark:border-pink-800/50' },
    ];

    function getDepartmentBadgeStyle(deptName) {
        if (!deptName) return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-200';
        let hash = 0;
        for (let i = 0; i < deptName.length; i++) {
            hash = deptName.charCodeAt(i) + ((hash << 5) - hash);
        }
        const index = Math.abs(hash) % deptColorPalette.length;
        const c = deptColorPalette[index];
        return `${c.bg} ${c.text} border ${c.border}`;
    }

    // Masaüstü ve Veritabanındaki Canlı Renk Haritası Resolver'ı
    function resolveColor(code, dbColor) {
        const uc = (code || 'X').toUpperCase();
        
        if (dbColor && typeof dbColor === 'string' && dbColor.startsWith('#') && dbColor.length >= 4) {
            const lc = dbColor.toLowerCase();
            if (lc !== '#ffffff' && lc !== '#fff' && lc !== '#f46a6a' && lc !== '#556ee6') {
                return dbColor;
            }
        }

        if (dbColor && typeof dbColor === 'string') {
            const c = dbColor.toLowerCase();
            if (c.includes('blue')) return '#2563eb';
            if (c.includes('amber') || c.includes('yellow')) return '#d97706';
            if (c.includes('green') || c.includes('emerald')) return '#059669';
            if (c.includes('red') || c.includes('rose')) return '#dc2626';
            if (c.includes('purple')) return '#7c3aed';
            if (c.includes('violet')) return '#6d28d9';
            if (c.includes('pink')) return '#db2777';
            if (c.includes('cyan')) return '#0891b2';
            if (c.includes('indigo')) return '#4f46e5';
            if (c.includes('teal')) return '#0f766e';
            if (c.includes('sky')) return '#0284c7';
        }

        const defaults = {
            'X':   '#2563eb', // Çalışılan Gün (Mavi)
            'HT':  '#d97706', // Hafta Tatili (Turuncu/Amber)
            'Yİ':  '#059669', // Yıllık İzin (Yeşil)
            'Üİ':  '#7c3aed', // Ücretsiz İzin (Mor)
            'SGK': '#dc2626', // Raporlu (Kırmızı)
            'RP':  '#dc2626', // Raporlu (Kırmızı)
            'Mİ':  '#0891b2', // Mazeret İzni (Siyan)
            'Eİ':  '#4f46e5', // Evlilik İzni (İndigo)
            'HTÇ': '#b45309', // Hafta Tatili Çalışması (Koyu Amber)
            'RTÇ': '#0f766e', // Resmi Tatil Çalışması (Koyu Teal)
            'Bİ':  '#6d28d9', // Ücretli İzin (Koyu Mor)
            'D':   '#b91c1c', // Devamsız (Bordo/Kırmızı)
            'UZ':  '#0284c7', // Uzaktan Çalışma (Açık Mavi)
        };

        return defaults[uc] || '#2563eb';
    }

    function getBadgeStyle(code, dbColor) {
        const hex = resolveColor(code, dbColor);
        return `background-color: ${hex} !important; color: #ffffff !important; font-weight: 800 !important; border: 1px solid ${hex}; text-shadow: 0 1px 2px rgba(0,0,0,0.3);`;
    }

    // DOM Elements
    const monthPickerInput = document.getElementById('monthPickerInput');
    const currentMonthDisplay = document.getElementById('currentMonthDisplay');
    const searchInput = document.getElementById('puantajSearchInput');
    const filterDepartman = document.getElementById('filterDepartman');
    const filterBolge = document.getElementById('filterBolge');
    const filterIskur = document.getElementById('filterIskur');
    const personelListContainer = document.getElementById('personelListContainer');
    const puantajLoading = document.getElementById('puantajLoading');

    const unsavedBottomBar = document.getElementById('unsavedBottomBar');
    const unsavedCountEl = document.getElementById('unsavedCount');

    // Initialize Flatpickr Month Picker
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#monthPickerInput', {
            locale: 'tr',
            plugins: [
                new monthSelectPlugin({
                    shorthand: false,
                    dateFormat: "Y-m",
                    altFormat: "F Y",
                    theme: "light"
                })
            ],
            onChange: function(selectedDates, dateStr) {
                if (dateStr) {
                    const parts = dateStr.split('-');
                    currentYear = parseInt(parts[0]);
                    currentMonth = parseInt(parts[1]);
                    updateMonthDisplay();
                    loadPuantajData();
                }
            }
        });
    }

    // Helper functions
    function padZero(num) {
        return num < 10 ? '0' + num : '' + num;
    }

    function getDaysInMonth(year, month) {
        return new Date(year, month, 0).getDate();
    }

    function updateMonthDisplay() {
        const str = monthNamesTR[currentMonth - 1] + ' ' + currentYear;
        currentMonthDisplay.textContent = str.toUpperCase();
    }

    window.changeMonth = function(delta) {
        if (checkUnsavedBeforeNavigate()) return;

        currentMonth += delta;
        if (currentMonth > 12) {
            currentMonth = 1;
            currentYear++;
        } else if (currentMonth < 1) {
            currentMonth = 12;
            currentYear--;
        }
        updateMonthDisplay();
        loadPuantajData();
    };

    window.resetToCurrentMonth = function() {
        if (checkUnsavedBeforeNavigate()) return;

        const now = new Date();
        currentYear = now.getFullYear();
        currentMonth = now.getMonth() + 1;
        updateMonthDisplay();
        loadPuantajData();
    };

    function checkUnsavedBeforeNavigate() {
        if (Object.keys(unsavedChanges).length > 0) {
            Swal.fire({
                ...customSwalConfig,
                icon: 'warning',
                title: 'Kaydedilmemiş Değişiklikler Var',
                text: 'Sayfadan ayrılırsanız yaptığınız puantaj değişiklikleri kaybolacaktır. Devam etmek istiyor musunuz?',
                showCancelButton: true,
                confirmButtonText: 'Evet, Devam Et',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    unsavedChanges = {};
                    updateUnsavedBar();
                    loadPuantajData();
                }
            });
            return true;
        }
        return false;
    }

    window.toggleFilterDrawer = function() {
        const drawer = document.getElementById('filterDrawer');
        drawer.classList.toggle('hidden');
    };

    window.toggleRangeCard = function() {
        const body = document.getElementById('rangeCardBody');
        const icon = document.getElementById('rangeCardIcon');
        body.classList.toggle('hidden');
        if (body.classList.contains('hidden')) {
            icon.style.transform = 'rotate(0deg)';
        } else {
            icon.style.transform = 'rotate(180deg)';
        }
    };

    // Live Search
    searchInput.addEventListener('input', function() {
        renderPersonelList();
    });

    // Load Definitions
    function loadDefinitions() {
        fetch('../views/personel/api/puantaj_izin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get-definitions'
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                definitions = res.data;
                populateFilterDropdowns();
                buildTypeMaps();
            }
        })
        .catch(err => console.error('Definitions load error:', err));
    }

    function populateFilterDropdowns() {
        filterDepartman.innerHTML = '<option value="">Tüm Departmanlar</option>';
        (definitions.departmanlar || []).forEach(d => {
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            filterDepartman.appendChild(opt);
        });

        filterBolge.innerHTML = '<option value="">Tüm Bölgeler</option>';
        (definitions.bolgeler || []).forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = b;
            filterBolge.appendChild(opt);
        });
    }

    function buildTypeMaps() {
        typeMapByCode = {};
        typeMapById = {};

        const all = [...(definitions.ucretli || []), ...(definitions.ucretsiz || [])];
        all.forEach(t => {
            const code = (t.kisa_kod || t.tur_adi.substr(0,2)).toUpperCase();
            const hex = resolveColor(code, t.renk);
            const typeObj = {
                id: t.id,
                name: t.tur_adi,
                code: code,
                color: hex
            };
            typeMapByCode[code] = typeObj;
            typeMapById[t.id] = typeObj;
        });

        // Fallbacks
        if (!typeMapByCode['X']) typeMapByCode['X'] = { id: 0, name: 'Çalışılan Gün', code: 'X', color: '#2563eb' };
        if (!typeMapByCode['HT']) typeMapByCode['HT'] = { id: 0, name: 'Hafta Tatili', code: 'HT', color: '#d97706' };
    }

    // Load Main Puantaj Data
    window.loadPuantajData = function() {
        puantajLoading.classList.remove('hidden');
        personelListContainer.classList.add('hidden');

        const params = new URLSearchParams();
        params.append('action', 'get-calendar-data');
        params.append('ay', padZero(currentMonth));
        params.append('yil', currentYear);
        params.append('departman', filterDepartman.value);
        params.append('bolge', filterBolge.value);
        params.append('iskur_dahil', filterIskur.checked ? '1' : '0');

        fetch('../views/personel/api/puantaj_izin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(r => r.json())
        .then(res => {
            puantajLoading.classList.add('hidden');
            if (res.status === 'success') {
                rawCalendarData = res.data || [];
                renderPersonelList();
            } else {
                Swal.fire({
                    ...customSwalConfig,
                    icon: 'error',
                    title: 'Hata',
                    text: res.message || 'Veriler alınamadı.'
                });
            }
        })
        .catch(err => {
            console.error(err);
            puantajLoading.classList.add('hidden');
            Swal.fire({
                ...customSwalConfig,
                icon: 'error',
                title: 'Bağlantı Hatası',
                text: 'Veriler yüklenirken sunucu hatası oluştu.'
            });
        });
    };

    function getEffectiveEntry(personelId, dateStr, originalEntries) {
        const key = `${personelId}_${dateStr}`;
        if (unsavedChanges[key]) {
            const typeId = unsavedChanges[key].type_id;
            if (!typeId) {
                const d = new Date(dateStr);
                const isSun = d.getDay() === 0;
                return isSun 
                    ? { kisa_kod: 'HT', name: 'Hafta Tatili', color: '#d97706', type: 'default', tip_id: 0 }
                    : { kisa_kod: 'X', name: 'Çalışılan Gün', color: '#2563eb', type: 'default', tip_id: 0 };
            }
            const typeObj = typeMapById[typeId] || { kisa_kod: 'İZN', name: 'İzin', color: '#059669' };
            return {
                kisa_kod: typeObj.code,
                name: typeObj.name,
                color: typeObj.color,
                type: 'custom',
                tip_id: typeId
            };
        }

        if (originalEntries && originalEntries.length > 0) {
            const orig = originalEntries[0];
            return {
                ...orig,
                color: resolveColor(orig.kisa_kod, orig.color)
            };
        }

        const d = new Date(dateStr);
        const isSun = d.getDay() === 0;
        return isSun 
            ? { kisa_kod: 'HT', name: 'Hafta Tatili', color: '#d97706', type: 'default', tip_id: 0 }
            : { kisa_kod: 'X', name: 'Çalışılan Gün', color: '#2563eb', type: 'default', tip_id: 0 };
    }

    // Render Personnel Clean List
    function renderPersonelList() {
        const searchTerm = (searchInput.value || '').toLowerCase().trim();
        const daysInMonth = getDaysInMonth(currentYear, currentMonth);

        const baseFilteredData = rawCalendarData.filter(p => {
            if (!searchTerm) return true;
            return (p.adi_soyadi || '').toLowerCase().includes(searchTerm) ||
                   (p.tc_kimlik_no || '').includes(searchTerm) ||
                   (p.departman || '').toLowerCase().includes(searchTerm);
        });

        // Update Stat Counters on base filtered dataset
        updateStats(baseFilteredData);
        updateCardFilterStyles();

        // Step 2: Apply card filter (izinli / raporlu)
        const finalData = baseFilteredData.filter(p => {
            if (!activeCardFilter) return true;

            let leaveDays = 0;
            let sgkDays = 0;

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${currentYear}-${padZero(currentMonth)}-${padZero(d)}`;
                const entry = getEffectiveEntry(p.id, dateStr, p.entries[dateStr]);
                const code = (entry.kisa_kod || 'X').toUpperCase();

                if (code === 'SGK' || code === 'RP') {
                    sgkDays++;
                } else if (code !== 'X' && code !== 'HTÇ' && code !== 'RTÇ' && code !== 'HT' && code !== '') {
                    leaveDays++;
                }
            }

            if (activeCardFilter === 'izinli') {
                return leaveDays > 0;
            } else if (activeCardFilter === 'raporlu') {
                return sgkDays > 0;
            }
            return true;
        });

        personelListContainer.innerHTML = '';

        if (activeCardFilter) {
            const isLeave = activeCardFilter === 'izinli';
            const filterBg = isLeave 
                ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-300' 
                : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800/50 text-rose-700 dark:text-rose-300';
            
            const banner = document.createElement('div');
            banner.className = `flex items-center justify-between border px-3 py-2 rounded-xl text-xs font-bold ${filterBg} mb-2 shadow-xs`;
            banner.innerHTML = `
                <span class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base">filter_alt</span>
                    <span>${isLeave ? 'İzinli Personeller Filtrelendi' : 'Raporlu Personeller Filtrelendi'} (${finalData.length} Personel)</span>
                </span>
                <button type="button" onclick="toggleCardFilter('all')" class="text-[11px] font-black underline active:scale-95">
                    Filtreyi Temizle
                </button>
            `;
            personelListContainer.appendChild(banner);
        }

        if (finalData.length === 0) {
            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'bg-white dark:bg-card-dark p-8 rounded-2xl text-center text-slate-400 border border-slate-100 dark:border-slate-800';
            emptyMsg.innerHTML = `
                <span class="material-symbols-outlined text-4xl mb-1 text-slate-300">group_off</span>
                <p class="text-xs font-bold">Filtreye uygun personel bulunamadı.</p>
            `;
            personelListContainer.appendChild(emptyMsg);
            personelListContainer.classList.remove('hidden');
            return;
        }

        finalData.forEach(p => {
            let workedCount = 0;
            let leaveCount = 0;
            let sgkCount = 0;

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${currentYear}-${padZero(currentMonth)}-${padZero(d)}`;
                const entry = getEffectiveEntry(p.id, dateStr, p.entries[dateStr]);
                const code = (entry.kisa_kod || 'X').toUpperCase();

                if (code === 'X' || code === 'HTÇ' || code === 'RTÇ') {
                    workedCount++;
                } else if (code === 'SGK' || code === 'RP') {
                    sgkCount++;
                } else if (code !== 'HT') {
                    leaveCount++;
                }
            }

            const pct = Math.round((workedCount / daysInMonth) * 100);
            const initials = getInitials(p.adi_soyadi);
            const avatarUrl = getPersonelAvatarUrl(p.resim);

            const card = document.createElement('div');
            card.className = 'bg-white dark:bg-card-dark p-3.5 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-xs hover:shadow-md transition-all cursor-pointer active:scale-[0.99] flex items-center justify-between gap-3';
            card.onclick = function() {
                openPersonelDetailModal(p.id);
            };

            card.innerHTML = `
                <div class="flex items-center gap-3 shrink-0 flex-1 min-w-0">
                    ${avatarUrl ? `
                        <img src="${avatarUrl}" class="w-11 h-11 rounded-full object-cover border-2 border-emerald-500/20 shrink-0" onerror="this.onerror=null; this.outerHTML='<div class=\\'w-11 h-11 rounded-full bg-emerald-500/10 text-emerald-600 font-black text-sm flex items-center justify-center shrink-0 border border-emerald-500/20\\'>${initials}</div>';" alt="">
                    ` : `
                        <div class="w-11 h-11 rounded-full bg-emerald-500/10 text-emerald-600 font-black text-sm flex items-center justify-center shrink-0 border border-emerald-500/20">
                            ${initials}
                        </div>
                    `}
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-slate-900 dark:text-white text-xs leading-tight truncate mb-0.5">${escapeHtml(p.adi_soyadi)}</h3>
                        <div class="flex items-center gap-1.5 text-[10px] text-slate-400 font-medium truncate">
                            ${p.departman ? `<span class="font-extrabold px-1.5 py-0.2 rounded ${getDepartmentBadgeStyle(p.departman)}">${escapeHtml(p.departman)}</span>` : ''}
                            <span>TC: ${p.tc_kimlik_no ? escapeHtml(p.tc_kimlik_no) : '—'}</span>
                            ${leaveCount > 0 ? `<span class="px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 font-bold">${leaveCount} İzin</span>` : ''}
                            ${sgkCount > 0 ? `<span class="px-1.5 py-0.2 rounded bg-rose-50 text-rose-600 dark:bg-rose-900/30 font-bold">${sgkCount} Rapor</span>` : ''}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <div class="text-right">
                        <div class="text-xs font-black text-emerald-600 dark:text-emerald-400 leading-none mb-1">${workedCount} / ${daysInMonth} Gün</div>
                        <div class="w-16 bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden ml-auto">
                            <div class="bg-emerald-500 h-full rounded-full" style="width: ${pct}%"></div>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-slate-300 text-lg">chevron_right</span>
                </div>
            `;
            personelListContainer.appendChild(card);
        });

        personelListContainer.classList.remove('hidden');
    }

    function getInitials(name) {
        if (!name) return '?';
        const parts = name.trim().split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return name.substr(0, 2).toUpperCase();
    }

    function updateStats(data) {
        let totalWorked = 0;
        let totalLeave = 0;
        let totalSgk = 0;
        const totalDays = getDaysInMonth(currentYear, currentMonth);

        data.forEach(p => {
            for (let d = 1; d <= totalDays; d++) {
                const dateStr = `${currentYear}-${padZero(currentMonth)}-${padZero(d)}`;
                const entry = getEffectiveEntry(p.id, dateStr, p.entries[dateStr]);
                const code = (entry.kisa_kod || '').toUpperCase();

                if (code === 'X' || code === 'HTÇ' || code === 'RTÇ') {
                    totalWorked++;
                } else if (code === 'SGK' || code === 'RP') {
                    totalSgk++;
                } else if (code !== 'HT' && code !== '') {
                    totalLeave++;
                }
            }
        });

        document.getElementById('statPersonelCount').textContent = data.length;
        document.getElementById('statWorkDays').textContent = totalWorked;
        document.getElementById('statLeaveDays').textContent = totalLeave;
        document.getElementById('statSgkDays').textContent = totalSgk;
    }

    function applyStatusToCell(personelId, dateStr, typeId) {
        const key = `${personelId}_${dateStr}`;
        unsavedChanges[key] = {
            personel_id: personelId,
            type_id: typeId,
            date: dateStr,
            baslangic_tarihi: dateStr,
            bitis_tarihi: dateStr
        };

        updateUnsavedBar();
        renderPersonelList();
    }

    function updateUnsavedBar() {
        const count = Object.keys(unsavedChanges).length;
        unsavedCountEl.textContent = count;

        if (count > 0) {
            unsavedBottomBar.classList.remove('translate-y-28', 'opacity-0', 'pointer-events-none');
        } else {
            unsavedBottomBar.classList.add('translate-y-28', 'opacity-0', 'pointer-events-none');
        }
    }

    window.discardUnsavedChanges = function() {
        unsavedChanges = {};
        updateUnsavedBar();
        renderPersonelList();
        if (selectedDetailPersonel) {
            renderModalCalendarGrid(selectedDetailPersonel);
        }
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'Değişiklikler sıfırlandı.',
            showConfirmButton: false,
            timer: 2000
        });
    };

    // Kaydet Butonuna Tıklanınca Doğrudan Kaydet
    window.savePuantajChanges = function() {
        const rawData = Object.values(unsavedChanges);
        if (rawData.length === 0) return;

        Swal.fire({
            ...customSwalConfig,
            title: 'Kaydediliyor...',
            html: '<div class="flex flex-col items-center justify-center py-4"><div class="w-8 h-8 border-3 border-emerald-500/30 border-t-emerald-500 rounded-full animate-spin mb-2"></div><p class="text-xs font-bold text-slate-500">Değişiklikler veritabanına işleniyor...</p></div>',
            showConfirmButton: false,
            allowOutsideClick: false
        });

        fetch('../views/personel/api/puantaj_izin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'save-bulk-entries',
                data: JSON.stringify(rawData),
                ay: padZero(currentMonth),
                yil: currentYear
            }).toString()
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                unsavedChanges = {};
                updateUnsavedBar();

                Swal.fire({
                    ...customSwalConfig,
                    icon: 'success',
                    title: 'İşlem Başarılı',
                    text: res.message || 'Puantaj değişiklikleri kaydedildi.',
                    timer: 2000,
                    showConfirmButton: false
                });

                loadPuantajData();
                if (selectedDetailPersonel) {
                    renderModalCalendarGrid(selectedDetailPersonel);
                }
            } else {
                Swal.fire({
                    ...customSwalConfig,
                    icon: 'error',
                    title: 'Hata',
                    text: res.message || 'Kayıt sırasında hata oluştu.'
                });
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                ...customSwalConfig,
                icon: 'error',
                title: 'Bağlantı Hatası',
                text: 'Sunucu ile iletişim kurulamadı.'
            });
        });
    };

    // PERSONEL AY DETAY MODALI LOGIC (NATIVE BOTTOM SHEET)
    window.openPersonelDetailModal = function(personelId) {
        const p = rawCalendarData.find(x => x.id == personelId);
        if (!p) return;

        selectedDetailPersonel = p;

        document.getElementById('detailPersonelName').textContent = p.adi_soyadi;
        
        const deptBadge = p.departman ? `<span class="font-extrabold px-2 py-0.5 rounded text-[10px] ${getDepartmentBadgeStyle(p.departman)}">${escapeHtml(p.departman)}</span>` : '';
        const tcStr = p.tc_kimlik_no ? `TC: ${escapeHtml(p.tc_kimlik_no)}` : '';
        document.getElementById('detailPersonelDept').innerHTML = `${deptBadge} ${tcStr ? '<span class="text-slate-400 font-semibold ml-1.5">• ' + tcStr + '</span>' : ''}`;

        const avatarContainer = document.getElementById('detailPersonelAvatarContainer');
        const avatarUrl = getPersonelAvatarUrl(p.resim);

        if (avatarUrl) {
            avatarContainer.innerHTML = `<img src="${avatarUrl}" class="w-10 h-10 rounded-full object-cover border-2 border-emerald-500/30" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-600 font-black text-xs flex items-center justify-center border border-emerald-500/30\\'>${getInitials(p.adi_soyadi)}</div>';" alt="">`;
        } else {
            avatarContainer.innerHTML = `<div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-600 font-black text-xs flex items-center justify-center border border-emerald-500/30">${getInitials(p.adi_soyadi)}</div>`;
        }

        const daysInMonth = getDaysInMonth(currentYear, currentMonth);

        // Render Hızlı Damga Paleti (Ücretli / Ücretsiz Sekmeli)
        renderModalTypePalette();

        // Populate Start / End Day Selects
        const startSel = document.getElementById('rangeStartDay');
        const endSel = document.getElementById('rangeEndDay');
        startSel.innerHTML = '';
        endSel.innerHTML = '';

        for (let d = 1; d <= daysInMonth; d++) {
            startSel.appendChild(new Option(`${d} ${monthNamesTR[currentMonth - 1]}`, d));
            endSel.appendChild(new Option(`${d} ${monthNamesTR[currentMonth - 1]}`, d));
        }
        endSel.value = daysInMonth;

        // Populate Status Select
        const statusSel = document.getElementById('rangeStatusType');
        statusSel.innerHTML = '<option value="0">X - Çalışılan Gün (Sıfırla)</option>';
        const allTypes = [...(definitions.ucretli || []), ...(definitions.ucretsiz || [])];
        allTypes.forEach(t => {
            const code = (t.kisa_kod || t.tur_adi.substr(0,2)).toUpperCase();
            statusSel.appendChild(new Option(`${code} - ${t.tur_adi}`, t.id));
        });

        // Render Calendar Grid inside Modal
        renderModalCalendarGrid(p);

        const overlay = document.getElementById('personelDetailModalOverlay');
        const modal = document.getElementById('personelDetailModal');

        overlay.classList.remove('opacity-0', 'pointer-events-none');
        modal.classList.remove('translate-y-full', 'opacity-0', 'pointer-events-none');
        modal.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');
    };

    window.closePersonelDetailModal = function() {
        clearModalStampMode();

        const overlay = document.getElementById('personelDetailModalOverlay');
        const modal = document.getElementById('personelDetailModal');

        overlay.classList.add('opacity-0', 'pointer-events-none');
        modal.classList.add('translate-y-full', 'opacity-0', 'pointer-events-none');
        modal.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');

        selectedDetailPersonel = null;
    };

    window.switchPaletteTab = function(tab) {
        currentPaletteTab = tab;
        const btnUcretli = document.getElementById('btnPaletteUcretli');
        const btnUcretsiz = document.getElementById('btnPaletteUcretsiz');

        if (tab === 'ucretli') {
            btnUcretli.className = 'flex-1 py-1.5 rounded-lg bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs font-black transition-all';
            btnUcretsiz.className = 'flex-1 py-1.5 rounded-lg text-slate-500 dark:text-slate-400 font-bold transition-all';
        } else {
            btnUcretsiz.className = 'flex-1 py-1.5 rounded-lg bg-white dark:bg-slate-700 text-purple-600 dark:text-purple-400 shadow-xs font-black transition-all';
            btnUcretli.className = 'flex-1 py-1.5 rounded-lg text-slate-500 dark:text-slate-400 font-bold transition-all';
        }

        renderModalTypePalette();
    };

    function renderModalTypePalette() {
        const container = document.getElementById('modalTypePaletteContainer');
        container.innerHTML = '';

        let sourceList = [];
        if (currentPaletteTab === 'ucretli') {
            sourceList = [
                { id: 0, tur_adi: 'Çalışılan Gün', kisa_kod: 'X', renk: '#2563eb' },
                { id: 0, tur_adi: 'Hafta Tatili', kisa_kod: 'HT', renk: '#d97706' },
                ...(definitions.ucretli || [])
            ];
        } else {
            sourceList = [
                ...(definitions.ucretsiz || [])
            ];
        }

        const seenCodes = new Set();
        const typeList = [];
        sourceList.forEach(t => {
            const code = (t.kisa_kod || t.tur_adi.substr(0,2)).toUpperCase();
            if (!seenCodes.has(code)) {
                seenCodes.add(code);
                typeList.push(t);
            }
        });

        typeList.forEach(t => {
            const code = (t.kisa_kod || t.tur_adi.substr(0,2)).toUpperCase();
            const badgeStyle = getBadgeStyle(code, t.renk);

            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'modal-type-chip shrink-0 px-3 py-1.5 rounded-xl text-xs font-black flex items-center gap-1.5 active:scale-95 transition-all shadow-xs';
            chip.style.cssText = badgeStyle;
            chip.innerHTML = `<span>${code}</span>`;
            chip.title = t.tur_adi;
            chip.onclick = function() {
                toggleModalStampMode({ id: t.id, name: t.tur_adi, code: code, color: resolveColor(code, t.renk) }, chip);
            };
            container.appendChild(chip);
        });
    }

    function toggleModalStampMode(typeObj, chipEl) {
        if (modalStampActiveType && modalStampActiveType.code === typeObj.code) {
            clearModalStampMode();
            return;
        }

        modalStampActiveType = typeObj;
        const stampBanner = document.getElementById('modalStampBanner');
        const stampName = document.getElementById('modalStampTypeName');
        const stampBadge = document.getElementById('modalStampActiveBadge');

        stampName.textContent = `${typeObj.code} - ${typeObj.name}`;
        stampBanner.classList.remove('hidden');
        stampBadge.classList.remove('hidden');

        document.querySelectorAll('.modal-type-chip').forEach(c => {
            c.classList.remove('ring-4', 'ring-amber-400');
        });
        if (chipEl) {
            chipEl.classList.add('ring-4', 'ring-amber-400');
        }
    }

    window.clearModalStampMode = function() {
        modalStampActiveType = null;
        document.getElementById('modalStampBanner').classList.add('hidden');
        document.getElementById('modalStampActiveBadge').classList.add('hidden');
        document.querySelectorAll('.modal-type-chip').forEach(c => {
            c.classList.remove('ring-4', 'ring-amber-400');
        });
    };

    function renderModalCalendarGrid(p) {
        const daysInMonth = getDaysInMonth(currentYear, currentMonth);
        const grid = document.getElementById('detailCalendarGrid');
        grid.innerHTML = '';

        const firstDayObj = new Date(currentYear, currentMonth - 1, 1);
        let startDayOfWeek = firstDayObj.getDay();
        if (startDayOfWeek === 0) startDayOfWeek = 7;

        for (let i = 1; i < startDayOfWeek; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'h-14 rounded-xl bg-slate-50/40 dark:bg-slate-900/30 border border-dashed border-slate-200/40 dark:border-slate-800/40';
            grid.appendChild(emptyCell);
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${currentYear}-${padZero(currentMonth)}-${padZero(d)}`;
            const entry = getEffectiveEntry(p.id, dateStr, p.entries[dateStr]);
            const code = (entry.kisa_kod || 'X').toUpperCase();
            const badgeStyle = getBadgeStyle(code, entry.color);

            const dateObj = new Date(currentYear, currentMonth - 1, d);
            const isSunday = dateObj.getDay() === 0;

            const key = `${p.id}_${dateStr}`;
            const isUnsaved = !!unsavedChanges[key];
            const unsavedClass = isUnsaved ? 'ring-2 ring-amber-400 shadow-md' : '';
            const sunBgClass = isSunday ? 'bg-rose-50/80 dark:bg-rose-950/30 border-rose-200 dark:border-rose-900/50' : 'bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700/60';

            const item = document.createElement('div');
            item.className = `p-1 rounded-xl border ${sunBgClass} ${unsavedClass} text-center flex flex-col items-center justify-between cursor-pointer active:scale-90 transition-transform h-14 shadow-2xs`;
            item.innerHTML = `
                <span class="text-[10px] font-extrabold ${isSunday ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'}">${d}</span>
                <span class="w-full py-1 rounded-lg text-[11px] font-extrabold flex items-center justify-center shadow-xs" style="${badgeStyle}">
                    ${code}
                </span>
            `;
            item.onclick = function() {
                if (modalStampActiveType) {
                    applyStatusToCell(p.id, dateStr, modalStampActiveType.id);
                    renderModalCalendarGrid(p);
                } else {
                    cycleDayStatus(p.id, dateStr, code);
                    renderModalCalendarGrid(p);
                }
            };
            grid.appendChild(item);
        }
    }

    function cycleDayStatus(personelId, dateStr, currentCode) {
        let nextTypeId = 0;
        if (currentCode === 'X') {
            const htType = typeMapByCode['HT'];
            nextTypeId = htType ? htType.id : 0;
        } else if (currentCode === 'HT') {
            const yiType = typeMapByCode['Yİ'];
            nextTypeId = yiType ? yiType.id : 0;
        } else if (currentCode === 'Yİ') {
            const sgkType = typeMapByCode['SGK'] || typeMapByCode['RP'];
            nextTypeId = sgkType ? sgkType.id : 0;
        } else {
            nextTypeId = 0;
        }

        applyStatusToCell(personelId, dateStr, nextTypeId);
    }

    window.applyRangeStatus = function() {
        if (!selectedDetailPersonel) return;

        const startDay = parseInt(document.getElementById('rangeStartDay').value);
        const endDay = parseInt(document.getElementById('rangeEndDay').value);
        const typeId = parseInt(document.getElementById('rangeStatusType').value);

        if (startDay > endDay) {
            Swal.fire({
                ...customSwalConfig,
                icon: 'warning',
                title: 'Uyarı',
                text: 'Başlangıç günü bitiş gününden büyük olamaz.'
            });
            return;
        }

        for (let d = startDay; d <= endDay; d++) {
            const dateStr = `${currentYear}-${padZero(currentMonth)}-${padZero(d)}`;
            applyStatusToCell(selectedDetailPersonel.id, dateStr, typeId === 0 ? null : typeId);
        }

        renderModalCalendarGrid(selectedDetailPersonel);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `${startDay} - ${endDay} ${monthNamesTR[currentMonth - 1]} aralığına uygulandı.`,
            showConfirmButton: false,
            timer: 2000
        });
    };

    // SGK Vizite Raporları Modalı Logic (NATIVE BOTTOM SHEET)
    window.openSgkModal = function() {
        const overlay = document.getElementById('sgkModalOverlay');
        const modal = document.getElementById('sgkModal');
        const body = document.getElementById('sgkModalBody');

        body.innerHTML = `
            <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                <div class="w-8 h-8 border-2 border-rose-500/30 border-t-rose-500 rounded-full animate-spin mb-3"></div>
                <p class="text-xs font-bold uppercase tracking-wider">SGK Raporları Çekiliyor...</p>
            </div>
        `;

        overlay.classList.remove('opacity-0', 'pointer-events-none');
        modal.classList.remove('translate-y-full', 'opacity-0', 'pointer-events-none');
        modal.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');

        fetch('../views/personel/api/puantaj_izin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'get-sgk-onaylanmis-raporlar',
                ay: padZero(currentMonth),
                yil: currentYear
            }).toString()
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                renderSgkReports(res.data || []);
            } else {
                body.innerHTML = `<div class="p-4 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 rounded-2xl text-xs font-bold text-center">${escapeHtml(res.message || 'SGK verileri alınamadı.')}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = `<div class="p-4 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 rounded-2xl text-xs font-bold text-center">Sunucu bağlantı hatası oluştu.</div>`;
        });
    };

    function renderSgkReports(reports) {
        const body = document.getElementById('sgkModalBody');
        if (reports.length === 0) {
            body.innerHTML = `
                <div class="p-8 text-center text-slate-400 space-y-2">
                    <span class="material-symbols-outlined text-4xl text-slate-300">verified</span>
                    <p class="text-xs font-bold">Seçili ay için kayıtlarda SGK raporu bulunamadı.</p>
                </div>
            `;
            return;
        }

        let html = '<div class="space-y-2.5">';
        reports.forEach((r, idx) => {
            html += `
                <div class="bg-slate-50 dark:bg-slate-900 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-bold text-xs text-slate-900 dark:text-white">${escapeHtml(r.ad_soyad || 'Bilinmeyen Personel')}</h4>
                            <p class="text-[10px] text-slate-400">T.C: ${escapeHtml(r.tc_kimlik || '-')}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                            ${r.toplam_gun || 1} Gün
                        </span>
                    </div>

                    <div class="text-[11px] text-slate-600 dark:text-slate-300 font-medium flex items-center justify-between pt-1 border-t border-slate-200/60 dark:border-slate-800">
                        <span>Tarih: <strong>${escapeHtml(r.baslangic_raw || r.baslangic)} - ${escapeHtml(r.bitis_raw || r.bitis)}</strong></span>
                        <span class="text-[10px] text-slate-400">${escapeHtml(r.vaka_adi || 'Hastalık')}</span>
                    </div>

                    ${r.personel_id ? `
                        <button type="button" onclick="applySgkReportToPuantaj(${r.personel_id}, '${r.baslangic}', '${r.bitis}')" class="w-full mt-1 py-2 bg-emerald-500 text-white font-extrabold text-xs rounded-xl shadow-xs active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[15px]">add_task</span>
                            <span>Puantaja İşle (SGK)</span>
                        </button>
                    ` : `
                        <div class="text-[10px] text-rose-500 font-semibold italic text-center pt-1">Personel T.C. sistemde eşleşmedi</div>
                    `}
                </div>
            `;
        });
        html += '</div>';
        body.innerHTML = html;
    }

    window.applySgkReportToPuantaj = function(personelId, baslangic, bitis) {
        const sgkType = typeMapByCode['SGK'] || typeMapByCode['RP'] || { id: 5 };
        
        let start = new Date(baslangic);
        let end = new Date(bitis);

        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            const dateStr = d.toISOString().split('T')[0];
            applyStatusToCell(personelId, dateStr, sgkType.id);
        }

        closeSgkModal();
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'SGK Raporu puantaja yansıtıldı. Kaydetmeyi unutmayın!',
            showConfirmButton: false,
            timer: 3000
        });
    };

    window.closeSgkModal = function() {
        const overlay = document.getElementById('sgkModalOverlay');
        const modal = document.getElementById('sgkModal');

        overlay.classList.add('opacity-0', 'pointer-events-none');
        modal.classList.add('translate-y-full', 'opacity-0', 'pointer-events-none');
        modal.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Init App
    loadDefinitions();
    updateMonthDisplay();
    loadPuantajData();

})();
</script>
