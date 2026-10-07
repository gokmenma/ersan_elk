<?php
\App\Service\Gate::authorizeOrDie('efatura/taslak-list');
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
                <h1 class="text-base font-black text-slate-900 dark:text-white leading-tight">Taslak Faturalar</h1>
                <p class="text-[11px] font-medium text-slate-500">Hazırlanan ve gönderilmeyi bekleyenler</p>
            </div>
        </div>

        <a href="?p=efatura-olustur" class="flex items-center gap-1 px-3 py-2 rounded-xl bg-primary text-white text-xs font-bold shadow-sm shadow-primary/30 active:scale-95 transition-transform">
            <span class="material-symbols-outlined text-[18px]">add</span>
            <span>Yeni Taslak</span>
        </a>
    </div>

    <!-- 2. Dönem Filtre Butonları (Varsayılan: Bu Ay) -->
    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5" id="taslakPeriodGroup">
        <button type="button" onclick="setTaslakPeriod('this_month', this)" class="taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap">Bu Ay</button>
        <button type="button" onclick="setTaslakPeriod('last_month', this)" class="taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Geçen Ay</button>
        <button type="button" onclick="setTaslakPeriod('last_3_months', this)" class="taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Son 3 Ay</button>
        <button type="button" onclick="setTaslakPeriod('this_year', this)" class="taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap"><?= date('Y') ?> Yılı</button>
        <button type="button" onclick="setTaslakPeriod('all', this)" class="taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Tümü</button>
        <button type="button" onclick="openTaslakPeriodModal()" class="taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap flex items-center gap-1" id="btnCustomTaslakPeriod">
            <span class="material-symbols-outlined text-[15px] text-primary">calendar_month</span>
            <span id="customTaslakPeriodLabel">Dönem Seç</span>
        </button>
    </div>

    <!-- 3. Özet Kartı -->
    <div class="bg-white dark:bg-card-dark rounded-2xl p-4 border border-amber-200 dark:border-amber-900/50 bg-amber-50/40 dark:bg-amber-950/10 shadow-xs flex items-center justify-between">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 dark:text-amber-400">BEKLEYEN TASLAKLAR</span>
            <div class="text-lg font-black text-slate-900 dark:text-white mt-0.5" id="statTaslakTotalAmount">0,00 ₺</div>
            <p class="text-[11px] text-slate-500 mt-0.5" id="statTaslakTotalCount">Toplam 0 adet taslak fatura</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 flex items-center justify-center">
            <span class="material-symbols-outlined text-2xl">edit_document</span>
        </div>
    </div>

    <!-- 4. Arama Kutuları (Genel Arama & Ürün/Marka/Kalem Arama) -->
    <div class="space-y-2">
        <!-- Genel Arama -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="material-symbols-outlined text-slate-400 text-lg">search</span>
            </div>
            <input type="text" id="taslakSearchInput" placeholder="Alıcı Ünvanı veya VKN Ara..." autocomplete="off"
                   class="w-full pl-9 pr-4 py-2.5 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-700 focus:border-primary focus:ring-0 rounded-xl shadow-xs text-xs text-slate-900 dark:text-white placeholder-slate-400">
        </div>

        <!-- Ürün / Marka / Kalem Arama -->
        <div class="space-y-1">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="material-symbols-outlined text-purple-500 text-lg">inventory_2</span>
                </div>
                <input type="text" id="taslakProductSearchInput" placeholder="Taslak içeriğindeki Ürün / Marka / Kalem Ara..." autocomplete="off"
                       class="w-full pl-9 pr-8 py-2.5 bg-purple-50/40 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-900/50 focus:border-purple-500 focus:ring-0 rounded-xl shadow-xs text-xs text-slate-900 dark:text-white placeholder-purple-400">
                <button type="button" id="btnClearTaslakProductSearch" onclick="clearTaslakProductSearch()" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 hidden">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div id="taslakProductGlobalBadge" class="hidden text-[10px] font-semibold text-purple-700 dark:text-purple-300 bg-purple-100 dark:bg-purple-950/60 px-2 py-0.5 rounded-md flex items-center gap-1">
                <span class="material-symbols-outlined text-xs">public</span> Tüm dönemlerde aranıyor
            </div>
        </div>
    </div>

    <!-- 5. Taslak Listesi -->
    <div class="space-y-2.5" id="taslakInvoiceList">
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-amber-600">progress_activity</span>
            <p class="mt-2 font-bold">Taslaklar yükleniyor...</p>
        </div>
    </div>
</div>

<script>
let currentTaslakStartDate = '';
let currentTaslakEndDate = '';
let taslakSearchTimer = null;

