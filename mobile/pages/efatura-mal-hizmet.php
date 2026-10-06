<?php
\App\Service\Gate::authorizeOrDie('efatura/mal-hizmet-list');
use App\Model\EFaturaMalHizmetModel;
use App\Helper\Security;
use App\Config\EdmConfig;

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$unitCodes = EdmConfig::getUnitCodes();
?>

<div class="px-3.5 py-4 space-y-3.5 pb-24">

    <!-- 1. Üst Başlık & Ekle Butonu -->
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
            <a href="?p=efatura" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">arrow_back</span>
            </a>
            <div>
                <h1 class="text-base font-black text-slate-900 dark:text-white leading-tight">Mal & Hizmetler</h1>
                <p class="text-[11px] font-medium text-slate-500">Ürün, hizmet ve fiyat tanımları</p>
            </div>
        </div>

        <button type="button" onclick="openAddMhModal()" class="flex items-center gap-1 px-3 py-2 rounded-xl bg-purple-600 text-white text-xs font-bold shadow-sm shadow-purple-600/30 active:scale-95 transition-transform">
            <span class="material-symbols-outlined text-[18px]">add_box</span>
            <span>Yeni Kalem</span>
        </button>
    </div>

    <!-- 2. Özet Bilgi & Sayı Çubuğu -->
    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-purple-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Toplam Tanım</p>
            <p class="font-black text-slate-900 dark:text-white text-xs sm:text-sm mt-0.5" id="statMhTotal">0</p>
            <p class="text-[10px] text-slate-400 font-medium">Kalem</p>
        </div>

        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-emerald-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Mallar</p>
            <p class="font-black text-emerald-600 text-xs sm:text-sm mt-0.5" id="statMhMal">0</p>
            <p class="text-[10px] text-emerald-600/70 font-medium">Ticari Mal</p>
        </div>

        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-sky-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Hizmetler</p>
            <p class="font-black text-sky-600 text-xs sm:text-sm mt-0.5" id="statMhHizmet">0</p>
            <p class="text-[10px] text-sky-600/70 font-medium">İşçilik / Hizmet</p>
        </div>
    </div>

    <!-- 3. Arama Kutusu ve Hızlı Filtre Butonları -->
    <div class="space-y-2">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="material-symbols-outlined text-slate-400 text-lg">search</span>
            </div>
            <input type="text" id="mhSearchInput" placeholder="Mal / Hizmet Adı, Stok Kodu veya Barkod Ara..." autocomplete="off"
                   class="w-full pl-9 pr-8 py-2.5 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-700 focus:border-purple-600 focus:ring-0 rounded-xl shadow-xs text-xs text-slate-900 dark:text-white placeholder-slate-400">
            <button type="button" id="btnClearMhSearch" onclick="clearMhSearch()" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 hidden">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- Hızlı Filtre Hapları -->
        <div class="flex items-center gap-1 overflow-x-auto no-scrollbar py-0.5" id="mhFilterGroup">
            <button type="button" onclick="filterMhStatus('all', this)" class="mh-filter-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-purple-600 text-white shadow-xs whitespace-nowrap">Tümü</button>
            <button type="button" onclick="filterMhStatus('mal', this)" class="mh-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Mallar</button>
            <button type="button" onclick="filterMhStatus('hizmet', this)" class="mh-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Hizmetler</button>
            <button type="button" onclick="filterMhStatus('aktif', this)" class="mh-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Aktifler</button>
            <button type="button" onclick="filterMhStatus('pasif', this)" class="mh-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Pasifler</button>
        </div>
    </div>

    <!-- 4. Mal / Hizmet Kartları Listesi -->
    <div class="space-y-2.5" id="mhListContainer">
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-purple-600">progress_activity</span>
            <p class="mt-2 font-bold">Mal ve Hizmetler yükleniyor...</p>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MAL / HİZMET EKLE / DÜZENLE MODAL (BOTTOM SHEET)               -->
