<?php
use App\Helper\Date;
use App\Model\TanimlamalarModel;
use App\Model\PersonelModel;

$Tanimlamalar = new TanimlamalarModel();
$Personel = new PersonelModel();
?>

<div class="px-3 py-2 space-y-3 pb-5">
    
    <!-- STICKY HEADER WRAPPER -->
    <div class="sticky top-0 z-30 bg-slate-50 dark:bg-slate-950 -mx-3 px-3 pt-2 pb-3 space-y-3 shadow-md shadow-slate-200/50 dark:shadow-none">
        
        <!-- Top Nav / Header Card -->
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined">analytics</span>
                    </div>
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-white leading-tight mb-0.5 text-sm">İşlem Özetleri</h2>
                        <p class="text-[11px] text-slate-500 font-medium tracking-wide">Personel Bazlı Performans</p>
                    </div>
                </div>
            </div>
            
            <!-- Filter Toggle Buttons -->
            <div class="flex gap-2 mt-1">
                <button id="btnFilterBugun" class="flex-[0.35] py-2 text-xs font-bold rounded-lg shadow-sm active:scale-95 transition-all bg-primary text-white shadow-primary/20" onclick="window.applyDateFilter('bugun')">Bugün</button>
                <div class="flex-[0.65] relative">
                    <input type="month" id="monthPickerInput" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="window.applyDateFilter('custom', this.value)" title="Geçmiş ayları seçmek için dokunun" onclick="if(currentFilterType !== 'buay') { window.applyDateFilter('custom', this.value); }" max="<?= date('Y-m') ?>" value="<?= date('Y-m') ?>">
                    <button id="btnFilterCustom" class="w-full h-full pointer-events-none flex flex-row items-center justify-between px-3 gap-1 py-1.5 text-xs font-bold rounded-lg shadow-sm transition-all bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-700">
                        <span id="customFilterLabel" class="truncate overflow-hidden w-full text-left">Bu Ay</span>
                        <span class="material-symbols-outlined text-[16px] text-slate-400 border-l border-slate-200 dark:border-slate-700 pl-2">calendar_month</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Scrollable Tabs -->
        <div class="overflow-x-auto hide-scrollbar -mx-3 px-3" id="raporTabsContainer">
            <div class="flex gap-2 min-w-max pb-1">
                <button class="rapor-tab bg-primary text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm shadow-primary/20 active:scale-95 transition-transform" data-tab="okuma">Endeks Okuma</button>
                <button class="rapor-tab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-xs font-bold shadow-sm active:scale-95 transition-transform" data-tab="kesme">Kesme/Açma</button>
                <button class="rapor-tab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-xs font-bold shadow-sm active:scale-95 transition-transform" data-tab="sokme_takma">Sayaç Sö/Ta</button>
                <button class="rapor-tab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-xs font-bold shadow-sm active:scale-95 transition-transform" data-tab="muhurleme">Mühürleme</button>
                <button class="rapor-tab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-xs font-bold shadow-sm active:scale-95 transition-transform" data-tab="kacakkontrol">Kaçak Kont.</button>
            </div>
        </div>

        <!-- Live Search -->
        <div class="relative px-1">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-[18px]">search</span>
            <input type="text" id="personelSearchInput" placeholder="Personel ara..." class="w-full pl-10 pr-4 py-3 bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-xl text-sm focus:border-primary focus:ring-1 focus:ring-primary/20 transition-all font-semibold shadow-sm placeholder:text-slate-400 placeholder:font-normal">
        </div>
    </div>

    <!-- Report Cards Container -->
    <div class="relative -mx-1 px-1">
        <div id="reportContent" class="pb-2">
            <div class="flex flex-col items-center justify-center p-12 text-slate-400">
                <div class="w-8 h-8 border-2 border-primary/30 border-t-primary rounded-full animate-spin mb-3"></div>
                <p class="text-xs font-semibold">Veriler getiriliyor...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Overlay -->
<div id="filterModalOverlay" class="fixed inset-0 bg-slate-900/50 dark:bg-black/60 z-[60] opacity-0 pointer-events-none transition-opacity duration-300" onclick="window.closePersonelDetailsModal()"></div>

