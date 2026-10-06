<?php
\App\Service\Gate::authorizeOrDie('efatura/dashboard');
use App\Model\EInvoiceModel;
use App\Helper\Security;

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$invoiceModel = new EInvoiceModel();

// Başlangıçta bu ayın verilerini alalım
$startDate = date('Y-m-01');
$endDate = date('Y-m-t');
$dashData = $invoiceModel->getDashboardData($firmId, $startDate, $endDate);

$gelen = $dashData['gelen'] ?? [];
$giden = $dashData['giden'] ?? [];
$kdv = $dashData['kdv'] ?? [];
$tahsilat = $dashData['tahsilat'] ?? [];

function fmtMobMoney($val) {
    return number_format((float)$val, 2, ',', '.') . ' ₺';
}
?>

<div class="px-3.5 py-4 space-y-4 pb-24">

    <!-- 1. Üst Başlık & Hızlı Dönem Filtreleri -->
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 flex items-center justify-center font-bold shadow-xs">
                <span class="material-symbols-outlined text-2xl">receipt_long</span>
            </div>
            <div>
                <h1 class="text-base font-black text-slate-900 dark:text-white leading-tight">E-Fatura & Finans</h1>
                <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Aylık fatura ve KDV özeti</p>
            </div>
        </div>

        <a href="?p=efatura-olustur" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-primary text-white text-xs font-bold shadow-sm shadow-primary/30 active:scale-95 transition-transform">
            <span class="material-symbols-outlined text-[18px]">add_circle</span>
            <span>Fatura Kes</span>
        </a>
    </div>

    <!-- Hızlı Filtre Butonları -->
    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1" id="mobPeriodButtons">
        <button type="button" onclick="changeMobPeriod('this_month', this)" class="mob-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap">Bu Ay</button>
        <button type="button" onclick="changeMobPeriod('last_month', this)" class="mob-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Geçen Ay</button>
        <button type="button" onclick="changeMobPeriod('this_year', this)" class="mob-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap"><?= date('Y') ?> Yılı</button>
        <button type="button" onclick="changeMobPeriod('all', this)" class="mob-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap">Tümü</button>
    </div>

    <!-- 2. Ana Finans & KDV Özet Kartları -->
    <div class="grid grid-cols-2 gap-2.5">
        <!-- Kart 1: Giden Faturalar (Satış) -->
        <a href="?p=efatura-giden" class="bg-white dark:bg-card-dark rounded-2xl p-3.5 border border-slate-100 dark:border-slate-700/60 shadow-sm relative overflow-hidden block active:scale-[0.98] transition-transform">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">GİDEN (SATIŞ)</span>
                <span class="material-symbols-outlined text-emerald-500 text-[20px] bg-emerald-50 dark:bg-emerald-900/30 w-7 h-7 rounded-lg flex items-center justify-center">upload</span>
            </div>
            <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white truncate" id="mob_giden_tutar"><?= fmtMobMoney($giden['toplam_tutar'] ?? 0) ?></div>
            <div class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-[11px]">
                <span class="text-slate-500 font-medium">Matrah:</span>
                <span class="font-bold text-slate-700 dark:text-slate-300" id="mob_giden_matrah"><?= fmtMobMoney($giden['toplam_matrah'] ?? 0) ?></span>
            </div>
            <div class="flex justify-between items-center text-[11px] mt-0.5">
                <span class="text-slate-500 font-medium">KDV:</span>
                <span class="font-bold text-emerald-600" id="mob_giden_kdv"><?= fmtMobMoney($giden['toplam_kdv'] ?? 0) ?></span>
            </div>
            <div class="mt-2 flex items-center justify-between text-[10px] text-slate-400">
                <span id="mob_giden_adet"><?= (int)($giden['toplam_adet'] ?? 0) ?> Fatura</span>
                <span class="text-emerald-500 font-bold flex items-center">Detay <span class="material-symbols-outlined text-[14px]">chevron_right</span></span>
            </div>
        </a>

        <!-- Kart 2: Gelen Faturalar (Alış) -->
        <a href="?p=efatura-gelen" class="bg-white dark:bg-card-dark rounded-2xl p-3.5 border border-slate-100 dark:border-slate-700/60 shadow-sm relative overflow-hidden block active:scale-[0.98] transition-transform">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-sky-600 dark:text-sky-400">GELEN (ALIŞ)</span>
                <span class="material-symbols-outlined text-sky-500 text-[20px] bg-sky-50 dark:bg-sky-900/30 w-7 h-7 rounded-lg flex items-center justify-center">download</span>
            </div>
            <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white truncate" id="mob_gelen_tutar"><?= fmtMobMoney($gelen['toplam_tutar'] ?? 0) ?></div>
            <div class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-[11px]">
                <span class="text-slate-500 font-medium">Matrah:</span>
                <span class="font-bold text-slate-700 dark:text-slate-300" id="mob_gelen_matrah"><?= fmtMobMoney($gelen['toplam_matrah'] ?? 0) ?></span>
            </div>
            <div class="flex justify-between items-center text-[11px] mt-0.5">
                <span class="text-slate-500 font-medium">KDV:</span>
                <span class="font-bold text-sky-600" id="mob_gelen_kdv"><?= fmtMobMoney($gelen['toplam_kdv'] ?? 0) ?></span>
            </div>
            <div class="mt-2 flex items-center justify-between text-[10px] text-slate-400">
                <span id="mob_gelen_adet"><?= (int)($gelen['toplam_adet'] ?? 0) ?> Fatura</span>
                <span class="text-sky-500 font-bold flex items-center">Detay <span class="material-symbols-outlined text-[14px]">chevron_right</span></span>
            </div>
        </a>
    </div>

    <!-- Kart 3: Net KDV Durumu (Vurgulu) -->
    <?php
    $netKdv = (float)($kdv['net_kdv'] ?? 0);
    $isPayable = $netKdv > 0;
    ?>
    <div class="rounded-2xl p-4 border <?= $isPayable ? 'bg-rose-50/70 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/50' : 'bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/50' ?> shadow-sm" id="mob_kdv_box">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined <?= $isPayable ? 'text-rose-600' : 'text-emerald-600' ?> text-[22px]" id="mob_kdv_icon">
                    <?= $isPayable ? 'error' : 'check_circle' ?>
                </span>
                <span class="text-xs font-black uppercase tracking-wider <?= $isPayable ? 'text-rose-800 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-300' ?>" id="mob_kdv_title">
                    <?= $isPayable ? 'ÖDENECEK KDV (MALİYE)' : 'SONRAKİ DÖNEME DEVREDEN KDV' ?>
                </span>
            </div>
            <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $isPayable ? 'bg-rose-200/80 text-rose-800' : 'bg-emerald-200/80 text-emerald-800' ?>">Net Durum</span>
        </div>
        <div class="text-xl font-black <?= $isPayable ? 'text-rose-600' : 'text-emerald-600' ?>" id="mob_kdv_amount">
            <?= fmtMobMoney($isPayable ? ($kdv['odenecek_kdv'] ?? 0) : ($kdv['devreden_kdv'] ?? 0)) ?>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-3 pt-2.5 border-t <?= $isPayable ? 'border-rose-200/60 dark:border-rose-900/40' : 'border-emerald-200/60 dark:border-emerald-900/40' ?> text-[11px]">
            <div>
                <span class="text-slate-500 font-medium">Hesaplanan (Satış):</span>
                <div class="font-bold text-slate-800 dark:text-slate-200" id="mob_hesaplanan_kdv"><?= fmtMobMoney($kdv['hesaplanan_kdv'] ?? 0) ?></div>
            </div>
            <div>
                <span class="text-slate-500 font-medium">İndirilecek (Alış):</span>
                <div class="font-bold text-slate-800 dark:text-slate-200" id="mob_indirilecek_kdv"><?= fmtMobMoney($kdv['indirilecek_kdv'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <!-- 3. Hızlı Modül Menüleri Grid -->
    <div>
        <h2 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-2 px-1">E-Fatura İşlemleri</h2>
        <div class="grid grid-cols-3 gap-2">
            <?php if (\App\Service\Gate::allows('efatura/olustur')): ?>
            <a href="?p=efatura-olustur" class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-700/60 flex flex-col items-center justify-center text-center shadow-xs active:scale-95 transition-transform">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-1.5">
                    <span class="material-symbols-outlined text-[22px]">post_add</span>
                </div>
                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200">Fatura Kes</span>
            </a>
            <?php endif; ?>

            <?php if (\App\Service\Gate::allows('efatura/giden-list')): ?>
            <a href="?p=efatura-giden" class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-700/60 flex flex-col items-center justify-center text-center shadow-xs active:scale-95 transition-transform">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 flex items-center justify-center mb-1.5">
                    <span class="material-symbols-outlined text-[22px]">upload_file</span>
                </div>
                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200">Gidenler</span>
            </a>
            <?php endif; ?>

            <?php if (\App\Service\Gate::allows('efatura/gelen-list')): ?>
            <a href="?p=efatura-gelen" class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-700/60 flex flex-col items-center justify-center text-center shadow-xs active:scale-95 transition-transform">
                <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-900/30 text-sky-600 flex items-center justify-center mb-1.5">
                    <span class="material-symbols-outlined text-[22px]">download_for_offline</span>
                </div>
                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200">Gelenler</span>
            </a>
            <?php endif; ?>

            <?php if (\App\Service\Gate::allows('efatura/taslak-list')): ?>
            <a href="?p=efatura-taslak" class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-700/60 flex flex-col items-center justify-center text-center shadow-xs active:scale-95 transition-transform">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center mb-1.5">
                    <span class="material-symbols-outlined text-[22px]">edit_note</span>
                </div>
                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200">Taslaklar</span>
            </a>
            <?php endif; ?>

            <?php if (\App\Service\Gate::allows('efatura/cari-list')): ?>
            <a href="?p=efatura-cari" class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-700/60 flex flex-col items-center justify-center text-center shadow-xs active:scale-95 transition-transform">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 flex items-center justify-center mb-1.5">
                    <span class="material-symbols-outlined text-[22px]">contact_page</span>
                </div>
                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200">Cariler</span>
            </a>
            <?php endif; ?>

            <?php if (\App\Service\Gate::allows('efatura/mal-hizmet-list')): ?>
            <a href="?p=efatura-mal-hizmet" class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-700/60 flex flex-col items-center justify-center text-center shadow-xs active:scale-95 transition-transform">
                <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 flex items-center justify-center mb-1.5">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                </div>
                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200">Mal/Hizmet</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4. Son Faturalar Hızlı Akışı -->
    <?php 
    $canSeeGelen = \App\Service\Gate::allows('efatura/gelen-list');
    $canSeeGiden = \App\Service\Gate::allows('efatura/giden-list');
    if ($canSeeGelen || $canSeeGiden):
        $defaultTab = $canSeeGelen ? 'gelen' : 'giden';
    ?>
    <div>
        <div class="flex items-center justify-between mb-2 px-1">
            <h2 class="text-xs font-black text-slate-400 uppercase tracking-wider">Son Faturalar</h2>
            <div class="flex gap-1">
                <?php if ($canSeeGelen): ?>
                <button type="button" id="tabBtnGelen" onclick="switchRecentTab('gelen')" class="px-2 py-1 rounded-lg text-[10px] font-bold <?= $defaultTab === 'gelen' ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' ?>">Gelen</button>
                <?php endif; ?>
                <?php if ($canSeeGiden): ?>
                <button type="button" id="tabBtnGiden" onclick="switchRecentTab('giden')" class="px-2 py-1 rounded-lg text-[10px] font-bold <?= $defaultTab === 'giden' ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' ?>">Giden</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gelen Son Faturalar -->
        <?php if ($canSeeGelen): ?>
        <div id="recentGelenList" class="space-y-2 <?= $defaultTab === 'gelen' ? '' : 'hidden' ?>">
            <?php 
            $recentGelen = $dashData['recent_gelen'] ?? [];
            if (empty($recentGelen)): ?>
                <div class="bg-white dark:bg-card-dark p-6 rounded-2xl text-center text-slate-400 text-xs font-medium border border-slate-100 dark:border-slate-700/60">
                    Henüz gelen fatura bulunmuyor
                </div>
            <?php else: 
                foreach ($recentGelen as $rg): ?>
                <div class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-700/60 shadow-xs flex items-center justify-between gap-2" onclick="openInvoicePreview('<?= $rg['encrypted_id'] ?>', '<?= htmlspecialchars($rg['fatura_no'] ?? '') ?>')">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs font-bold text-primary"><?= htmlspecialchars($rg['fatura_no'] ?? 'Taslak') ?></span>
                            <span class="text-[10px] text-slate-400"><?= $rg['fatura_tarihi_fmt'] ?></span>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate mt-0.5">
                            <?= htmlspecialchars($rg['alici_unvan'] ?? 'Cari') ?>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-xs font-black text-slate-900 dark:text-white"><?= $rg['odenecek_tutar_fmt'] ?></div>
                        <span class="inline-block text-[9px] font-bold px-1.5 py-0.5 rounded-full <?= ($rg['ticari_yanit'] ?? '') === 'KABUL' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                            <?= htmlspecialchars($rg['ticari_yanit'] ?? 'Bekliyor') ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>

        <!-- Giden Son Faturalar -->
        <?php if ($canSeeGiden): ?>
        <div id="recentGidenList" class="space-y-2 <?= $defaultTab === 'giden' ? '' : 'hidden' ?>">
            <?php 
            $recentGiden = $dashData['recent_giden'] ?? [];
            if (empty($recentGiden)): ?>
                <div class="bg-white dark:bg-card-dark p-6 rounded-2xl text-center text-slate-400 text-xs font-medium border border-slate-100 dark:border-slate-700/60">
                    Henüz giden fatura bulunmuyor
                </div>
            <?php else: 
                foreach ($recentGiden as $rg): ?>
                <div class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-700/60 shadow-xs flex items-center justify-between gap-2" onclick="openInvoicePreview('<?= $rg['encrypted_id'] ?>', '<?= htmlspecialchars($rg['fatura_no'] ?? '') ?>')">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs font-bold text-emerald-600"><?= htmlspecialchars($rg['fatura_no'] ?? 'Taslak') ?></span>
                            <span class="text-[10px] text-slate-400"><?= $rg['fatura_tarihi_fmt'] ?></span>
                        </div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate mt-0.5">
                            <?= htmlspecialchars($rg['alici_unvan'] ?? 'Cari') ?>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-xs font-black text-slate-900 dark:text-white"><?= $rg['odenecek_tutar_fmt'] ?></div>
                        <span class="inline-block text-[9px] font-bold px-1.5 py-0.5 rounded-full <?= ($rg['entegrator_durum_kodu'] ?? '') === 'ONAYLANDI' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' ?>">
                            <?= htmlspecialchars($rg['entegrator_durum_kodu'] ?? 'İletildi') ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Fatura PDF Önizleme Modalı / Sheet -->
<div id="mobPdfModal" class="fixed inset-0 z-[110] bg-slate-900/80 backdrop-blur-xs flex flex-col justify-end hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-t-[28px] w-full h-[90vh] flex flex-col shadow-2xl overflow-hidden transform translate-y-full transition-transform duration-300" id="mobPdfSheet">
        <!-- Header -->
        <div class="p-3.5 px-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">description</span>
                <span class="text-sm font-black text-slate-900 dark:text-white" id="mobPdfTitle">Fatura Önizleme</span>
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
        <!-- Body Iframe -->
        <div class="flex-1 bg-slate-100 relative">
            <iframe id="mobPdfFrame" class="w-full h-full border-0" src="about:blank"></iframe>
        </div>
    </div>
</div>

<script>
function switchRecentTab(type) {
    if (type === 'gelen') {
        document.getElementById('recentGelenList').classList.remove('hidden');
        document.getElementById('recentGidenList').classList.add('hidden');
        document.getElementById('tabBtnGelen').className = 'px-2 py-1 rounded-lg text-[10px] font-bold bg-primary text-white';
        document.getElementById('tabBtnGiden').className = 'px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
    } else {
        document.getElementById('recentGelenList').classList.add('hidden');
        document.getElementById('recentGidenList').classList.remove('hidden');
        document.getElementById('tabBtnGiden').className = 'px-2 py-1 rounded-lg text-[10px] font-bold bg-primary text-white';
        document.getElementById('tabBtnGelen').className = 'px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
    }
}

function changeMobPeriod(period, btn) {
    document.querySelectorAll('.mob-period-btn').forEach(b => {
        b.className = 'mob-period-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 whitespace-nowrap';
    });
    btn.className = 'mob-period-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-primary text-white shadow-xs whitespace-nowrap';

    let sDate = '', eDate = '';
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const toIso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    if (period === 'this_month') {
        sDate = toIso(new Date(y, m, 1));
        eDate = toIso(new Date(y, m + 1, 0));
    } else if (period === 'last_month') {
        sDate = toIso(new Date(y, m - 1, 1));
        eDate = toIso(new Date(y, m, 0));
    } else if (period === 'this_year') {
        sDate = toIso(new Date(y, 0, 1));
        eDate = toIso(new Date(y, 11, 31));
    }

    let url = '../api/efatura-api.php?action=dashboard_stats';
    if (sDate) url += `&baslangic_tarihi=${sDate}`;
    if (eDate) url += `&bitis_tarihi=${eDate}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const d = res.data;
                const fmt = (v) => Number(v || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';

                document.getElementById('mob_giden_tutar').textContent = fmt(d.giden?.toplam_tutar);
                document.getElementById('mob_giden_matrah').textContent = fmt(d.giden?.toplam_matrah);
                document.getElementById('mob_giden_kdv').textContent = fmt(d.giden?.toplam_kdv);
                document.getElementById('mob_giden_adet').textContent = (d.giden?.toplam_adet || 0) + ' Fatura';

                document.getElementById('mob_gelen_tutar').textContent = fmt(d.gelen?.toplam_tutar);
                document.getElementById('mob_gelen_matrah').textContent = fmt(d.gelen?.toplam_matrah);
                document.getElementById('mob_gelen_kdv').textContent = fmt(d.gelen?.toplam_kdv);
                document.getElementById('mob_gelen_adet').textContent = (d.gelen?.toplam_adet || 0) + ' Fatura';

                const netKdv = Number(d.kdv?.net_kdv || 0);
                const isPayable = netKdv > 0;
                const box = document.getElementById('mob_kdv_box');
                const title = document.getElementById('mob_kdv_title');
                const amt = document.getElementById('mob_kdv_amount');
                const icon = document.getElementById('mob_kdv_icon');

                if (isPayable) {
                    box.className = 'rounded-2xl p-4 border bg-rose-50/70 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/50 shadow-sm';
                    title.textContent = 'ÖDENECEK KDV (MALİYE)';
                    title.className = 'text-xs font-black uppercase tracking-wider text-rose-800 dark:text-rose-300';
                    amt.className = 'text-xl font-black text-rose-600';
                    amt.textContent = fmt(d.kdv?.odenecek_kdv);
                    icon.className = 'material-symbols-outlined text-rose-600 text-[22px]';
                    icon.textContent = 'error';
                } else {
                    box.className = 'rounded-2xl p-4 border bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/50 shadow-sm';
                    title.textContent = 'SONRAKİ DÖNEME DEVREDEN KDV';
                    title.className = 'text-xs font-black uppercase tracking-wider text-emerald-800 dark:text-emerald-300';
                    amt.className = 'text-xl font-black text-emerald-600';
                    amt.textContent = fmt(d.kdv?.devreden_kdv);
                    icon.className = 'material-symbols-outlined text-emerald-600 text-[22px]';
                    icon.textContent = 'check_circle';
                }

                document.getElementById('mob_hesaplanan_kdv').textContent = fmt(d.kdv?.hesaplanan_kdv);
                document.getElementById('mob_indirilecek_kdv').textContent = fmt(d.kdv?.indirilecek_kdv);
            }
        });
}

function openInvoicePreview(encId, faturaNo) {
    const modal = document.getElementById('mobPdfModal');
    const sheet = document.getElementById('mobPdfSheet');
    const frame = document.getElementById('mobPdfFrame');
    const title = document.getElementById('mobPdfTitle');
    const dlBtn = document.getElementById('mobPdfDownloadBtn');

    title.textContent = faturaNo || 'Fatura Önizleme';
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

function closeInvoicePreview() {
    const modal = document.getElementById('mobPdfModal');
    const sheet = document.getElementById('mobPdfSheet');
    const frame = document.getElementById('mobPdfFrame');

    sheet.classList.add('translate-y-full');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        frame.srcdoc = '';
    }, 300);
}
</script>