const formatMoney = (v) => Number(v || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';

document.addEventListener('DOMContentLoaded', function() {
    // Varsayılan: Bu Ay
    setTaslakPeriod('this_month', document.querySelector('#taslakPeriodGroup button'));

    // Arama dinleyicileri
    document.getElementById('taslakSearchInput').addEventListener('input', function() {
        clearTimeout(taslakSearchTimer);
        taslakSearchTimer = setTimeout(loadTaslakInvoices, 350);
    });

    document.getElementById('taslakProductSearchInput').addEventListener('input', function() {
        const val = this.value.trim();
        const btnClear = document.getElementById('btnClearTaslakProductSearch');
        const badge = document.getElementById('taslakProductGlobalBadge');
        if (val.length > 0) {
            btnClear.classList.remove('hidden');
            if (badge) badge.classList.remove('hidden');
        } else {
            btnClear.classList.add('hidden');
            if (badge) badge.classList.add('hidden');
        }

        clearTimeout(taslakSearchTimer);
        taslakSearchTimer = setTimeout(loadTaslakInvoices, 350);
    });
});

function setTaslakPeriod(period, btn) {
    document.querySelectorAll('.taslak-period-btn').forEach(b => {
        b.className = 'taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    if (btn) {
        btn.className = 'taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap';
    }

    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const toIso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    if (period === 'this_month') {
        currentTaslakStartDate = toIso(new Date(y, m, 1));
        currentTaslakEndDate = toIso(new Date(y, m + 1, 0));
    } else if (period === 'last_month') {
        currentTaslakStartDate = toIso(new Date(y, m - 1, 1));
        currentTaslakEndDate = toIso(new Date(y, m, 0));
    } else if (period === 'last_3_months') {
        currentTaslakStartDate = toIso(new Date(y, m - 2, 1));
        currentTaslakEndDate = toIso(new Date(y, m + 1, 0));
    } else if (period === 'this_year') {
        currentTaslakStartDate = toIso(new Date(y, 0, 1));
        currentTaslakEndDate = toIso(new Date(y, 11, 31));
    } else if (period === 'all') {
        currentTaslakStartDate = '';
        currentTaslakEndDate = '';
    }

    loadTaslakInvoices();
}

function clearTaslakProductSearch() {
    const inp = document.getElementById('taslakProductSearchInput');
    inp.value = '';
    document.getElementById('btnClearTaslakProductSearch').classList.add('hidden');
    const badge = document.getElementById('taslakProductGlobalBadge');
    if (badge) badge.classList.add('hidden');
    loadTaslakInvoices();
}

function loadTaslakInvoices() {
    const listContainer = document.getElementById('taslakInvoiceList');
    listContainer.innerHTML = `
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-amber-600">progress_activity</span>
            <p class="mt-2 font-bold">Taslaklar yükleniyor...</p>
        </div>
    `;

    const searchVal = document.getElementById('taslakSearchInput').value.trim();
    const prodVal = document.getElementById('taslakProductSearchInput').value.trim();

    let url = '../api/efatura-api.php?action=list_invoices&list_type=taslak&start=0&length=100';
    if (prodVal) {
        url += `&urun_ara=${encodeURIComponent(prodVal)}`;
    } else {
        if (currentTaslakStartDate) url += `&baslangic_tarihi=${currentTaslakStartDate}`;
        if (currentTaslakEndDate) url += `&bitis_tarihi=${currentTaslakEndDate}`;
    }
    if (searchVal) url += `&search[value]=${encodeURIComponent(searchVal)}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            const data = res.data || [];

            let totAmt = 0;
            data.forEach(item => {
                totAmt += (item.odenecek_tutar_raw || 0);
            });

            document.getElementById('statTaslakTotalAmount').textContent = formatMoney(totAmt);
            document.getElementById('statTaslakTotalCount').textContent = `Toplam ${data.length} adet taslak fatura`;

            if (data.length === 0) {
                listContainer.innerHTML = `
                    <div class="bg-white dark:bg-card-dark p-8 rounded-2xl text-center border border-slate-100 dark:border-slate-700/60 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2">
                            <span class="material-symbols-outlined text-2xl">drafts</span>
                        </div>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Seçilen kriterlere uygun taslak bulunamadı</p>
                        <p class="text-[11px] text-slate-400 mt-1">Dönem veya arama filtresini değiştirerek tekrar deneyebilirsiniz.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            data.forEach(d => {
                html += `
                <div class="bg-white dark:bg-card-dark rounded-2xl p-3.5 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs font-bold text-slate-500">${d.fatura_no || 'TASLAK'}</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">Taslak</span>
                            <span class="text-[10px] text-slate-400">${d.belge_turu || 'EFATURA'}</span>
                        </div>
                        <span class="text-[11px] text-slate-400 font-medium">${d.fatura_tarihi || '-'}</span>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white line-clamp-1">${d.alici_unvan || 'Alıcı Bilgisi Yok'}</h3>
                        <p class="text-[10px] text-slate-400 font-medium">VKN/TCKN: ${d.alici_vkn_tckn || '-'}</p>
                    </div>

                    ${d.kalemler_ozet ? `
                        <div class="text-[10px] text-slate-500 bg-slate-50 dark:bg-slate-800/50 p-1.5 rounded-lg line-clamp-1">
                            <strong class="text-purple-600">İçerik:</strong> ${d.kalemler_ozet}
                        </div>
                    ` : ''}

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div>
                            <span class="text-[10px] text-slate-400">Tutar:</span>
                            <span class="font-black text-slate-900 dark:text-white text-sm">${d.odenecek_tutar || '0,00 ₺'}</span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="deleteDraftInvoice('${d.encrypted_id}')" class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-900/30 text-rose-600 flex items-center justify-center active:scale-95 transition-transform" title="Taslağı Sil">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                            <a href="?p=efatura-olustur&draft_id=${d.encrypted_id}" class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-bold shadow-xs active:scale-95 transition-transform">
                                <span class="material-symbols-outlined text-[16px]">edit</span> Düzenle / Gönder
                            </a>
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
</script>

<!-- 7. Dönem Seçim Modal / Bottom Sheet -->
<div id="taslakPeriodModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 hidden opacity-0 transition-opacity duration-200">
    <div id="taslakPeriodSheet" class="w-full sm:max-w-md bg-white dark:bg-card-dark rounded-t-3xl sm:rounded-2xl p-4 sm:p-5 shadow-2xl transform translate-y-full transition-transform duration-200 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-xl">calendar_month</span>
                <h3 class="text-sm font-black text-slate-900 dark:text-white">Dönem & Tarih Seçimi</h3>
            </div>
            <button type="button" onclick="closeTaslakPeriodModal()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- 1. Yıl ve Ay Seçimi -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Aylık Dönem Seç</span>
                <select id="taslakModalYearSelect" class="text-xs font-bold bg-slate-100 dark:bg-slate-800 border-0 rounded-lg px-2.5 py-1 text-slate-800 dark:text-slate-200">
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
                    <button type="button" onclick="applyTaslakMonth(<?= $mNum ?>, '<?= $mName ?>')" 
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
                    <input type="date" id="taslakStartDateInput" value="<?= date('Y-m-01') ?>" class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 block mb-1">Bitiş</label>
                    <input type="date" id="taslakEndDateInput" value="<?= date('Y-m-t') ?>" class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-slate-800 dark:text-slate-200">
                </div>
            </div>
            <button type="button" onclick="applyTaslakCustomRange()" class="w-full mt-3 py-2.5 rounded-xl bg-primary text-white text-xs font-bold shadow-sm shadow-primary/30 active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">check</span>
                <span>Tarih Aralığını Uygula</span>
            </button>
        </div>
    </div>
</div>

<script>
function openTaslakPeriodModal() {
    const modal = document.getElementById('taslakPeriodModal');
    const sheet = document.getElementById('taslakPeriodSheet');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function closeTaslakPeriodModal() {
    const modal = document.getElementById('taslakPeriodModal');
    const sheet = document.getElementById('taslakPeriodSheet');
    sheet.classList.add('translate-y-full');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

function applyTaslakMonth(monthNum, monthName) {
    const year = document.getElementById('taslakModalYearSelect').value;
    const lastDay = new Date(year, monthNum, 0).getDate();
    currentTaslakStartDate = `${year}-${String(monthNum).padStart(2, '0')}-01`;
    currentTaslakEndDate = `${year}-${String(monthNum).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
    const label = `${monthName} ${year}`;
    
    closeTaslakPeriodModal();
    setCustomTaslakPeriodActive(label);
}

function applyTaslakCustomRange() {
    const sDate = document.getElementById('taslakStartDateInput').value;
    const eDate = document.getElementById('taslakEndDateInput').value;
    if (!sDate || !eDate) {
        alert('Lütfen başlangıç ve bitiş tarihlerini seçin.');
        return;
    }
    const fmtShort = (dStr) => {
        const p = dStr.split('-');
        return `${p[2]}.${p[1]}`;
    };
    currentTaslakStartDate = sDate;
    currentTaslakEndDate = eDate;
    const label = `${fmtShort(sDate)} - ${fmtShort(eDate)}`;
    
    closeTaslakPeriodModal();
    setCustomTaslakPeriodActive(label);
}

function setCustomTaslakPeriodActive(label) {
    document.querySelectorAll('.taslak-period-btn').forEach(b => {
        b.className = 'taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    const btn = document.getElementById('btnCustomTaslakPeriod');
    btn.className = 'taslak-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap flex items-center gap-1';
    document.getElementById('customTaslakPeriodLabel').textContent = label;

    loadTaslakInvoices();
}

function deleteDraftInvoice(encId) {
    if (!confirm('Bu taslak faturayı silmek istediğinizden emin misiniz?')) return;

    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helper\Security::csrf() ?>');
    fd.append('invoice_id', encId);

    fetch('../api/efatura-api.php?action=delete_draft', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            loadTaslakInvoices();
        } else {
            alert(res.message || 'Silme işlemi sırasında hata oluştu.');
        }
    })
    .catch(err => {
        alert('Sunucu ile iletişim hatası.');
    });
}
</script>