<!-- User Detail Bottom Sheet -->
<div id="personelDetailModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[61] transform translate-y-full transition-transform duration-300 shadow-[0_-10px_40px_rgba(0,0,0,0.1)] safe-area-bottom max-h-[85vh] h-[85vh] flex flex-col w-full max-w-lg mx-auto">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between sticky top-0 bg-white/95 dark:bg-card-dark/95 backdrop-blur-sm z-10 shrink-0">
        <h3 class="font-bold text-slate-900 dark:text-white text-base truncate flex-grow" id="pd_name">Detay</h3>
        <button onclick="window.closePersonelDetailsModal()" class="w-8 h-8 flex items-center justify-center text-slate-400 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform shrink-0 ml-3">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    
    <div class="p-5 overflow-y-auto flex-grow" id="pd_content">
        <!-- Fetched content will go here -->
    </div>
</div>

<style>
/* Remove extra padding from the body wrapper for mobile */
.wrapper { padding-bottom: 0 !important; }
</style>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
let currentTab = 'okuma';
let currentFilterType = 'bugun'; // bugun, buay
let currentYear = '<?= date("Y") ?>';
let currentMonth = '<?= date("m") ?>';

// Formatting month name
const monthNames = {
    '01': 'Ocak', '02': 'Şubat', '03': 'Mart', '04': 'Nisan',
    '05': 'Mayıs', '06': 'Haziran', '07': 'Temmuz', '08': 'Ağustos',
    '09': 'Eylül', '10': 'Ekim', '11': 'Kasım', '12': 'Aralık'
};

window.applyDateFilter = function(opt, val = null) {
    const btnBugun = document.getElementById('btnFilterBugun');
    const btnCustom = document.getElementById('btnFilterCustom');
    const customLabel = document.getElementById('customFilterLabel');

    if (opt === 'bugun') {
        currentFilterType = 'bugun';
        
        btnBugun.className = 'flex-[0.35] py-2 text-xs font-bold rounded-lg shadow-sm active:scale-95 transition-all bg-primary text-white shadow-primary/20';
        btnCustom.className = 'w-full h-full pointer-events-none flex flex-row items-center justify-between px-3 gap-1 py-1.5 text-xs font-bold rounded-lg shadow-sm transition-all bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-700';
        customLabel.textContent = 'Ay Seç'; // Reset label
        
    } else if (opt === 'custom') {
        currentFilterType = 'buay'; // We send buay mode to the backend
        
        if (val) {
            const parts = val.split('-');
            currentYear = parts[0];
            currentMonth = parts[1];
        } else {
            const now = new Date();
            currentYear = String(now.getFullYear());
            currentMonth = String(now.getMonth() + 1).padStart(2, '0');
        }
        
        btnBugun.className = 'flex-[0.35] py-2 text-xs font-bold rounded-lg shadow-sm active:scale-95 transition-all bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-700';
        btnCustom.className = 'w-full h-full pointer-events-none flex flex-row items-center justify-between px-3 gap-1 py-1.5 text-xs font-bold rounded-lg shadow-sm transition-all bg-primary text-white shadow-primary/20';
        
        const now = new Date();
        const isCurrentBlock = currentYear == now.getFullYear() && parseInt(currentMonth) === (now.getMonth() + 1);
        let mName = monthNames[currentMonth] || currentMonth;
        customLabel.textContent = (isCurrentBlock ? 'Bu Ay' : mName + ' ' + currentYear);
    }
    
    loadMobileReport();
};

function setupLiveSearch() {
    const srch = document.getElementById('personelSearchInput');
    srch.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        const regions = document.querySelectorAll('#reportContent div.flex-col > div.border');
        
        regions.forEach(region => {
            let hasVisibleCard = false;
            const cards = region.querySelectorAll('.grid > div.group');
            cards.forEach(c => {
                const h4 = c.querySelector('h4');
                const h4text = h4 ? h4.innerText.toLowerCase() : '';
                if(val === '' || h4text.indexOf(val) !== -1) {
                    c.style.display = 'flex';
                    hasVisibleCard = true;
                } else {
                    c.style.display = 'none';
                }
            });
            
            // Hide region wrapper entirely if no card inside is visible
            region.style.display = hasVisibleCard ? 'block' : 'none';
        });
    });
}