<!-- ============================================================== -->
<div id="mhModal" class="fixed inset-0 z-[110] bg-slate-900/80 backdrop-blur-xs flex flex-col justify-end hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-t-[28px] w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden transform translate-y-full transition-transform duration-300" id="mhSheet">
        <!-- Header -->
        <div class="p-3.5 px-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-600 text-[22px]" id="mhModalIcon">inventory_2</span>
                <span class="text-sm font-black text-slate-900 dark:text-white" id="mhModalTitle">Yeni Mal/Hizmet Ekle</span>
            </div>
            <button type="button" onclick="closeMhModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Body Form -->
        <form id="mhForm" class="flex-1 overflow-y-auto p-4 space-y-3.5" onsubmit="handleMhSubmit(event)">
            <input type="hidden" name="enc_id" id="mh_enc_id" value="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">

            <!-- Tür Seçimi (MAL / HİZMET) -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Kayıt Türü</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer bg-slate-50 dark:bg-slate-800/60 has-[:checked]:bg-purple-50 dark:has-[:checked]:bg-purple-950/40 has-[:checked]:border-purple-600">
                        <input type="radio" name="tur" value="MAL" checked class="text-purple-600 focus:ring-0">
                        <span class="text-xs font-bold text-slate-900 dark:text-white">Ticari Mal</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer bg-slate-50 dark:bg-slate-800/60 has-[:checked]:bg-purple-50 dark:has-[:checked]:bg-purple-950/40 has-[:checked]:border-purple-600">
                        <input type="radio" name="tur" value="HIZMET" class="text-purple-600 focus:ring-0">
                        <span class="text-xs font-bold text-slate-900 dark:text-white">Hizmet / İşçilik</span>
                    </label>
                </div>
            </div>

            <!-- Mal / Hizmet Adı -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Mal / Hizmet Adı <span class="text-rose-500">*</span></label>
                <input type="text" name="urun_adi" id="inpMhUrunAdi" required placeholder="Faturada görünecek ürün veya hizmet tam adı"
                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-semibold focus:border-purple-600 focus:ring-0">
            </div>

            <!-- Stok Kodu & Barkod -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Stok Kodu</label>
                    <input type="text" name="stok_kodu" id="inpMhStokKodu" placeholder="Örn: STK-001"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:border-purple-600 focus:ring-0">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Barkod</label>
                    <input type="text" name="barkod" id="inpMhBarkod" placeholder="869..."
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:border-purple-600 focus:ring-0">
                </div>
            </div>

            <!-- Satış Fiyatı & Alış Fiyatı -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Satış Fiyatı (Birim)</label>
                    <input type="number" step="0.01" name="satis_fiyati" id="inpMhSatisFiyati" placeholder="0.00"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-purple-700 dark:text-purple-400 focus:border-purple-600 focus:ring-0">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Alış Fiyatı (Birim)</label>
                    <input type="number" step="0.01" name="alis_fiyati" id="inpMhAlisFiyati" placeholder="0.00"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-purple-600 focus:ring-0">
                </div>
            </div>

            <!-- Birim, Para Birimi & KDV Oranı -->
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Birim</label>
                    <select name="birim" id="selMhBirim" class="w-full px-2.5 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-purple-600 focus:ring-0">
                        <?php foreach ($unitCodes as $code => $unitName): ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $code === 'C62' ? 'selected' : '' ?>><?= htmlspecialchars($unitName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Döviz</label>
                    <select name="para_birimi" id="selMhParaBirimi" class="w-full px-2.5 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-purple-600 focus:ring-0">
                        <option value="TRY">TRY (₺)</option>
                        <option value="USD">USD ($)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="GBP">GBP (£)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">KDV %</label>
                    <select name="kdv_orani" id="selMhKdvOrani" class="w-full px-2.5 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-purple-600 focus:ring-0">
                        <option value="20.00">%20</option>
                        <option value="10.00">%10</option>
                        <option value="1.00">%1</option>
                        <option value="0.00">%0</option>
                    </select>
                </div>
            </div>

            <!-- GTİP No & Açıklama -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">GTİP No</label>
                    <input type="text" name="gtip_no" id="inpMhGtip" placeholder="Gümrük Tarife No"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-purple-600 focus:ring-0">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Açıklama</label>
                    <input type="text" name="aciklama" id="inpMhAciklama" placeholder="Kısa açıklama..."
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-purple-600 focus:ring-0">
                </div>
            </div>

            <!-- Seçenekler: KDV Dahil & Aktif Mi -->
            <div class="space-y-2 pt-1">
                <label class="flex items-center gap-2.5 p-2.5 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer">
                    <input type="checkbox" name="kdv_dahil_mi" id="chkMhKdvDahil" value="1" class="w-4 h-4 rounded text-purple-600 focus:ring-0">
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Fiyatlara KDV Dahildir</span>
                </label>

                <label class="flex items-center gap-2.5 p-2.5 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_active" id="chkMhIsActive" value="1" checked class="w-4 h-4 rounded text-purple-600 focus:ring-0">
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Aktif Olarak Kullanımda</span>
                </label>
            </div>

            <div class="pt-2 pb-6">
                <button type="submit" id="btnSaveMhSubmit" class="w-full py-3 bg-purple-600 text-white rounded-xl text-xs font-bold shadow-md shadow-purple-600/30 flex items-center justify-center gap-2 active:scale-98 transition-transform">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    <span id="btnSaveMhText">Mal/Hizmeti Kaydet</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let allMalHizmetler = [];
let currentMhStatusFilter = 'all';
let mhSearchTimer = null;

const unitsMap = <?= json_encode($unitCodes, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadMalHizmetler();

    document.getElementById('mhSearchInput').addEventListener('input', function() {
        const val = this.value.trim();
        const btnClear = document.getElementById('btnClearMhSearch');
        if (val.length > 0) btnClear.classList.remove('hidden');
        else btnClear.classList.add('hidden');

        clearTimeout(mhSearchTimer);
        mhSearchTimer = setTimeout(renderMalHizmetler, 200);
    });
});

