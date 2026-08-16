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

<div id="bordroSheet" class="fixed inset-0 z-[200] hidden items-end bg-slate-900/60 backdrop-blur-sm" onclick="handleBordroSheetBackdrop(event)">
    <div id="bordroSheetPanel" class="mx-auto flex max-h-[90vh] w-full max-w-lg translate-y-full flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl transition-transform duration-300 ease-out dark:bg-card-dark">
        <div class="flex justify-center pb-1 pt-3"><span class="h-1 w-11 rounded-full bg-slate-200 dark:bg-slate-700"></span></div>
        <div class="flex items-start gap-2 border-b border-slate-100 px-4 pb-3 pt-1 dark:border-slate-800">
            <div class="min-w-0 flex-1">
                <h2 id="bordroSheetTitle" class="text-sm font-black dark:text-white"></h2>
                <div id="bordroSheetPerson" class="mobile-payroll-person hidden"></div>
            </div>
            <button type="button" onclick="closeBordroSheet()" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800" aria-label="Paneli kapat"><span class="material-symbols-outlined text-lg">close</span></button>
        </div>
        <div id="bordroSheetBody" class="overflow-y-auto p-4 text-sm dark:text-slate-200"></div>
    </div>
</div>

<style>
    #bordroSheetBody .mobile-payroll-legacy { color: #334155; font-size: 11px; line-height: 1.45; }
    #bordroSheetBody .mobile-payroll-legacy .table-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 10px; }
    #bordroSheetBody .mobile-payroll-legacy table { width: 100%; min-width: 520px; border-collapse: collapse; background: #fff; }
    #bordroSheetBody .mobile-payroll-legacy th { padding: 9px 10px; background: #f8fafc; color: #64748b; font-size: 9px; text-align: left; text-transform: uppercase; letter-spacing: .04em; }
    #bordroSheetBody .mobile-payroll-legacy td { padding: 9px 10px; border-top: 1px solid #eef2f7; vertical-align: middle; }
    #bordroSheetBody .mobile-payroll-legacy h5,
    #bordroSheetBody .mobile-payroll-legacy h6 { margin: 12px 0 7px; color: #1e293b; font-weight: 800; }
    #bordroSheetBody .mobile-payroll-legacy .card { margin-bottom: 10px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; }
    #bordroSheetBody .mobile-payroll-legacy .card-header,
    #bordroSheetBody .mobile-payroll-legacy .card-body { padding: 10px; }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view { padding: 4px !important; background: transparent !important; }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .row { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 18px; }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .col { min-width: 0; }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .ref-card,
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .panel-card { margin: 0; border: 1px solid #bfdbfe; border-radius: 14px; padding: 14px; background: #eff6ff; box-shadow: 0 4px 14px rgba(59, 130, 246, .06); }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .section-title.gains + .row .ref-card { border-color: #bbf7d0; background: #f0fdf4; box-shadow: 0 4px 14px rgba(34, 197, 94, .06); }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .section-title.deductions + .row .ref-card { border-color: #fecdd3; background: #fff1f2; box-shadow: 0 4px 14px rgba(244, 63, 94, .06); }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .ref-card { margin: 0; border: 1px solid #bfdbfe; border-radius: 14px; padding: 14px; background: #eff6ff; box-shadow: 0 4px 14px rgba(59, 130, 246, .06); }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .section-title { margin: 20px 0 10px; }
    #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .bottom-panels { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 18px; }
    .mobile-payroll-person .bg-white { background: transparent !important; }
    .mobile-payroll-person > div { display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 7px; margin: 0 !important; border: 0 !important; padding: 0 !important; }
    .mobile-payroll-person h5 { margin: 0 0 4px; color: #0f172a; font-size: 12px; line-height: 1.25; font-weight: 900; }
    .mobile-payroll-person .small { display: flex; flex-wrap: wrap; gap: 4px 10px; color: #64748b; font-size: 9px; line-height: 1.35; font-weight: 700; }
    .mobile-payroll-person > div > div:last-child { display: flex !important; flex-wrap: wrap; gap: 6px; }
    .mobile-payroll-person .badge { display: inline-flex; min-width: 76px; flex-direction: column; align-items: flex-start !important; margin: 0; border: 1px solid #dbeafe; border-radius: 9px; padding: 5px 8px; color: #1e3a8a; background: #eff6ff; font-size: 9px; line-height: 1.3; }
    .mobile-payroll-person .badge small { margin-bottom: 2px; font-size: 8px !important; }
    #bordroSheetBody .mobile-payroll-legacy .text-end { text-align: right; }
    #bordroSheetBody .mobile-payroll-legacy .text-center { text-align: center; }
    #bordroSheetBody .mobile-payroll-legacy .fw-bold { font-weight: 800; }
    #bordroSheetBody .mobile-payroll-legacy .text-danger,
    #bordroSheetBody .mobile-payroll-legacy .red-text { color: #e11d48; }
    #bordroSheetBody .mobile-payroll-legacy .text-success,
    #bordroSheetBody .mobile-payroll-legacy .green-text { color: #059669; }
    #bordroSheetBody .mobile-payroll-legacy .text-primary { color: #4f46e5; }
    .dark #bordroSheetBody .mobile-payroll-legacy { color: #cbd5e1; }
    .dark #bordroSheetBody .mobile-payroll-legacy table,
    .dark #bordroSheetBody .mobile-payroll-legacy .card { background: #111827; border-color: #334155; }
    .dark #bordroSheetBody .mobile-payroll-legacy th { background: #1e293b; color: #94a3b8; }
    .dark #bordroSheetBody .mobile-payroll-legacy td { border-color: #334155; }
    .dark .mobile-payroll-person h5 { color: #f8fafc; }
    .dark .mobile-payroll-person .badge { border-color: #1e40af; background: rgba(30,58,138,.25); color: #dbeafe; }
    .dark #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .section-title.gains + .row .ref-card { border-color: rgba(34,197,94,.3); background: rgba(20,83,45,.18); }
    .dark #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .section-title.deductions + .row .ref-card { border-color: rgba(244,63,94,.3); background: rgba(136,19,55,.18); }
    .dark #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .ref-card,
    .dark #bordroSheetBody .mobile-payroll-legacy .bordro-ref-view .panel-card { border-color: rgba(59,130,246,.3); background: rgba(30,64,175,.14); }
    #bordroSheetBody .mobile-bank-tooltip > div { min-width: 0 !important; padding: 0 !important; font-size: 10px !important; }
    #bordroSheetBody .mobile-bank-tooltip h6 { margin: 0 0 8px !important; padding: 0 0 7px !important; border-bottom: 1px solid rgba(148,163,184,.22) !important; color: #cbd5e1 !important; font-size: 10px !important; font-weight: 800 !important; }
    #bordroSheetBody .mobile-bank-tooltip .d-flex { display: flex !important; }
    #bordroSheetBody .mobile-bank-tooltip .flex-column { flex-direction: column !important; }
    #bordroSheetBody .mobile-bank-tooltip .justify-content-between { justify-content: space-between !important; gap: 14px; }
    #bordroSheetBody .mobile-bank-tooltip .border-top { margin-top: 6px !important; padding-top: 6px !important; border-top: 1px solid rgba(148,163,184,.22) !important; }
    #bordroSheetBody .mobile-bank-tooltip .fw-bold { font-weight: 900 !important; }
    #bordroSheetBody .mobile-bank-tooltip .text-danger { color: #fb7185 !important; }
    #bordroSheetBody .mobile-bank-tooltip .text-success { color: #34d399 !important; }
    #bordroSheetBody .mobile-bank-tooltip .text-primary { color: #60a5fa !important; }
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
function closeBordroSheet() { const sheet=document.getElementById('bordroSheet'), panel=document.getElementById('bordroSheetPanel'); panel.classList.add('translate-y-full'); setTimeout(()=>{sheet.classList.add('hidden'); sheet.classList.remove('flex'); document.body.classList.remove('overflow-hidden');},300); }
function showBordroSheet(title, html) { const sheet=document.getElementById('bordroSheet'), panel=document.getElementById('bordroSheetPanel'), person=document.getElementById('bordroSheetPerson'), titleEl=document.getElementById('bordroSheetTitle'); titleEl.textContent=title; titleEl.classList.toggle('hidden',!title); document.getElementById('bordroSheetBody').innerHTML=html; person.innerHTML=''; person.classList.add('hidden'); sheet.classList.remove('hidden'); sheet.classList.add('flex'); document.body.classList.add('overflow-hidden'); requestAnimationFrame(()=>requestAnimationFrame(()=>panel.classList.remove('translate-y-full'))); }
function handleBordroSheetBackdrop(event) { if(event.target===document.getElementById('bordroSheet'))closeBordroSheet(); }
function toggleBankTooltip(event) { event.stopPropagation(); const tooltip=document.getElementById('mobileBankTooltip'); if(!tooltip)return; const willOpen=tooltip.classList.contains('hidden'); document.querySelectorAll('.mobile-bank-tooltip').forEach(item=>item.classList.add('hidden')); tooltip.classList.toggle('hidden',!willOpen); event.currentTarget.setAttribute('aria-expanded',willOpen?'true':'false'); }
document.addEventListener('click',event=>{ if(!event.target.closest('.bank-tooltip-wrap'))document.querySelectorAll('.mobile-bank-tooltip').forEach(item=>item.classList.add('hidden')); });
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
function payrollSummaryValue(value) { return value || '—'; }
async function openPayrollDetail(token) {
    showBordroSheet('', '<div class="flex flex-col items-center py-12 text-slate-400"><div class="mb-3 h-8 w-8 animate-spin rounded-full border-3 border-indigo-200 border-t-indigo-600"></div><span class="text-xs font-bold">Bordro hazırlanıyor…</span></div>');
    try {
        const result = await bordroPost({action:'get-detail', bordro_token:token});
        const s = result.summary || {};
        const legacyHost = document.createElement('div');
        legacyHost.innerHTML = result.html || '';
        const personInfo = legacyHost.querySelector('.bordro-ref-view > div');
        const personHtml = personInfo ? personInfo.outerHTML : '';
        if (personInfo) personInfo.remove();
        const legacyHtml = legacyHost.innerHTML;
        const sheetPerson = document.getElementById('bordroSheetPerson');
        if (personHtml) { sheetPerson.innerHTML = personHtml; sheetPerson.classList.remove('hidden'); }
        document.getElementById('bordroSheetBody').innerHTML = `<div class="space-y-3">
            <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 p-4 text-white shadow-lg shadow-indigo-600/20">
                <div class="absolute -right-8 -top-10 h-28 w-28 rounded-full bg-white/10"></div>
                <p class="relative text-[9px] font-black uppercase tracking-[.16em] text-indigo-100">Ele Geçen Net Tutar</p>
                <p class="relative mt-1 text-2xl font-black">${payrollSummaryValue(s.net_maas)}</p>
                <div class="relative mt-3 grid grid-cols-2 gap-2 border-t border-white/20 pt-3">
                    <div><span class="block text-[9px] font-bold text-indigo-100">TOPLAM HAKEDİŞ</span><b class="text-sm">${payrollSummaryValue(s.brut_toplam)}</b></div>
                    <div class="text-right"><span class="block text-[9px] font-bold text-indigo-100">TOPLAM KESİNTİ</span><b class="text-sm text-rose-200">${payrollSummaryValue(s.kesinti_toplam)}</b></div>
                </div>
            </section>
            <section class="grid grid-cols-2 gap-2">
                ${s.banka ? `<div class="bank-tooltip-wrap relative rounded-xl border border-blue-100 bg-blue-50 p-3 dark:border-blue-900/30 dark:bg-blue-900/15"><div class="flex items-start justify-between gap-2"><span class="material-symbols-outlined text-lg text-blue-500">account_balance</span>${s.banka_detay ? `<button type="button" onclick="toggleBankTooltip(event)" class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-blue-600 active:scale-95 dark:bg-blue-900/40 dark:text-blue-300" aria-label="Banka ödemesi açıklaması" aria-expanded="false"><span class="material-symbols-outlined text-[16px]">info</span></button>` : ''}</div><span class="mt-1 block text-[9px] font-black uppercase text-blue-400">Banka</span><b class="text-sm text-blue-700 dark:text-blue-300">${s.banka}</b>${s.banka_detay ? `<div id="mobileBankTooltip" class="mobile-bank-tooltip absolute left-0 top-[calc(100%+8px)] z-40 hidden w-[300px] max-w-[calc(100vw-64px)] rounded-xl border border-slate-700 bg-slate-800 p-3 text-[10px] leading-relaxed text-slate-300 shadow-2xl">${s.banka_detay}</div>` : ''}</div>` : ''}
                ${s.elden ? `<div class="rounded-xl border border-amber-100 bg-amber-50 p-3 dark:border-amber-900/30 dark:bg-amber-900/15"><span class="material-symbols-outlined text-lg text-amber-500">payments</span><span class="mt-1 block text-[9px] font-black uppercase text-amber-500">Elden</span><b class="text-sm text-amber-700 dark:text-amber-300">${s.elden}</b></div>` : ''}
                ${s.sodexo ? `<div class="rounded-xl border border-cyan-100 bg-cyan-50 p-3 dark:border-cyan-900/30 dark:bg-cyan-900/15"><span class="material-symbols-outlined text-lg text-cyan-500">restaurant</span><span class="mt-1 block text-[9px] font-black uppercase text-cyan-500">Sodexo</span><b class="text-sm text-cyan-700 dark:text-cyan-300">${s.sodexo}</b></div>` : ''}
                ${s.diger ? `<div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800"><span class="material-symbols-outlined text-lg text-slate-500">wallet</span><span class="mt-1 block text-[9px] font-black uppercase text-slate-400">Diğer</span><b class="text-sm text-slate-700 dark:text-slate-200">${s.diger}</b></div>` : ''}
            </section>
            <details open class="group overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/40">
                <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-xs font-black text-slate-700 dark:text-slate-200"><span class="material-symbols-outlined text-lg text-indigo-500">receipt_long</span>Hesaplama Kalemleri<span class="material-symbols-outlined ml-auto text-lg transition-transform group-open:rotate-180">expand_more</span></summary>
                <div class="mobile-payroll-legacy border-t border-slate-100 p-3 dark:border-slate-800">${legacyHtml || '<p>Detay bulunamadı.</p>'}</div>
            </details>
        </div>`;
    } catch(e) {
        document.getElementById('bordroSheetBody').innerHTML = '<p class="rounded-xl bg-rose-50 p-3 font-bold text-rose-600">'+e.message+'</p>';
    }
}
function openPaymentModal(button) { const d=button.dataset; showBordroSheet('Ödeme Dağıt · '+d.name, `<form onsubmit="savePayment(event)" data-token="${d.token}" class="space-y-3"><label class="block text-xs font-bold">Banka<input name="banka" inputmode="decimal" value="${d.banka}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Sodexo<input name="sodexo" inputmode="decimal" value="${d.sodexo}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Diğer ödeme<input name="diger" inputmode="decimal" value="${d.diger}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><button class="h-11 w-full rounded-xl bg-indigo-600 text-xs font-black text-white">Dağılımı Kaydet</button></form>`); }
async function savePayment(event) { event.preventDefault(); const form=event.currentTarget; try { const result=await bordroPost({action:'odeme-dagit', bordro_token:form.dataset.token, banka_odemesi:form.banka.value.replace(',','.'), sodexo_odemesi:form.sodexo.value.replace(',','.'), diger_odeme:form.diger.value.replace(',','.')}); notifyBordro(result.message); setTimeout(()=>location.reload(),500); } catch(e) { notifyBordro(e.message,true); } }
function openAdjustmentModal(button, kind) { const d=button.dataset; const isIncome=kind==='gelir'; const options=isIncome?'<option value="prim">Prim</option><option value="mesai">Fazla Mesai</option><option value="ikramiye">İkramiye</option><option value="yol">Yol Yardımı</option><option value="yemek">Yemek Yardımı</option><option value="diger">Diğer</option>':'<option value="avans">Avans</option><option value="icra">İcra</option><option value="nafaka">Nafaka</option><option value="izin_kesinti">Ücretsiz İzin</option><option value="diger">Diğer</option>'; showBordroSheet((isIncome?'Gelir Ekle · ':'Kesinti Ekle · ')+d.name, `<form onsubmit="saveAdjustment(event)" data-token="${d.token}" data-kind="${kind}" class="space-y-3"><label class="block text-xs font-bold">Tür<select name="tur" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900">${options}</select></label><label class="block text-xs font-bold">Tutar<input name="tutar" inputmode="decimal" required class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Açıklama<input name="aciklama" required maxlength="250" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><button class="h-11 w-full rounded-xl ${isIncome?'bg-emerald-600':'bg-rose-600'} text-xs font-black text-white">Kaydet</button></form>`); }
async function saveAdjustment(event) { event.preventDefault(); const form=event.currentTarget, income=form.dataset.kind==='gelir'; const data={action:income?'personel-gelir-ekle':'personel-kesinti-ekle', personel_token:form.dataset.token, donem_token:bordroPeriodToken, aciklama:form.aciklama.value, tarih:'<?= date('d.m.Y') ?>'}; data[income?'ek_odeme_tur':'kesinti_tur']=form.tur.value; data[income?'gelir_tutar':'kesinti_tutar']=form.tutar.value.replace(',','.'); try { const result=await bordroPost(data); notifyBordro(result.message); setTimeout(()=>location.reload(),500); } catch(e) { notifyBordro(e.message,true); } }
</script>
