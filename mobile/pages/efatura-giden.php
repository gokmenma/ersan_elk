<?php
\App\Service\Gate::authorizeOrDie('efatura/giden-list');
use App\Model\EInvoiceModel;
use App\Helper\Security;

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
?>

<div class="px-3.5 py-4 space-y-3.5 pb-24">

    <!-- 1. Üst Başlık & Aksiyon -->
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
            <a href="?p=efatura" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">arrow_back</span>
            </a>
            <div>
                <h1 class="text-base font-black text-slate-900 dark:text-white leading-tight">Giden Faturalar</h1>
                <p class="text-[11px] font-medium text-slate-500">Satış ve e-arşiv faturaları</p>
            </div>
        </div>

        <a href="?p=efatura-olustur" class="flex items-center gap-1 px-3 py-2 rounded-xl bg-primary text-white text-xs font-bold shadow-sm shadow-primary/30 active:scale-95 transition-transform">
            <span class="material-symbols-outlined text-[18px]">add</span>
            <span>Yeni</span>
        </a>
    </div>

    <!-- 2. Dönem Filtre Butonları (Varsayılan: Bu Ay) -->
    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5" id="gidenPeriodGroup">
        <button type="button" onclick="setGidenPeriod('this_month', this)" class="giden-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap">Bu Ay</button>
        <button type="button" onclick="setGidenPeriod('last_month', this)" class="giden-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Geçen Ay</button>
        <button type="button" onclick="setGidenPeriod('last_3_months', this)" class="giden-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Son 3 Ay</button>
        <button type="button" onclick="setGidenPeriod('this_year', this)" class="giden-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap"><?= date('Y') ?> Yılı</button>
        <button type="button" onclick="setGidenPeriod('all', this)" class="giden-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Tümü</button>
        <button type="button" onclick="openGidenPeriodModal()" class="giden-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap flex items-center gap-1" id="btnCustomGidenPeriod">
            <span class="material-symbols-outlined text-[15px] text-primary">calendar_month</span>
            <span id="customGidenPeriodLabel">Dönem Seç</span>
        </button>
    </div>

    <!-- 3. Özet Kartları -->
    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-primary text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Toplam Tutar</p>
            <p class="font-black text-slate-900 dark:text-white text-xs sm:text-sm mt-0.5 truncate" id="statGidenTotalAmount">0,00 ₺</p>
            <p class="text-[10px] text-slate-400 font-medium" id="statGidenTotalCount">0 Fatura</p>
        </div>

        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-emerald-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">GİB Onaylı</p>
            <p class="font-black text-emerald-600 text-xs sm:text-sm mt-0.5" id="statGidenOnayliCount">0</p>
            <p class="text-[10px] text-emerald-600/70 font-medium" id="statGidenOnayliTutar">0,00 ₺</p>
        </div>

        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-amber-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Bekleyen</p>
            <p class="font-black text-amber-600 text-xs sm:text-sm mt-0.5" id="statGidenBekleyenCount">0</p>
            <p class="text-[10px] text-amber-600/70 font-medium" id="statGidenBekleyenTutar">0,00 ₺</p>
        </div>
    </div>

    <!-- 4. Arama Kutuları (Genel Arama & Ürün/Marka/Kalem Arama) -->
    <div class="space-y-2">
        <!-- Genel Arama -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="material-symbols-outlined text-slate-400 text-lg">search</span>
            </div>
            <input type="text" id="gidenSearchInput" placeholder="Fatura No, Müşteri Ünvanı veya VKN..." autocomplete="off"
                   class="w-full pl-9 pr-4 py-2.5 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-700 focus:border-primary focus:ring-0 rounded-xl shadow-xs text-xs text-slate-900 dark:text-white placeholder-slate-400">
        </div>

        <!-- Ürün / Marka / Kalem Arama -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="material-symbols-outlined text-purple-500 text-lg">inventory_2</span>
            </div>
            <input type="text" id="gidenProductSearchInput" placeholder="Fatura içeriğindeki Ürün / Marka / Kalem Ara..." autocomplete="off"
                   class="w-full pl-9 pr-8 py-2.5 bg-purple-50/40 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-900/50 focus:border-purple-500 focus:ring-0 rounded-xl shadow-xs text-xs text-slate-900 dark:text-white placeholder-purple-400">
            <button type="button" id="btnClearGidenProductSearch" onclick="clearGidenProductSearch()" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 hidden">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- Durum Filtreleri -->
        <div class="flex items-center gap-1 overflow-x-auto no-scrollbar py-0.5" id="gidenStatusFilters">
            <button type="button" onclick="filterGidenStatus('', this)" class="giden-status-pill px-2.5 py-1 rounded-lg text-[11px] font-bold bg-primary text-white whitespace-nowrap">Tümü</button>
            <button type="button" onclick="filterGidenStatus('ONAYLANDI', this)" class="giden-status-pill px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Onaylı</button>
            <button type="button" onclick="filterGidenStatus('BEKLEYEN_ILETILEN', this)" class="giden-status-pill px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Bekleyen</button>
        </div>
    </div>

    <!-- 5. Fatura Kartları Listesi -->
    <div class="space-y-2.5" id="gidenInvoiceList">
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-primary">progress_activity</span>
            <p class="mt-2 font-bold">Faturalar yükleniyor...</p>
        </div>
    </div>
