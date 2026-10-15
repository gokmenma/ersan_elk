<?php

use App\Helper\Security;
use App\Model\BordroDonemModel;
use App\Model\BordroParametreModel;
use App\Model\BordroPersonelModel;
use App\Model\MenuModel;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$menuModel = new MenuModel();
if (!$menuModel->userCanAccessMenuLink($userId, 'bordro/list')) {
    http_response_code(403);
    echo '<div class="m-4 rounded-2xl bg-rose-50 p-5 text-sm font-bold text-rose-700">Bordro sayfasını görüntüleme yetkiniz bulunmuyor.</div>';
    return;
}

$donemModel = new BordroDonemModel();
$bordroModel = new BordroPersonelModel();
$parametreModel = new BordroParametreModel();
$donemler = $donemModel->getAllDonemsForFilter();
$selectedId = 0;
if (!empty($_GET['donem'])) {
    try {
        $selectedId = (int) Security::decrypt((string) $_GET['donem']);
    } catch (Throwable $e) {
        $selectedId = 0;
    }
}
$selectedDonem = null;
foreach ($donemler as $donem) {
    if (($selectedId > 0 && (int) $donem->id === $selectedId) || ($selectedId === 0 && $selectedDonem === null)) {
        $selectedDonem = $donem;
        if ($selectedId > 0) break;
    }
}

$rows = [];
$summary = ['personel' => 0, 'alacak' => 0.0, 'kesinti' => 0.0, 'net' => 0.0, 'banka' => 0.0, 'sodexo' => 0.0, 'elden' => 0.0];
if ($selectedDonem && (int) $selectedDonem->firma_id === $firmaId) {
    $personeller = $bordroModel->getPersonellerByDonem((int) $selectedDonem->id);
    $asgariNet = (float) ($parametreModel->getGenelAyar('asgari_ucret_net', $selectedDonem->baslangic_tarihi) ?? 0);
    foreach ($personeller as $personel) {
        $calc = $bordroModel->hesaplaOrtakGosterimDegerleri($personel, $selectedDonem, $asgariNet);
        $row = [
            'record' => $personel,
            'token' => Security::encrypt((int) $personel->id),
            'personel_token' => Security::encrypt((int) $personel->personel_id),
            'net' => (float) ($calc['netAlacagi'] ?? 0),
            'alacak' => (float) ($calc['toplamAlacagi'] ?? 0),
            'kesinti' => (float) ($calc['kesintiHaricIcra'] ?? 0),
            'icra' => (float) ($calc['icraKesintisi'] ?? 0),
            'banka' => (float) ($calc['bankaOdemesi'] ?? 0),
            'sodexo' => (float) ($calc['sodexoOdemesi'] ?? 0),
            'elden' => (float) ($calc['eldenOdeme'] ?? 0),
            'gun' => (float) ($calc['calismaGunu'] ?? 0),
        ];
        $rows[] = $row;
        $summary['personel']++;
        foreach (['alacak', 'kesinti', 'net', 'banka', 'sodexo', 'elden'] as $key) $summary[$key] += $row[$key];
    }
}
$money = static fn(float $amount): string => number_format($amount, 2, ',', '.') . ' ₺';
$donemToken = $selectedDonem ? Security::encrypt((int) $selectedDonem->id) : '';
$closed = $selectedDonem && (int) ($selectedDonem->kapali_mi ?? 0) === 1;
?>

