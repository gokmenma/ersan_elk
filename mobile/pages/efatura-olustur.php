<?php
\App\Service\Gate::authorizeOrDie('efatura/olustur');
use App\Model\EInvoiceModel;
use App\Model\EFaturaMalHizmetModel;
use App\Model\EFaturaNotSablonModel;
use App\Helper\Security;
use App\Config\EdmConfig;

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$invoiceModel = new EInvoiceModel();
$malHizmetModel = new EFaturaMalHizmetModel();
$notSablonModel = new EFaturaNotSablonModel();

// Cariler listesi (Hızlı seçim için)
$cariler = $invoiceModel->invoiceCustomers($firmId);
foreach ($cariler as &$customer) {
    $customer['enc_id'] = Security::encrypt((string)$customer['id']);
}
unset($customer);

// Mal / Hizmet listesi (Hızlı seçim için)
$malHizmetler = $malHizmetModel->getAllActive($firmId);

// Not Şablonları
$notSablonlari = $notSablonModel->getAll($firmId);

// Düzenleme durumu (Draft Edit)
$draftId = $_GET['draft_id'] ?? null;
$existingDraft = null;
if (!empty($draftId)) {
    try {
        $rawDraftId = Security::decrypt($draftId);
        if ($rawDraftId && is_numeric($rawDraftId)) {
            $existingDraft = $invoiceModel->getInvoiceById((int)$rawDraftId, $firmId);
        }
    } catch (\Exception $e) {}
}

