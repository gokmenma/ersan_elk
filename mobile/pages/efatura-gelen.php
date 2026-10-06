<?php
\App\Service\Gate::authorizeOrDie('efatura/gelen-list');
use App\Model\EInvoiceModel;
use App\Helper\Security;

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
?>

<div class="px-3.5 py-4 space-y-3.5 pb-24">

    <!-- 1. Üst Başlık -->
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
            <a href="?p=efatura" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">arrow_back</span>
            </a>
            <div>
                <h1 class="text-base font-black text-slate-900 dark:text-white leading-tight">Gelen Faturalar</h1>
                <p class="text-[11px] font-medium text-slate-500">Tedarikçi ve alış faturaları</p>
            </div>
        </div>

        <button type="button" onclick="loadGelenInvoices()" class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-900/30 text-sky-600 flex items-center justify-center active:rotate-180 transition-transform">
            <span class="material-symbols-outlined text-xl">refresh</span>
        </button>
    </div>

    <!-- 2. Dönem Filtre Butonları (Varsayılan: Bu Ay) -->
    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5" id="gelenPeriodGroup">
        <button type="button" onclick="setGelenPeriod('this_month', this)" class="gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap">Bu Ay</button>
        <button type="button" onclick="setGelenPeriod('last_month', this)" class="gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Geçen Ay</button>
        <button type="button" onclick="setGelenPeriod('last_3_months', this)" class="gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Son 3 Ay</button>
        <button type="button" onclick="setGelenPeriod('this_year', this)" class="gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap"><?= date('Y') ?> Yılı</button>
        <button type="button" onclick="setGelenPeriod('all', this)" class="gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Tümü</button>
        <button type="button" onclick="openGelenPeriodModal()" class="gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap flex items-center gap-1" id="btnCustomGelenPeriod">
            <span class="material-symbols-outlined text-[15px] text-primary">calendar_month</span>
            <span id="customGelenPeriodLabel">Dönem Seç</span>
        </button>
    </div>

    <!-- 3. Özet Kartları -->
    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-sky-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Toplam Tutar</p>
            <p class="font-black text-slate-900 dark:text-white text-xs sm:text-sm mt-0.5 truncate" id="statGelenTotalAmount">0,00 ₺</p>
            <p class="text-[10px] text-slate-400 font-medium" id="statGelenTotalCount">0 Fatura</p>
        </div>

        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-emerald-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Kabul Edilen</p>
            <p class="font-black text-emerald-600 text-xs sm:text-sm mt-0.5" id="statGelenKabulCount">0</p>
            <p class="text-[10px] text-emerald-600/70 font-medium">Onaylı</p>
        </div>

        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-amber-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Yanıt Bekleyen</p>
            <p class="font-black text-amber-600 text-xs sm:text-sm mt-0.5" id="statGelenBekleyenCount">0</p>
            <p class="text-[10px] text-amber-600/70 font-medium">Bekliyor</p>
        </div>
    </div>

    <!-- 4. Arama Kutuları (Genel Arama & Ürün/Marka/Kalem Arama) -->
    <div class="space-y-2">
        <!-- Genel Arama -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="material-symbols-outlined text-slate-400 text-lg">search</span>
            </div>
            <input type="text" id="gelenSearchInput" placeholder="Fatura No, Tedarikçi Ünvanı veya VKN..." autocomplete="off"
                   class="w-full pl-9 pr-4 py-2.5 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-700 focus:border-primary focus:ring-0 rounded-xl shadow-xs text-xs text-slate-900 dark:text-white placeholder-slate-400">
        </div>

        <!-- Ürün / Marka / Kalem Arama -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="material-symbols-outlined text-purple-500 text-lg">inventory_2</span>
            </div>
            <input type="text" id="gelenProductSearchInput" placeholder="Fatura içeriğindeki Ürün / Marka / Kalem Ara..." autocomplete="off"
                   class="w-full pl-9 pr-8 py-2.5 bg-purple-50/40 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-900/50 focus:border-purple-500 focus:ring-0 rounded-xl shadow-xs text-xs text-slate-900 dark:text-white placeholder-purple-400">
            <button type="button" id="btnClearGelenProductSearch" onclick="clearGelenProductSearch()" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 hidden">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- Ticari Yanıt Filtreleri -->
        <div class="flex items-center gap-1 overflow-x-auto no-scrollbar py-0.5">
            <button type="button" onclick="filterGelenYanit('', this)" class="gelen-yanit-pill px-2.5 py-1 rounded-lg text-[11px] font-bold bg-primary text-white whitespace-nowrap">Tümü</button>
            <button type="button" onclick="filterGelenYanit('BEKLIYOR', this)" class="gelen-yanit-pill px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Bekleyen</button>
            <button type="button" onclick="filterGelenYanit('KABUL', this)" class="gelen-yanit-pill px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Kabul</button>
            <button type="button" onclick="filterGelenYanit('RED', this)" class="gelen-yanit-pill px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Red</button>
        </div>
    </div>

    <!-- 5. Fatura Kartları Listesi -->
    <div class="space-y-2.5" id="gelenInvoiceList">
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-sky-600">progress_activity</span>
            <p class="mt-2 font-bold">Faturalar yükleniyor...</p>
        </div>
    </div>