<div class="pb-48">
    <main class="space-y-4 px-4 pt-4">
        <section class="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
            <div class="mb-2 flex items-center justify-between gap-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Bordro Dönemi</span>
            <?php if ($selectedDonem): ?>
                <span class="rounded-lg px-2 py-1 text-[9px] font-black <?= $closed ? 'bg-rose-50 text-rose-600 dark:bg-rose-900/20' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20' ?>">
                    <?= $closed ? 'KAPALI' : 'AÇIK' ?>
                </span>
            <?php endif; ?>
            </div>
            <select id="bordroPeriod" aria-label="Bordro dönemi" class="h-11 w-full rounded-xl border-slate-200 bg-slate-50 py-0 pl-3 pr-9 text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <?php if (!$donemler): ?><option value="">Dönem bulunamadı</option><?php endif; ?>
                <?php foreach ($donemler as $donem): ?>
                    <option value="<?= htmlspecialchars(Security::encrypt((int) $donem->id), ENT_QUOTES, 'UTF-8') ?>" <?= $selectedDonem && (int) $selectedDonem->id === (int) $donem->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($donem->donem_adi, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </section>

        <?php if (!$selectedDonem): ?>
            <div class="rounded-2xl border border-slate-100 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-card-dark">
                <span class="material-symbols-outlined text-4xl text-slate-300">event_busy</span>
                <p class="mt-2 text-sm font-bold text-slate-600 dark:text-slate-300">Görüntülenecek bordro dönemi bulunamadı.</p>
            </div>
        <?php else: ?>
            <section class="grid grid-cols-2 gap-2">
                <div class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
                    <span class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800"><span class="material-symbols-outlined text-lg">groups</span></span>
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Personel</span>
                    <p class="mt-1 text-lg font-black text-slate-800 dark:text-white"><?= $summary['personel'] ?></p>
                </div>
                <div class="relative overflow-hidden rounded-2xl border border-emerald-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
                    <span class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20"><span class="material-symbols-outlined text-lg">payments</span></span>
                    <span class="text-[9px] font-black uppercase tracking-wider text-emerald-500">Net Ödenecek</span>
                    <p class="mt-1 truncate pr-8 text-base font-black text-emerald-600"><?= $money($summary['net']) ?></p>
                </div>
                <div class="relative overflow-hidden rounded-2xl border border-blue-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
                    <span class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/20"><span class="material-symbols-outlined text-lg">account_balance</span></span>
                    <span class="text-[9px] font-black uppercase tracking-wider text-blue-500">Banka</span>
                    <p class="mt-1 truncate pr-8 text-sm font-black text-blue-600"><?= $money($summary['banka']) ?></p>
                </div>
                <div class="relative overflow-hidden rounded-2xl border border-amber-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
                    <span class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-900/20"><span class="material-symbols-outlined text-lg">wallet</span></span>
                    <span class="text-[9px] font-black uppercase tracking-wider text-amber-500">Elden / Sodexo</span>
                    <p class="mt-1 truncate pr-8 text-sm font-black text-amber-600"><?= $money($summary['elden'] + $summary['sodexo']) ?></p>
                </div>
            </section>

            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">search</span>
                <input id="bordroSearch" type="search" placeholder="Personel, departman veya görev ara..." class="h-11 w-full rounded-xl border-slate-200 bg-white pl-10 pr-3 text-xs font-semibold shadow-sm dark:border-slate-800 dark:bg-card-dark dark:text-white">
            </div>

            <section id="bordroCards" class="space-y-2">
                <?php foreach ($rows as $row): $p = $row['record']; ?>
                    <article class="bordro-person-card overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-card-dark" data-search="<?= htmlspecialchars(mb_strtolower(($p->adi_soyadi ?? '') . ' ' . ($p->departman ?? '') . ' ' . ($p->gorev ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="flex w-full items-center gap-3 p-3">
                            <img src="../<?= htmlspecialchars((!empty($p->resim_yolu) && is_file($p->resim_yolu)) ? $p->resim_yolu : 'assets/images/users/user-dummy-img.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-11 w-11 rounded-xl object-cover">
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-black text-slate-800 dark:text-white"><?= htmlspecialchars($p->adi_soyadi ?? '-', ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="truncate text-[10px] font-semibold text-slate-400"><?= htmlspecialchars(($p->departman ?? '-') . ' · ' . ($p->gorev ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <button type="button" onclick="openPayrollDetail('<?= htmlspecialchars($row['token'], ENT_QUOTES, 'UTF-8') ?>')" class="rounded-lg px-1.5 py-1 text-right active:bg-slate-50 dark:active:bg-slate-800" aria-label="<?= htmlspecialchars(($p->adi_soyadi ?? '') . ' bordro detayını aç', ENT_QUOTES, 'UTF-8') ?>">
                                <p class="text-[9px] font-bold uppercase text-slate-400">Net</p>
                                <p class="text-sm font-black underline decoration-dotted underline-offset-4 <?= $row['net'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $money($row['net']) ?></p>
                            </button>
                        </div>
                        <div class="flex gap-2 border-t border-slate-100 px-3 py-2 dark:border-slate-800">
                            <button type="button" onclick="openPayrollDetail('<?= htmlspecialchars($row['token'], ENT_QUOTES, 'UTF-8') ?>')" class="flex h-9 flex-1 items-center justify-center gap-1 rounded-xl bg-indigo-50 text-[11px] font-black text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">
                                <span class="material-symbols-outlined text-base">receipt_long</span>Bordro Detayı
                            </button>
                            <button type="button" onclick="togglePayrollCard(this)" class="flex h-9 items-center justify-center gap-1 rounded-xl bg-slate-100 px-3 text-[11px] font-black text-slate-600 dark:bg-slate-800 dark:text-slate-300" aria-expanded="false">
                                İşlemler<span class="card-chevron material-symbols-outlined text-base transition-transform">expand_more</span>
                            </button>
                        </div>
                        <div class="payroll-card-detail hidden border-t border-slate-100 p-3 dark:border-slate-800">
                            <div class="mb-2 flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800"><span class="material-symbols-outlined text-sm">account_balance_wallet</span></span>
                                <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Ödeme Özeti</h4>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-900/40"><span class="mb-1 flex items-center gap-1.5 text-[9px] font-bold uppercase text-slate-400"><i class="flex h-5 w-5 items-center justify-center rounded-md bg-slate-200/70 not-italic text-slate-500 dark:bg-slate-700"><span class="material-symbols-outlined text-[13px]">calendar_month</span></i>Çalışma</span><b class="text-slate-700 dark:text-slate-200"><?= number_format($row['gun'], 1, ',', '.') ?> gün</b></div>
                                <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-900/40"><span class="mb-1 flex items-center gap-1.5 text-[9px] font-bold uppercase text-slate-400"><i class="flex h-5 w-5 items-center justify-center rounded-md bg-slate-200/70 not-italic text-slate-500 dark:bg-slate-700"><span class="material-symbols-outlined text-[13px]">paid</span></i>Toplam Alacak</span><b class="text-slate-700 dark:text-slate-200"><?= $money($row['alacak']) ?></b></div>
                                <div class="rounded-xl bg-rose-50 p-2.5 dark:bg-rose-900/10"><span class="mb-1 flex items-center gap-1.5 text-[9px] font-bold uppercase text-rose-400"><i class="flex h-5 w-5 items-center justify-center rounded-md bg-rose-100 not-italic text-rose-500 dark:bg-rose-900/30"><span class="material-symbols-outlined text-[13px]">remove_circle</span></i>Kesinti / İcra</span><b class="text-rose-600"><?= $money($row['kesinti'] + $row['icra']) ?></b></div>
                                <div class="rounded-xl bg-blue-50 p-2.5 dark:bg-blue-900/10"><span class="mb-1 flex items-center gap-1.5 text-[9px] font-bold uppercase text-blue-400"><i class="flex h-5 w-5 items-center justify-center rounded-md bg-blue-100 not-italic text-blue-500 dark:bg-blue-900/30"><span class="material-symbols-outlined text-[13px]">account_balance</span></i>Banka</span><b class="text-blue-600"><?= $money($row['banka']) ?></b></div>
                                <div class="rounded-xl bg-cyan-50 p-2.5 dark:bg-cyan-900/10"><span class="mb-1 flex items-center gap-1.5 text-[9px] font-bold uppercase text-cyan-500"><i class="flex h-5 w-5 items-center justify-center rounded-md bg-cyan-100 not-italic text-cyan-600 dark:bg-cyan-900/30"><span class="material-symbols-outlined text-[13px]">restaurant</span></i>Sodexo</span><b class="text-cyan-600"><?= $money($row['sodexo']) ?></b></div>
                                <div class="rounded-xl bg-amber-50 p-2.5 dark:bg-amber-900/10"><span class="mb-1 flex items-center gap-1.5 text-[9px] font-bold uppercase text-amber-500"><i class="flex h-5 w-5 items-center justify-center rounded-md bg-amber-100 not-italic text-amber-600 dark:bg-amber-900/30"><span class="material-symbols-outlined text-[13px]">payments</span></i>Elden</span><b class="text-amber-600"><?= $money($row['elden']) ?></b></div>
                            </div>
                            <div class="my-3 border-t border-dashed border-slate-200 dark:border-slate-700"></div>
                            <div class="mb-2 flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20"><span class="material-symbols-outlined text-sm">touch_app</span></span>
                                <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Yapılabilecek İşlemler</h4>
                            </div>
                            <div class="space-y-2">
                                <button type="button" <?= $closed ? 'disabled' : '' ?> onclick="openPaymentModal(this)" data-token="<?= htmlspecialchars($row['token'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($p->adi_soyadi ?? '-', ENT_QUOTES, 'UTF-8') ?>" data-banka="<?= $row['banka'] ?>" data-sodexo="<?= $row['sodexo'] ?>" data-diger="<?= (float) ($p->diger_odeme ?? 0) ?>" class="flex w-full items-center gap-3 rounded-xl border border-indigo-100 bg-indigo-50 p-3 text-left disabled:opacity-40 dark:border-indigo-900/30 dark:bg-indigo-900/15">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white"><span class="material-symbols-outlined text-lg">account_balance_wallet</span></span>
                                    <span class="min-w-0 flex-1"><b class="block text-xs font-black text-indigo-800 dark:text-indigo-200">Ödeme Dağılımını Düzenle</b><small class="mt-0.5 block text-[9px] font-semibold text-indigo-500">Banka, Sodexo ve diğer ödeme tutarları</small></span>
                                    <span class="material-symbols-outlined text-lg text-indigo-400">chevron_right</span>
                                </button>
                                <?php if (!$closed): ?>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" onclick="openAdjustmentModal(this, 'gelir')" data-token="<?= htmlspecialchars($row['personel_token'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($p->adi_soyadi ?? '-', ENT_QUOTES, 'UTF-8') ?>" class="flex min-h-[76px] flex-col items-start justify-between rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-left dark:border-emerald-900/30 dark:bg-emerald-900/15">
                                        <span class="material-symbols-outlined text-xl text-emerald-600">add_circle</span><span><b class="block text-[11px] font-black text-emerald-800 dark:text-emerald-200">Gelir Ekle</b><small class="block text-[8px] font-semibold text-emerald-600/80">Prim, mesai, ikramiye</small></span>
                                    </button>
                                    <button type="button" onclick="openAdjustmentModal(this, 'kesinti')" data-token="<?= htmlspecialchars($row['personel_token'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($p->adi_soyadi ?? '-', ENT_QUOTES, 'UTF-8') ?>" class="flex min-h-[76px] flex-col items-start justify-between rounded-xl border border-rose-100 bg-rose-50 p-3 text-left dark:border-rose-900/30 dark:bg-rose-900/15">
                                        <span class="material-symbols-outlined text-xl text-rose-600">do_not_disturb_on</span><span><b class="block text-[11px] font-black text-rose-800 dark:text-rose-200">Kesinti Ekle</b><small class="block text-[8px] font-semibold text-rose-600/80">Avans, icra, ücretsiz izin</small></span>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                <div id="bordroEmptySearch" class="hidden rounded-2xl bg-white p-8 text-center text-xs font-bold text-slate-400 dark:bg-card-dark">Aramanızla eşleşen personel yok.</div>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php if ($selectedDonem): ?>
    <div class="fixed bottom-24 right-4 z-[70] flex w-14 flex-col items-center gap-2">
        <button type="button" onclick="openOtherPayrollActions()" class="flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-lg shadow-slate-900/15 active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" aria-label="Diğer bordro işlemleri" title="Diğer İşlemler">
            <span class="material-symbols-outlined block text-xl leading-none" aria-hidden="true">more_horiz</span>
            <span class="sr-only">Diğer İşlemler</span>
        </button>
        <button type="button" onclick="calculatePayroll()" <?= $closed || !$rows ? 'disabled' : '' ?> class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-xl shadow-indigo-600/30 transition-transform active:scale-95 disabled:cursor-not-allowed disabled:bg-slate-400 disabled:shadow-none" aria-label="Maaşları hesapla" title="Maaşları Hesapla">
            <span class="material-symbols-outlined block text-2xl leading-none" aria-hidden="true">calculate</span>
            <span class="sr-only">Maaşları Hesapla</span>
        </button>
    </div>
<?php endif; ?>

<div id="bordroSheet" class="bg-slate-900/60 backdrop-blur-sm" hidden onclick="handleBordroSheetBackdrop(event)">
    <div id="bordroSheetPanel" class="bg-white shadow-2xl dark:bg-card-dark" role="dialog" aria-modal="true" aria-labelledby="bordroSheetTitle" tabindex="-1">
        <div class="flex justify-center pb-1 pt-3"><span class="h-1 w-11 rounded-full bg-slate-200 dark:bg-slate-700"></span></div>
        <div class="flex shrink-0 items-start gap-2 border-b border-slate-100 px-4 pb-3 pt-1 dark:border-slate-800">
            <div class="min-w-0 flex-1">
                <h2 id="bordroSheetTitle" class="text-sm font-black dark:text-white"></h2>
            </div>
            <button type="button" onclick="closeBordroSheet()" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800" aria-label="Paneli kapat"><span class="material-symbols-outlined text-lg">close</span></button>
        </div>
        <div id="bordroSheetBody" class="overflow-y-auto p-4 text-sm dark:text-slate-200"></div>
    </div>
</div>

<link rel="stylesheet" href="../assets/css/icons.css">
<style>
    #bordroSheet { position: fixed; inset: 0; z-index: 200; display: flex; align-items: flex-end; justify-content: center; }
    #bordroSheet[hidden] { display: none; }
    #bordroSheetPanel { display: flex; flex-direction: column; width: 100%; max-width: 640px; max-height: 94vh; max-height: 94dvh; overflow: hidden; border-radius: 28px 28px 0 0; transform: translateY(100%); transition: transform .3s ease; }
    #bordroSheet.is-open #bordroSheetPanel { transform: translateY(0); }
    #bordroSheetBody { min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding-bottom: max(20px, env(safe-area-inset-bottom)); }
    #bordroSheetBody .mobile-payroll-desktop { color: #334155; }
    #bordroSheetBody .mobile-payroll-desktop .row { display: flex; flex-wrap: wrap; gap: 12px; }
    #bordroSheetBody .mobile-payroll-desktop [class*="col-"] { width: 100%; min-width: 0; }
    #bordroSheetBody .mobile-payroll-desktop .d-flex { display: flex; }
    #bordroSheetBody .mobile-payroll-desktop .flex-wrap { flex-wrap: wrap; }
    #bordroSheetBody .mobile-payroll-desktop .flex-column { flex-direction: column; }
    #bordroSheetBody .mobile-payroll-desktop .justify-content-between { justify-content: space-between; }
    #bordroSheetBody .mobile-payroll-desktop .align-items-center { align-items: center; }
    #bordroSheetBody .mobile-payroll-desktop .align-items-end { align-items: flex-end; }
    #bordroSheetBody .mobile-payroll-desktop .text-end { text-align: right; }
    #bordroSheetBody .mobile-payroll-desktop .d-block { display: block; }
    #bordroSheetBody .mobile-payroll-desktop .calculation-toggle > span:last-child { flex-shrink: 0; white-space: nowrap; }
    #bordroSheetBody .mobile-payroll-desktop .calculation-toggle .badge { padding: 4px 8px; border: 1px solid currentColor; border-radius: 999px; font-size: 11px; }
    #bordroSheetBody .mobile-payroll-desktop .card-header-tint { padding: 14px 12px; }
    #bordroSheetBody .mobile-payroll-desktop .calculation-note { color: inherit; opacity: .7; }
    #bordroSheetBody .mobile-payroll-desktop .net-bottom-banner .text-white-50 { color: #64748b; }
    #bordroSheetBody .mobile-payroll-desktop .me-1 { margin-right: 4px; }
    #bordroSheetBody .mobile-payroll-desktop .me-2 { margin-right: 6px; }
    #bordroSheetBody .mobile-payroll-desktop .ps-4 { padding-left: 24px; }

    #bordroSheetBody .mobile-payroll-desktop .fw-bold, #bordroSheetBody .mobile-payroll-desktop .fw-bolder { font-weight: 800; }
    #bordroSheetBody .mobile-payroll-desktop .fw-semibold { font-weight: 600; }
    #bordroSheetBody .mobile-payroll-desktop .text-muted, #bordroSheetBody .mobile-payroll-desktop .small { color: #64748b; font-size: 11px; }
    #bordroSheetBody .mobile-payroll-desktop .text-success { color: #10b981; }
    #bordroSheetBody .mobile-payroll-desktop .text-danger { color: #ef4444; }
    #bordroSheetBody .mobile-payroll-desktop .text-warning { color: #d97706; }
    #bordroSheetBody .mobile-payroll-desktop .text-primary { color: #2563eb; }
    #bordroSheetBody .mobile-payroll-desktop .header-glass { display: flex; flex-direction: column; align-items: stretch; gap: 12px; padding: 14px; }
    #bordroSheetBody .mobile-payroll-desktop .header-glass h5 { font-weight: 800; margin-bottom: 6px; }
    #bordroSheetBody .mobile-payroll-desktop .header-glass .badge { display: flex; flex-direction: column; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; font-size: 11px; }
    #bordroSheetBody .mobile-payroll-desktop .btn-toggle-old-detail-layout, #bordroSheetBody .mobile-payroll-desktop .btn-toggle-net-expand { display: none; }
    #bordroSheetBody .mobile-payroll-desktop .collapse { visibility: visible; }
    #bordroSheetBody .mobile-payroll-desktop .collapse:not(.show) { display: none; }
    #bordroSheetBody .mobile-payroll-desktop tr.collapse.show { display: table-row; }
    #bordroSheetBody .mobile-payroll-desktop .calculation-toggle { min-height: 64px; gap: 8px; font-size: 12px; }
    #bordroSheetBody .mobile-payroll-desktop .unified-table { table-layout: fixed; }
    #bordroSheetBody .mobile-payroll-desktop .unified-table td { padding: 12px; font-size: 12px; overflow-wrap: anywhere; }
    #bordroSheetBody .mobile-payroll-desktop .unified-table td:last-child:not([colspan]) { width: 38%; }
    #bordroSheetBody .mobile-payroll-desktop .unified-table .footer-row td { padding: 14px 12px; }
    #bordroSheetBody .mobile-payroll-desktop .net-bottom-banner { padding: 18px; margin-top: 0; background: #f2fbf6; color: #334155; border-color: #a7f3d0; }
    #bordroSheetBody .mobile-payroll-desktop .dist-badge { flex-direction: row; gap: 10px; background: #fff; border-color: #d6eadf; }
    #bordroSheetBody .mobile-payroll-desktop .net-value-xl { font-size: 28px; }
    .dark #bordroSheetBody .mobile-payroll-desktop { color: #e2e8f0; }
    .dark #bordroSheetBody .mobile-payroll-desktop .main-card, .dark #bordroSheetBody .mobile-payroll-desktop .header-glass,
    .dark #bordroSheetBody .mobile-payroll-desktop .unified-table .parent-row, .dark #bordroSheetBody .mobile-payroll-desktop .dist-badge { background: #1e293b; border-color: #475569; }
    .dark #bordroSheetBody .mobile-payroll-desktop .unified-table .child-row, .dark #bordroSheetBody .mobile-payroll-desktop .unified-table .footer-row,
    .dark #bordroSheetBody .mobile-payroll-desktop .section-row td, .dark #bordroSheetBody .mobile-payroll-desktop .header-glass .badge { background: #0f172a; color: #cbd5e1; }
    .dark #bordroSheetBody .mobile-payroll-desktop .net-bottom-banner { background: #14352b; color: #f1f5f9; }
    .dark #bordroSheetBody .mobile-payroll-desktop .tint-hakedis { background: #14352b; color: #86efac; }
    .dark #bordroSheetBody .mobile-payroll-desktop .tint-resmi { background: #172554; color: #93c5fd; }
    @media (prefers-reduced-motion: reduce) { #bordroSheetPanel { transition: none; } }
</style>

<script>
const bordroPeriodToken = <?= json_encode($donemToken) ?>;
const bordroIsClosed = <?= $closed ? 'true' : 'false' ?>;
const bordroTokens = <?= json_encode(array_column($rows, 'token')) ?>;
const bordroApiUrl = 'api/bordro.php';

document.getElementById('bordroPeriod')?.addEventListener('change', function () { location.href = '?p=bordro&donem=' + encodeURIComponent(this.value); });
document.getElementById('bordroSearch')?.addEventListener('input', function () {
    const query = this.value.toLocaleLowerCase('tr-TR').trim(); let visible = 0;
    document.querySelectorAll('.bordro-person-card').forEach(card => { const show = card.dataset.search.includes(query); card.classList.toggle('hidden', !show); if (show) visible++; });
    document.getElementById('bordroEmptySearch')?.classList.toggle('hidden', visible !== 0);
});
function togglePayrollCard(button) { const detail = button.parentElement.nextElementSibling; const opening = detail.classList.contains('hidden'); detail.classList.toggle('hidden'); button.setAttribute('aria-expanded', opening ? 'true' : 'false'); button.querySelector('.card-chevron')?.classList.toggle('rotate-180', opening); }
// Keep the fixed dialog outside the animated page shell's stacking context.
const bordroSheet = document.getElementById('bordroSheet');
document.body.appendChild(bordroSheet);
let bordroSheetVersion = 0;
let bordroSheetReturnFocus = null;
function closeBordroSheet() {
    ++bordroSheetVersion;
    bordroSheet.classList.remove('is-open');
    bordroSheet.hidden = true;
    document.body.classList.remove('overflow-hidden');
    bordroSheetReturnFocus?.focus();
}
function showBordroSheet(title, html) {
    ++bordroSheetVersion;
    if (bordroSheet.hidden) bordroSheetReturnFocus = document.activeElement;
    document.getElementById('bordroSheetTitle').textContent = title;
    document.getElementById('bordroSheetBody').innerHTML = html;
    document.getElementById('bordroSheetBody').scrollTop = 0;
    bordroSheet.hidden = false;
    document.body.classList.add('overflow-hidden');
    // Force the initial translated position before starting the transition.
    void bordroSheet.offsetHeight;
    bordroSheet.classList.add('is-open');
    document.getElementById('bordroSheetPanel').focus();
}
function handleBordroSheetBackdrop(event) { if (event.target === bordroSheet) closeBordroSheet(); }
bordroSheet.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeBordroSheet();
    if (event.key !== 'Tab') return;
    const items = [...bordroSheet.querySelectorAll('button, [href], input, select, textarea, [tabindex="0"]')].filter(el => el.getClientRects().length);
    if (!items.length) { event.preventDefault(); return; }
    const first = items[0], last = items[items.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement.id === 'bordroSheetPanel')) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
});
function toggleMobilePayrollDetail(trigger) {
    const selector = trigger.dataset.collapseTarget || trigger.dataset.bsTarget;
    if (!selector) return;
    const targets = bordroSheet.querySelectorAll(selector);
    const opening = [...targets].some(el => !el.classList.contains('show'));
    targets.forEach(el => el.classList.toggle('show', opening));
    trigger.setAttribute('aria-expanded', String(opening));
}
bordroSheet.addEventListener('click', event => {
    const trigger = event.target.closest('[data-collapse-target], [data-bs-toggle="collapse"]');
    if (trigger) toggleMobilePayrollDetail(trigger);
});
bordroSheet.addEventListener('keydown', event => {
    const trigger = event.target.closest('.calculation-toggle');
    if (trigger && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); toggleMobilePayrollDetail(trigger); }
});
function openOtherPayrollActions() { showBordroSheet('Diğer Bordro İşlemleri', `<div class="space-y-2">
    <button type="button" onclick="closeBordroSheet(); togglePeriod()" class="flex h-12 w-full items-center gap-3 rounded-xl <?= $closed ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?> px-4 text-left text-xs font-black"><span class="material-symbols-outlined"><?= $closed ? 'lock_open' : 'lock' ?></span><span><?= $closed ? 'Dönemi Yeniden Aç' : 'Dönemi Kapat' ?></span><span class="material-symbols-outlined ml-auto text-base">chevron_right</span></button>
    <button type="button" <?= $closed ? 'disabled' : '' ?> onclick="resetAllPayments()" class="flex h-12 w-full items-center gap-3 rounded-xl bg-amber-50 px-4 text-left text-xs font-black text-amber-700 disabled:cursor-not-allowed disabled:opacity-40"><span class="material-symbols-outlined">restart_alt</span><span>Ödeme Dağılımlarını Sıfırla</span><span class="material-symbols-outlined ml-auto text-base">chevron_right</span></button>
    <button type="button" onclick="location.reload()" class="flex h-12 w-full items-center gap-3 rounded-xl bg-blue-50 px-4 text-left text-xs font-black text-blue-700"><span class="material-symbols-outlined">refresh</span><span>Listeyi Yenile</span><span class="material-symbols-outlined ml-auto text-base">chevron_right</span></button>
</div>`); }
async function bordroPost(data) { const body = new URLSearchParams(); Object.entries(data).forEach(([key, value]) => Array.isArray(value) ? value.forEach(item => body.append(key + '[]', item)) : body.append(key, value)); const response = await fetch(bordroApiUrl, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body}); const result = await response.json(); if (!response.ok || result.status === 'error') throw new Error(result.message || 'İşlem tamamlanamadı.'); return result; }
function notifyBordro(message, error = false) {
    if (window.Swal) return Swal.fire({icon:error?'error':'success', title:error?'İşlem başarısız':'İşlem tamamlandı', text:message, confirmButtonText:'Tamam', confirmButtonColor:error?'#e11d48':'#4f46e5'});
    const toast=document.createElement('div'); toast.className='fixed left-4 right-4 top-20 z-[300] rounded-2xl px-4 py-3 text-center text-xs font-black text-white shadow-2xl '+(error?'bg-rose-600':'bg-emerald-600'); toast.textContent=message; document.body.appendChild(toast); setTimeout(()=>toast.remove(),3500);
}
async function confirmBordro({title, text, icon='question', confirmText='Devam Et', danger=false, html=''}) {
    if (!window.Swal) { notifyBordro('Onay penceresi yüklenemedi. Lütfen sayfayı yenileyin.', true); return false; }
    const result=await Swal.fire({title, text:html?'':text, html:html||undefined, icon, showCancelButton:true, confirmButtonText:confirmText, cancelButtonText:'Vazgeç', reverseButtons:true, focusCancel:true, confirmButtonColor:danger?'#e11d48':'#4f46e5', cancelButtonColor:'#64748b'});
    return result.isConfirmed;
}
async function calculatePayroll() { const approved=await confirmBordro({title:'Maaşlar hesaplansın mı?', text:'Bu dönemdeki tüm personellerin maaşı yeniden hesaplanacak.', icon:'question', confirmText:'Hesapla'}); if(!approved)return; try { const result = await bordroPost({action:'maas-hesapla', donem_token:bordroPeriodToken, personel_tokens:bordroTokens}); await notifyBordro(result.message); location.reload(); } catch(e) { notifyBordro(e.message, true); } }
async function togglePeriod(force = false) { if(!force){const approved=await confirmBordro({title:bordroIsClosed?'Dönem yeniden açılsın mı?':'Dönem kapatılsın mı?', text:bordroIsClosed?'Dönem yeniden düzenlemeye açılacak.':'Kapalı dönemde bordro verileri değiştirilemez.', icon:'warning', confirmText:bordroIsClosed?'Dönemi Aç':'Dönemi Kapat', danger:!bordroIsClosed}); if(!approved)return;} try { const data = {action:bordroIsClosed?'donem-ac':'donem-kapat', donem_token:bordroPeriodToken}; if(force)data.force_close='1'; const result=await bordroPost(data); if(result.status==='warning'){const warnings=(result.warnings||[]).map(item=>`<li class="mb-1">${item}</li>`).join(''); const approved=await confirmBordro({title:'Bekleyen işlemler var', icon:'warning', confirmText:'Yine de Kapat', danger:true, html:`<div class="text-left text-sm"><p class="mb-2">Bu döneme ait tamamlanmamış işlemler bulundu:</p><ul class="list-disc pl-5">${warnings}</ul></div>`}); if(approved)return togglePeriod(true); return;} await notifyBordro(result.message); location.reload(); } catch(e) { notifyBordro(e.message,true); } }
async function resetAllPayments() { const approved=await confirmBordro({title:'Ödeme dağılımları sıfırlansın mı?', text:'Tüm manuel ödeme dağılımları kaldırılıp varsayılan değerler yeniden hesaplanacak.', icon:'warning', confirmText:'Sıfırla', danger:true}); if(!approved)return; try { const result=await bordroPost({action:'odeme-reset-all', donem_token:bordroPeriodToken}); await notifyBordro(result.message); location.reload(); } catch(e) { notifyBordro(e.message,true); } }
async function openPayrollDetail(token) {
    showBordroSheet('Bordro Detayı', '<p class="py-12 text-center text-slate-400" role="status">Bordro hazırlanıyor…</p>');
    const version = bordroSheetVersion;
    try {
        const result = await bordroPost({action:'get-detail-old', bordro_token:token});
        if (version !== bordroSheetVersion) return;
        const host = document.createElement('div');
        host.className = 'mobile-payroll-desktop';
        host.innerHTML = result.html || '<p>Detay bulunamadı.</p>';
        // Desktop starts panels collapsed; show both accounts on mobile initially.
        host.querySelectorAll('.bordro-main-calculation-collapse').forEach(el => el.classList.add('show'));
        host.querySelectorAll('.calculation-toggle').forEach(el => el.setAttribute('aria-expanded', 'true'));
        document.getElementById('bordroSheetBody').replaceChildren(host);
    } catch (e) {
        if (version !== bordroSheetVersion) return;
        const error = document.createElement('p');
        error.className = 'rounded-xl bg-rose-50 p-3 font-bold text-rose-600';
        error.setAttribute('role', 'alert');
        error.textContent = e.message;
        document.getElementById('bordroSheetBody').replaceChildren(error);
    }
}
function openPaymentModal(button) { const d=button.dataset; showBordroSheet('Ödeme Dağıt · '+d.name, `<form onsubmit="savePayment(event)" data-token="${d.token}" class="space-y-3"><label class="block text-xs font-bold">Banka<input name="banka" inputmode="decimal" value="${d.banka}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Sodexo<input name="sodexo" inputmode="decimal" value="${d.sodexo}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Diğer ödeme<input name="diger" inputmode="decimal" value="${d.diger}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><button class="h-11 w-full rounded-xl bg-indigo-600 text-xs font-black text-white">Dağılımı Kaydet</button></form>`); }
async function savePayment(event) { event.preventDefault(); const form=event.currentTarget; try { const result=await bordroPost({action:'odeme-dagit', bordro_token:form.dataset.token, banka_odemesi:form.banka.value.replace(',','.'), sodexo_odemesi:form.sodexo.value.replace(',','.'), diger_odeme:form.diger.value.replace(',','.')}); notifyBordro(result.message); setTimeout(()=>location.reload(),500); } catch(e) { notifyBordro(e.message,true); } }
function openAdjustmentModal(button, kind) { const d=button.dataset; const isIncome=kind==='gelir'; const options=isIncome?'<option value="prim">Prim</option><option value="mesai">Fazla Mesai</option><option value="ikramiye">İkramiye</option><option value="yol">Yol Yardımı</option><option value="yemek">Yemek Yardımı</option><option value="diger">Diğer</option>':'<option value="avans">Avans</option><option value="icra">İcra</option><option value="nafaka">Nafaka</option><option value="izin_kesinti">Ücretsiz İzin</option><option value="diger">Diğer</option>'; showBordroSheet((isIncome?'Gelir Ekle · ':'Kesinti Ekle · ')+d.name, `<form onsubmit="saveAdjustment(event)" data-token="${d.token}" data-kind="${kind}" class="space-y-3"><label class="block text-xs font-bold">Tür<select name="tur" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900">${options}</select></label><label class="block text-xs font-bold">Tutar<input name="tutar" inputmode="decimal" required class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Açıklama<input name="aciklama" required maxlength="250" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><button class="h-11 w-full rounded-xl ${isIncome?'bg-emerald-600':'bg-rose-600'} text-xs font-black text-white">Kaydet</button></form>`); }
async function saveAdjustment(event) { event.preventDefault(); const form=event.currentTarget, income=form.dataset.kind==='gelir'; const data={action:income?'personel-gelir-ekle':'personel-kesinti-ekle', personel_token:form.dataset.token, donem_token:bordroPeriodToken, aciklama:form.aciklama.value, tarih:'<?= date('d.m.Y') ?>'}; data[income?'ek_odeme_tur':'kesinti_tur']=form.tur.value; data[income?'gelir_tutar':'kesinti_tutar']=form.tutar.value.replace(',','.'); try { const result=await bordroPost(data); notifyBordro(result.message); setTimeout(()=>location.reload(),500); } catch(e) { notifyBordro(e.message,true); } }
</script>