function loadMobileReport() {
    const content = document.getElementById('reportContent');
    content.innerHTML = `
        <div class="flex flex-col items-center justify-center p-12 text-slate-400 h-full">
            <div class="w-8 h-8 border-2 border-primary/30 border-t-primary rounded-full animate-spin mb-4"></div>
            <p class="text-[11px] font-bold tracking-widest uppercase">Veriler Yükleniyor...</p>
        </div>
    `;
    
    const urlParams = new URLSearchParams();
    urlParams.append('action', 'get-mobile-report-cards');
    urlParams.append('tab', currentTab);
    urlParams.append('filter_type', currentFilterType);
    urlParams.append('year', currentYear);
    urlParams.append('month', currentMonth);
    
    fetch('../views/puantaj/api.php?' + urlParams.toString())
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
            // Clear search field after reload
            document.getElementById('personelSearchInput').value = '';
            
            // Extract and run scripts sequentially to ensure chart functions work
            const scripts = content.querySelectorAll("script");
            scripts.forEach((script) => {
                const newScript = document.createElement("script");
                if (script.src) {
                    newScript.src = script.src;
                } else {
                    newScript.textContent = script.textContent;
                }
                document.body.appendChild(newScript);
                document.body.removeChild(newScript);
            });
        })
        .catch(error => {
            console.error(error);
            content.innerHTML = `<div class="p-5 text-center text-rose-500 font-bold bg-rose-50 dark:bg-rose-900/20 rounded-xl my-4 mx-2 text-sm"><span class="material-symbols-outlined text-2xl mb-1 block">error</span> Bağlantı hatası oluştu. Lütfen tekrar deneyin.</div>`;
        });
}

// Tab Switching logic
document.querySelectorAll('.rapor-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.rapor-tab').forEach(b => {
            b.className = "rapor-tab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-xs font-bold shadow-sm active:scale-95 transition-transform";
        });
        
        this.className = "rapor-tab bg-primary text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm shadow-primary/20 active:scale-95 transition-transform";
        
        currentTab = this.getAttribute('data-tab');
        
        const container = document.getElementById('raporTabsContainer');
        const scrollLeft = this.offsetLeft - (container.offsetWidth / 2) + (this.offsetWidth / 2);
        container.scrollTo({ left: scrollLeft, behavior: 'smooth' });
        
        loadMobileReport();
    });
});

window.openPersonelMonthlyDetails = function(pId, pName, activeTabParam) {
    document.getElementById('pd_name').innerText = pName;
    document.getElementById('filterModalOverlay').classList.remove('pointer-events-none', 'opacity-0');
    document.getElementById('personelDetailModal').classList.remove('translate-y-full');
    
    const content = document.getElementById('pd_content');
    content.innerHTML = `
        <div class="flex flex-col items-center justify-center p-12 text-slate-400 h-full mt-10">
            <div class="w-8 h-8 border-2 border-primary/30 border-t-primary rounded-full animate-spin mb-3"></div>
            <p class="text-xs font-semibold">Kayıtlar taranıyor...</p>
        </div>
    `;
    
    const uParams = new URLSearchParams({
        action: 'get-mobile-personel-details',
        pId: pId,
        tab: activeTabParam,
        year: currentYear,
        month: currentMonth
    });
    
    fetch('../views/puantaj/api.php?' + uParams.toString())
        .then(response => response.text())
        .then(html => content.innerHTML = html)
        .catch(() => content.innerHTML = `<div class="p-5 text-center text-rose-500 text-sm font-bold bg-rose-50 rounded-xl">Hata oluştu, yüklenemedi.</div>`);
};

window.closePersonelDetailsModal = function() {
    document.getElementById('filterModalOverlay').classList.add('pointer-events-none', 'opacity-0');
    document.getElementById('personelDetailModal').classList.add('translate-y-full');
};

document.addEventListener('DOMContentLoaded', () => {
    setupLiveSearch();
    setTimeout(() => { loadMobileReport(); }, 100);
});
</script>