</div>

<!-- Fatura PDF Önizleme Modalı -->
<div id="mobPdfModal" class="fixed inset-0 z-[110] bg-slate-900/80 backdrop-blur-xs flex flex-col justify-end hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-t-[28px] w-full h-[90vh] flex flex-col shadow-2xl overflow-hidden transform translate-y-full transition-transform duration-300" id="mobPdfSheet">
        <div class="p-3.5 px-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sky-600 text-[22px]">description</span>
                <span class="text-sm font-black text-slate-900 dark:text-white" id="mobPdfTitle">Gelen Fatura</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="#" id="mobPdfDownloadBtn" target="_blank" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                </a>
                <button type="button" onclick="closeInvoicePreview()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
        </div>
        <div class="flex-1 bg-slate-100 relative">
            <iframe id="mobPdfFrame" class="w-full h-full border-0" src="about:blank"></iframe>
        </div>
    </div>
</div>

<script>
let currentGelenStartDate = '';
let currentGelenEndDate = '';
let currentGelenYanit = '';
let gelenSearchTimer = null;

const formatMoney = (v) => Number(v || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';

document.addEventListener('DOMContentLoaded', function() {
    // Varsayılan: Bu Ay
    setGelenPeriod('this_month', document.querySelector('#gelenPeriodGroup button'));

    // Arama dinleyicileri
    document.getElementById('gelenSearchInput').addEventListener('input', function() {
        clearTimeout(gelenSearchTimer);
        gelenSearchTimer = setTimeout(loadGelenInvoices, 350);
    });

    document.getElementById('gelenProductSearchInput').addEventListener('input', function() {
        const val = this.value.trim();
        const btnClear = document.getElementById('btnClearGelenProductSearch');
        if (val.length > 0) btnClear.classList.remove('hidden');
        else btnClear.classList.add('hidden');

        clearTimeout(gelenSearchTimer);
        gelenSearchTimer = setTimeout(loadGelenInvoices, 350);
    });
});

function setGelenPeriod(period, btn) {
    document.querySelectorAll('.gelen-period-btn').forEach(b => {
        b.className = 'gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    if (btn) {
        btn.className = 'gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap';
    }

    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const toIso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    if (period === 'this_month') {
        currentGelenStartDate = toIso(new Date(y, m, 1));
        currentGelenEndDate = toIso(new Date(y, m + 1, 0));
    } else if (period === 'last_month') {
        currentGelenStartDate = toIso(new Date(y, m - 1, 1));
        currentGelenEndDate = toIso(new Date(y, m, 0));
    } else if (period === 'last_3_months') {
        currentGelenStartDate = toIso(new Date(y, m - 2, 1));
        currentGelenEndDate = toIso(new Date(y, m + 1, 0));
    } else if (period === 'this_year') {
        currentGelenStartDate = toIso(new Date(y, 0, 1));
        currentGelenEndDate = toIso(new Date(y, 11, 31));
    } else if (period === 'all') {
        currentGelenStartDate = '';
        currentGelenEndDate = '';
    }

    loadGelenInvoices();
}

function filterGelenYanit(yanit, btn) {
    currentGelenYanit = yanit;
    document.querySelectorAll('.gelen-yanit-pill').forEach(b => {
        b.className = 'gelen-yanit-pill px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    btn.className = 'gelen-yanit-pill px-2.5 py-1 rounded-lg text-[11px] font-bold bg-primary text-white whitespace-nowrap';
    loadGelenInvoices();
}

function clearGelenProductSearch() {
    const inp = document.getElementById('gelenProductSearchInput');
    inp.value = '';
    document.getElementById('btnClearGelenProductSearch').classList.add('hidden');
    loadGelenInvoices();
}

function loadGelenInvoices() {
    const listContainer = document.getElementById('gelenInvoiceList');
    listContainer.innerHTML = `
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-sky-600">progress_activity</span>
            <p class="mt-2 font-bold">Faturalar yükleniyor...</p>
        </div>
    `;

    const searchVal = document.getElementById('gelenSearchInput').value.trim();
    const prodVal = document.getElementById('gelenProductSearchInput').value.trim();

    let url = '../api/efatura-api.php?action=list_invoices&list_type=gelen&start=0&length=100';
    if (currentGelenStartDate) url += `&baslangic_tarihi=${currentGelenStartDate}`;
    if (currentGelenEndDate) url += `&bitis_tarihi=${currentGelenEndDate}`;
    if (currentGelenYanit) url += `&durum_filtre=${encodeURIComponent(currentGelenYanit)}`;
    if (searchVal) url += `&search[value]=${encodeURIComponent(searchVal)}`;
    if (prodVal) url += `&urun_ara=${encodeURIComponent(prodVal)}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            const data = res.data || [];

            // Gelen özetini hesaplayalım
            let totAmt = 0;
            let kabulCount = 0;
            let bekleyenCount = 0;

            data.forEach(item => {
                totAmt += (item.odenecek_tutar_raw || 0);
                if (item.ticari_yanit === 'KABUL') kabulCount++;
                else if (item.ticari_yanit === 'BEKLIYOR' || !item.ticari_yanit) bekleyenCount++;
            });

            document.getElementById('statGelenTotalAmount').textContent = formatMoney(totAmt);
            document.getElementById('statGelenTotalCount').textContent = data.length + ' Fatura';
            document.getElementById('statGelenKabulCount').textContent = kabulCount;
            document.getElementById('statGelenBekleyenCount').textContent = bekleyenCount;

            if (data.length === 0) {
                listContainer.innerHTML = `
                    <div class="bg-white dark:bg-card-dark p-8 rounded-2xl text-center border border-slate-100 dark:border-slate-700/60 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2">
                            <span class="material-symbols-outlined text-2xl">search_off</span>
                        </div>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Seçilen kriterlere uygun gelen fatura bulunamadı</p>
                        <p class="text-[11px] text-slate-400 mt-1">Dönem veya arama filtresini değiştirerek tekrar deneyebilirsiniz.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            data.forEach(inv => {
                const yanit = inv.ticari_yanit || 'BEKLIYOR';
                let yanitClass = 'bg-amber-100 text-amber-700';
                let yanitText = 'Bekliyor';
                if (yanit === 'KABUL') {
                    yanitClass = 'bg-emerald-100 text-emerald-700';
                    yanitText = 'Kabul Edildi';
                } else if (yanit === 'RED') {
                    yanitClass = 'bg-rose-100 text-rose-700';
                    yanitText = 'Reddedildi';
                }

                html += `
                <div class="gelen-inv-card bg-white dark:bg-card-dark rounded-2xl p-3.5 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-2.5 active:bg-slate-50 dark:active:bg-slate-800/40 transition-colors"
                     onclick="openInvoicePreview('${inv.encrypted_id}', '${inv.fatura_no || ''}')">
                    
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs font-black text-sky-600">${inv.fatura_no || 'Fatura'}</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">${inv.belge_turu || 'EFATURA'}</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${yanitClass}">${yanitText}</span>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white line-clamp-1">${inv.alici_unvan || 'Tedarikçi'}</h3>
                        <p class="text-[10px] text-slate-400 font-medium">VKN/TCKN: ${inv.alici_vkn_tckn || '-'} • Tarih: ${inv.fatura_tarihi || '-'}</p>
                    </div>

                    ${inv.kalemler_ozet ? `
                        <div class="text-[10px] text-slate-500 bg-slate-50 dark:bg-slate-800/50 p-1.5 rounded-lg line-clamp-1">
                            <strong class="text-purple-600">İçerik:</strong> ${inv.kalemler_ozet}
                        </div>
                    ` : ''}

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <div>
                            <span class="text-[10px] text-slate-400">Tutar:</span>
                            <span class="font-black text-slate-900 dark:text-white text-sm">${inv.odenecek_tutar || '0,00 ₺'}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="event.stopPropagation(); openInvoicePreview('${inv.encrypted_id}', '${inv.fatura_no || ''}')" class="flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-bold text-[10px]">
                                <span class="material-symbols-outlined text-[14px]">visibility</span> Fatura Gör
                            </button>
                        </div>
                    </div>
                </div>`;
            });

            listContainer.innerHTML = html;
        })
        .catch(err => {
            listContainer.innerHTML = `<div class="p-4 text-center text-xs text-rose-500 font-bold">Veriler alınırken hata oluştu.</div>`;
        });
}

function openInvoicePreview(encId, faturaNo) {
    const modal = document.getElementById('mobPdfModal');
    const sheet = document.getElementById('mobPdfSheet');
    const frame = document.getElementById('mobPdfFrame');
    const title = document.getElementById('mobPdfTitle');
    const dlBtn = document.getElementById('mobPdfDownloadBtn');

    title.textContent = faturaNo || 'Gelen Fatura';
    dlBtn.href = `../api/efatura-api.php?action=download_pdf&invoice_id=${encodeURIComponent(encId)}`;

    frame.srcdoc = `
        <!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;display:flex;align-items:center;justify-content:center;height:80vh;color:#64748b;margin:0;}</style></head>
        <body><div style="text-align:center;"><p style="font-size:14px;font-weight:bold;">Fatura yükleniyor...</p></div></body></html>
    `;

    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        sheet.classList.remove('translate-y-full');
    }, 10);

    fetch(`../api/efatura-api.php?action=preview_html&invoice_id=${encodeURIComponent(encId)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.html) {
                let htmlContent = res.html;
                if (!htmlContent.includes('<html') && !htmlContent.includes('<!DOCTYPE')) {
                    htmlContent = `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0, user-scalable=yes"><style>body { margin: 0; padding: 12px; background: #fff; font-family: sans-serif; -webkit-text-size-adjust: 100%; } table { max-width: 100% !important; }</style></head><body>${htmlContent}</body></html>`;
                } else if (!htmlContent.includes('name="viewport"')) {
                    htmlContent = htmlContent.replace('<head>', '<head><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0, user-scalable=yes">');
                }
                frame.srcdoc = htmlContent;
            } else {
                frame.srcdoc = `<div style="padding:20px;color:#dc2626;font-family:sans-serif;font-weight:bold;">${res.message || 'Önizleme oluşturulamadı.'}</div>`;
            }
        })
        .catch(err => {
            frame.srcdoc = `<div style="padding:20px;color:#dc2626;font-family:sans-serif;font-weight:bold;">Fatura yüklenirken sunucu hatası oluştu.</div>`;
        });
}

