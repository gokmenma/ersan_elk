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
                        <?= htmlspecialchars($donem->donem_adi . ' · ' . date('Y', strtotime($donem->baslangic_tarihi)), ENT_QUOTES, 'UTF-8') ?>
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
                <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Personel</span>
                    <p class="mt-1 text-lg font-black text-slate-800 dark:text-white"><?= $summary['personel'] ?></p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
                    <span class="text-[9px] font-black uppercase tracking-wider text-emerald-500">Net Ödenecek</span>
                    <p class="mt-1 truncate text-base font-black text-emerald-600"><?= $money($summary['net']) ?></p>
                </div>
                <div class="rounded-2xl border border-blue-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
                    <span class="text-[9px] font-black uppercase tracking-wider text-blue-500">Banka</span>
                    <p class="mt-1 truncate text-sm font-black text-blue-600"><?= $money($summary['banka']) ?></p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-card-dark">
                    <span class="text-[9px] font-black uppercase tracking-wider text-amber-500">Elden / Sodexo</span>
                    <p class="mt-1 truncate text-sm font-black text-amber-600"><?= $money($summary['elden'] + $summary['sodexo']) ?></p>
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
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-900/40"><span class="block text-[9px] font-bold uppercase text-slate-400">Çalışma</span><b class="text-slate-700 dark:text-slate-200"><?= number_format($row['gun'], 1, ',', '.') ?> gün</b></div>
                                <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-900/40"><span class="block text-[9px] font-bold uppercase text-slate-400">Toplam Alacak</span><b class="text-slate-700 dark:text-slate-200"><?= $money($row['alacak']) ?></b></div>
                                <div class="rounded-xl bg-rose-50 p-2.5 dark:bg-rose-900/10"><span class="block text-[9px] font-bold uppercase text-rose-400">Kesinti / İcra</span><b class="text-rose-600"><?= $money($row['kesinti'] + $row['icra']) ?></b></div>
                                <div class="rounded-xl bg-blue-50 p-2.5 dark:bg-blue-900/10"><span class="block text-[9px] font-bold uppercase text-blue-400">Banka</span><b class="text-blue-600"><?= $money($row['banka']) ?></b></div>
                                <div class="rounded-xl bg-cyan-50 p-2.5 dark:bg-cyan-900/10"><span class="block text-[9px] font-bold uppercase text-cyan-500">Sodexo</span><b class="text-cyan-600"><?= $money($row['sodexo']) ?></b></div>
                                <div class="rounded-xl bg-amber-50 p-2.5 dark:bg-amber-900/10"><span class="block text-[9px] font-bold uppercase text-amber-500">Elden</span><b class="text-amber-600"><?= $money($row['elden']) ?></b></div>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <button type="button" <?= $closed ? 'disabled' : '' ?> onclick="openPaymentModal(this)" data-token="<?= htmlspecialchars($row['token'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($p->adi_soyadi ?? '-', ENT_QUOTES, 'UTF-8') ?>" data-banka="<?= $row['banka'] ?>" data-sodexo="<?= $row['sodexo'] ?>" data-diger="<?= (float) ($p->diger_odeme ?? 0) ?>" class="h-9 flex-1 rounded-xl bg-indigo-50 text-[11px] font-black text-indigo-700 disabled:opacity-40 dark:bg-indigo-900/20 dark:text-indigo-300">Ödeme Dağıt</button>
                            </div>
                            <?php if (!$closed): ?>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <button type="button" onclick="openAdjustmentModal(this, 'gelir')" data-token="<?= htmlspecialchars($row['personel_token'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($p->adi_soyadi ?? '-', ENT_QUOTES, 'UTF-8') ?>" class="h-9 rounded-xl bg-emerald-50 text-[11px] font-black text-emerald-700 dark:bg-emerald-900/20">+ Gelir Ekle</button>
                                    <button type="button" onclick="openAdjustmentModal(this, 'kesinti')" data-token="<?= htmlspecialchars($row['personel_token'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($p->adi_soyadi ?? '-', ENT_QUOTES, 'UTF-8') ?>" class="h-9 rounded-xl bg-rose-50 text-[11px] font-black text-rose-700 dark:bg-rose-900/20">− Kesinti Ekle</button>
                                </div>
                            <?php endif; ?>
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