if (!function_exists('cleanInvoiceNotes')) {
    function cleanInvoiceNotes(?string $notes): string {
        if (empty($notes)) return '';
        if (preg_match('/<[a-z][\s\S]*>/i', $notes)) {
            $n = preg_replace('/<\s*br\s*\/?>/i', "\n", $notes);
            $n = preg_replace('/<\s*\/\s*tr\s*>/i', "\n", $n);
            $n = preg_replace('/<\s*\/\s*td\s*>/i', " - ", $n);
            $n = preg_replace('/<\s*\/\s*th\s*>/i', " - ", $n);
            $n = preg_replace('/<\s*\/\s*(?:p|div|li|h[1-6])\s*>/i', "\n", $n);
            $n = strip_tags($n);
            $n = html_entity_decode($n, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $lines = array_map(function($line) {
                $line = trim(preg_replace('/\s+/', ' ', $line));
                return trim($line, " -");
            }, explode("\n", $n));
            $lines = array_filter($lines, fn($l) => $l !== '');
            return implode("\n", $lines);
        }
        return html_entity_decode($notes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

$unitCodes = [
    'C62' => 'Adet',
    'KGM' => 'Kilogram',
    'MTR' => 'Metre',
    'LTR' => 'Litre',
    'HUR' => 'Saat',
    'DAY' => 'Gün',
    'MON' => 'Ay',
    'PK'  => 'Paket',
    'BX'  => 'Kutu',
    'SET' => 'Set'
];
?>

<div class="px-3.5 py-4 space-y-4 pb-28">

    <!-- 1. Üst Başlık & Geri Dön Butonu -->
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
            <a href="?p=efatura" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">arrow_back</span>
            </a>
            <div>
                <h1 class="text-base font-black text-slate-900 dark:text-white leading-tight">
                    <?= $existingDraft ? 'Taslak Faturayı Düzenle' : 'Yeni Fatura Kes' ?>
                </h1>
                <p class="text-[11px] font-medium text-slate-500">Adım adım e-fatura / e-arşiv düzenleme</p>
            </div>
        </div>

        <input type="hidden" id="editInvoiceId" value="<?= $draftId ? htmlspecialchars($draftId) : '' ?>">
    </div>

    <!-- 2. 4 Aşamalı Step Wizard Çubuğu -->
    <div class="bg-white dark:bg-card-dark rounded-2xl p-2.5 border border-slate-100 dark:border-slate-700/60 shadow-xs">
        <div class="grid grid-cols-4 gap-1 relative" id="wizardTabs">
            <!-- Adım 1 -->
            <button type="button" onclick="goToStep(1)" id="stepTab-1" class="wizard-tab-btn flex flex-col items-center py-2 px-1 rounded-xl text-primary bg-primary/10 transition-all">
                <div class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-black mb-1 shadow-xs" id="stepIcon-1">1</div>
                <span class="text-[10px] font-black uppercase tracking-wider">Cari</span>
            </button>

            <!-- Adım 2 -->
            <button type="button" onclick="goToStep(2)" id="stepTab-2" class="wizard-tab-btn flex flex-col items-center py-2 px-1 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center text-xs font-black mb-1" id="stepIcon-2">2</div>
                <span class="text-[10px] font-bold uppercase tracking-wider">Fatura</span>
            </button>

            <!-- Adım 3 -->
            <button type="button" onclick="goToStep(3)" id="stepTab-3" class="wizard-tab-btn flex flex-col items-center py-2 px-1 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center text-xs font-black mb-1" id="stepIcon-3">3</div>
                <span class="text-[10px] font-bold uppercase tracking-wider">Kalemler</span>
            </button>

            <!-- Adım 4 -->
            <button type="button" onclick="goToStep(4)" id="stepTab-4" class="wizard-tab-btn flex flex-col items-center py-2 px-1 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center text-xs font-black mb-1" id="stepIcon-4">4</div>
                <span class="text-[10px] font-bold uppercase tracking-wider">Özet</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- ADIM 1: CARİ BİLGİLERİ -->
    <!-- ========================================================================= -->
    <div id="stepContent-1" class="step-content space-y-3.5">
        <!-- Kayıtlı Cari Seçimi -->
        <div class="bg-white dark:bg-card-dark rounded-2xl p-4 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-primary text-[18px]">person_search</span>
                    Alıcı / Müşteri Bilgileri
                </h3>
            </div>

            <!-- Kayıtlı Carilerden Hızlı Seçim -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Kayıtlı Cari Seç (İsteğe Bağlı)</label>
                <select id="selRegisteredCari" onchange="onSelectCustomer(this)" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-900 dark:text-white">
                    <option value="">-- Listeden Cari Seçin veya Manuel Girin --</option>
                    <?php foreach ($cariler as $c): 
                        $unv = !empty($c['unvan']) ? $c['unvan'] : (!empty($c['CariAdi']) ? $c['CariAdi'] : ($c['firma'] ?? 'Cari'));
                    ?>
                        <option value="<?= $c['enc_id'] ?>"
                                data-unvan="<?= htmlspecialchars($unv) ?>"
                                data-vkn="<?= htmlspecialchars($c['vkn_tckn'] ?? '') ?>"
                                data-vd="<?= htmlspecialchars($c['vergi_dairesi'] ?? '') ?>"
                                data-adres="<?= htmlspecialchars($c['adres'] ?? ($c['Adres'] ?? '')) ?>"
                                data-il="<?= htmlspecialchars($c['il'] ?? '') ?>"
                                data-ilce="<?= htmlspecialchars($c['ilce'] ?? '') ?>"
                                data-eposta="<?= htmlspecialchars($c['eposta'] ?? ($c['Email'] ?? '')) ?>"
                                data-tel="<?= htmlspecialchars($c['telefon'] ?? ($c['Telefon'] ?? '')) ?>"
                                data-pk="<?= htmlspecialchars($c['posta_kutusu'] ?? '') ?>"
                                data-belge="<?= htmlspecialchars($c['belge_turu'] ?? 'OTOMATIK') ?>">
                            <?= htmlspecialchars($unv) ?> (<?= htmlspecialchars($c['vkn_tckn'] ?? 'VKN Yok') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- VKN / TCKN & Mükellef Sorgula -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">
                    VKN / TCKN <span class="text-rose-500">*</span>
                </label>
                <div class="flex gap-1.5">
                    <input type="text" id="inpAliciVkn" maxlength="11" placeholder="10 Haneli VKN veya 11 Haneli TCKN"
                           value="<?= htmlspecialchars($existingDraft['alici_vkn_tckn'] ?? '') ?>"
                           class="flex-1 px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                    <button type="button" onclick="checkTaxpayerUser()" class="px-3 py-2.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 rounded-xl text-xs font-bold flex items-center gap-1 active:scale-95 transition-transform" id="btnCheckTaxpayer">
                        <span class="material-symbols-outlined text-[16px]">verified</span> Sorgula
                    </button>
                </div>
                <div id="taxpayerStatusBadge" class="mt-1 hidden"></div>
            </div>

            <!-- Alıcı Ünvanı -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">
                    Alıcı Ünvanı / Adı Soyadı <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="inpAliciUnvan" placeholder="Fatura kesilecek firma ünvanı veya kişi adı"
                       value="<?= htmlspecialchars($existingDraft['alici_unvan'] ?? '') ?>"
                       class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
            </div>

            <!-- Vergi Dairesi -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Vergi Dairesi</label>
                <input type="text" id="inpAliciVd" placeholder="Vergi dairesi adı"
                       value="<?= htmlspecialchars($existingDraft['alici_vergi_dairesi'] ?? '') ?>"
                       class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
            </div>

            <!-- İl & İlçe -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">İl</label>
                    <input type="text" id="inpAliciIl" placeholder="İstanbul"
                           value="<?= htmlspecialchars($existingDraft['alici_il'] ?? '') ?>"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">İlçe</label>
                    <input type="text" id="inpAliciIlce" placeholder="Kadıköy"
                           value="<?= htmlspecialchars($existingDraft['alici_ilce'] ?? '') ?>"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
                </div>
            </div>

            <!-- Adres -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Açık Adres</label>
                <textarea id="inpAliciAdres" rows="2" placeholder="Cadde, sokak, bina ve kapı no..."
                          class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white"><?= htmlspecialchars($existingDraft['alici_adres'] ?? '') ?></textarea>
            </div>

            <!-- E-Posta & Telefon -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">E-Posta</label>
                    <input type="email" id="inpAliciEposta" placeholder="fatura@firma.com"
                           value="<?= htmlspecialchars($existingDraft['alici_eposta'] ?? '') ?>"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Telefon</label>
                    <input type="tel" id="inpAliciTel" placeholder="0532..."
                           value="<?= htmlspecialchars($existingDraft['alici_telefon'] ?? '') ?>"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
                </div>
            </div>

            <!-- Belge Türü & Fatura Profili -->
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Belge Türü</label>
                    <select id="selBelgeTuru" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                        <option value="EFATURA" <?= ($existingDraft['belge_turu'] ?? '') === 'EFATURA' ? 'selected' : '' ?>>E-Fatura</option>
                        <option value="EARSIV" <?= ($existingDraft['belge_turu'] ?? '') === 'EARSIV' ? 'selected' : '' ?>>E-Arşiv Fatura</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Fatura Profili</label>
                    <select id="selFaturaProfili" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                        <option value="TICARIFATURA" <?= ($existingDraft['fatura_profili'] ?? '') === 'TICARIFATURA' ? 'selected' : '' ?>>Ticari Fatura</option>
                        <option value="TEMELFATURA" <?= ($existingDraft['fatura_profili'] ?? '') === 'TEMELFATURA' ? 'selected' : '' ?>>Temel Fatura</option>
                        <option value="EARSIVFATURA" <?= ($existingDraft['fatura_profili'] ?? '') === 'EARSIVFATURA' ? 'selected' : '' ?>>E-Arşiv</option>
                        <option value="KAMU" <?= ($existingDraft['fatura_profili'] ?? '') === 'KAMU' ? 'selected' : '' ?>>Kamu</option>
                    </select>
                </div>
            </div>
        </div>

        <button type="button" onclick="goToStep(2)" class="w-full py-3 rounded-xl bg-primary text-white text-xs font-black shadow-sm shadow-primary/30 flex items-center justify-center gap-1.5 active:scale-[0.98] transition-transform">
            <span>Fatura Bilgilerine İlerle</span>
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- ADIM 2: FATURA DETAY BİLGİLERİ -->
    <!-- ========================================================================= -->
    <div id="stepContent-2" class="step-content space-y-3.5 hidden">
        <div class="bg-white dark:bg-card-dark rounded-2xl p-4 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-3">
            <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                <span class="material-symbols-outlined text-primary text-[18px]">receipt_long</span>
                Fatura Tarih & Tip Bilgileri
            </h3>

            <!-- Fatura Tipi -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Fatura Tipi</label>
                <select id="selFaturaTipi" onchange="onFaturaTipiChange(this.value)" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                    <option value="SATIS" <?= ($existingDraft['fatura_tipi'] ?? 'SATIS') === 'SATIS' ? 'selected' : '' ?>>SATIŞ (Standart Satış Faturası)</option>
                    <option value="IADE" <?= ($existingDraft['fatura_tipi'] ?? '') === 'IADE' ? 'selected' : '' ?>>İADE (Alış İade Faturası)</option>
                    <option value="TEVKIFAT" <?= ($existingDraft['fatura_tipi'] ?? '') === 'TEVKIFAT' ? 'selected' : '' ?>>TEVKİFAT (KDV Tevkifatlı)</option>
                    <option value="ISTISNA" <?= ($existingDraft['fatura_tipi'] ?? '') === 'ISTISNA' ? 'selected' : '' ?>>İSTİSNA (KDV İstisnası)</option>
                    <option value="OZELMATRAH" <?= ($existingDraft['fatura_tipi'] ?? '') === 'OZELMATRAH' ? 'selected' : '' ?>>ÖZEL MATRAH</option>
                    <option value="IHRACKAYITLI" <?= ($existingDraft['fatura_tipi'] ?? '') === 'IHRACKAYITLI' ? 'selected' : '' ?>>İHRAÇ KAYITLI</option>
                </select>
            </div>

            <!-- Fatura Tarihi & Düzenleme Saati -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Fatura Tarihi</label>
                    <input type="date" id="inpFaturaTarihi" value="<?= $existingDraft['fatura_tarihi'] ?? date('Y-m-d') ?>"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Düzenleme Saati</label>
                    <input type="time" id="inpDuzenlemeSaati" value="<?= !empty($existingDraft['duzenleme_saati']) ? date('H:i', strtotime($existingDraft['duzenleme_saati'])) : date('H:i') ?>"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                </div>
            </div>

            <!-- Para Birimi & Kur -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Para Birimi</label>
                    <select id="selParaBirimi" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                        <option value="TRY" <?= ($existingDraft['para_birimi'] ?? 'TRY') === 'TRY' ? 'selected' : '' ?>>TRY - Türk Lirası</option>
                        <option value="USD" <?= ($existingDraft['para_birimi'] ?? '') === 'USD' ? 'selected' : '' ?>>USD - Amerikan Doları</option>
                        <option value="EUR" <?= ($existingDraft['para_birimi'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Döviz Kuru</label>
                    <input type="number" step="0.0001" id="inpDovizKuru" value="<?= $existingDraft['doviz_kuru'] ?? '1.0000' ?>"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                </div>
            </div>

            <!-- İade Fatura Bilgileri (İade tipi seçilirse) -->
            <div id="iadeFaturaRow" class="space-y-2 p-3 bg-amber-50/60 dark:bg-amber-950/20 rounded-xl border border-amber-200 dark:border-amber-900/50 <?= ($existingDraft['fatura_tipi'] ?? '') === 'IADE' ? '' : 'hidden' ?>">
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-800 dark:text-amber-400">İADE REFERANS BİLGİSİ</span>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-300 mb-1">İade Fatura No</label>
                        <input type="text" id="inpIadeFaturaNo" placeholder="GİB Fatura No" value="<?= htmlspecialchars($existingDraft['iade_fatura_no'] ?? '') ?>"
                               class="w-full px-2.5 py-2 bg-white dark:bg-slate-800 border border-amber-300 rounded-lg text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-300 mb-1">İade Fatura Tarihi</label>
                        <input type="date" id="inpIadeFaturaTarihi" value="<?= $existingDraft['iade_fatura_tarihi'] ?? '' ?>"
                               class="w-full px-2.5 py-2 bg-white dark:bg-slate-800 border border-amber-300 rounded-lg text-xs">
                    </div>
                </div>
            </div>

            <!-- Sipariş & İrsaliye Bilgileri (Opsiyonel) -->
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Sipariş No</label>
                    <input type="text" id="inpSiparisNo" placeholder="SIP-2026-..." value="<?= htmlspecialchars($existingDraft['siparis_no'] ?? '') ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">İrsaliye No</label>
                    <input type="text" id="inpIrsaliyeNo" placeholder="IRS-2026-..." value="<?= htmlspecialchars($existingDraft['irsaliye_no'] ?? '') ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="button" onclick="goToStep(1)" class="w-1/3 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold active:scale-[0.98] transition-transform">
                Geri
            </button>
            <button type="button" onclick="goToStep(3)" class="w-2/3 py-3 rounded-xl bg-primary text-white text-xs font-black shadow-sm shadow-primary/30 flex items-center justify-center gap-1 active:scale-[0.98] transition-transform">
                <span>Mal / Hizmet Kalemlerine İlerle</span>
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- ADIM 3: MAL / HİZMET KALEMLERİ -->
    <!-- ========================================================================= -->
    <div id="stepContent-3" class="step-content space-y-3.5 hidden">
        <!-- Kalem Ekleme Butonu -->
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                <span class="material-symbols-outlined text-primary text-[18px]">inventory_2</span>
                Fatura Kalemleri (<span id="lineCountLabel">0</span>)
            </h3>
            <button type="button" onclick="openAddLineSheet()" class="flex items-center gap-1 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold shadow-xs active:scale-95 transition-transform">
                <span class="material-symbols-outlined text-[16px]">add</span> Kalem Ekle
            </button>
        </div>

        <!-- Eklenen Kalemler Listesi -->
        <div id="invoiceLinesContainer" class="space-y-2.5">
            <!-- Dinamik Olarak JS ile Doldurulacak -->
        </div>

        <!-- Anlık Kalemler Ara Toplam Kartı -->
        <div class="bg-white dark:bg-card-dark rounded-2xl p-3.5 border border-slate-100 dark:border-slate-700/60 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase">Ara Toplam:</span>
                <div class="text-sm font-black text-slate-900 dark:text-white" id="step3_matrah">0,00 ₺</div>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-slate-400 uppercase">KDV Dahil Toplam:</span>
                <div class="text-sm font-black text-primary" id="step3_toplam">0,00 ₺</div>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="button" onclick="goToStep(2)" class="w-1/3 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold active:scale-[0.98] transition-transform">
                Geri
            </button>
            <button type="button" onclick="goToStep(4)" class="w-2/3 py-3 rounded-xl bg-primary text-white text-xs font-black shadow-sm shadow-primary/30 flex items-center justify-center gap-1 active:scale-[0.98] transition-transform">
                <span>Özet ve Onaya İlerle</span>
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- ADIM 4: ALT BİLGİ, NOTLAR & ONAY -->
    <!-- ========================================================================= -->
    <div id="stepContent-4" class="step-content space-y-3.5 hidden">
        <!-- Not Şablonu ve Özel Notlar -->
        <div class="bg-white dark:bg-card-dark rounded-2xl p-4 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-3">
            <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                <span class="material-symbols-outlined text-primary text-[18px]">notes</span>
                Fatura Notları
            </h3>

            <!-- Not Şablonu Seçici -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Not Şablonu Ekle</label>
                <select id="selNoteTemplate" onchange="onSelectNoteTemplate(this)" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
                    <option value="">-- Şablon Seçin --</option>
                    <?php foreach ($notSablonlari as $ns): ?>
                        <option value="<?= htmlspecialchars($ns['icerik'] ?? '') ?>"><?= htmlspecialchars($ns['baslik'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fatura Notu Metin Alanı -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Fatura Üzerinde Görünecek Notlar</label>
                <textarea id="inpFaturaNotlar" rows="3" placeholder="Banka IBAN, ödeme vadesi veya diğer açıklamalar..."
                          class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white"><?= htmlspecialchars(cleanInvoiceNotes($existingDraft['notlar'] ?? '')) ?></textarea>
            </div>
        </div>

        <!-- Canlı Finansal Özet Kartı (Hero Card) -->
        <div class="bg-white dark:bg-card-dark rounded-2xl p-4 border border-slate-100 dark:border-slate-700/60 shadow-sm space-y-2">
            <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-2">Genel Toplam Özeti</h3>
            
            <div class="flex justify-between items-center text-xs py-1 border-b border-slate-100 dark:border-slate-800">
                <span class="text-slate-500 font-medium">Mal / Hizmet Toplamı:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200" id="summary_satir_toplami">0,00 ₺</span>
            </div>
            <div class="flex justify-between items-center text-xs py-1 border-b border-slate-100 dark:border-slate-800">
                <span class="text-slate-500 font-medium">İskonto Toplamı:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200" id="summary_iskonto_toplami">0,00 ₺</span>
            </div>
            <div class="flex justify-between items-center text-xs py-1 border-b border-slate-100 dark:border-slate-800">
                <span class="text-slate-500 font-medium">KDV Matrahı:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200" id="summary_kdv_matrahi">0,00 ₺</span>
            </div>
            <div class="flex justify-between items-center text-xs py-1 border-b border-slate-100 dark:border-slate-800">
                <span class="text-slate-500 font-medium">Hesaplanan KDV:</span>
                <span class="font-bold text-emerald-600" id="summary_hesaplanan_kdv">0,00 ₺</span>
            </div>
            <div class="flex justify-between items-center text-xs py-1 border-b border-slate-100 dark:border-slate-800" id="summary_tevkifat_row" style="display: none;">
                <span class="text-slate-500 font-medium">Tevkifat Tutarı:</span>
                <span class="font-bold text-rose-600" id="summary_tevkifat_tutari">0,00 ₺</span>
            </div>

            <div class="pt-2 flex justify-between items-center">
                <span class="text-xs font-black uppercase text-slate-900 dark:text-white">ÖDENECEK TUTAR:</span>
                <span class="text-lg font-black text-primary" id="summary_odenecek_tutar">0,00 ₺</span>
            </div>
        </div>

        <!-- 2 Büyük Aksiyon Butonu -->
        <div class="space-y-2 pt-1">
            <button type="button" onclick="saveDraftInvoice()" class="w-full py-3.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-black shadow-sm shadow-amber-500/30 flex items-center justify-center gap-1.5 active:scale-[0.98] transition-transform">
                <span class="material-symbols-outlined text-[18px]">save</span>
                <span>Taslak Olarak Kaydet</span>
            </button>

            <button type="button" onclick="sendInvoiceGib()" class="w-full py-3.5 rounded-xl bg-primary text-white text-xs font-black shadow-sm shadow-primary/30 flex items-center justify-center gap-1.5 active:scale-[0.98] transition-transform">
                <span class="material-symbols-outlined text-[18px]">send</span>
                <span>EDM / GİB'e Faturayı Gönder</span>
            </button>

            <button type="button" onclick="goToStep(3)" class="w-full py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold active:scale-[0.98] transition-transform">
                Kalemleri Düzenle
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- KALEM EKLEME / DÜZENLEME BOTTOM SHEET -->
<!-- ========================================================================= -->
<div id="mobAddLineModal" class="fixed inset-0 z-[120] bg-slate-900/80 backdrop-blur-xs flex flex-col justify-end hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-t-[28px] w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden transform translate-y-full transition-transform duration-300" id="mobAddLineSheet">
        <!-- Header -->
        <div class="p-3.5 px-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">add_shopping_cart</span>
                <span class="text-sm font-black text-slate-900 dark:text-white" id="lineModalTitle">Kalem Ekle</span>
            </div>
            <button type="button" onclick="closeAddLineSheet()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Body Form -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <input type="hidden" id="modalLineIndex" value="-1">

            <!-- Kayıtlı Mal/Hizmet Seçici -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Kayıtlı Mal/Hizmet Seç</label>
                <select id="selRegisteredProduct" onchange="onSelectProduct(this)" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-900 dark:text-white">
                    <option value="">-- Listeden Ürün/Hizmet Seçin veya Manuel Girin --</option>
                    <?php foreach ($malHizmetler as $mh): ?>
                        <option value="<?= htmlspecialchars($mh['id']) ?>"
                                data-ad="<?= htmlspecialchars($mh['urun_adi']) ?>"
                                data-birim="<?= htmlspecialchars($mh['birim'] ?? 'C62') ?>"
                                data-fiyat="<?= htmlspecialchars($mh['satis_fiyati'] ?? '0') ?>"
                                data-kdv="<?= htmlspecialchars($mh['kdv_orani'] ?? '20') ?>">
                            <?= htmlspecialchars($mh['urun_adi']) ?> (<?= number_format((float)$mh['satis_fiyati'], 2) ?> ₺)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ürün / Hizmet Adı -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Ürün / Hizmet Adı <span class="text-rose-500">*</span></label>
                <input type="text" id="modalLineAd" placeholder="Örn: Elektrik Tesisat Malzemesi"
                       class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
            </div>

            <!-- Miktar & Birim -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Miktar <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.0001" id="modalLineMiktar" value="1" oninput="recalcModalLineTotal()"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Birim</label>
                    <select id="modalLineBirim" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                        <?php foreach ($unitCodes as $uCode => $uName): ?>
                            <option value="<?= $uCode ?>" <?= $uCode === 'C62' ? 'selected' : '' ?>><?= $uName ?> (<?= $uCode ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Birim Fiyat & KDV Oranı -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Birim Fiyat (KDV Hariç) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.0001" id="modalLineFiyat" placeholder="0.0000" oninput="recalcModalLineTotal()"
                           class="w-full px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">KDV Oranı (%)</label>
                    <select id="modalLineKdv" onchange="recalcModalLineTotal()" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white">
                        <option value="20" selected>%20 KDV</option>
                        <option value="10">%10 KDV</option>
                        <option value="1">%1 KDV</option>
                        <option value="0">%0 (KDV Yok)</option>
                    </select>
                </div>
            </div>

            <!-- İskonto Oranı (%) -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">İskonto Oranı (%)</label>
                <input type="number" step="0.01" id="modalLineIskonto" value="0" placeholder="0" oninput="recalcModalLineTotal()"
                       class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white">
            </div>

            <!-- Satır Anlık Toplamı -->
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 flex justify-between items-center">
                <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Satır KDV Dahil Toplam:</span>
                <span class="text-sm font-black text-primary" id="modalLineTotalDisplay">0,00 ₺</span>
            </div>
        </div>

        <!-- Footer Buton -->
        <div class="p-3 px-4 border-t border-slate-100 dark:border-slate-800 shrink-0">
            <button type="button" onclick="saveLineFromModal()" class="w-full py-3 rounded-xl bg-primary text-white text-xs font-black shadow-sm shadow-primary/30 active:scale-[0.98] transition-transform">
                Kalemi Faturaya Ekle
            </button>
        </div>
    </div>
</div>

<script>
let currentStep = 1;
let invoiceLines = [];
let selectedCariId = null;

// Mevcut taslak varsa satırları / kalemleri doldur
<?php if (!empty($existingDraft['satirlar'])): ?>
invoiceLines = <?= json_encode(array_map(function($row) {
    return [
        'urun_hizmet_adi' => $row['urun_hizmet_adi'] ?? '',
        'miktar'          => (float)($row['miktar'] ?? 1),
        'birim'           => $row['birim'] ?? 'C62',
        'birim_fiyat'     => (float)($row['birim_fiyat'] ?? 0),
        'kdv_orani'       => (float)($row['kdv_orani'] ?? 20),
        'iskonto_orani'   => (float)($row['iskonto_orani'] ?? 0),
        'iskonto_tutari'  => (float)($row['iskonto_tutari'] ?? 0),
        'tevkifat_kodu'   => $row['tevkifat_kodu'] ?? '',
        'tevkifat_orani'  => (float)($row['tevkifat_orani'] ?? 0),
        'tevkifat_tutari' => (float)($row['tevkifat_tutari'] ?? 0),
        'satir_toplami'   => (float)($row['satir_toplami'] ?? 0)
    ];
}, $existingDraft['satirlar']), JSON_UNESCAPED_UNICODE) ?>;
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function() {
    renderLines();
    recalcInvoice();

    <?php if (!empty($existingDraft['cari_id'])): 
        $encCariId = Security::encrypt((string)$existingDraft['cari_id']);
    ?>
    selectedCariId = <?= json_encode($encCariId) ?>;
    const existingCariSel = document.getElementById('selRegisteredCari');
    if (existingCariSel) {
        existingCariSel.value = <?= json_encode($encCariId) ?>;
    }
    <?php endif; ?>

    <?php if (!empty($_GET['cari_id'])): ?>
    const preCariId = <?= json_encode($_GET['cari_id']) ?>;
    const cariSelect = document.getElementById('selRegisteredCari');
    if (cariSelect) {
        cariSelect.value = preCariId;
        if (cariSelect.selectedIndex >= 0) {
            onSelectCustomer(cariSelect);
        }
    }
    <?php endif; ?>
});

// Adım Geçişleri
function goToStep(step) {
    if (step === 2 || step === 3 || step === 4) {
        const vkn = document.getElementById('inpAliciVkn').value.trim();
        const unvan = document.getElementById('inpAliciUnvan').value.trim();
        if (!vkn || !unvan) {
            Alert.warning('Eksik Bilgi', 'Lütfen önce Alıcı VKN ve Ünvan bilgilerini doldurun.');
            return;
        }
    }

    if (step === 4) {
        if (invoiceLines.length === 0) {
            Alert.warning('Kalem Eklenmedi', 'Lütfen faturaya en az 1 mal / hizmet kalemi ekleyin.');
            return;
        }
    }

    currentStep = step;

    // Sekme İçeriklerini Göster / Gizle
    for (let i = 1; i <= 4; i++) {
        const content = document.getElementById(`stepContent-${i}`);
        const tabBtn = document.getElementById(`stepTab-${i}`);
        const icon = document.getElementById(`stepIcon-${i}`);

        if (i === step) {
            content.classList.remove('hidden');
            tabBtn.className = 'wizard-tab-btn flex flex-col items-center py-2 px-1 rounded-xl text-primary bg-primary/10 transition-all';
            icon.className = 'w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-black mb-1 shadow-xs';
        } else if (i < step) {
            content.classList.add('hidden');
            tabBtn.className = 'wizard-tab-btn flex flex-col items-center py-2 px-1 rounded-xl text-emerald-600 hover:text-emerald-700 transition-all';
            icon.className = 'w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-black mb-1';
        } else {
            content.classList.add('hidden');
            tabBtn.className = 'wizard-tab-btn flex flex-col items-center py-2 px-1 rounded-xl text-slate-400 hover:text-slate-600 transition-all';
            icon.className = 'w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center text-xs font-black mb-1';
        }
    }

    recalcInvoice();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Cari Seçildiğinde Doldur
function onSelectCustomer(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) {
        selectedCariId = null;
        return;
    }

    selectedCariId = opt.value;
    document.getElementById('inpAliciUnvan').value = opt.getAttribute('data-unvan') || '';
    document.getElementById('inpAliciVkn').value = opt.getAttribute('data-vkn') || '';
    document.getElementById('inpAliciVd').value = opt.getAttribute('data-vd') || '';
    document.getElementById('inpAliciAdres').value = opt.getAttribute('data-adres') || '';
    document.getElementById('inpAliciIl').value = opt.getAttribute('data-il') || '';
    document.getElementById('inpAliciIlce').value = opt.getAttribute('data-ilce') || '';
    document.getElementById('inpAliciEposta').value = opt.getAttribute('data-eposta') || '';
    document.getElementById('inpAliciTel').value = opt.getAttribute('data-tel') || '';

    const bTuru = opt.getAttribute('data-belge') || 'OTOMATIK';
    if (bTuru === 'EFATURA') {
        document.getElementById('selBelgeTuru').value = 'EFATURA';
        document.getElementById('selFaturaProfili').value = 'TICARIFATURA';
    } else if (bTuru === 'EARSIV') {
        document.getElementById('selBelgeTuru').value = 'EARSIV';
        document.getElementById('selFaturaProfili').value = 'EARSIVFATURA';
    }

    checkTaxpayerUser();
}

// Mükellef Sorgula
function checkTaxpayerUser() {
    const vkn = document.getElementById('inpAliciVkn').value.trim();
    const badge = document.getElementById('taxpayerStatusBadge');
    if (!vkn || (vkn.length !== 10 && vkn.length !== 11)) {
        badge.classList.add('hidden');
        return;
    }

    badge.className = 'mt-1 text-[11px] font-bold text-slate-400';
    badge.textContent = 'Mükellef sorgulanıyor...';
    badge.classList.remove('hidden');

    const fd = new FormData();
    fd.append('csrf_token', '<?= \App\Helper\Security::csrf() ?>');
    fd.append('vkn_tckn', vkn);

    fetch('../api/efatura-api.php?action=check_taxpayer', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success' && res.data) {
            if (res.data.is_taxpayer) {
                badge.className = 'mt-1 text-[11px] font-bold text-emerald-600 flex items-center gap-1';
                badge.innerHTML = '<span class="material-symbols-outlined text-sm">verified</span> E-Fatura Mükellefi (Kayıtlı)';
                document.getElementById('selBelgeTuru').value = 'EFATURA';
                document.getElementById('selFaturaProfili').value = 'TICARIFATURA';
            } else {
                badge.className = 'mt-1 text-[11px] font-bold text-sky-600 flex items-center gap-1';
                badge.innerHTML = '<span class="material-symbols-outlined text-sm">mail</span> E-Arşiv Fatura Düzenlenecek';
                document.getElementById('selBelgeTuru').value = 'EARSIV';
                document.getElementById('selFaturaProfili').value = 'EARSIVFATURA';
            }
        }
    })
    .catch(() => {
        badge.classList.add('hidden');
    });
}

function onFaturaTipiChange(val) {
    const iadeRow = document.getElementById('iadeFaturaRow');
    if (val === 'IADE') {
        iadeRow.classList.remove('hidden');
    } else {
        iadeRow.classList.add('hidden');
    }
}

// HTML metinlerini temiz, okunabilir düz metne dönüştürme fonksiyonu (Tablolar, paragraflar, br etiketleri)
function htmlToPlainText(html) {
    if (!html) return '';
    if (!/<[a-z][\s\S]*>/i.test(html)) {
        return html.replace(/&nbsp;/g, ' ').trim();
    }
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;

    // Tabloları temiz satır formatına çevir
    const tables = tempDiv.querySelectorAll('table');
    tables.forEach(table => {
        const rows = table.querySelectorAll('tr');
        const rowTexts = [];
        rows.forEach(tr => {
            const cells = Array.from(tr.querySelectorAll('th, td'))
                .map(td => td.textContent.trim().replace(/\u00a0/g, ' '))
                .filter(txt => txt.length > 0);
            if (cells.length > 0) {
                rowTexts.push(cells.join(' - '));
            }
        });
        const tableTextNode = document.createTextNode('\n' + rowTexts.join('\n') + '\n');
        table.parentNode.replaceChild(tableTextNode, table);
    });

    tempDiv.querySelectorAll('br').forEach(br => br.replaceWith('\n'));
    tempDiv.querySelectorAll('p, div, li, h1, h2, h3, h4, h5, h6').forEach(el => {
        el.prepend(document.createTextNode('\n'));
        el.append(document.createTextNode('\n'));
    });

    let text = tempDiv.textContent || tempDiv.innerText || '';
    text = text.replace(/\r\n/g, '\n').replace(/\n\s*\n\s*\n+/g, '\n\n').trim();
    return text;
}

function onSelectNoteTemplate(sel) {
    const rawVal = sel.value;
    if (rawVal) {
        const cleanTxt = htmlToPlainText(rawVal);
        const cur = document.getElementById('inpFaturaNotlar').value.trim();
        document.getElementById('inpFaturaNotlar').value = cur ? (cur + '\n' + cleanTxt) : cleanTxt;
    }
}

// Kalem Modal Fonksiyonları
function openAddLineSheet(editIdx = -1) {
    document.getElementById('modalLineIndex').value = editIdx;
    if (editIdx >= 0 && invoiceLines[editIdx]) {
        const item = invoiceLines[editIdx];
        document.getElementById('lineModalTitle').textContent = 'Kalemi Düzenle';
        document.getElementById('modalLineAd').value = item.urun_hizmet_adi || '';
        document.getElementById('modalLineMiktar').value = item.miktar || 1;
        document.getElementById('modalLineBirim').value = item.birim || 'C62';
        document.getElementById('modalLineFiyat').value = item.birim_fiyat || '';
        document.getElementById('modalLineKdv').value = item.kdv_orani || '20';
        document.getElementById('modalLineIskonto').value = item.iskonto_orani || '0';
    } else {
        document.getElementById('lineModalTitle').textContent = 'Yeni Kalem Ekle';
        document.getElementById('modalLineAd').value = '';
        document.getElementById('modalLineMiktar').value = '1';
        document.getElementById('modalLineBirim').value = 'C62';
        document.getElementById('modalLineFiyat').value = '';
        document.getElementById('modalLineKdv').value = '20';
        document.getElementById('modalLineIskonto').value = '0';
    }

    recalcModalLineTotal();

    const modal = document.getElementById('mobAddLineModal');
    const sheet = document.getElementById('mobAddLineSheet');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function closeAddLineSheet() {
    const modal = document.getElementById('mobAddLineModal');
    const sheet = document.getElementById('mobAddLineSheet');
    sheet.classList.add('translate-y-full');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function onSelectProduct(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('modalLineAd').value = opt.getAttribute('data-ad') || '';
        document.getElementById('modalLineBirim').value = opt.getAttribute('data-birim') || 'C62';
        document.getElementById('modalLineFiyat').value = opt.getAttribute('data-fiyat') || '';
        document.getElementById('modalLineKdv').value = opt.getAttribute('data-kdv') || '20';
        recalcModalLineTotal();
    }
}

function recalcModalLineTotal() {
    const m = parseFloat(document.getElementById('modalLineMiktar').value) || 0;
    const f = parseFloat(document.getElementById('modalLineFiyat').value) || 0;
    const k = parseFloat(document.getElementById('modalLineKdv').value) || 0;
    const isk = parseFloat(document.getElementById('modalLineIskonto').value) || 0;

    let sub = m * f;
    let iskTutar = (sub * isk) / 100;
    let matrah = sub - iskTutar;
    let kdvTutar = (matrah * k) / 100;
    let total = matrah + kdvTutar;

    document.getElementById('modalLineTotalDisplay').textContent = Number(total).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
}

function saveLineFromModal() {
    const ad = document.getElementById('modalLineAd').value.trim();
    const miktar = parseFloat(document.getElementById('modalLineMiktar').value) || 0;
    const birim = document.getElementById('modalLineBirim').value;
    const birimFiyat = parseFloat(document.getElementById('modalLineFiyat').value) || 0;
    const kdvOrani = parseFloat(document.getElementById('modalLineKdv').value) || 0;
    const iskontoOrani = parseFloat(document.getElementById('modalLineIskonto').value) || 0;
    const idx = parseInt(document.getElementById('modalLineIndex').value);

    if (!ad) {
        Alert.warning('Eksik Bilgi', 'Lütfen Ürün / Hizmet adını girin.');
        return;
    }
    if (miktar <= 0) {
        Alert.warning('Geçersiz Miktar', 'Miktar 0 dan büyük olmalıdır.');
        return;
    }
    if (birimFiyat <= 0) {
        Alert.warning('Geçersiz Fiyat', 'Birim fiyat 0 dan büyük olmalıdır.');
        return;
    }

    const lineObj = {
        urun_hizmet_adi: ad,
        miktar: miktar,
        birim: birim,
        birim_fiyat: birimFiyat,
        kdv_orani: kdvOrani,
        iskonto_orani: iskontoOrani,
        iskonto_tutari: (miktar * birimFiyat * iskontoOrani) / 100,
        tevkifat_kodu: '',
        tevkifat_orani: 0
    };

    if (idx >= 0) {
        invoiceLines[idx] = lineObj;
    } else {
        invoiceLines.push(lineObj);
    }

    closeAddLineSheet();
    renderLines();
    recalcInvoice();
}

async function removeLine(idx) {
    const confirmed = await Alert.confirmDelete('Kalemi Çıkar', 'Bu kalemi faturadan çıkarmak istediğinize emin misiniz?');
    if (confirmed) {
        invoiceLines.splice(idx, 1);
        renderLines();
        recalcInvoice();
    }
}

function renderLines() {
    const container = document.getElementById('invoiceLinesContainer');
    document.getElementById('lineCountLabel').textContent = invoiceLines.length;

    if (invoiceLines.length === 0) {
        container.innerHTML = `
            <div class="bg-white dark:bg-card-dark p-6 rounded-2xl text-center border border-slate-100 dark:border-slate-700/60 shadow-xs">
                <span class="material-symbols-outlined text-3xl text-slate-300 mb-1">add_shopping_cart</span>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Henüz kalem eklenmedi</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Yukarıdaki "Kalem Ekle" butonuna dokunarak ekleyin.</p>
            </div>
        `;
        return;
    }

    let html = '';
    invoiceLines.forEach((item, i) => {
        const sub = (item.miktar * item.birim_fiyat);
        const isk = (sub * (item.iskonto_orani || 0)) / 100;
        const matrah = sub - isk;
        const kdv = (matrah * (item.kdv_orani || 0)) / 100;
        const total = matrah + kdv;

        html += `
        <div class="bg-white dark:bg-card-dark rounded-2xl p-3 border border-slate-100 dark:border-slate-700/60 shadow-xs space-y-2">
            <div class="flex items-center justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <span class="text-xs font-bold text-slate-900 dark:text-white line-clamp-1">${item.urun_hizmet_adi}</span>
                    <span class="text-[10px] text-slate-400">${item.miktar} ${item.birim || 'Adet'} × ${Number(item.birim_fiyat).toLocaleString('tr-TR', { minimumFractionDigits: 2 })} ₺ • %${item.kdv_orani} KDV</span>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-xs font-black text-primary">${Number(total).toLocaleString('tr-TR', { minimumFractionDigits: 2 })} ₺</div>
                    <div class="flex items-center gap-1 mt-1 justify-end">
                        <button type="button" onclick="openAddLineSheet(${i})" class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center" title="Düzenle">
                            <span class="material-symbols-outlined text-[14px]">edit</span>
                        </button>
                        <button type="button" onclick="removeLine(${i})" class="w-6 h-6 rounded-md bg-rose-50 dark:bg-rose-900/30 text-rose-600 flex items-center justify-center" title="Sil">
                            <span class="material-symbols-outlined text-[14px]">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

// Genel Fatura Toplam Hesaplamaları
function recalcInvoice() {
    let topSatir = 0;
    let topIskonto = 0;
    let rawMatrah = 0;
    const vatBases = {};
    const moneyRound = value => Math.round((Number(value) + Number.EPSILON) * 100) / 100;

    invoiceLines.forEach(l => {
        const sub = (l.miktar * l.birim_fiyat);
        const roundedSub = moneyRound(sub);
        const isk = moneyRound((roundedSub * (l.iskonto_orani || 0)) / 100);
        const mat = sub - isk;
        const vatKey = String(Number(l.kdv_orani || 0));

        topSatir += roundedSub;
        topIskonto += isk;
        rawMatrah += mat;
        vatBases[vatKey] = (vatBases[vatKey] || 0) + mat;
    });

    const topMatrah = moneyRound(rawMatrah);
    const topKdv = Object.entries(vatBases).reduce((sum, [rate, base]) => sum + moneyRound(base * Number(rate) / 100), 0);
    const netOdenecek = topMatrah + topKdv;
    const fmt = (v) => Number(v || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';

    // Adım 3 Altı
    if (document.getElementById('step3_matrah')) document.getElementById('step3_matrah').textContent = fmt(topMatrah);
    if (document.getElementById('step3_toplam')) document.getElementById('step3_toplam').textContent = fmt(netOdenecek);

    // Adım 4 Özeti
    if (document.getElementById('summary_satir_toplami')) document.getElementById('summary_satir_toplami').textContent = fmt(topSatir);
    if (document.getElementById('summary_iskonto_toplami')) document.getElementById('summary_iskonto_toplami').textContent = fmt(topIskonto);
    if (document.getElementById('summary_kdv_matrahi')) document.getElementById('summary_kdv_matrahi').textContent = fmt(topMatrah);
    if (document.getElementById('summary_hesaplanan_kdv')) document.getElementById('summary_hesaplanan_kdv').textContent = fmt(topKdv);
    if (document.getElementById('summary_odenecek_tutar')) document.getElementById('summary_odenecek_tutar').textContent = fmt(netOdenecek);
}

function buildInvoicePayload() {
    const header = {
        cari_id: selectedCariId || null,
        alici_vkn_tckn: document.getElementById('inpAliciVkn').value.trim(),
        alici_unvan: document.getElementById('inpAliciUnvan').value.trim(),
        alici_vergi_dairesi: document.getElementById('inpAliciVd').value.trim(),
        alici_adres: document.getElementById('inpAliciAdres').value.trim(),
        alici_il: document.getElementById('inpAliciIl').value.trim(),
        alici_ilce: document.getElementById('inpAliciIlce').value.trim(),
        alici_ulke: 'Türkiye',
        alici_eposta: document.getElementById('inpAliciEposta').value.trim(),
        alici_telefon: document.getElementById('inpAliciTel').value.trim(),
        belge_turu: document.getElementById('selBelgeTuru').value,
        fatura_profili: document.getElementById('selFaturaProfili').value,
        fatura_tipi: document.getElementById('selFaturaTipi').value,
        fatura_tarihi: document.getElementById('inpFaturaTarihi').value,
        duzenleme_saati: (document.getElementById('inpDuzenlemeSaati').value || '12:00') + ':00',
        para_birimi: document.getElementById('selParaBirimi').value,
        doviz_kuru: parseFloat(document.getElementById('inpDovizKuru').value) || 1.0,
        siparis_no: document.getElementById('inpSiparisNo').value.trim(),
        irsaliye_no: document.getElementById('inpIrsaliyeNo').value.trim(),
        iade_fatura_no: document.getElementById('inpIadeFaturaNo')?.value.trim() || '',
        iade_fatura_tarihi: document.getElementById('inpIadeFaturaTarihi')?.value || null,
        notlar: document.getElementById('inpFaturaNotlar').value.trim()
    };

    const invoice_id = document.getElementById('editInvoiceId').value || null;
    return { invoice_id, header, lines: invoiceLines };
}

function saveDraftInvoice() {
    const payload = buildInvoicePayload();
    if (!payload.header.alici_vkn_tckn || !payload.header.alici_unvan) {
        Alert.warning('Eksik Bilgi', 'Lütfen Alıcı VKN ve Unvan bilgilerini doldurun.').then(() => {
            goToStep(1);
        });
        return;
    }
    if (payload.lines.length === 0) {
        Alert.warning('Kalem Eklenmedi', 'Lütfen faturaya en az bir kalem ekleyin.').then(() => {
            goToStep(3);
        });
        return;
    }

    Alert.loading('Kaydediliyor...', 'Taslak fatura kaydediliyor, lütfen bekleyin.');

    fetch('../api/efatura-api.php?action=save_draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            Alert.show({
                icon: 'success',
                title: 'Başarılı',
                text: 'Fatura başarıyla taslak olarak kaydedildi.',
                confirmButtonText: 'Taslaklara Git'
            }).then(() => {
                window.location.href = '?p=efatura-taslak';
            });
        } else {
            Alert.error('Hata', res.message || 'Taslak kaydedilirken hata oluştu.');
        }
    })
    .catch(err => {
        Alert.error('Bağlantı Hatası', 'Sunucu ile iletişim kurulurken bir hata oluştu.');
    });
}

async function sendInvoiceGib() {
    const payload = buildInvoicePayload();
    if (!payload.header.alici_vkn_tckn || !payload.header.alici_unvan) {
        Alert.warning('Eksik Bilgi', 'Lütfen Alıcı VKN ve Unvan bilgilerini doldurun.').then(() => {
            goToStep(1);
        });
        return;
    }
    if (payload.lines.length === 0) {
        Alert.warning('Kalem Eklenmedi', 'Lütfen faturaya en az bir kalem ekleyin.').then(() => {
            goToStep(3);
        });
        return;
    }

    const confirmed = await Alert.confirm('Faturayı Gönder', 'Fatura kaydedilip EDM Bilişim & GİB sistemine iletilecektir. Onaylıyor musunuz?', 'Evet, Gönder', 'Vazgeç');
    if (!confirmed) return;

    Alert.loading('Gönderiliyor...', 'Fatura kaydedilip EDM servisine iletiliyor, lütfen bekleyin.');

    fetch('../api/efatura-api.php?action=save_draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(resDraft => {
        if (resDraft.status === 'success' && (resDraft.encrypted_id || resDraft.invoice_id)) {
            const targetId = resDraft.encrypted_id || resDraft.invoice_id;
            const fd = new FormData();
            fd.append('csrf_token', '<?= \App\Helper\Security::csrf() ?>');
            fd.append('invoice_id', targetId);

            return fetch('../api/efatura-api.php?action=send_invoice', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '<?= \App\Helper\Security::csrf() ?>' },
                body: fd
            });
        } else {
            throw new Error(resDraft.message || 'Taslak kaydedilemedi.');
        }
    })
    .then(r => r.json())
    .then(resSend => {
        if (resSend.status === 'success') {
            Alert.show({
                icon: 'success',
                title: 'Başarıyla Gönderildi!',
                html: `Fatura başarıyla EDM ve GİB sistemine iletildi.<br><strong>Fatura No:</strong> ${resSend.fatura_no || '-'}<br><br><span class="text-xs text-slate-500">Fatura Giden Faturalar ekranına aktarıldı.</span>`,
                confirmButtonText: 'Giden Faturalara Git'
            }).then(() => {
                window.location.href = '?p=efatura-giden';
            });
        } else {
            Alert.show({
                icon: 'warning',
                title: 'Taslak Kaydedildi, Gönderim Hatası',
                text: 'Fatura taslak olarak kaydedildi ancak GİB gönderiminde hata oluştu: ' + (resSend.message || ''),
                confirmButtonText: 'Taslaklara Git'
            }).then(() => {
                window.location.href = '?p=efatura-taslak';
            });
        }
    })
    .catch(err => {
        Alert.error('Hata', err.message || 'Gönderim sırasında hata oluştu.');
    });
}
</script>