<!-- 7. Dönem Seçim Modal / Bottom Sheet -->
<div id="gelenPeriodModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 hidden opacity-0 transition-opacity duration-200">
    <div id="gelenPeriodSheet" class="w-full sm:max-w-md bg-white dark:bg-card-dark rounded-t-3xl sm:rounded-2xl p-4 sm:p-5 shadow-2xl transform translate-y-full transition-transform duration-200 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-xl">calendar_month</span>
                <h3 class="text-sm font-black text-slate-900 dark:text-white">Dönem & Tarih Seçimi</h3>
            </div>
            <button type="button" onclick="closeGelenPeriodModal()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- 1. Yıl ve Ay Seçimi -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Aylık Dönem Seç</span>
                <select id="gelenModalYearSelect" class="text-xs font-bold bg-slate-100 dark:bg-slate-800 border-0 rounded-lg px-2.5 py-1 text-slate-800 dark:text-slate-200">
                    <?php 
                    $currY = (int)date('Y');
                    for ($y = $currY; $y >= $currY - 3; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="grid grid-cols-4 gap-1.5">
                <?php 
                $months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
                $currM = (int)date('n');
                foreach ($months as $idx => $mName): 
                    $mNum = $idx + 1;
                ?>
                    <button type="button" onclick="applyGelenMonth(<?= $mNum ?>, '<?= $mName ?>')" 
                            class="month-btn py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-800 hover:bg-primary/10 hover:text-primary active:scale-95 transition-all text-center <?= $mNum === $currM ? 'border border-primary text-primary' : '' ?>">
                        <?= $mName ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 2. Özel Tarih Aralığı -->
        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-2">Özel Tarih Aralığı</span>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] font-bold text-slate-400 block mb-1">Başlangıç</label>
                    <input type="date" id="gelenStartDateInput" value="<?= date('Y-m-01') ?>" class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 block mb-1">Bitiş</label>
                    <input type="date" id="gelenEndDateInput" value="<?= date('Y-m-t') ?>" class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-slate-800 dark:text-slate-200">
                </div>
            </div>
            <button type="button" onclick="applyGelenCustomRange()" class="w-full mt-3 py-2.5 rounded-xl bg-primary text-white text-xs font-bold shadow-sm shadow-primary/30 active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">check</span>
                <span>Tarih Aralığını Uygula</span>
            </button>
        </div>
    </div>
</div>

<script>
function openGelenPeriodModal() {
    const modal = document.getElementById('gelenPeriodModal');
    const sheet = document.getElementById('gelenPeriodSheet');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function closeGelenPeriodModal() {
    const modal = document.getElementById('gelenPeriodModal');
    const sheet = document.getElementById('gelenPeriodSheet');
    sheet.classList.add('translate-y-full');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function applyGelenMonth(monthNum, monthName) {
    const year = document.getElementById('gelenModalYearSelect').value;
    const lastDay = new Date(year, monthNum, 0).getDate();
    currentGidenStartDate = `${year}-${String(monthNum).padStart(2, '0')}-01`;
    currentGidenEndDate = `${year}-${String(monthNum).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
    const label = `${monthName} ${year}`;
    
    closeGelenPeriodModal();
    setCustomGelenPeriodActive(label);
}

function applyGelenCustomRange() {
    const sDate = document.getElementById('gelenStartDateInput').value;
    const eDate = document.getElementById('gelenEndDateInput').value;
    if (!sDate || !eDate) {
        alert('Lütfen başlangıç ve bitiş tarihlerini seçin.');
        return;
    }
    const fmtShort = (dStr) => {
        const p = dStr.split('-');
        return `${p[2]}.${p[1]}`;
    };
    currentGidenStartDate = sDate;
    currentGidenEndDate = eDate;
    const label = `${fmtShort(sDate)} - ${fmtShort(eDate)}`;
    
    closeGelenPeriodModal();
    setCustomGelenPeriodActive(label);
}

function setCustomGelenPeriodActive(label) {
    document.querySelectorAll('.gelen-period-btn').forEach(b => {
        b.className = 'gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    const btn = document.getElementById('btnCustomGelenPeriod');
    btn.className = 'gelen-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap flex items-center gap-1';
    document.getElementById('customGelenPeriodLabel').textContent = label;

    loadGelenInvoices();
}
</script>