<div id="bordroSheet" class="fixed inset-0 z-[200] hidden bg-slate-900/60 p-3 backdrop-blur-sm">
    <div class="mx-auto mt-8 flex max-h-[85vh] max-w-lg flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-card-dark">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800"><h2 id="bordroSheetTitle" class="text-sm font-black dark:text-white">Bordro Detayı</h2><button type="button" onclick="closeBordroSheet()" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800"><span class="material-symbols-outlined text-lg">close</span></button></div>
        <div id="bordroSheetBody" class="overflow-y-auto p-4 text-sm dark:text-slate-200"></div>
    </div>
</div>

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
function closeBordroSheet() { document.getElementById('bordroSheet').classList.add('hidden'); }
function showBordroSheet(title, html) { document.getElementById('bordroSheetTitle').textContent = title; document.getElementById('bordroSheetBody').innerHTML = html; document.getElementById('bordroSheet').classList.remove('hidden'); }
function openOtherPayrollActions() { showBordroSheet('Diğer Bordro İşlemleri', `<div class="space-y-2">
    <button type="button" onclick="closeBordroSheet(); togglePeriod()" class="flex h-12 w-full items-center gap-3 rounded-xl <?= $closed ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?> px-4 text-left text-xs font-black"><span class="material-symbols-outlined"><?= $closed ? 'lock_open' : 'lock' ?></span><span><?= $closed ? 'Dönemi Yeniden Aç' : 'Dönemi Kapat' ?></span><span class="material-symbols-outlined ml-auto text-base">chevron_right</span></button>
    <button type="button" <?= $closed ? 'disabled' : '' ?> onclick="resetAllPayments()" class="flex h-12 w-full items-center gap-3 rounded-xl bg-amber-50 px-4 text-left text-xs font-black text-amber-700 disabled:cursor-not-allowed disabled:opacity-40"><span class="material-symbols-outlined">restart_alt</span><span>Ödeme Dağılımlarını Sıfırla</span><span class="material-symbols-outlined ml-auto text-base">chevron_right</span></button>
    <button type="button" onclick="location.reload()" class="flex h-12 w-full items-center gap-3 rounded-xl bg-blue-50 px-4 text-left text-xs font-black text-blue-700"><span class="material-symbols-outlined">refresh</span><span>Listeyi Yenile</span><span class="material-symbols-outlined ml-auto text-base">chevron_right</span></button>
    <a href="?force_desktop=1&p=bordro/list" class="flex h-12 w-full items-center gap-3 rounded-xl bg-slate-100 px-4 text-left text-xs font-black text-slate-700 dark:bg-slate-800 dark:text-slate-200"><span class="material-symbols-outlined">desktop_windows</span><span>Tüm Bordro Araçları</span><span class="material-symbols-outlined ml-auto text-base">open_in_new</span></a>
</div>`); }
async function bordroPost(data) { const body = new URLSearchParams(); Object.entries(data).forEach(([key, value]) => Array.isArray(value) ? value.forEach(item => body.append(key + '[]', item)) : body.append(key, value)); const response = await fetch(bordroApiUrl, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body}); const result = await response.json(); if (!response.ok || result.status === 'error') throw new Error(result.message || 'İşlem tamamlanamadı.'); return result; }
function notifyBordro(message, error = false) { if (window.Swal) Swal.fire({icon:error?'error':'success', title:error?'İşlem başarısız':'İşlem tamamlandı', text:message}); else alert(message); }
async function calculatePayroll() { if (!confirm('Bu dönemdeki tüm personellerin maaşı yeniden hesaplansın mı?')) return; try { const result = await bordroPost({action:'maas-hesapla', donem_token:bordroPeriodToken, personel_tokens:bordroTokens}); notifyBordro(result.message); setTimeout(()=>location.reload(), 600); } catch(e) { notifyBordro(e.message, true); } }
async function togglePeriod(force = false) { if (!confirm(bordroIsClosed ? 'Dönem yeniden açılsın mı?' : 'Dönem kapatılsın mı?')) return; try { const data = {action:bordroIsClosed?'donem-ac':'donem-kapat', donem_token:bordroPeriodToken}; if(force) data.force_close='1'; const result = await bordroPost(data); if(result.status === 'warning') { if(confirm((result.warnings || []).join('\n') + '\n\nYine de kapatılsın mı?')) return togglePeriod(true); return; } notifyBordro(result.message); setTimeout(()=>location.reload(), 500); } catch(e) { notifyBordro(e.message, true); } }
async function resetAllPayments() { if (!confirm('Bu dönemdeki tüm manuel ödeme dağılımları varsayılana döndürülsün mü?')) return; try { const result = await bordroPost({action:'odeme-reset-all', donem_token:bordroPeriodToken}); notifyBordro(result.message); setTimeout(()=>location.reload(), 600); } catch(e) { notifyBordro(e.message, true); } }
async function openPayrollDetail(token) { showBordroSheet('Bordro Detayı', '<div class="py-12 text-center text-slate-400">Detay yükleniyor…</div>'); try { const result = await bordroPost({action:'get-detail', bordro_token:token}); document.getElementById('bordroSheetBody').innerHTML = result.html || result.data || '<p>Detay bulunamadı.</p>'; } catch(e) { document.getElementById('bordroSheetBody').innerHTML = '<p class="rounded-xl bg-rose-50 p-3 font-bold text-rose-600">'+e.message+'</p>'; } }
function openPaymentModal(button) { const d=button.dataset; showBordroSheet('Ödeme Dağıt · '+d.name, `<form onsubmit="savePayment(event)" data-token="${d.token}" class="space-y-3"><label class="block text-xs font-bold">Banka<input name="banka" inputmode="decimal" value="${d.banka}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Sodexo<input name="sodexo" inputmode="decimal" value="${d.sodexo}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Diğer ödeme<input name="diger" inputmode="decimal" value="${d.diger}" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><button class="h-11 w-full rounded-xl bg-indigo-600 text-xs font-black text-white">Dağılımı Kaydet</button></form>`); }
async function savePayment(event) { event.preventDefault(); const form=event.currentTarget; try { const result=await bordroPost({action:'odeme-dagit', bordro_token:form.dataset.token, banka_odemesi:form.banka.value.replace(',','.'), sodexo_odemesi:form.sodexo.value.replace(',','.'), diger_odeme:form.diger.value.replace(',','.')}); notifyBordro(result.message); setTimeout(()=>location.reload(),500); } catch(e) { notifyBordro(e.message,true); } }
function openAdjustmentModal(button, kind) { const d=button.dataset; const isIncome=kind==='gelir'; const options=isIncome?'<option value="prim">Prim</option><option value="mesai">Fazla Mesai</option><option value="ikramiye">İkramiye</option><option value="yol">Yol Yardımı</option><option value="yemek">Yemek Yardımı</option><option value="diger">Diğer</option>':'<option value="avans">Avans</option><option value="icra">İcra</option><option value="nafaka">Nafaka</option><option value="izin_kesinti">Ücretsiz İzin</option><option value="diger">Diğer</option>'; showBordroSheet((isIncome?'Gelir Ekle · ':'Kesinti Ekle · ')+d.name, `<form onsubmit="saveAdjustment(event)" data-token="${d.token}" data-kind="${kind}" class="space-y-3"><label class="block text-xs font-bold">Tür<select name="tur" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900">${options}</select></label><label class="block text-xs font-bold">Tutar<input name="tutar" inputmode="decimal" required class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><label class="block text-xs font-bold">Açıklama<input name="aciklama" required maxlength="250" class="mt-1 w-full rounded-xl border-slate-200 dark:bg-slate-900"></label><button class="h-11 w-full rounded-xl ${isIncome?'bg-emerald-600':'bg-rose-600'} text-xs font-black text-white">Kaydet</button></form>`); }
async function saveAdjustment(event) { event.preventDefault(); const form=event.currentTarget, income=form.dataset.kind==='gelir'; const data={action:income?'personel-gelir-ekle':'personel-kesinti-ekle', personel_token:form.dataset.token, donem_token:bordroPeriodToken, aciklama:form.aciklama.value, tarih:'<?= date('d.m.Y') ?>'}; data[income?'ek_odeme_tur':'kesinti_tur']=form.tur.value; data[income?'gelir_tutar':'kesinti_tutar']=form.tutar.value.replace(',','.'); try { const result=await bordroPost(data); notifyBordro(result.message); setTimeout(()=>location.reload(),500); } catch(e) { notifyBordro(e.message,true); } }
</script>
