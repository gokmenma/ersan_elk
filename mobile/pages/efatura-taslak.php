<?php
if (!\App\Service\Gate::allows('efatura/taslak-list') && !\App\Service\Gate::allows('efatura/giden-list') && !\App\Service\Gate::allows('efatura/dashboard') && !\App\Service\Gate::allows('efatura/gelen-list')) {
    \App\Service\Gate::authorizeOrDie('efatura/taslak-list');
}
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
        if (val.length > 0) btnClear.classList.remove('hidden');
        else btnClear.classList.add('hidden');

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
    if (currentTaslakStartDate) url += `&baslangic_tarihi=${currentTaslakStartDate}`;
    if (currentTaslakEndDate) url += `&bitis_tarihi=${currentTaslakEndDate}`;
    if (searchVal) url += `&search[value]=${encodeURIComponent(searchVal)}`;
    if (prodVal) url += `&urun_ara=${encodeURIComponent(prodVal)}`;

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
