<?php
\App\Service\Gate::authorizeOrDie('efatura/cari-list');
use App\Model\EFaturaCariModel;
use App\Helper\Security;

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);

// İl ve İlçe listesi
$edmJsonPath = dirname(__DIR__, 2) . '/views/efatura/data/edm_kod_listesi.json';
$edmKodListesi = file_exists($edmJsonPath) ? json_decode(file_get_contents($edmJsonPath), true) : [];
$sehirler = $edmKodListesi['cities'] ?? [];
$ilceler = $edmKodListesi['districts'] ?? [];
?>

<script>
const EDM_DISTRICTS = <?= json_encode($ilceler, JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="px-3.5 py-4 space-y-3.5 pb-24">

    <!-- 1. Üst Başlık & Ekle Butonu -->
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
            <a href="?p=efatura" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">arrow_back</span>
            </a>
            <div>
                <h1 class="text-base font-black text-slate-900 dark:text-white leading-tight">E-Fatura Carileri</h1>
                <p class="text-[11px] font-medium text-slate-500">Müşteri ve tedarikçi tanımları</p>
            </div>
        </div>

        <button type="button" onclick="openAddCariModal()" class="flex items-center gap-1 px-3 py-2 rounded-xl bg-primary text-white text-xs font-bold shadow-sm shadow-primary/30 active:scale-95 transition-transform">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            <span>Yeni Cari</span>
        </button>
    </div>

    <!-- 2. Özet Bilgi & Sayı Çubuğu -->
    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-indigo-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Toplam Cari</p>
            <p class="font-black text-slate-900 dark:text-white text-xs sm:text-sm mt-0.5" id="statCariTotal">0</p>
            <p class="text-[10px] text-slate-400 font-medium">Kayıt</p>
        </div>

        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-emerald-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">e-Fatura</p>
            <p class="font-black text-emerald-600 text-xs sm:text-sm mt-0.5" id="statCariEfatura">0</p>
            <p class="text-[10px] text-emerald-600/70 font-medium">GİB Kayıtlı</p>
        </div>

        <div class="bg-white dark:bg-card-dark rounded-xl p-2.5 border-b-2 border-amber-500 text-center shadow-xs">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">e-Arşiv</p>
            <p class="font-black text-amber-600 text-xs sm:text-sm mt-0.5" id="statCariEarsiv">0</p>
            <p class="text-[10px] text-amber-600/70 font-medium">Klasik / Diğer</p>
        </div>
    </div>

    <!-- 3. Arama Kutusu ve Hızlı Filtre Butonları -->
    <div class="space-y-2">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="material-symbols-outlined text-slate-400 text-lg">search</span>
            </div>
            <input type="text" id="cariSearchInput" placeholder="Cari Ünvanı, VKN/TCKN, İl veya Telefon Ara..." autocomplete="off"
                   class="w-full pl-9 pr-8 py-2.5 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-700 focus:border-primary focus:ring-0 rounded-xl shadow-xs text-xs text-slate-900 dark:text-white placeholder-slate-400">
            <button type="button" id="btnClearCariSearch" onclick="clearCariSearch()" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 hidden">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- Hızlı Filtre Hapları -->
        <div class="flex items-center gap-1 overflow-x-auto no-scrollbar py-0.5" id="cariFilterGroup">
            <button type="button" onclick="filterCariStatus('all', this)" class="cari-filter-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap">Tümü</button>
            <button type="button" onclick="filterCariStatus('efatura', this)" class="cari-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">e-Fatura</button>
            <button type="button" onclick="filterCariStatus('earsiv', this)" class="cari-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">e-Arşiv</button>
            <button type="button" onclick="filterCariStatus('kurumsal', this)" class="cari-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Kurumsal</button>
            <button type="button" onclick="filterCariStatus('bireysel', this)" class="cari-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Bireysel</button>
        </div>
    </div>

    <!-- 4. Cari Kartları Listesi -->
    <div class="space-y-2.5" id="cariListContainer">
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-indigo-600">progress_activity</span>
            <p class="mt-2 font-bold">Cariler yükleniyor...</p>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- CARİ EKLE / DÜZENLE MODAL (BOTTOM SHEET)                       -->
<!-- ============================================================== -->
<div id="cariModal" class="fixed inset-0 z-[110] bg-slate-900/80 backdrop-blur-xs flex flex-col justify-end hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-t-[28px] w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden transform translate-y-full transition-transform duration-300" id="cariSheet">
        <!-- Header -->
        <div class="p-3.5 px-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-600 text-[22px]" id="cariModalIcon">person_add</span>
                <span class="text-sm font-black text-slate-900 dark:text-white" id="cariModalTitle">Yeni Cari Ekle</span>
            </div>
            <button type="button" onclick="closeCariModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Body Form -->
        <form id="cariForm" class="flex-1 overflow-y-auto p-4 space-y-3.5" onsubmit="handleCariSubmit(event)">
            <input type="hidden" name="enc_id" id="cari_enc_id" value="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">

            <!-- VKN / TCKN & GİB Sorgula -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">VKN / TCKN <span class="text-rose-500">*</span></label>
                <div class="flex gap-2">
                    <input type="text" name="vkn_tckn" id="inpCariVkn" required maxlength="11" placeholder="10 haneli VKN veya 11 haneli TCKN"
                           class="flex-1 px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono font-bold focus:border-primary focus:ring-0">
                    <button type="button" id="btnGibCheck" onclick="checkTaxpayerUser()" class="px-3 py-2 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 font-bold text-xs rounded-xl flex items-center gap-1 shrink-0 active:scale-95 transition-transform">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                        <span>GİB Sorgula</span>
                    </button>
                </div>
                <div id="gibStatusBadge" class="mt-1.5 hidden">
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md" id="gibStatusText"></span>
                </div>
            </div>

            <!-- Cari Ünvan / Ad Soyad -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Cari Resmî Ünvanı / Ad Soyad <span class="text-rose-500">*</span></label>
                <input type="text" name="unvan" id="inpCariUnvan" required placeholder="Firma tam ünvanı veya şahıs adı soyadı"
                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-semibold focus:border-primary focus:ring-0">
            </div>

            <!-- Kısa Ad & Cari Kodu -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Kısa Ad / Rumuz</label>
                    <input type="text" name="kisa_ad" id="inpCariKisaAd" placeholder="Örn: Ersan Elektrik"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Cari Kodu</label>
                    <input type="text" name="cari_kodu" id="inpCariKodu" placeholder="Örn: CARI-001"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:border-primary focus:ring-0">
                </div>
            </div>

            <!-- Alıcı Türü & Belge Türü -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Alıcı Türü</label>
                    <select name="alici_turu" id="selCariAliciTuru" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
                        <option value="KURUMSAL">Kurumsal Firma</option>
                        <option value="BIREYSEL">Bireysel Şahıs</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Belge Türü</label>
                    <select name="belge_turu" id="selCariBelgeTuru" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
                        <option value="OTOMATIK">Otomatik (GİB)</option>
                        <option value="EFATURA">e-Fatura</option>
                        <option value="EARSIV">e-Arşiv</option>
                    </select>
                </div>
            </div>

            <!-- Vergi Dairesi & GB Posta Kutusu -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Vergi Dairesi</label>
                    <input type="text" name="vergi_dairesi" id="inpCariVd" placeholder="Vergi dairesi adı"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">GB Alias (Posta Kutusu)</label>
                    <input type="text" name="posta_kutusu" id="inpCariGbAlias" placeholder="urn:mail:defaultgb@..."
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:border-primary focus:ring-0">
                </div>
            </div>

            <!-- Telefon & E-Posta -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Telefon</label>
                    <input type="text" name="telefon" id="inpCariTel" placeholder="05XX XXX XX XX"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">E-Posta</label>
                    <input type="email" name="eposta" id="inpCariEposta" placeholder="ornek@firma.com"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
                </div>
            </div>

            <!-- İl & İlçe -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">İl</label>
                    <select name="il" id="selCariIl" onchange="handleCityChange(this.value)" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
                        <option value="">İl Seçiniz...</option>
                        <?php foreach ($sehirler as $sehir): ?>
                            <option value="<?= htmlspecialchars($sehir) ?>"><?= htmlspecialchars($sehir) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">İlçe</label>
                    <select name="ilce" id="selCariIlce" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
                        <option value="">İlçe Seçiniz...</option>
                    </select>
                </div>
            </div>

            <!-- Açık Adres -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Açık Adres</label>
                <textarea name="adres" id="inpCariAdres" rows="2" placeholder="Cadde, sokak, kapı no vb."
                          class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0"></textarea>
            </div>

            <!-- Notlar -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Dahili Notlar</label>
                <input type="text" name="notlar" id="inpCariNotlar" placeholder="Özel notlar veya açıklamalar..."
                       class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:border-primary focus:ring-0">
            </div>

            <div class="pt-2 pb-6">
                <button type="submit" id="btnSaveCariSubmit" class="w-full py-3 bg-primary text-white rounded-xl text-xs font-bold shadow-md shadow-primary/30 flex items-center justify-center gap-2 active:scale-98 transition-transform">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    <span id="btnSaveCariText">Cariyi Kaydet</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let allCariler = [];
let currentCariStatusFilter = 'all';
let cariSearchTimer = null;

document.addEventListener('DOMContentLoaded', function() {
    loadCariler();

    document.getElementById('cariSearchInput').addEventListener('input', function() {
        const val = this.value.trim();
        const btnClear = document.getElementById('btnClearCariSearch');
        if (val.length > 0) btnClear.classList.remove('hidden');
        else btnClear.classList.add('hidden');

        clearTimeout(cariSearchTimer);
        cariSearchTimer = setTimeout(renderCariler, 200);
    });
});

function handleCityChange(selectedCity, selectedDistrict = '') {
    const ilceSelect = document.getElementById('selCariIlce');
    ilceSelect.innerHTML = '<option value="">İlçe Seçiniz...</option>';

    if (selectedCity && typeof EDM_DISTRICTS !== 'undefined' && EDM_DISTRICTS[selectedCity]) {
        EDM_DISTRICTS[selectedCity].forEach(d => {
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            if (d === selectedDistrict) opt.selected = true;
            ilceSelect.appendChild(opt);
        });
    }
}

function filterCariStatus(status, btn) {
    currentCariStatusFilter = status;
    document.querySelectorAll('.cari-filter-btn').forEach(b => {
        b.className = 'cari-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    if (btn) {
        btn.className = 'cari-filter-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap';
    }
    renderCariler();
}

function clearCariSearch() {
    document.getElementById('cariSearchInput').value = '';
    document.getElementById('btnClearCariSearch').classList.add('hidden');
    renderCariler();
}

function loadCariler() {
    const listContainer = document.getElementById('cariListContainer');
    listContainer.innerHTML = `
        <div class="py-12 text-center text-slate-400 text-xs">
            <span class="material-symbols-outlined text-3xl animate-spin text-indigo-600">progress_activity</span>
            <p class="mt-2 font-bold">Cariler yükleniyor...</p>
        </div>
    `;

    fetch('../api/efatura-cari-api.php?action=list&start=0&length=1000')
        .then(r => r.json())
        .then(res => {
            allCariler = res.data || [];
            updateCariSummaryStats();
            renderCariler();
        })
        .catch(err => {
            listContainer.innerHTML = `<div class="p-4 text-center text-xs text-rose-500 font-bold">Cariler alınırken hata oluştu.</div>`;
        });
}

function updateCariSummaryStats() {
    let efaturaCount = 0;
    let earsivCount = 0;

    allCariler.forEach(c => {
        if (c.belge_turu === 'EFATURA') efaturaCount++;
        else earsivCount++;
    });

    document.getElementById('statCariTotal').textContent = allCariler.length;
    document.getElementById('statCariEfatura').textContent = efaturaCount;
    document.getElementById('statCariEarsiv').textContent = earsivCount;
}

function renderCariler() {
    const listContainer = document.getElementById('cariListContainer');
    const searchVal = document.getElementById('cariSearchInput').value.trim().toLowerCase();

    const filtered = allCariler.filter(c => {
        // Durum filtresi
        if (currentCariStatusFilter === 'efatura' && c.belge_turu !== 'EFATURA') return false;
        if (currentCariStatusFilter === 'earsiv' && c.belge_turu === 'EFATURA') return false;
        if (currentCariStatusFilter === 'kurumsal' && c.alici_turu !== 'KURUMSAL') return false;
        if (currentCariStatusFilter === 'bireysel' && c.alici_turu !== 'BIREYSEL') return false;

        // Arama filtresi
        if (searchVal) {
            const searchStr = `${c.unvan || ''} ${c.kisa_ad || ''} ${c.vkn_tckn || ''} ${c.il || ''} ${c.ilce || ''} ${c.telefon || ''} ${c.eposta || ''}`.toLowerCase();
            return searchStr.includes(searchVal);
        }
        return true;
    });

    if (filtered.length === 0) {
        listContainer.innerHTML = `
            <div class="bg-white dark:bg-card-dark p-8 rounded-2xl text-center border border-slate-100 dark:border-slate-700/60 shadow-xs">
                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-2xl">person_off</span>
                </div>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Kriterlere uygun cari bulunamadı</p>
                <p class="text-[11px] text-slate-400 mt-1">Yeni bir cari ekleyebilir veya arama filtrenizi temizleyebilirsiniz.</p>
            </div>
        `;
        return;
    }

    let html = '';
    filtered.forEach(c => {
        const isEfatura = c.belge_turu === 'EFATURA';
        const badgeClass = isEfatura ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400';
        const badgeText = isEfatura ? 'e-Fatura' : (c.belge_turu === 'EARSIV' ? 'e-Arşiv' : 'Otomatik');

        html += `
        <div class="bg-white dark:bg-card-dark rounded-2xl p-3.5 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-2.5">
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-xs text-slate-900 dark:text-white line-clamp-1">${c.unvan || 'İsimsiz Cari'}</span>
                    </div>
                    ${c.kisa_ad ? `<p class="text-[11px] text-slate-400 font-medium">${c.kisa_ad}</p>` : ''}
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0 ${badgeClass}">${badgeText}</span>
            </div>

            <div class="grid grid-cols-2 gap-1 text-[11px] text-slate-500 bg-slate-50 dark:bg-slate-800/40 p-2 rounded-xl">
                <div>
                    <span class="text-slate-400">VKN/TCKN:</span>
                    <strong class="font-mono text-slate-700 dark:text-slate-200">${c.vkn_tckn || '-'}</strong>
                </div>
                <div>
                    <span class="text-slate-400">Konum:</span>
                    <span class="text-slate-700 dark:text-slate-200 font-medium">${c.il ? (c.ilce ? c.ilce + ' / ' + c.il : c.il) : '-'}</span>
                </div>
                ${c.telefon ? `
                <div>
                    <span class="text-slate-400">Tel:</span>
                    <span class="text-slate-700 dark:text-slate-200">${c.telefon}</span>
                </div>` : ''}
                ${c.eposta ? `
                <div class="truncate">
                    <span class="text-slate-400">E-Posta:</span>
                    <span class="text-slate-700 dark:text-slate-200">${c.eposta}</span>
                </div>` : ''}
            </div>

            <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800 text-xs">
                <a href="?p=efatura-olustur&cari_id=${c.enc_id}" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-bold text-[11px] active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-[15px]">receipt_long</span>
                    <span>Fatura Kes</span>
                </a>

                <div class="flex items-center gap-1">
                    <button type="button" onclick="openEditCariModal('${c.enc_id}')" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center active:scale-95 transition-transform" title="Düzenle">
                        <span class="material-symbols-outlined text-[17px]">edit</span>
                    </button>
                    <button type="button" onclick="deleteCari('${c.enc_id}', '${escapeJs(c.unvan)}')" class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-900/30 text-rose-600 flex items-center justify-center active:scale-95 transition-transform" title="Sil">
                        <span class="material-symbols-outlined text-[17px]">delete</span>
                    </button>
                </div>
            </div>
        </div>`;
    });

    listContainer.innerHTML = html;
}

function openAddCariModal() {
    document.getElementById('cariForm').reset();
    document.getElementById('cari_enc_id').value = '';
    document.getElementById('cariModalTitle').textContent = 'Yeni Cari Ekle';
    document.getElementById('cariModalIcon').textContent = 'person_add';
    document.getElementById('btnSaveCariText').textContent = 'Cariyi Kaydet';
    document.getElementById('gibStatusBadge').classList.add('hidden');
    document.getElementById('selCariIlce').innerHTML = '<option value="">İlçe Seçiniz...</option>';

    showModalSheet();
}

function openEditCariModal(encId) {
    fetch(`../api/efatura-cari-api.php?action=get&id=${encodeURIComponent(encId)}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const d = res.data;
                document.getElementById('cariForm').reset();
                document.getElementById('cari_enc_id').value = d.enc_id || encId;
                document.getElementById('cariModalTitle').textContent = 'Cariyi Düzenle';
                document.getElementById('cariModalIcon').textContent = 'edit';
                document.getElementById('btnSaveCariText').textContent = 'Değişiklikleri Kaydet';

                document.getElementById('inpCariVkn').value = d.vkn_tckn || '';
                document.getElementById('inpCariUnvan').value = d.unvan || '';
                document.getElementById('inpCariKisaAd').value = d.kisa_ad || '';
                document.getElementById('inpCariKodu').value = d.cari_kodu || '';
                document.getElementById('selCariAliciTuru').value = d.alici_turu || 'KURUMSAL';
                document.getElementById('selCariBelgeTuru').value = d.belge_turu || 'OTOMATIK';
                document.getElementById('inpCariVd').value = d.vergi_dairesi || '';
                document.getElementById('inpCariGbAlias').value = d.posta_kutusu || '';
                document.getElementById('inpCariTel').value = d.telefon || '';
                document.getElementById('inpCariEposta').value = d.eposta || '';
                document.getElementById('inpCariAdres').value = d.adres || '';
                document.getElementById('inpCariNotlar').value = d.notlar || '';

                document.getElementById('selCariIl').value = d.il || '';
                handleCityChange(d.il || '', d.ilce || '');

                document.getElementById('gibStatusBadge').classList.add('hidden');
                showModalSheet();
            } else {
                alert(res.message || 'Cari bilgisi getirilemedi.');
            }
        })
        .catch(err => {
            alert('Cari yüklenirken hata oluştu.');
        });
}

function showModalSheet() {
    const modal = document.getElementById('cariModal');
    const sheet = document.getElementById('cariSheet');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function closeCariModal() {
    const modal = document.getElementById('cariModal');
    const sheet = document.getElementById('cariSheet');
    sheet.classList.add('translate-y-full');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function checkTaxpayerUser() {
    const vkn = document.getElementById('inpCariVkn').value.replace(/\D/g, '');
    if (vkn.length !== 10 && vkn.length !== 11) {
        alert('Lütfen geçerli 10 haneli VKN veya 11 haneli TCKN giriniz.');
        return;
    }

    const btn = document.getElementById('btnGibCheck');
    const badge = document.getElementById('gibStatusBadge');
    const text = document.getElementById('gibStatusText');

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span><span>Sorgulanıyor...</span>';

    fetch(`../api/efatura-cari-api.php?action=check_taxpayer&vkn=${encodeURIComponent(vkn)}`)
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">search</span><span>GİB Sorgula</span>';

            if (res.status === 'success' && res.data) {
                const d = res.data;
                badge.classList.remove('hidden');

                if (d.is_taxpayer) {
                    text.className = 'inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800';
                    text.innerHTML = '<span class="material-symbols-outlined text-[14px]">check_circle</span> e-Fatura Mükellefi Tespit Edildi';
                    document.getElementById('selCariBelgeTuru').value = 'EFATURA';
                    document.getElementById('selCariAliciTuru').value = vkn.length === 10 ? 'KURUMSAL' : 'BIREYSEL';

                    if (d.title && !document.getElementById('inpCariUnvan').value) {
                        document.getElementById('inpCariUnvan').value = d.title;
                    }
                    if (d.alias) {
                        document.getElementById('inpCariGbAlias').value = d.alias;
                    }
                } else {
                    text.className = 'inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-800';
                    text.innerHTML = '<span class="material-symbols-outlined text-[14px]">info</span> e-Fatura Mükellefi Değil (e-Arşiv Fatura)';
                    document.getElementById('selCariBelgeTuru').value = 'EARSIV';
                }
            } else {
                alert(res.message || 'GİB mükellefiyet sorgusu başarısız.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">search</span><span>GİB Sorgula</span>';
            alert('GİB sorgusu sırasında hata oluştu.');
        });
}

function handleCariSubmit(e) {
    e.preventDefault();
    const form = document.getElementById('cariForm');
    const btnSubmit = document.getElementById('btnSaveCariSubmit');
    const vkn = document.getElementById('inpCariVkn').value.trim();
    const unvan = document.getElementById('inpCariUnvan').value.trim();

    if (!vkn || !unvan) {
        alert('Lütfen zorunlu alanları (VKN/TCKN ve Ünvan) doldurunuz.');
        return;
    }

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span><span>Kaydediliyor...</span>';

    const formData = new FormData(form);

    fetch('../api/efatura-cari-api.php?action=save', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[18px]">save</span><span>Kaydet</span>';

        if (res.status === 'success') {
            closeCariModal();
            loadCariler();
        } else {
            alert(res.message || 'Cari kaydedilirken hata oluştu.');
        }
    })
    .catch(err => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[18px]">save</span><span>Kaydet</span>';
        alert('Sunucu hatası oluştu.');
    });
}

function deleteCari(encId, unvan) {
    if (!confirm(`"${unvan}" carisini silmek istediğinizden emin misiniz?`)) return;

    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helper\Security::csrf() ?>');
    fd.append('id', encId);

    fetch('../api/efatura-cari-api.php?action=delete', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            loadCariler();
        } else {
            alert(res.message || 'Cari silinemedi.');
        }
    })
    .catch(err => {
        alert('Cari silinirken sunucu hatası oluştu.');
    });
}

function escapeJs(text) {
    return String(text || '').replace(/'/g, "\\'");
}
</script>