</div>

<!-- Fatura PDF Önizleme Modalı -->
<div id="mobPdfModal" class="fixed inset-0 z-[110] bg-slate-900/80 backdrop-blur-xs flex flex-col justify-end hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-t-[28px] w-full h-[95vh] flex flex-col shadow-2xl overflow-hidden transform translate-y-full transition-transform duration-300" id="mobPdfSheet">
        <div class="p-3.5 px-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">description</span>
                <span class="text-sm font-black text-slate-900 dark:text-white" id="mobPdfTitle">Fatura Önizleme</span>
            </div>
            <div class="flex items-center gap-1.5">
                <a href="#" id="mobPdfNewTabBtn" target="_blank" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center" title="Yeni Sekmede Aç">
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                </a>
                <button type="button" onclick="printMobileInvoice()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center" title="Yazdır">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                </button>
                <a href="#" id="mobPdfDownloadBtn" target="_blank" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center" title="PDF İndir">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                </a>
                <button type="button" onclick="closeInvoicePreview()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center" title="Kapat">
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
let currentGidenStartDate = '';
let currentGidenEndDate = '';
let currentGidenStatus = '';
let gidenSearchTimer = null;

const formatMoney = (v) => Number(v || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';

document.addEventListener('DOMContentLoaded', function() {
    // Varsayılan: Bu Ay
    setGidenPeriod('this_month', document.querySelector('#gidenPeriodGroup button'));

    // Arama dinleyicileri
    document.getElementById('gidenSearchInput').addEventListener('input', function() {
        clearTimeout(gidenSearchTimer);
        gidenSearchTimer = setTimeout(loadGidenInvoices, 350);
    });

    document.getElementById('gidenProductSearchInput').addEventListener('input', function() {
        const val = this.value.trim();
        const btnClear = document.getElementById('btnClearGidenProductSearch');
        if (val.length > 0) btnClear.classList.remove('hidden');
        else btnClear.classList.add('hidden');

        clearTimeout(gidenSearchTimer);
        gidenSearchTimer = setTimeout(loadGidenInvoices, 350);
    });
});

function setGidenPeriod(period, btn) {
    document.querySelectorAll('.giden-period-btn').forEach(b => {
        b.className = 'giden-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    if (btn) {
        btn.className = 'giden-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap';
    }

    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const toIso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    if (period === 'this_month') {
        currentGidenStartDate = toIso(new Date(y, m, 1));
        currentGidenEndDate = toIso(new Date(y, m + 1, 0));
    } else if (period === 'last_month') {
        currentGidenStartDate = toIso(new Date(y, m - 1, 1));
        currentGidenEndDate = toIso(new Date(y, m, 0));
    } else if (period === 'last_3_months') {
        currentGidenStartDate = toIso(new Date(y, m - 2, 1));
        currentGidenEndDate = toIso(new Date(y, m + 1, 0));
    } else if (period === 'this_year') {
        currentGidenStartDate = toIso(new Date(y, 0, 1));
        currentGidenEndDate = toIso(new Date(y, 11, 31));
    } else if (period === 'all') {
        currentGidenStartDate = '';
        currentGidenEndDate = '';
    }

    loadGidenInvoices();
}

function filterGidenStatus(status, btn) {
    currentGidenStatus = status;
    document.querySelectorAll('.giden-status-pill').forEach(b => {
        b.className = 'giden-status-pill px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    btn.className = 'giden-status-pill px-2.5 py-1 rounded-lg text-[11px] font-bold bg-primary text-white whitespace-nowrap';
    loadGidenInvoices();
}

function clearGidenProductSearch() {
    const inp = document.getElementById('gidenProductSearchInput');
    inp.value = '';
    document.getElementById('btnClearGidenProductSearch').classList.add('hidden');
    loadGidenInvoices();
}

function loadGidenInvoices() {
    const listContainer = document.getElementById('gidenInvoiceList');
    listContainer.innerHTML = `
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-primary">progress_activity</span>
            <p class="mt-2 font-bold">Faturalar yükleniyor...</p>
        </div>
    `;

    const searchVal = document.getElementById('gidenSearchInput').value.trim();
    const prodVal = document.getElementById('gidenProductSearchInput').value.trim();

    let url = '../api/efatura-api.php?action=list_invoices&list_type=giden&start=0&length=100';
    if (currentGidenStartDate) url += `&baslangic_tarihi=${currentGidenStartDate}`;
    if (currentGidenEndDate) url += `&bitis_tarihi=${currentGidenEndDate}`;
    if (currentGidenStatus) url += `&durum_filtre=${encodeURIComponent(currentGidenStatus)}`;
    if (searchVal) url += `&search[value]=${encodeURIComponent(searchVal)}`;
    if (prodVal) url += `&urun_ara=${encodeURIComponent(prodVal)}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            const data = res.data || [];
            const summary = res.summary || {};

            // Özet kartları güncelle
            document.getElementById('statGidenTotalAmount').textContent = formatMoney(summary.toplam_tutar);
            document.getElementById('statGidenTotalCount').textContent = (summary.toplam_adet || 0) + ' Fatura';
            document.getElementById('statGidenOnayliCount').textContent = summary.onaylanan_adet || 0;
            document.getElementById('statGidenOnayliTutar').textContent = formatMoney(summary.onaylanan_tutar);
            document.getElementById('statGidenBekleyenCount').textContent = summary.bekleyen_adet || 0;
            document.getElementById('statGidenBekleyenTutar').textContent = formatMoney(summary.bekleyen_tutar);

            if (data.length === 0) {
                listContainer.innerHTML = `
                    <div class="bg-white dark:bg-card-dark p-8 rounded-2xl text-center border border-slate-100 dark:border-slate-700/60 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2">
                            <span class="material-symbols-outlined text-2xl">search_off</span>
                        </div>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Seçilen kriterlere uygun fatura bulunamadı</p>
                        <p class="text-[11px] text-slate-400 mt-1">Dönem veya arama filtresini değiştirerek tekrar deneyebilirsiniz.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            data.forEach(inv => {
                const durum = inv.entegrator_durum_kodu;
                let statusClass = 'bg-slate-100 text-slate-700';
                let statusText = durum;
                if (durum === 'ONAYLANDI') {
                    statusClass = 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50';
                    statusText = 'Onaylı';
                } else if (['KUYRUKTA', 'GONDERILDI', 'BEKLIYOR'].includes(durum)) {
                    statusClass = 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50';
                    statusText = 'İletildi / Bekliyor';
                } else if (durum === 'HATALI') {
                    statusClass = 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50';
                    statusText = 'Hatalı';
                }

                const tahsilatDurumu = inv.tahsilat_durumu || 'ODENMEDI';

                html += `
                <div class="giden-inv-card bg-white dark:bg-card-dark rounded-2xl p-3.5 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-2.5 active:bg-slate-50 dark:active:bg-slate-800/40 transition-colors"
                     onclick="openInvoicePreview('${inv.encrypted_id}', '${inv.fatura_no || ''}')">
                    
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs font-black text-primary">${inv.fatura_no || 'Taslak'}</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">${inv.belge_turu || 'EFATURA'}</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${statusClass}">${statusText}</span>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white line-clamp-1">${inv.alici_unvan || 'İsimsiz Cari'}</h3>
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
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold ${tahsilatDurumu === 'ODENDI' ? 'text-emerald-600' : (tahsilatDurumu === 'KISMI_ODENDI' ? 'text-amber-600' : 'text-slate-400')}">
                                ${tahsilatDurumu === 'ODENDI' ? '✓ Ödendi' : (tahsilatDurumu === 'KISMI_ODENDI' ? '◑ Kısmi' : '○ Ödenmedi')}
                            </span>
                            <button type="button" onclick="event.stopPropagation(); openInvoicePreview('${inv.encrypted_id}', '${inv.fatura_no || ''}')" class="flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-[10px]">
                                <span class="material-symbols-outlined text-[14px]">visibility</span> PDF
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
    const newTabBtn = document.getElementById('mobPdfNewTabBtn');

    title.textContent = faturaNo || 'Fatura Önizleme';
    if (dlBtn) dlBtn.href = `../api/efatura-api.php?action=download_pdf&invoice_id=${encodeURIComponent(encId)}`;
    if (newTabBtn) newTabBtn.href = `../api/efatura-api.php?action=show_invoice&invoice_id=${encodeURIComponent(encId)}`;

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
                    htmlContent = `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=850, initial-scale=0.45, minimum-scale=0.25, maximum-scale=3.0, user-scalable=yes"><style>body { margin: 0; padding: 12px; background: #fff; font-family: sans-serif; -webkit-text-size-adjust: 100%; }</style></head><body>${htmlContent}</body></html>`;
                } else if (!htmlContent.includes('name="viewport"')) {
                    htmlContent = htmlContent.replace(/<head>/i, '<head><meta name="viewport" content="width=850, initial-scale=0.45, minimum-scale=0.25, maximum-scale=3.0, user-scalable=yes">');
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

function printMobileInvoice() {
    const frame = document.getElementById('mobPdfFrame');
    if (frame && frame.contentWindow) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    }
}
</script>

<!-- 7. Dönem Seçim Modal / Bottom Sheet -->
<div id="gidenPeriodModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 hidden opacity-0 transition-opacity duration-200">
    <div id="gidenPeriodSheet" class="w-full sm:max-w-md bg-white dark:bg-card-dark rounded-t-3xl sm:rounded-2xl p-4 sm:p-5 shadow-2xl transform translate-y-full transition-transform duration-200 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-xl">calendar_month</span>
                <h3 class="text-sm font-black text-slate-900 dark:text-white">Dönem & Tarih Seçimi</h3>
            </div>
            <button type="button" onclick="closeGidenPeriodModal()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- 1. Yıl ve Ay Seçimi -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Aylık Dönem Seç</span>
                <select id="gidenModalYearSelect" class="text-xs font-bold bg-slate-100 dark:bg-slate-800 border-0 rounded-lg px-2.5 py-1 text-slate-800 dark:text-slate-200">
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
                    <button type="button" onclick="applyGidenMonth(<?= $mNum ?>, '<?= $mName ?>')" 
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
                    <input type="date" id="gidenStartDateInput" value="<?= date('Y-m-01') ?>" class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 block mb-1">Bitiş</label>
                    <input type="date" id="gidenEndDateInput" value="<?= date('Y-m-t') ?>" class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-slate-800 dark:text-slate-200">
                </div>
            </div>
            <button type="button" onclick="applyGidenCustomRange()" class="w-full mt-3 py-2.5 rounded-xl bg-primary text-white text-xs font-bold shadow-sm shadow-primary/30 active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">check</span>
                <span>Tarih Aralığını Uygula</span>
            </button>
        </div>
    </div>
</div>

<script>
function openGidenPeriodModal() {
    const modal = document.getElementById('gidenPeriodModal');
    const sheet = document.getElementById('gidenPeriodSheet');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function closeGidenPeriodModal() {
    const modal = document.getElementById('gidenPeriodModal');
    const sheet = document.getElementById('gidenPeriodSheet');
    sheet.classList.add('translate-y-full');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function applyGidenMonth(monthNum, monthName) {
    const year = document.getElementById('gidenModalYearSelect').value;
    const lastDay = new Date(year, monthNum, 0).getDate();
    currentGidenStartDate = `${year}-${String(monthNum).padStart(2, '0')}-01`;
    currentGidenEndDate = `${year}-${String(monthNum).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
    const label = `${monthName} ${year}`;
    
    closeGidenPeriodModal();
    setCustomGidenPeriodActive(label);
}

function applyGidenCustomRange() {
    const sDate = document.getElementById('gidenStartDateInput').value;
    const eDate = document.getElementById('gidenEndDateInput').value;
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
    
    closeGidenPeriodModal();
    setCustomGidenPeriodActive(label);
}

function setCustomGidenPeriodActive(label) {
    document.querySelectorAll('.giden-period-btn').forEach(b => {
        b.className = 'giden-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    const btn = document.getElementById('btnCustomGidenPeriod');
    btn.className = 'giden-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap flex items-center gap-1';
    document.getElementById('customGidenPeriodLabel').textContent = label;

    loadGidenInvoices();
}
</script>