function filterMhStatus(status, btn) {
    currentMhStatusFilter = status;
    document.querySelectorAll('.mh-filter-btn').forEach(b => {
        b.className = 'mh-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    if (btn) {
        btn.className = 'mh-filter-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-purple-600 text-white shadow-xs whitespace-nowrap';
    }
    renderMalHizmetler();
}

function clearMhSearch() {
    document.getElementById('mhSearchInput').value = '';
    document.getElementById('btnClearMhSearch').classList.add('hidden');
    renderMalHizmetler();
}

function loadMalHizmetler() {
    const listContainer = document.getElementById('mhListContainer');
    listContainer.innerHTML = `
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-purple-600">progress_activity</span>
            <p class="mt-2 font-bold">Mal ve Hizmetler yükleniyor...</p>
        </div>
    `;

    fetch('../api/efatura-mal-hizmet-api.php?action=list&start=0&length=1000')
        .then(r => r.json())
        .then(res => {
            allMalHizmetler = res.data || [];
            updateMhSummaryStats();
            renderMalHizmetler();
        })
        .catch(err => {
            listContainer.innerHTML = `<div class="p-4 text-center text-xs text-rose-500 font-bold">Kayıtlar alınırken hata oluştu.</div>`;
        });
}

function updateMhSummaryStats() {
    let malCount = 0;
    let hizmetCount = 0;

    allMalHizmetler.forEach(item => {
        if (item.tur === 'HIZMET') hizmetCount++;
        else malCount++;
    });

    document.getElementById('statMhTotal').textContent = allMalHizmetler.length;
    document.getElementById('statMhMal').textContent = malCount;
    document.getElementById('statMhHizmet').textContent = hizmetCount;
}

function renderMalHizmetler() {
    const listContainer = document.getElementById('mhListContainer');
    const searchVal = document.getElementById('mhSearchInput').value.trim().toLowerCase();

    const filtered = allMalHizmetler.filter(item => {
        // Durum filtresi
        if (currentMhStatusFilter === 'mal' && item.tur !== 'MAL') return false;
        if (currentMhStatusFilter === 'hizmet' && item.tur !== 'HIZMET') return false;
        if (currentMhStatusFilter === 'aktif' && parseInt(item.is_active) !== 1) return false;
        if (currentMhStatusFilter === 'pasif' && parseInt(item.is_active) !== 0) return false;

        // Arama filtresi
        if (searchVal) {
            const searchStr = `${item.urun_adi || ''} ${item.stok_kodu || ''} ${item.barkod || ''} ${item.aciklama || ''}`.toLowerCase();
            return searchStr.includes(searchVal);
        }
        return true;
    });

    if (filtered.length === 0) {
        listContainer.innerHTML = `
            <div class="bg-white dark:bg-card-dark p-8 rounded-2xl text-center border border-slate-100 dark:border-slate-700/60 shadow-xs">
                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-2xl">category</span>
                </div>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Kriterlere uygun mal / hizmet bulunamadı</p>
                <p class="text-[11px] text-slate-400 mt-1">Yeni bir kalem ekleyebilir veya arama filtrenizi temizleyebilirsiniz.</p>
            </div>
        `;
        return;
    }

    let html = '';
    filtered.forEach(item => {
        const isHizmet = item.tur === 'HIZMET';
        const turBadgeClass = isHizmet ? 'bg-sky-100 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400' : 'bg-purple-100 dark:bg-purple-950/40 text-purple-700 dark:text-purple-400';
        const turBadgeText = isHizmet ? 'Hizmet' : 'Mal';
        const unitLabel = unitsMap[item.birim] || item.birim || 'Adet';
        const satisFiyat = Number(item.satis_fiyati || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const currency = item.para_birimi === 'USD' ? '$' : (item.para_birimi === 'EUR' ? '€' : '₺');
        const isActive = parseInt(item.is_active) === 1;

        html += `
        <div class="bg-white dark:bg-card-dark rounded-2xl p-3.5 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-2.5">
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-xs text-slate-900 dark:text-white line-clamp-1">${item.urun_adi || 'İsimsiz Kalem'}</span>
                    </div>
                    ${item.stok_kodu ? `<p class="font-mono text-[10px] text-slate-400 font-medium">Kod: ${item.stok_kodu}</p>` : ''}
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${turBadgeClass}">${turBadgeText}</span>
                    ${!isActive ? '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 text-rose-700">Pasif</span>' : ''}
                </div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-500 bg-slate-50 dark:bg-slate-800/40 p-2 rounded-xl">
                <div>
                    <span class="text-slate-400">Birim & Vergi:</span>
                    <strong class="text-slate-700 dark:text-slate-200">${unitLabel} • %${parseInt(item.kdv_orani || 20)} KDV</strong>
                </div>
                <div class="text-right">
                    <span class="text-slate-400">Satış Fiyatı:</span>
                    <div class="font-black text-xs sm:text-sm text-purple-700 dark:text-purple-400">${satisFiyat} ${currency}</div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800 text-xs">
                <span class="text-[10px] text-slate-400">${item.barkod ? 'Barkod: ' + item.barkod : (item.gtip_no ? 'GTİP: ' + item.gtip_no : '')}</span>

                <div class="flex items-center gap-1">
                    <button type="button" onclick="openEditMhModal('${item.enc_id}')" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center active:scale-95 transition-transform" title="Düzenle">
                        <span class="material-symbols-outlined text-[17px]">edit</span>
                    </button>
                    <button type="button" onclick="deleteMh('${item.enc_id}', '${escapeJs(item.urun_adi)}')" class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-900/30 text-rose-600 flex items-center justify-center active:scale-95 transition-transform" title="Sil">
                        <span class="material-symbols-outlined text-[17px]">delete</span>
                    </button>
                </div>
            </div>
        </div>`;
    });

    listContainer.innerHTML = html;
}

function openAddMhModal() {
    document.getElementById('mhForm').reset();
    document.getElementById('mh_enc_id').value = '';
    document.getElementById('mhModalTitle').textContent = 'Yeni Mal / Hizmet Ekle';
    document.getElementById('mhModalIcon').textContent = 'add_box';
    document.getElementById('btnSaveMhText').textContent = 'Mal/Hizmeti Kaydet';
    document.getElementById('chkMhIsActive').checked = true;
    document.getElementById('chkMhKdvDahil').checked = false;

    showMhSheet();
}

function openEditMhModal(encId) {
    fetch(`../api/efatura-mal-hizmet-api.php?action=get&id=${encodeURIComponent(encId)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const d = res.data;
                document.getElementById('mhForm').reset();
                document.getElementById('mh_enc_id').value = d.enc_id || encId;
                document.getElementById('mhModalTitle').textContent = 'Mal / Hizmeti Düzenle';
                document.getElementById('mhModalIcon').textContent = 'edit';
                document.getElementById('btnSaveMhText').textContent = 'Değişiklikleri Kaydet';

                const radios = document.querySelectorAll('input[name="tur"]');
                radios.forEach(r => {
                    r.checked = (r.value === (d.tur || 'MAL'));
                });

                document.getElementById('inpMhUrunAdi').value = d.urun_adi || '';
                document.getElementById('inpMhStokKodu').value = d.stok_kodu || '';
                document.getElementById('inpMhBarkod').value = d.barkod || '';
                document.getElementById('inpMhSatisFiyati').value = d.satis_fiyati || '';
                document.getElementById('inpMhAlisFiyati').value = d.alis_fiyati || '';
                document.getElementById('selMhBirim').value = d.birim || 'C62';
                document.getElementById('selMhParaBirimi').value = d.para_birimi || 'TRY';
                document.getElementById('selMhKdvOrani').value = parseFloat(d.kdv_orani || 20).toFixed(2);
                document.getElementById('inpMhGtip').value = d.gtip_no || '';
                document.getElementById('inpMhAciklama').value = d.aciklama || '';

                document.getElementById('chkMhKdvDahil').checked = (parseInt(d.kdv_dahil_mi) === 1);
                document.getElementById('chkMhIsActive').checked = (parseInt(d.is_active) === 1);

                showMhSheet();
            } else {
                alert(res.message || 'Kayıt bilgisi getirilemedi.');
            }
        })
        .catch(err => {
            alert('Yüklenirken hata oluştu.');
        });
}

function showMhSheet() {
    const modal = document.getElementById('mhModal');
    const sheet = document.getElementById('mhSheet');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function closeMhModal() {
    const modal = document.getElementById('mhModal');
    const sheet = document.getElementById('mhSheet');
    sheet.classList.add('translate-y-full');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function handleMhSubmit(e) {
    e.preventDefault();
    const form = document.getElementById('mhForm');
    const btnSubmit = document.getElementById('btnSaveMhSubmit');
    const urunAdi = document.getElementById('inpMhUrunAdi').value.trim();

    if (!urunAdi) {
        alert('Lütfen Mal/Hizmet Adını giriniz.');
        return;
    }

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span><span>Kaydediliyor...</span>';

    const formData = new FormData(form);

    fetch('../api/efatura-mal-hizmet-api.php?action=save', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[18px]">save</span><span>Kaydet</span>';

        if (res.status === 'success') {
            closeMhModal();
            loadMalHizmetler();
        } else {
            alert(res.message || 'Kayıt sırasında hata oluştu.');
        }
    })
    .catch(err => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[18px]">save</span><span>Kaydet</span>';
        alert('Sunucu hatası oluştu.');
    });
}

function deleteMh(encId, urunAdi) {
    if (!confirm(`"${urunAdi}" kaydını silmek istediğinizden emin misiniz?`)) return;

    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helper\Security::csrf() ?>');
    fd.append('id', encId);

    fetch('../api/efatura-mal-hizmet-api.php?action=delete', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            loadMalHizmetler();
        } else {
            alert(res.message || 'Kayıt silinemedi.');
        }
    })
    .catch(err => {
        alert('Silinirken sunucu hatası oluştu.');
    });
}

function escapeJs(text) {
    return String(text || '').replace(/'/g, "\\'");
}
</script>
