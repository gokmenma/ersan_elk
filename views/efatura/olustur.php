<?php
\App\Service\Gate::authorizeOrDie('efatura/olustur');
use App\Config\EdmConfig;
use App\Helper\Form;
use App\Helper\EInvoiceSecurity;
use App\Service\InvoiceValidationService;
use App\Helper\Security;
use App\Helper\Helper;
use App\Model\EInvoiceModel;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Yeni Fatura Düzenle';

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$invoiceModel = new EInvoiceModel();
$cariler = $invoiceModel->invoiceCustomers($firmId);
foreach ($cariler as &$customer) $customer['id'] = Security::encrypt((string)$customer['id']);
unset($customer);

$cariOptions = ['' => '-- Cari seçin veya VKN girin --'];
foreach ($cariler as $customer) $cariOptions[$customer['id']] = $customer['CariAdi'];

$withholdingOptions = ['' => 'Tevkifat Yok'];
foreach (InvoiceValidationService::codes('WithholdingTaxTypeWithPercent') as $entry) {
    $code = substr($entry, 0, 3); $rate = substr($entry, 3);
    if (in_array($code, InvoiceValidationService::codes('WithholdingTaxType'), true)) $withholdingOptions[$code . '|' . $rate] = $code . ' — %' . $rate;
}
$exemptionOptions = ['' => 'İstisna / İşlem Kodu Yok'];
foreach (InvoiceValidationService::codes('TaxExemptionReasonCodeType') as $code) $exemptionOptions[$code] = $code;
$unitCodes = EdmConfig::getUnitCodes();
$kdvOranlari = [
    '20' => '%20',
    '10' => '%10',
    '1'  => '%1',
    '0'  => '%0'
];

$vergiListesi = [
    ['kod' => '0003', 'ad' => 'GELİR VERGİSİ STOPAJI', 'kisa' => 'STPJ', 'tur' => 'stopaj'],
    ['kod' => '0011', 'ad' => 'KURUMLAR VERGİSİ STOPAJI', 'kisa' => 'KURM. VERG. STPJ', 'tur' => 'stopaj'],
    ['kod' => '0015', 'ad' => 'GERÇEK USULDE KATMA DEĞER VERGİSİ', 'kisa' => 'KDV GERCEK', 'tur' => 'kdv'],
    ['kod' => '0021', 'ad' => 'BANKA MUAMELELERİ VERGİSİ', 'kisa' => 'BANKA MUAM.VER', 'tur' => 'diger'],
    ['kod' => '0059', 'ad' => 'KONAKLAMA VERGİSİ', 'kisa' => 'KONAKLAMA V.', 'tur' => 'konaklama'],
    ['kod' => '0061', 'ad' => 'KAYNAK KULLANIMI DESTEKLEME FONU KESİNTİSİ', 'kisa' => 'KKDF KESİNTİ', 'tur' => 'fon'],
    ['kod' => '0071', 'ad' => 'PETROL VE DOĞALGAZ ÜRÜNLERİNE İLİŞKİN ÖZEL TÜKETİM VERGİSİ', 'kisa' => 'ÖTV 1.LİSTE', 'tur' => 'otv'],
    ['kod' => '0073', 'ad' => 'KOLALI GAZOZ, ALKOLLÜ İÇEÇEKLER VE TÜTÜN MAMÜLLERİNE İLİŞKİN ÖZEL TÜKETİM VERGİSİ', 'kisa' => 'ÖTV 3.LİSTE', 'tur' => 'otv'],
    ['kod' => '0074', 'ad' => 'DAYANIKLI TÜKETİM VE DİĞER MALLARA İLİŞKİN ÖZEL TÜKETİM VERGİSİ', 'kisa' => 'ÖTV 4.LİSTE', 'tur' => 'otv'],
    ['kod' => '0075', 'ad' => 'ALKOLLÜ İÇEÇEKLERE İLİŞKİN ÖZEL TÜKETİM VERGİSİ', 'kisa' => 'ÖTV 3A LİSTE', 'tur' => 'otv'],
    ['kod' => '0076', 'ad' => 'TÜTÜN MAMÜLLERİNE İLİŞKİN ÖZEL TÜKETİM VERGİSİ', 'kisa' => 'ÖTV 3B LİSTE', 'tur' => 'otv'],
    ['kod' => '0077', 'ad' => 'KOLALI GAZOZLARA İLİŞKİN ÖZEL TÜKETİM VERGİSİ', 'kisa' => 'ÖTV 3C LİSTE', 'tur' => 'otv'],
    ['kod' => '1047', 'ad' => 'DAMGA VERGİSİ', 'kisa' => 'DAMGA V', 'tur' => 'damga'],
    ['kod' => '1048', 'ad' => '5035 SAYILI KANUNA GÖRE DAMGA VERGİSİ', 'kisa' => '5035SKDAMGAV', 'tur' => 'damga'],
    ['kod' => '4071', 'ad' => 'ELEKTRİK VE HAVAGAZI TÜKETİM VERGİSİ', 'kisa' => 'ELK.HVGZ.TÜK. VER', 'tur' => 'tuketim'],
    ['kod' => '4080', 'ad' => 'ÖZEL İLETİŞİM VERGİSİ', 'kisa' => 'Ö.İLETİŞİM V', 'tur' => 'oiv'],
    ['kod' => '4081', 'ad' => '5035 SAYILI KANUNA GÖRE ÖZEL İLETİŞİM VERGİSİ', 'kisa' => '5035ÖZİLETV', 'tur' => 'oiv'],
    ['kod' => '4171', 'ad' => 'PETROL VE DOĞALGAZ ÜRÜNLERİNE İLİŞKİN ÖTV TEVKİFATI', 'kisa' => 'PET. D.GAZ ÖTV TEVK', 'tur' => 'otv'],
    ['kod' => '8001', 'ad' => 'BORSA TESCİL ÜCRETİ', 'kisa' => 'BORSA TES.ÜC.', 'tur' => 'ucret'],
    ['kod' => '8002', 'ad' => 'ENERJİ FONU TİP', 'kisa' => 'ENERJİ FONU', 'tur' => 'fon'],
    ['kod' => '8004', 'ad' => 'TRT PAYI', 'kisa' => 'TRT PAYI', 'tur' => 'fon'],
    ['kod' => '8005', 'ad' => 'ELEKTRİK TÜKETİM VERGİSİ', 'kisa' => 'ELK.TÜK.VER.', 'tur' => 'tuketim'],
    ['kod' => '8006', 'ad' => 'TELSİZ KULLANIM ÜCRETİ', 'kisa' => 'TK KULLANIM', 'tur' => 'ucret'],
    ['kod' => '8007', 'ad' => 'TELSİZ RUHSAT ÜCRETİ', 'kisa' => 'TK RUHSAT', 'tur' => 'ucret'],
    ['kod' => '8008', 'ad' => 'ÇEVRE TEMİZLİK VERGİSİ', 'kisa' => 'ÇEV. TEM .VER', 'tur' => 'cevre'],
    ['kod' => '9015', 'ad' => 'KATMA DEĞER VERGİSİ TEVKİFATI TİP', 'kisa' => 'KDV TEVKİFAT', 'tur' => 'tevkifat'],
    ['kod' => '9021', 'ad' => '4961 BANKA SİGORTA MUAMELELERİ VERGİSİ TİP', 'kisa' => '4961BANKASMV', 'tur' => 'diger'],
    ['kod' => '9040', 'ad' => 'MERA FONU', 'kisa' => 'MERA FONU', 'tur' => 'fon'],
    ['kod' => '9077', 'ad' => 'MOTORLU TAŞIT ARAÇLARINA İLİŞKİN ÖZEL TÜKETİM VERGİSİ (TESCİLE TABİ OLANLAR) TİP', 'kisa' => 'ÖTV 2.LİSTE', 'tur' => 'otv'],
    ['kod' => '9944', 'ad' => 'BEL.ÖD.HAL RÜSUM', 'kisa' => 'BEL.ÖD.HAL RÜSUM', 'tur' => 'rusum']
];

// EDM Portal Kod Listesi JSON verisini yükle
$edmJsonPath = __DIR__ . '/data/edm_kod_listesi.json';
$edmKodListesi = file_exists($edmJsonPath) ? json_decode(file_get_contents($edmJsonPath), true) : [];
$sehirler = $edmKodListesi['cities'] ?? [];
$ilceler = $edmKodListesi['districts'] ?? [];
$odemeSekilleri = $edmKodListesi['payments'] ?? [];
$ulkeler = $edmKodListesi['countries'] ?? [];

// İl Seçenekleri
$ilOptions = ['' => 'İl Seçiniz...'];
foreach ($sehirler as $sehir) {
    $ilOptions[$sehir] = $sehir;
}

// Ülke Seçenekleri
$ulkeOptions = ['Türkiye' => 'Türkiye'];
foreach ($ulkeler as $u) {
    if ($u['name'] !== 'Türkiye') {
        $ulkeOptions[$u['name']] = $u['name'] . ' (' . $u['code'] . ')';
    }
}

// Ödeme Şekli Seçenekleri
$odemeSekliOptions = ['' => 'Ödeme Şekli Seçiniz...'];
foreach ($odemeSekilleri as $p) {
    $odemeSekliOptions[$p['code']] = $p['code'] . ' - ' . $p['name'];
}

// Fatura Tipi Seçenekleri
$faturaTipleri = [
    'SATIS'        => 'Satış',
    'IADE'         => 'İade',
    'TEVKIFAT'     => 'Tevkifat',
    'ISTISNA'      => 'İstisna',
    'OZELMATRAH'   => 'Özel Matrah',
    'IHRACKAYITLI' => 'İhraç Kayıtlı',
    'SGK'          => 'SGK',
    'KAMU'         => 'Kamu',
    'HAL'          => 'Hal',
    'TEVKIFATIADE' => 'Tevkifat İade',
    'KONAKLAMA'    => 'Konaklama Vergisi'
];

// Fatura Senaryo Seçenekleri
$faturaSenaryolari = [
    'TEMELFATURA'  => 'Temel Fatura',
    'TICARIFATURA' => 'Ticari Fatura',
    'EARSIVFATURA' => 'E-Arşiv Fatura',
    'KAMU'         => 'Kamu',
    'IHRACAT'      => 'İhracat',
    'HAL'          => 'Hal'
];

// Para Birimleri
$paraBirimleri = [
    'TRY' => 'TRY - Türk Lirası (₺)',
    'USD' => 'USD - Amerikan Doları ($)',
    'EUR' => 'EUR - Euro (€)',
    'GBP' => 'GBP - İngiliz Sterlini (£)',
    'CHF' => 'CHF - İsviçre Frangı',
    'RUB' => 'RUB - Rus Rublesi'
];

$editInvoice = null;
$editInvoiceEncryptedId = $_GET['id'] ?? '';
$currentEttn = Helper::generateUuid();

if (!empty($editInvoiceEncryptedId)) {
    $decryptedId = EInvoiceSecurity::invoiceId($editInvoiceEncryptedId);
    if ($decryptedId > 0) {
        $invoiceModel = new EInvoiceModel();
        $editInvoice = $invoiceModel->getInvoiceById($decryptedId, $firmId);
        if ($editInvoice && ($editInvoice['yon'] !== 'GIDEN' || $editInvoice['entegrator_durum_kodu'] !== 'TASLAK' || !empty($editInvoice['kaynak_xml']) || !empty($editInvoice['islem_belirsiz']))) {
            echo '<div class="alert alert-warning">Yalnız yerel taslaklar düzenlenebilir.</div>'; return;
        }
        if ($editInvoice) {
            if (!empty($editInvoice['cari_id'])) $editInvoice['cari_id'] = Security::encrypt((string)$editInvoice['cari_id']);
            if (!empty($editInvoice['ettn'])) $currentEttn = $editInvoice['ettn'];
            unset($editInvoice['id'], $editInvoice['olusturan_user_id']);
            $title = 'Taslak Faturayı Düzenle';
        }
    }
}

// Seri No Seçenekleri (EDM Portal Entegrasyonu)
$edmSerials = [];
$seriOptions = [
    '' => 'Otomatik Seri (EDM Belirlesin)'
];

try {
    $eInvoiceService = new \App\Service\EInvoiceService();
    $connInfo = $eInvoiceService->connectionInfo($firmId);
    if (!empty($connInfo['SERIALS']) && is_array($connInfo['SERIALS'])) {
        $edmSerials = $connInfo['SERIALS'];
        $initialBelgeTuru = !empty($editInvoice['belge_turu']) ? $editInvoice['belge_turu'] : 'EARSIV';
        $isEarchiveTarget = ($initialBelgeTuru === 'EFATURA') ? 0 : 1;
        foreach ($edmSerials as $s) {
            if ((int)($s['active'] ?? 0) === 1 && (int)($s['earchive'] ?? 0) === $isEarchiveTarget) {
                $lastNo = !empty($s['last']) ? ' (Son No: ' . $s['last'] . ')' : '';
                $seriOptions[$s['series']] = $s['series'] . $lastNo;
            }
        }
    }
} catch (\Throwable $e) {
    error_log('EDM Serials fetch error: ' . $e->getMessage());
}

if (count($seriOptions) === 1) {
    $ayarlarModel = new \App\Model\EInvoiceSettingsModel();
    $efaturaAyarlar = $ayarlarModel->getSettings($firmId);
    if (!empty($efaturaAyarlar['varsayilan_seri_efatura'])) {
        $seriOptions[$efaturaAyarlar['varsayilan_seri_efatura']] = $efaturaAyarlar['varsayilan_seri_efatura'] . ' (E-Fatura)';
    }
    if (!empty($efaturaAyarlar['varsayilan_seri_earsiv'])) {
        $seriOptions[$efaturaAyarlar['varsayilan_seri_earsiv']] = $efaturaAyarlar['varsayilan_seri_earsiv'] . ' (E-Arşiv)';
    }
    if (count($seriOptions) === 1) {
        $seriOptions['ERS'] = 'ERS (E-Fatura)';
        $seriOptions['ERA'] = 'ERA (E-Arşiv)';
    }
}

// Kalem tablosu için düz HTML select şablonları
$unitSelectHtml = '<select class="form-select form-select-sm select2-item kalem-birim">';
foreach ($unitCodes as $k => $v) {
    $unitSelectHtml .= '<option value="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '"' . ($k === 'C62' ? ' selected' : '') . '>' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</option>';
}
$unitSelectHtml .= '</select>';

$vatSelectHtml = '<select class="form-select form-select-sm select2-item kalem-kdv">';
foreach ($kdvOranlari as $k => $v) {
    $vatSelectHtml .= '<option value="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '"' . ($k === '20' ? ' selected' : '') . '>' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</option>';
}
$vatSelectHtml .= '</select>';

$withholdingSelectHtml = '<select class="form-select form-select-sm select2-item kalem-tevkifat mb-1">';
foreach ($withholdingOptions as $k => $v) {
    $withholdingSelectHtml .= '<option value="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</option>';
}
$withholdingSelectHtml .= '</select>';

$exemptionSelectHtml = '<select class="form-select form-select-sm select2-item kalem-istisna mb-1">';
foreach ($exemptionOptions as $k => $v) {
    $exemptionSelectHtml .= '<option value="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</option>';
}
$exemptionSelectHtml .= '</select>';
?>
<meta name="efatura-csrf" content="<?= htmlspecialchars(\App\Helper\Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">
<script src="views/efatura/js/transport.js"></script>

<style>
/* Fatura Düzenleme Tablo ve Kart Stilleri */
.summary-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.03) !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

/* Üst Araç Çubuğu Butonları */
.top-action-btn {
    height: 38px;
    padding: 0 16px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 1px solid #cbd5e1;
    line-height: normal;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.top-icon-btn {
    height: 38px;
    width: 38px;
    padding: 0;
    font-size: 18px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cbd5e1;
    transition: all 0.2s ease;
}
.top-action-btn.btn-primary {
    background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%) !important;
    border: none !important;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(37, 99, 235, 0.3) !important;
}
.top-action-btn.btn-primary:hover {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4) !important;
}
.top-action-btn.btn-outline-secondary,
.top-icon-btn.btn-outline-secondary {
    color: #334155 !important;
    border-color: #cbd5e1 !important;
    background-color: #ffffff !important;
}
.top-action-btn.btn-outline-secondary:hover,
.top-icon-btn.btn-outline-secondary:hover {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
    border-color: #94a3b8 !important;
    transform: translateY(-1px);
}

/* Modern Renkli Butonlar (Subtle Buttons) */
.btn-subtle-primary {
    background-color: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    transition: all 0.18s ease;
}
.btn-subtle-primary:hover, .btn-subtle-primary:focus {
    background-color: #2563eb;
    color: #ffffff !important;
    border-color: #2563eb;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);
}

.btn-subtle-success {
    background-color: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
    transition: all 0.18s ease;
}
.btn-subtle-success:hover, .btn-subtle-success:focus {
    background-color: #16a34a;
    color: #ffffff !important;
    border-color: #16a34a;
    box-shadow: 0 2px 5px rgba(22, 163, 74, 0.25);
}

.btn-subtle-danger {
    background-color: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    transition: all 0.18s ease;
}
.btn-subtle-danger:hover, .btn-subtle-danger:focus {
    background-color: #dc2626;
    color: #ffffff !important;
    border-color: #dc2626;
    box-shadow: 0 2px 5px rgba(220, 38, 38, 0.25);
}

.btn-subtle-warning {
    background-color: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
    transition: all 0.18s ease;
}
.btn-subtle-warning:hover, .btn-subtle-warning:focus {
    background-color: #d97706;
    color: #ffffff !important;
    border-color: #d97706;
    box-shadow: 0 2px 5px rgba(217, 119, 6, 0.25);
}

.btn-subtle-info {
    background-color: #f5f3ff;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
    transition: all 0.18s ease;
}
.btn-subtle-info:hover, .btn-subtle-info:focus {
    background-color: #7c3aed;
    color: #ffffff !important;
    border-color: #7c3aed;
    box-shadow: 0 2px 5px rgba(124, 58, 237, 0.25);
}

.btn-subtle-secondary {
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
    transition: all 0.18s ease;
}
.btn-subtle-secondary:hover, .btn-subtle-secondary:focus {
    background-color: #475569;
    color: #ffffff !important;
    border-color: #475569;
    box-shadow: 0 2px 5px rgba(71, 85, 105, 0.25);
}

/* Muhasebe ve Tablo Satır Butonları */
.table-action-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    flex-shrink: 0;
}

.items-table {
    margin-bottom: 0;
    width: 100%;
    border-collapse: separate !important;
    border-spacing: 0;
}
.invoice-items-table-wrap {
    padding: 0.75rem 1rem 1rem;
}
.invoice-items-table-frame {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: #ffffff;
}
.items-table thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.70rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 8px 8px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}
.items-table thead th.th-tax-header {
    background-color: #f0f9ff !important;
    color: #0284c7 !important;
    border-left: 1px solid #e0f2fe;
    border-right: 1px solid #e0f2fe;
    padding: 6px 6px;
    font-size: 0.75rem;
}
.items-table thead th.th-tax-sub {
    background-color: #f8fafc !important;
    color: #0284c7 !important;
    font-size: 0.68rem !important;
    font-weight: 600;
    padding: 4px 6px !important;
    border-bottom: 1px solid #cbd5e1;
}
.items-table tbody td {
    padding: 6px 8px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.items-table .form-control-sm,
.items-table .form-select-sm {
    height: 34px;
    font-size: 0.82rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}
.items-table .form-control-sm:focus,
.items-table .form-select-sm:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}
.summary-card {
    background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px 0;
    font-size: 0.875rem;
    color: #475569;
}
.summary-row.grand-total {
    border-top: 2px dashed #cbd5e1;
    margin-top: 12px;
    padding-top: 14px;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
}
.drag-handle {
    cursor: grab;
    color: #94a3b8;
    transition: color 0.15s ease;
}
.drag-handle:hover {
    color: #3b82f6;
}
.drag-handle:active {
    cursor: grabbing;
}
.tax-detail-fields-wrap {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 8px;
}
.invoice-item-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.invoice-item-actions .top-action-btn {
    height: 36px !important;
    min-height: 36px !important;
    max-height: 36px !important;
    padding: 0 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    line-height: 1 !important;
    box-sizing: border-box !important;
    white-space: nowrap !important;
}
.invoice-item-actions .top-action-btn i {
    font-size: 16px !important;
    line-height: 1 !important;
    display: inline-flex;
    align-items: center;
}
.invoice-item-actions .top-action-btn .badge {
    line-height: 1.1 !important;
    padding: 3px 6px !important;
    font-size: 10px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.invoice-not-editor {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.invoice-not-editor .note-editor.note-frame {
    flex: 1;
    display: flex;
    flex-direction: column;
    margin-bottom: 0;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.invoice-not-editor .note-editor .note-editing-area {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.invoice-not-editor .note-editor .note-editable {
    flex: 1;
    min-height: 200px;
}

/* Büyük ve Belirgin Onay Kutuları */
.custom-invoice-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 0;
}
.custom-invoice-checkbox .form-check-input {
    width: 1.25rem;
    height: 1.25rem;
    margin-top: 0;
    cursor: pointer;
    border-radius: 5px;
    border: 1.5px solid #94a3b8;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.custom-invoice-checkbox .form-check-input:checked {
    background-color: #2563eb;
    border-color: #2563eb;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);
}
.custom-invoice-checkbox .form-check-label {
    font-size: 0.84rem;
    font-weight: 500;
    color: #334155;
    cursor: pointer;
    user-select: none;
    line-height: 1.3;
}

/* Personel Listesi Tarzı Segmented Pill Buton Grubu */
.status-filter-group {
    background: #f8fafc;
    padding: 3px 4px;
    border-radius: 50px;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.status-filter-group .btn-check + .btn {
    margin-bottom: 0 !important;
    border: none !important;
    border-radius: 50px !important;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 6px 14px;
    color: #64748b;
    background: transparent;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    line-height: normal;
    cursor: pointer;
}

.status-filter-group .btn-check + .btn i {
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.status-filter-group .btn-check + .btn:hover {
    background: rgba(0, 0, 0, 0.04);
    color: #1e293b;
}

.status-filter-group .btn-check:checked + .btn {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.status-filter-group .btn-check:checked + .btn[for="gonderim_efatura"] {
    background: #34c38f !important;
    color: #ffffff !important;
}

.status-filter-group .btn-check:checked + .btn[for="gonderim_earsiv"] {
    background: #2563eb !important;
    color: #ffffff !important;
}

.status-filter-group .btn-check:checked + .btn[for="gonderim_internet"] {
    background: #f59e0b !important;
    color: #ffffff !important;
}

/* Küçük Yardımcı Metin */
.field-help-text {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 3px;
    line-height: 1.25;
}
</style>

<div class="container-fluid pb-5">
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Üst Sayfa Başlığı ve Aksiyon Araç Çubuğu -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php?p=efatura/giden-list" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" title="Geri Dön">
                <i class="bx bx-arrow-back font-size-18 align-middle"></i>
            </a>
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-file font-size-22 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16"><?= !empty($editInvoice) ? 'Taslak Faturayı Düzenle' : 'Yeni Fatura Düzenle' ?></h4>
                <p class="text-muted mb-0 font-size-12"><?= !empty($editInvoice) ? 'Taslak faturayı güncelleyip kaydedin veya doğrudan GİB\'e gönderin' : 'E-Fatura & E-Arşiv Belgesi Oluşturma ve EDM İletimi' ?></p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div id="mukellefDurumuBadge">
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill fw-semibold font-size-12">
                    <i class="bx bx-help-circle me-1"></i> Mükellefiyet Kontrolü Bekleniyor
                </span>
            </div>
            <button type="button" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm" id="btnTaslakKaydet">
                <i class="bx bx-save me-1 font-size-16 text-primary align-middle"></i> <?= !empty($editInvoice) ? 'Değişiklikleri Kaydet' : 'Taslak Kaydet' ?>
            </button>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm" id="btnGonderDirect">
                <i class="bx bx-send me-1 font-size-16 text-white align-middle"></i> Kaydet ve Gönder
            </button>
        </div>
    </div>

    <form id="formFaturaOlustur">
        <input type="hidden" id="editInvoiceId" value="<?= !empty($editInvoice) ? htmlspecialchars($editInvoiceEncryptedId, ENT_QUOTES, 'UTF-8') : '' ?>">
        
        <!-- ÜST KARTLAR: FATURA BİLGİLERİ & ALICI BİLGİLERİ -->
        <div class="row g-4 mb-4">
            
            <!-- 1. KART: FATURA BİLGİLERİ -->
            <div class="col-lg-6">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="bx bx-receipt font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold text-dark font-size-14">FATURA BİLGİLERİ</h5>
                                <p class="text-muted mb-0 font-size-11">ETTN, senaryo ve tarih detayları</p>
                            </div>
                        </div>

                        <!-- Gönderim Şekli Segmented Pill Grubu (Personel Listesi Tarzı) -->
                        <div class="status-filter-group shadow-xs" role="group" aria-label="Gönderim Şekli">
                            <input type="radio" class="btn-check" name="gonderim_sekli" id="gonderim_efatura" value="EFATURA" autocomplete="off" <?= (!empty($editInvoice['belge_turu']) && $editInvoice['belge_turu'] === 'EFATURA') ? 'checked' : '' ?>>
                            <label class="btn" for="gonderim_efatura">
                                <i class="bx bx-paper-plane"></i> e-Fatura
                            </label>

                            <input type="radio" class="btn-check" name="gonderim_sekli" id="gonderim_earsiv" value="EARSIV" autocomplete="off" <?= (empty($editInvoice['belge_turu']) || $editInvoice['belge_turu'] === 'EARSIV') ? 'checked' : '' ?>>
                            <label class="btn" for="gonderim_earsiv">
                                <i class="bx bx-archive"></i> e-Arşiv
                            </label>

                            <input type="radio" class="btn-check" name="gonderim_sekli" id="gonderim_internet" value="INTERNET" autocomplete="off">
                            <label class="btn" for="gonderim_internet">
                                <i class="bx bx-globe"></i> İnternet Satış
                            </label>
                        </div>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <input type="hidden" id="belge_turu" name="belge_turu" value="<?= htmlspecialchars($editInvoice['belge_turu'] ?? 'EARSIV', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row g-3">
                            
                            <!-- ETTN (UUID v4) -->
                            <div class="col-12">
                                <label class="form-label font-size-12 fw-bold text-dark mb-1">ETTN</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace font-size-12 bg-light fw-bold" id="displayEttn" value="<?= htmlspecialchars($currentEttn, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                    <input type="hidden" id="ettn" name="ettn" value="<?= htmlspecialchars($currentEttn, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="button" class="btn btn-outline-secondary px-2.5" id="btnCopyEttn" title="ETTN Kopyala">
                                        <i class="bx bx-copy font-size-16"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary px-2.5" id="btnRegenerateEttn" title="Yeni ETTN Üret">
                                        <i class="bx bx-refresh font-size-16"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Fatura Tarihi ve Saati -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'fatura_tarihi', date('d.m.Y'), '', 'Fatura Tarihi *', 'calendar', 'form-control flatpickr', true) ?>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'duzenleme_saati', date('H:i'), '', 'Saat *', 'clock', 'form-control', true) ?>
                            </div>

                            <!-- Fatura No / Seri No -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2('seri_no', $seriOptions, '', 'Seri No', 'hash') ?>
                                <div class="field-help-text text-muted">
                                    <i class="bx bx-info-circle me-1"></i>Seçilmezse gönderimde otomatik seri atanır.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'fatura_no', $editInvoice['fatura_no'] ?? '', 'Fatura No (Gönderimde üretilir)', 'Fatura No', 'file-text') ?>
                                <div id="faturaNoHelpText" class="field-help-text text-muted">
                                    <i class="bx bx-info-circle me-1"></i>Gönderimde otomatik üretilir.
                                </div>
                            </div>

                            <!-- Fatura Tipi * -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2('fatura_tipi', $faturaTipleri, $editInvoice['fatura_tipi'] ?? 'SATIS', 'Fatura Tipi *', 'tag') ?>
                            </div>

                            <!-- Fatura Senaryo * -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2('fatura_profili', $faturaSenaryolari, $editInvoice['fatura_profili'] ?? 'EARSIVFATURA', 'Fatura Senaryo *', 'sliders') ?>
                            </div>

                            <!-- Para Birimi & Döviz Kuru -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2('para_birimi', $paraBirimleri, $editInvoice['para_birimi'] ?? 'TRY', 'Para Birimi *', 'dollar-sign') ?>
                            </div>
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('number', 'doviz_kuru', $editInvoice['doviz_kuru'] ?? '1.0000', '', 'Döviz Kuru', 'trending-up', 'form-control', false, null, 'on', false, 'min="0.0001" step="0.0001"') ?>
                            </div>

                            <!-- Özel Alan 1 -->
                            <div class="col-12">
                                <?= Form::FormFloatInput('text', 'ozel_alan_1', '', 'Özel Referans / Kod...', 'Özel Alan 1', 'edit-3') ?>
                            </div>

                            <!-- İade Fatura Alanları (Koşullu) -->
                            <div class="col-md-6 iade-fields" style="display: none;">
                                <?= Form::FormFloatInput('text', 'iade_fatura_no', '', '', 'İade Edilen Fatura No', 'file-text', 'form-control', false, 50) ?>
                            </div>
                            <div class="col-md-6 iade-fields" style="display: none;">
                                <?= Form::FormFloatInput('text', 'iade_fatura_tarihi', '', '', 'İade Edilen Fatura Tarihi', 'calendar', 'form-control flatpickr') ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. KART: ALICI BİLGİLERİ -->
            <div class="col-lg-6">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-4 py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="bx bx-user font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold text-dark font-size-14">ALICI BİLGİLERİ</h5>
                                <p class="text-muted mb-0 font-size-11">Cari hesap, iletişim ve adres detayları</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-3">
                            
                            <!-- Alıcı * (Cari Seçimi) -->
                            <div class="col-12">
                                <?= Form::FormSelect2('selectCari', $cariOptions, $editInvoice['cari_id'] ?? '', 'Alıcı *', 'users') ?>
                            </div>

                            <!-- Vergi No * (VKN / TCKN) & GİB'de Sorgula -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="form-floating form-floating-custom flex-grow-1">
                                        <input type="text" class="form-control fw-bold" id="alici_vkn_tckn" name="alici_vkn_tckn" maxlength="11" placeholder="10 veya 11 Haneli" required>
                                        <label for="alici_vkn_tckn">Vergi No *</label>
                                        <div class="form-floating-icon">
                                            <i data-feather="hash"></i>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-primary px-3 d-flex align-items-center justify-content-center shadow-xs" id="btnSorgulaVkn" title="GİB'de Sorgula" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                        <i class="bx bx-search font-size-18 me-1"></i> <span class="font-size-12 fw-semibold">Sorgula</span>
                                    </button>
                                </div>
                                <div class="field-help-text text-muted">
                                    Şahıs firmaları için TCKN giriniz.
                                </div>
                            </div>

                            <!-- Vergi Dairesi -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'alici_vergi_dairesi', '', 'Vergi Dairesi', 'Vergi Dairesi', 'briefcase') ?>
                            </div>

                            <!-- GİB Postakutusu * (e-Fatura ise görünür) -->
                            <div class="col-12" id="divPostaKutusu" style="display: none;">
                                <?= Form::FormSelect2('alici_posta_kutusu', ['' => 'Önce mükellef sorgulayın'], '', 'GİB Postakutusu *', 'mail') ?>
                            </div>

                            <!-- Alıcı Ünvanı * -->
                            <div class="col-12">
                                <?= Form::FormFloatInput('text', 'alici_unvan', '', 'Firma Ünvanı veya Ad Soyad', 'Alıcı Ünvanı *', 'user', 'form-control fw-semibold', true) ?>
                            </div>

                            <!-- Ülke *, İl * (Select2) & İlçe * (Select2) -->
                            <div class="col-md-4">
                                <?= Form::FormSelect2('alici_ulke', $ulkeOptions, $editInvoice['alici_ulke'] ?? 'Türkiye', 'Ülke *', 'globe') ?>
                            </div>
                            <div class="col-md-4">
                                <?= Form::FormSelect2('alici_il', $ilOptions, $editInvoice['alici_il'] ?? 'Kayseri', 'İl *', 'map') ?>
                            </div>
                            <div class="col-md-4">
                                <?= Form::FormSelect2('alici_ilce', ['' => 'İlçe Seçiniz...'], $editInvoice['alici_ilce'] ?? '', 'İlçe *', 'map-pin') ?>
                            </div>

                            <!-- Adres * -->
                            <div class="col-12">
                                <?= Form::FormFloatInput('text', 'alici_adres', '', 'Açık adres...', 'Adres *', 'map-pin') ?>
                            </div>

                            <!-- İletişim Bilgileri: E-posta, Web, Telefon -->
                            <div class="col-md-4">
                                <?= Form::FormFloatInput('email', 'alici_eposta', '', 'ornek@alanadi.com', 'Eposta', 'mail') ?>
                            </div>
                            <div class="col-md-4">
                                <?= Form::FormFloatInput('text', 'alici_web', '', 'www.alanadi.com', 'Web Adresi', 'globe') ?>
                            </div>
                            <div class="col-md-4">
                                <?= Form::FormFloatInput('tel', 'alici_telefon', '', '05xx xxx xx xx', 'Cep Telefonu', 'phone') ?>
                            </div>

                            <!-- Checkbox Seçenekleri (Büyük ve Belirgin) -->
                            <div class="col-12 pt-2">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check custom-invoice-checkbox">
                                            <input class="form-check-input" type="checkbox" id="giden_earsiv_sms" name="giden_earsiv_sms" value="1">
                                            <label class="form-check-label" for="giden_earsiv_sms">Giden E-Arşiv Fatura SMS Bildirimi</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check custom-invoice-checkbox">
                                            <input class="form-check-input" type="checkbox" id="giden_fatura_sms" name="giden_fatura_sms" value="1">
                                            <label class="form-check-label" for="giden_fatura_sms">Giden Fatura SMS Bildirimi</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check custom-invoice-checkbox">
                                            <input class="form-check-input" type="checkbox" id="adres_defteri_kayit" name="adres_defteri_kayit" value="1">
                                            <label class="form-check-label" for="adres_defteri_kayit">Adres Defterine Kaydet</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check custom-invoice-checkbox">
                                            <input class="form-check-input" type="checkbox" id="teslimat_adresi_farkli" name="teslimat_adresi_farkli" value="1">
                                            <label class="form-check-label" for="teslimat_adresi_farkli">Teslimat / Sevk Adresi Farklı</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- 3. KART: ÖDEME BİLGİLERİ -->
        <div class="card summary-kpi-card mb-4">
            <div class="card-header bg-transparent border-0 px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-warning-subtle text-warning rounded-3 border border-warning-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 36px; height: 36px;">
                        <i class="bx bx-credit-card font-size-18"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark font-size-14">ÖDEME BİLGİLERİ</h5>
                        <p class="text-muted mb-0 font-size-11">Vade tarihi, ödeme şekli, kanal ve banka hesap numarası</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-4 pt-0">
                <div class="row g-3">
                    
                    <!-- Ödeme Tarihi / Vade Tarihi -->
                    <div class="col-md-3">
                        <?= Form::FormFloatInput('text', 'vade_tarihi', '', '', 'Ödeme Tarihi / Vade', 'calendar', 'form-control flatpickr') ?>
                    </div>

                    <!-- Ödeme Şekli -->
                    <div class="col-md-3">
                        <?= Form::FormSelect2('odeme_sekli', $odemeSekliOptions, '', 'Ödeme Şekli', 'credit-card') ?>
                    </div>

                    <!-- Ödeme Kanalı -->
                    <div class="col-md-3">
                        <?= Form::FormFloatInput('text', 'odeme_kanali', '', 'Banka, Şube veya Kasa...', 'Ödeme Kanalı', 'shuffle') ?>
                    </div>

                    <!-- Ödeme Hesapno -->
                    <div class="col-md-3">
                        <?= Form::FormFloatInput('text', 'odeme_hesap_no', '', 'TRxx xxxx xxxx xxxx...', 'Ödeme Hesapno / IBAN', 'hash') ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- 4. KART: MAL VE HİZMET KALEMLERİ -->
        <div class="card summary-kpi-card mb-4">
            <div class="card-header bg-transparent border-0 px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 36px; height: 36px;">
                        <i class="bx bx-list-ul font-size-18"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark font-size-14">Mal & Hizmet Kalemleri</h5>
                        <p class="text-muted mb-0 font-size-11">Fatura satırları, vergi oranları ve tevkifatlar</p>
                    </div>
                </div>
                <div class="invoice-item-actions d-flex align-items-center gap-2" id="invoiceItemActions">
                    <button type="button" class="btn btn-subtle-warning top-action-btn shadow-xs" id="btnHeaderTevkifat" title="Tüm kalemlerde tevkifat ve istisna alanlarını aç">
                        <i class="bx bx-receipt font-size-16 me-1"></i> <span id="lblHeaderTevkifatText">Tevkifat / İstisna Ekle</span>
                    </button>
                    <button type="button" class="btn btn-subtle-info top-action-btn shadow-xs" id="btnOpenVergiModal" data-bs-toggle="modal" data-bs-target="#modalVergiSecimi" title="Ek vergi türleri ekleyin (ÖTV, Stopaj, Konaklama vb.)">
                        <i class="bx bx-purchase-tag font-size-16 me-1"></i> <span>Vergi Ekle</span> <span class="badge bg-primary text-white rounded-pill ms-1 font-size-10 px-1.5 py-0.5" id="badgeSelectedTaxesCount" style="display:none;">0</span>
                    </button>
                </div>
            </div>
            
            <div class="table-responsive invoice-items-table-wrap">
                <div class="invoice-items-table-frame">
                <table class="table items-table align-middle table-hover" id="tblKalemler">
                    <thead id="tblKalemlerHead">
                        <tr id="tblKalemlerHeadRowMain">
                            <th rowspan="2" style="width: 45px;" class="text-center align-middle">#</th>
                            <th rowspan="2" style="min-width: 220px;" class="align-middle">Mal / Hizmet Açıklaması <span class="text-danger">*</span></th>
                            <th rowspan="2" style="width: 85px;" class="text-end align-middle">Miktar</th>
                            <th rowspan="2" style="width: 110px;" class="align-middle">Birim</th>
                            <th rowspan="2" style="width: 110px;" class="text-end align-middle">Birim Fiyat</th>
                            <th rowspan="2" style="width: 85px;" class="text-end align-middle">İskonto %</th>
                            <!-- DİNAMİK VERGİLER BURAYA EKLENECEK -->
                            <th colspan="2" class="text-center th-tax-header" id="thKdvGroup" style="min-width: 170px;">
                                0015 KDV GERÇEK
                            </th>
                            <th rowspan="2" style="min-width: 220px; display: none;" id="colHeaderTevkifat" class="align-middle">Tevkifat / İstisna</th>
                            <th rowspan="2" style="width: 120px;" class="text-end align-middle" id="colHeaderSatirTutari">Satır Tutarı</th>
                            <th rowspan="2" style="width: 45px;" class="text-center align-middle"></th>
                        </tr>
                        <tr id="tblKalemlerHeadRowSub">
                            <!-- DİNAMİK VERGİ ALT BAŞLIKLARI (Oran / Tutar) -->
                            <th class="text-center th-tax-sub" id="thKdvSubOran" style="width: 85px;">Oran</th>
                            <th class="text-center th-tax-sub" id="thKdvSubTutar" style="width: 85px;">Tutar</th>
                        </tr>
                    </thead>
                    <tbody id="kalemlerContainer">
                        <!-- JS ile Dinamik Satırlar -->
                    </tbody>
                </table>
                </div>

                <!-- Tablo Altı: Satır Ekle Butonu & Kalem Sayacı -->
                <div class="d-flex align-items-center justify-content-between pt-2.5 px-1 mt-1">
                    <button type="button" class="btn btn-sm btn-subtle-primary top-action-btn shadow-xs" id="btnSatirEkle" style="height: 36px; padding: 0 16px;">
                        <i class="bx bx-plus font-size-16 me-1"></i> Satır Ekle
                    </button>
                    <span class="badge bg-light text-muted border px-2.5 py-1.5 rounded-pill font-size-11" id="lblTotalRowCount">1 Kalem</span>
                </div>
            </div>

            <!-- Alt Toplamlar & Notlar Alanı (Direkt Hizada, İç İçe Div Olmadan) -->
            <div class="p-4 bg-white border-top">
                <div class="row g-4 align-items-stretch">
                    <!-- Sol: Fatura Notu / Açıklama -->
                    <div class="col-lg-7 col-12 d-flex flex-column">
                        <label class="form-label fw-bold text-dark font-size-13 mb-2">
                            <i class="bx bx-notepad me-1 text-primary"></i>Fatura Notu / Açıklama
                        </label>
                        <div class="invoice-not-editor flex-grow-1 d-flex flex-column">
                            <textarea class="form-control" id="notlar" placeholder="Fatura üzerinde basılacak banka IBAN bilgileri, sipariş/sözleşme referansları vb..."></textarea>
                        </div>
                    </div>

                    <!-- Sağ: Toplam Bilgileri -->
                    <div class="col-lg-5 col-12 d-flex flex-column">
                        <div class="summary-card h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="summary-row">
                                    <span class="fw-semibold">Mal / Hizmet Toplamı:</span>
                                    <span class="fw-bold text-dark font-monospace" id="lblSatirToplami">0,00 ₺</span>
                                </div>
                                <div class="summary-row">
                                    <span class="text-danger fw-semibold">İskonto Toplamı (-):</span>
                                    <span class="fw-bold text-danger font-monospace" id="lblIskontoToplami">0,00 ₺</span>
                                </div>
                                <div class="summary-row">
                                    <span class="fw-semibold">KDV Matrahı:</span>
                                    <span class="fw-bold text-dark font-monospace" id="lblKdvMatrahi">0,00 ₺</span>
                                </div>
                                <div class="summary-row">
                                    <span class="text-success fw-semibold">Hesaplanan KDV:</span>
                                    <span class="fw-bold text-success font-monospace" id="lblHesaplananKdv">0,00 ₺</span>
                                </div>
                                <div class="summary-row" id="rowTevkifatSummary">
                                    <span class="fw-semibold">Tevkifat Tutarı (-):</span>
                                    <span class="fw-bold text-dark font-monospace" id="lblTevkifat">0,00 ₺</span>
                                </div>
                                <div id="dynamicTaxSummaryRows"></div>
                            </div>
                            <div class="summary-row grand-total mt-3">
                                <span>ÖDENECEK TOPLAM:</span>
                                <span class="text-primary font-monospace font-size-18" id="lblOdenecekTutar">0,00 ₺</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Vergi Seçimi Modalı -->
<div class="modal fade" id="modalVergiSecimi" tabindex="-1" aria-labelledby="modalVergiSecimiLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 36px; height: 36px;">
                        <i class="bx bx-purchase-tag font-size-18"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark font-size-15" id="modalVergiSecimiLabel">Vergi Türleri Seçimi</h5>
                        <p class="text-muted mb-0 font-size-11">Fatura kalemlerine uygulanacak stopaj, ÖTV ve diğer vergi türlerini seçin</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Arama Kutusu -->
                <div class="mb-3 position-relative">
                    <i class="bx bx-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted font-size-16"></i>
                    <input type="text" class="form-control ps-5 font-size-13 rounded-3" id="searchVergiInput" placeholder="Vergi kodu veya açıklama ile filtrele (Örn: Stopaj, 0003, Konaklama, ÖTV)...">
                </div>

                <div class="table-responsive border rounded-3" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tblModalVergiList">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 45px;" class="text-center">
                                    <div class="form-check font-size-15">
                                        <input class="form-check-input" type="checkbox" id="chkAllVergiModal">
                                    </div>
                                </th>
                                <th style="width: 90px;">KOD</th>
                                <th>VERGİ AÇIKLAMASI</th>
                                <th style="width: 150px;" class="text-center">KISA ADI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vergiListesi as $v): ?>
                            <tr class="vergi-modal-row" data-search="<?= htmlspecialchars(mb_strtolower($v['kod'] . ' ' . $v['ad'] . ' ' . $v['kisa'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>" data-kod="<?= htmlspecialchars($v['kod'], ENT_QUOTES, 'UTF-8') ?>" data-ad="<?= htmlspecialchars($v['ad'], ENT_QUOTES, 'UTF-8') ?>" data-kisa="<?= htmlspecialchars($v['kisa'], ENT_QUOTES, 'UTF-8') ?>">
                                <td class="text-center">
                                    <div class="form-check font-size-15">
                                        <input class="form-check-input chk-vergi-item" type="checkbox" value="<?= htmlspecialchars($v['kod'], ENT_QUOTES, 'UTF-8') ?>" id="chk_tax_<?= htmlspecialchars($v['kod'], ENT_QUOTES, 'UTF-8') ?>" <?= $v['kod'] === '0015' ? 'checked disabled' : '' ?>>
                                    </div>
                                </td>
                                <td>
                                    <label class="font-monospace fw-bold text-dark font-size-12 mb-0 cursor-pointer" for="chk_tax_<?= htmlspecialchars($v['kod'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v['kod'], ENT_QUOTES, 'UTF-8') ?></label>
                                </td>
                                <td>
                                    <label class="fw-semibold text-dark font-size-12 mb-0 cursor-pointer" for="chk_tax_<?= htmlspecialchars($v['kod'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($v['ad'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($v['kod'] === '0015'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-size-10 ms-1">Standart KDV Kolonu</span>
                                        <?php endif; ?>
                                    </label>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-secondary border font-size-11"><?= htmlspecialchars($v['kisa'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3 d-flex justify-content-between">
                <span class="text-muted font-size-12"><strong id="modalSelectedCount">1</strong> vergi seçili</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3 font-size-13" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="button" class="btn btn-primary rounded-3 px-4 font-size-13 fw-semibold shadow-xs" id="btnApplySelectedTaxes" data-bs-dismiss="modal">
                        <i class="bx bx-check me-1 font-size-15 align-middle"></i> Seçilen Vergileri Uygula
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SortableJS Kütüphanesi -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const CARI_DATA = <?= json_encode($cariler, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const UNIT_SELECT = <?= json_encode($unitSelectHtml, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const VAT_SELECT = <?= json_encode($vatSelectHtml, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const WITHHOLDING_SELECT = <?= json_encode($withholdingSelectHtml, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const EXEMPTION_SELECT = <?= json_encode($exemptionSelectHtml, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const EDIT_DATA = <?= json_encode($editInvoice, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const EDM_SERIALS = <?= json_encode($edmSerials, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const EDM_DATA = <?= json_encode([
    'districts' => $ilceler,
    'payments' => $odemeSekilleri
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

document.addEventListener('DOMContentLoaded', function() {
    let rowCounter = 0;
    let calculationTimer;
    let calculationVersion = 0;
    let isGlobalTaxDetailOpen = false;
    let activeDynamicTaxes = []; // Seçilen dinamik vergi kolonları [{ kod, ad, kisa }]

    // Feather ikonlarını render et
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Flatpickr Başlatma
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.flatpickr', {
            dateFormat: 'd.m.Y',
            locale: 'tr',
            allowInput: true
        });
    }

    // 1. Tüm Üst Select2 Elemanlarını Başlat
    $('.select2').select2({
        dropdownAutoWidth: true,
        width: '100%'
    });

    // Sıradaki Fatura Numarası Tahmin ve Önizleme Fonksiyonu
    function updateNextInvoiceNoPreview() {
        const seriVal = $('#seri_no').val();
        const belgeTuru = $('#belge_turu').val() || 'EARSIV';
        const isEarchiveTarget = (belgeTuru === 'EFATURA') ? 0 : 1;
        const faturaTarihiVal = $('#fatura_tarihi').val();
        let currentYear = new Date().getFullYear();
        if (faturaTarihiVal && /^\d{2}\.\d{2}\.\d{4}$/.test(faturaTarihiVal)) {
            currentYear = parseInt(faturaTarihiVal.split('.')[2], 10);
        }

        let targetSerialObj = null;
        if (Array.isArray(EDM_SERIALS) && EDM_SERIALS.length > 0) {
            if (seriVal) {
                targetSerialObj = EDM_SERIALS.find(s => s.series === seriVal && parseInt(s.active, 10) === 1 && parseInt(s.earchive, 10) === isEarchiveTarget);
                if (!targetSerialObj) {
                    targetSerialObj = EDM_SERIALS.find(s => s.series === seriVal);
                }
            } else {
                targetSerialObj = EDM_SERIALS.find(s => parseInt(s.active, 10) === 1 && parseInt(s.earchive, 10) === isEarchiveTarget);
            }
        }

        if (targetSerialObj) {
            const year = targetSerialObj.year || currentYear;
            const lastNo = parseInt(targetSerialObj.last, 10) || 0;
            const nextNo = lastNo + 1;
            const formattedNext = targetSerialObj.series + String(year) + String(nextNo).padStart(9, '0');

            if (!$('#fatura_no').val()) {
                $('#fatura_no').attr('placeholder', 'Sıradaki: ' + formattedNext);
            }
            $('#faturaNoHelpText').html(`<i class="bx bx-check-circle text-success me-1"></i>Sıradaki tahmini no: <strong class="text-primary font-monospace">${formattedNext}</strong> (Gönderimde kesinleşir)`);
        } else {
            const defaultPrefix = (belgeTuru === 'EFATURA' ? 'ERS' : 'ERA');
            const fallbackNext = defaultPrefix + String(currentYear) + '000000001';
            if (!$('#fatura_no').val()) {
                $('#fatura_no').attr('placeholder', 'Sıradaki: ' + fallbackNext);
            }
            $('#faturaNoHelpText').html(`<i class="bx bx-info-circle me-1"></i>Gönderimde otomatik üretilir.`);
        }
    }

    // Seri No Doldurma Fonksiyonu (EDM Entegrasyonu)
    function populateSeriOptions(belgeTuru, selectedSeri = '') {
        const seriSelect = $('#seri_no');
        if (!seriSelect.length) return;

        const currentVal = selectedSeri || seriSelect.val() || '';
        seriSelect.empty();
        seriSelect.append(new Option('Otomatik Seri (EDM Belirlesin)', ''));

        if (Array.isArray(EDM_SERIALS) && EDM_SERIALS.length > 0) {
            const isEarchiveTarget = (belgeTuru === 'EFATURA') ? 0 : 1;
            const matching = EDM_SERIALS.filter(s => parseInt(s.active, 10) === 1 && parseInt(s.earchive, 10) === isEarchiveTarget);
            matching.forEach(s => {
                const label = s.series + (s.last ? ' (Son No: ' + s.last + ')' : '');
                const isSelected = (s.series === currentVal);
                seriSelect.append(new Option(label, s.series, false, isSelected));
            });
        }

        if (currentVal) {
            seriSelect.val(currentVal);
        }
        seriSelect.trigger('change.select2');
        updateNextInvoiceNoPreview();
    }

    // İlçe Doldurma Fonksiyonu
    function populateDistricts(selectedCity, selectedDistrict = '') {
        const districtSelect = $('#alici_ilce');
        districtSelect.empty().append(new Option('İlçe Seçiniz...', ''));
        
        if (selectedCity && EDM_DATA.districts && EDM_DATA.districts[selectedCity]) {
            EDM_DATA.districts[selectedCity].forEach(d => {
                const isSelected = (d === selectedDistrict);
                const opt = new Option(d, d, false, isSelected);
                districtSelect.append(opt);
            });
        }
        districtSelect.trigger('change.select2');
    }

    $('#alici_il').on('change', function() {
        populateDistricts($(this).val());
    });

    $('#seri_no').on('change', function() {
        updateNextInvoiceNoPreview();
    });

    $('#fatura_tarihi').on('change', function() {
        updateNextInvoiceNoPreview();
    });

    // İlk yüklemede mevcut il için ilçeleri yükle
    const initialCity = $('#alici_il').val() || (EDIT_DATA ? EDIT_DATA.alici_il : 'Kayseri');
    const initialDistrict = EDIT_DATA ? (EDIT_DATA.alici_ilce || '') : '';
    populateDistricts(initialCity, initialDistrict);

    // İlk yüklemede seri numaralarını filtrele
    const initialBelgeTuru = $('input[name="gonderim_sekli"]:checked').val() || $('#belge_turu').val() || 'EARSIV';
    const initialSeri = EDIT_DATA ? (EDIT_DATA.seri_no || '') : '';
    populateSeriOptions(initialBelgeTuru, initialSeri);
    updateNextInvoiceNoPreview();

    // ETTN Kopyalama ve Yenileme
    $('#btnCopyEttn').on('click', function() {
        const ettnVal = $('#ettn').val();
        if (navigator.clipboard && ettnVal) {
            navigator.clipboard.writeText(ettnVal).then(() => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'ETTN panoya kopyalandı.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            });
        }
    });

    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    $('#btnRegenerateEttn').on('click', function() {
        const newUuid = generateUUID();
        $('#ettn').val(newUuid);
        $('#displayEttn').val(newUuid);
    });

    // Gönderim Şekli Radyo Buton Değişimi
    $('input[name="gonderim_sekli"]').on('change', function() {
        const val = $(this).val();
        if (val === 'EFATURA') {
            $('#belge_turu').val('EFATURA');
            $('#fatura_profili').val('TICARIFATURA').trigger('change.select2');
            $('#divPostaKutusu').slideDown(200);
            populateSeriOptions('EFATURA');
        } else {
            $('#belge_turu').val('EARSIV');
            $('#fatura_profili').val('EARSIVFATURA').trigger('change.select2');
            $('#divPostaKutusu').slideUp(200);
            populateSeriOptions('EARSIV');
        }
        updateNextInvoiceNoPreview();
    });

    // Tevkifat / İstisna Kolonunu Aç/Kapat Fonksiyonu
    function toggleTevkifatColumn(forceState = null) {
        if (forceState !== null) {
            isGlobalTaxDetailOpen = forceState;
        } else {
            isGlobalTaxDetailOpen = !isGlobalTaxDetailOpen;
        }

        if (isGlobalTaxDetailOpen) {
            $('#colHeaderTevkifat').show();
            $('.col-tevkifat-cell').show();
            $('#btnHeaderTevkifat')
                .removeClass('btn-subtle-warning')
                .addClass('btn-subtle-danger')
                .attr('title', 'Tüm kalemlerde tevkifat ve istisna alanlarını gizle');
            $('#btnHeaderTevkifat i').attr('class', 'bx bx-x font-size-16 me-1');
            $('#lblHeaderTevkifatText').text('Tevkifat / İstisnayı Gizle');
        } else {
            $('#colHeaderTevkifat').hide();
            $('.col-tevkifat-cell').hide();
            $('#btnHeaderTevkifat')
                .removeClass('btn-subtle-danger')
                .addClass('btn-subtle-warning')
                .attr('title', 'Tüm kalemlerde tevkifat ve istisna alanlarını aç');
            $('#btnHeaderTevkifat i').attr('class', 'bx bx-receipt font-size-16 me-1');
            $('#lblHeaderTevkifatText').text('Tevkifat / İstisna Ekle');

            // Değerleri temizle ve hesaplamayı güncelle
            $('.kalem-tevkifat, .kalem-istisna').val('').trigger('change.select2');
            $('.kalem-istisna-aciklama').val('');
            calculateTotals();
        }
    }

    // Dinamik Vergi Kolonlarını Tabloya Senkronize Et
    function syncDynamicTaxColumns() {
        // 1. Header (th) kolonlarını güncelle
        $('#tblKalemlerHeadRowMain .th-dynamic-tax-group').remove();
        $('#tblKalemlerHeadRowSub .th-dynamic-tax-sub').remove();
        
        activeDynamicTaxes.forEach(tax => {
            const thMain = `
                <th colspan="2" class="text-center th-tax-header th-dynamic-tax-group" data-tax-code="${tax.kod}" style="min-width: 165px;">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <span class="text-truncate" style="max-width: 125px;" title="${tax.ad}">${tax.kod} ${tax.kisa}</span>
                        <button type="button" class="btn btn-link text-danger p-0 border-0 btn-remove-tax-column" data-tax-code="${tax.kod}" title="Bu vergi kolonunu kaldır" style="font-size: 13px; line-height: 1; text-decoration: none;">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                </th>
            `;
            $(thMain).insertBefore('#thKdvGroup');

            const thSub = `
                <th class="text-center th-tax-sub th-dynamic-tax-sub" data-tax-code="${tax.kod}" style="width: 80px;">Oran</th>
                <th class="text-center th-tax-sub th-dynamic-tax-sub" data-tax-code="${tax.kod}" style="width: 85px;">Tutar</th>
            `;
            $(thSub).insertBefore('#thKdvSubOran');
        });

        // 2. Mevcut Satırları Güncelle (Girilen değerleri koruyarak)
        $('.kalem-row').each(function() {
            const row = $(this);
            const savedVals = {};
            row.find('.kalem-ek-vergi-oran').each(function() {
                savedVals[$(this).data('tax-code')] = $(this).val();
            });

            row.find('.td-dynamic-tax').remove();

            let dynamicTds = '';
            activeDynamicTaxes.forEach(tax => {
                const val = savedVals[tax.kod] !== undefined ? savedVals[tax.kod] : 0;
                dynamicTds += `
                    <td class="td-dynamic-tax td-dynamic-tax-oran" data-tax-code="${tax.kod}" style="width: 80px;">
                        <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm text-end kalem-ek-vergi-oran" data-tax-code="${tax.kod}" value="${val}" placeholder="0">
                    </td>
                    <td class="td-dynamic-tax td-dynamic-tax-tutar" data-tax-code="${tax.kod}" style="width: 85px;">
                        <input type="text" class="form-control form-control-sm text-end kalem-ek-vergi-tutar bg-light font-monospace" data-tax-code="${tax.kod}" value="0,00" readonly>
                    </td>
                `;
            });

            if (dynamicTds) {
                $(dynamicTds).insertBefore(row.find('.col-kdv-oran-cell'));
            }
        });

        // 3. Rozet ve Modal Durumunu Güncelle
        if (activeDynamicTaxes.length > 0) {
            $('#badgeSelectedTaxesCount').text(activeDynamicTaxes.length).show();
        } else {
            $('#badgeSelectedTaxesCount').hide();
        }

        // Modal Checkboxlarını Senkronize Et
        $('.chk-vergi-item').not(':disabled').each(function() {
            const code = $(this).val();
            $(this).prop('checked', activeDynamicTaxes.some(t => t.kod === code));
        });
        updateModalSelectedCount();

        calculateTotals();
    }

    // Sıra Numaralarını Baştan Sona Dinamik Güncelle
    function updateRowNumbers() {
        let idx = 1;
        $('#kalemlerContainer tr.kalem-row').each(function() {
            $(this).find('.row-number').text(idx++);
        });
        const totalRows = idx - 1;
        $('#lblTotalRowCount').text(totalRows + ' Kalem');
    }

    // 2. Dinamik Kalem Satırı Ekleme
    function addRow(data = {}) {
        rowCounter++;

        let dynamicTaxCells = '';
        activeDynamicTaxes.forEach(tax => {
            const val = (data.ek_vergiler && data.ek_vergiler[tax.kod] !== undefined)
                ? data.ek_vergiler[tax.kod]
                : (data['vergi_' + tax.kod] ?? 0);
            dynamicTaxCells += `
                <td class="td-dynamic-tax td-dynamic-tax-oran" data-tax-code="${tax.kod}" style="width: 80px;">
                    <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm text-end kalem-ek-vergi-oran" data-tax-code="${tax.kod}" value="${val}" placeholder="0">
                </td>
                <td class="td-dynamic-tax td-dynamic-tax-tutar" data-tax-code="${tax.kod}" style="width: 85px;">
                    <input type="text" class="form-control form-control-sm text-end kalem-ek-vergi-tutar bg-light font-monospace" data-tax-code="${tax.kod}" value="0,00" readonly>
                </td>
            `;
        });

        const rowHtml = `
            <tr id="row_${rowCounter}" class="kalem-row">
                <td class="text-center align-middle" style="width: 45px;">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <i class="bx bx-grid-vertical text-muted drag-handle font-size-18" title="Sıralamak için sürükleyin"></i>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill row-number font-size-11 fw-bold">${rowCounter}</span>
                    </div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm kalem-ad" value="" placeholder="Ürün / Hizmet tanımı..." required>
                </td>
                <td style="width: 85px;">
                    <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm kalem-miktar text-end fw-semibold" value="1">
                </td>
                <td style="width: 110px;">
                    ${UNIT_SELECT}
                </td>
                <td style="width: 110px;">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm kalem-fiyat text-end fw-semibold" value="0">
                </td>
                <td style="width: 85px;">
                    <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm kalem-iskonto text-end" value="0">
                </td>
                ${dynamicTaxCells}
                <td class="col-kdv-oran-cell" style="width: 80px;">
                    ${VAT_SELECT}
                </td>
                <td class="col-kdv-tutar-cell" style="width: 85px;">
                    <input type="text" class="form-control form-control-sm text-end kalem-kdv-tutar bg-light font-monospace" value="0,00" readonly>
                </td>
                <td class="col-tevkifat-cell" style="${isGlobalTaxDetailOpen ? '' : 'display: none;'}">
                    <div class="tax-detail-fields-wrap">
                        ${WITHHOLDING_SELECT}
                        <div class="mt-1">
                            ${EXEMPTION_SELECT}
                        </div>
                        <input type="text" class="form-control form-control-sm kalem-istisna-aciklama mt-1" placeholder="İstisna açıklaması">
                    </div>
                </td>
                <td class="text-end fw-bold kalem-toplam text-dark font-monospace" style="width: 120px;">0,00 ₺</td>
                <td class="text-center" style="width: 45px;">
                    <button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-satir-sil" title="Satırı Sil">
                        <i class="bx bx-trash font-size-15 align-middle"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#kalemlerContainer').append(rowHtml);
        const row = $(`#row_${rowCounter}`);
        
        row.find('.kalem-ad').val(data.urun_hizmet_adi ?? '');
        row.find('.kalem-miktar').val(data.miktar ?? '1');
        row.find('.kalem-fiyat').val(data.birim_fiyat ?? '0');
        row.find('.kalem-iskonto').val(data.iskonto_orani ?? '0');
        row.find('.kalem-kdv').val(data.kdv_orani == null ? '20' : String(parseFloat(data.kdv_orani)));
        row.find('.kalem-birim').val(data.birim ?? 'C62');
        row.find('.kalem-tevkifat').val(data.tevkifat_kodu ? data.tevkifat_kodu + '|' + parseInt(data.tevkifat_orani, 10) : '');
        row.find('.kalem-istisna').val(data.istisna_kodu ?? '');
        row.find('.kalem-istisna-aciklama').val(data.istisna_aciklama ?? '');

        if (data.tevkifat_kodu || data.istisna_kodu || data.istisna_aciklama) {
            toggleTevkifatColumn(true);
        }

        row.find('.select2-item').select2({
            dropdownAutoWidth: true,
            width: '100%'
        });

        updateRowNumbers();
        calculateTotals();
    }

    // Düzenleme modunda ise verileri forma yükle
    if (EDIT_DATA) {
        if (EDIT_DATA.cari_id) {
            $('#selectCari').val(EDIT_DATA.cari_id).trigger('change.select2');
        }
        $('#alici_vkn_tckn').val(EDIT_DATA.alici_vkn_tckn || '');
        $('#alici_vergi_dairesi').val(EDIT_DATA.alici_vergi_dairesi || '');
        $('#alici_unvan').val(EDIT_DATA.alici_unvan || '');
        $('#alici_adres').val(EDIT_DATA.alici_adres || '');
        $('#alici_il').val(EDIT_DATA.alici_il || 'Kayseri').trigger('change.select2');
        if (EDIT_DATA.alici_ilce) {
            setTimeout(() => {
                $('#alici_ilce').val(EDIT_DATA.alici_ilce).trigger('change.select2');
            }, 100);
        }
        $('#alici_ulke').val(EDIT_DATA.alici_ulke || 'Türkiye').trigger('change.select2');
        $('#alici_eposta').val(EDIT_DATA.alici_eposta || '');
        $('#alici_telefon').val(EDIT_DATA.alici_telefon || '');
        $('#fatura_no').val(EDIT_DATA.fatura_no || '');
        
        const bTuru = EDIT_DATA.belge_turu || 'EARSIV';
        $('#belge_turu').val(bTuru);
        if (bTuru === 'EFATURA') {
            $('#gonderim_efatura').prop('checked', true);
            $('#divPostaKutusu').show();
        } else {
            $('#gonderim_earsiv').prop('checked', true);
            $('#divPostaKutusu').hide();
        }

        $('#fatura_profili').val(EDIT_DATA.fatura_profili || 'EARSIVFATURA').trigger('change.select2');
        $('#fatura_tipi').val(EDIT_DATA.fatura_tipi || 'SATIS').trigger('change.select2');
        $('#para_birimi').val(EDIT_DATA.para_birimi || 'TRY').trigger('change.select2');
        
        if (EDIT_DATA.fatura_tarihi) {
            const parts = EDIT_DATA.fatura_tarihi.split('-');
            if (parts.length === 3) {
                $('#fatura_tarihi').val(`${parts[2]}.${parts[1]}.${parts[0]}`);
            } else {
                $('#fatura_tarihi').val(EDIT_DATA.fatura_tarihi);
            }
        }
        if (EDIT_DATA.duzenleme_saati) {
            $('#duzenleme_saati').val(EDIT_DATA.duzenleme_saati);
        }
        if (EDIT_DATA.vade_tarihi) {
            const parts = EDIT_DATA.vade_tarihi.split('-');
            if (parts.length === 3) {
                $('#vade_tarihi').val(`${parts[2]}.${parts[1]}.${parts[0]}`);
            } else {
                $('#vade_tarihi').val(EDIT_DATA.vade_tarihi);
            }
        }
        $('#notlar').val(EDIT_DATA.notlar || '');
        $('#doviz_kuru').val(EDIT_DATA.doviz_kuru ?? '1');
        $('#iade_fatura_no').val(EDIT_DATA.iade_fatura_no ?? '');
        if (EDIT_DATA.iade_fatura_tarihi) {
            const parts = EDIT_DATA.iade_fatura_tarihi.split('-');
            if (parts.length === 3) {
                $('#iade_fatura_tarihi').val(`${parts[2]}.${parts[1]}.${parts[0]}`);
            } else {
                $('#iade_fatura_tarihi').val(EDIT_DATA.iade_fatura_tarihi);
            }
        }

        $('#kalemlerContainer').empty();
        if (EDIT_DATA.satirlar && EDIT_DATA.satirlar.length > 0) {
            EDIT_DATA.satirlar.forEach(line => addRow(line));
        } else {
            addRow();
        }
    } else {
        // İlk satırı yükle
        addRow();
    }

    if (typeof $.fn.summernote !== 'undefined') {
        $('#notlar').summernote({
            height: 220,
            lang: 'tr-TR',
            placeholder: 'Fatura üzerinde basılacak banka IBAN bilgileri, sipariş/sözleşme referansları vb...',
            fontNames: ['Times New Roman', 'Arial'],
            fontNamesIgnoreCheck: ['Times New Roman', 'Arial'],
            toolbar: [
                ['style', ['style']],
                ['font', ['fontname', 'fontsize', 'bold', 'italic', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'table', 'hr']],
                ['view', ['fullscreen', 'codeview']]
            ],
            callbacks: {
                onInit: function() {
                    $('.invoice-not-editor .note-editable').css({fontFamily: '"Times New Roman", Times, serif', fontSize: '12pt'});
                    if ($('#notlar').summernote('isEmpty')) {
                        $('#notlar').summernote('fontName', 'Times New Roman');
                        $('#notlar').summernote('fontSize', '12');
                    }
                }
            }
        });
    }

    // Tablo Altı: Satır Ekle
    $('#btnSatirEkle').on('click', function() { addRow(); });

    // Üst Araç Çubuğu: Tüm Kalemlerde Tevkifat / İstisna Kolonunu Aç/Kapat
    $('#btnHeaderTevkifat').on('click', function() {
        toggleTevkifatColumn();
    });

    // Vergi Seçimi Modal Fonksiyonları
    $('#searchVergiInput').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        $('#tblModalVergiList tbody tr.vergi-modal-row').each(function() {
            const searchData = $(this).data('search') || '';
            $(this).toggle(!query || searchData.indexOf(query) > -1);
        });
    });

    $('#chkAllVergiModal').on('change', function() {
        const isChecked = this.checked;
        $('#tblModalVergiList tbody tr.vergi-modal-row:visible .chk-vergi-item').not(':disabled').prop('checked', isChecked);
        updateModalSelectedCount();
    });

    $(document).on('change', '.chk-vergi-item', function() {
        updateModalSelectedCount();
    });

    function updateModalSelectedCount() {
        const count = $('.chk-vergi-item:checked').length;
        $('#modalSelectedCount').text(count);
    }

    // Kolon Başlığındaki "x" Butonuna Basıldığında Vergi Kolonunu Kaldır
    $(document).on('click', '.btn-remove-tax-column', function(e) {
        e.preventDefault();
        const code = String($(this).data('tax-code'));
        activeDynamicTaxes = activeDynamicTaxes.filter(t => t.kod !== code);
        syncDynamicTaxColumns();
    });

    // Modal Üzerinden Vergi Seçimini Uygula
    $('#btnApplySelectedTaxes').on('click', function() {
        activeDynamicTaxes = [];
        $('.chk-vergi-item:checked').each(function() {
            const code = $(this).val();
            if (code === '0015') return; // Standart KDV zaten mevcut
            const tr = $(this).closest('tr');
            activeDynamicTaxes.push({
                kod: code,
                ad: tr.data('ad'),
                kisa: tr.data('kisa') || code
            });
        });

        syncDynamicTaxColumns();

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: activeDynamicTaxes.length > 0 ? (activeDynamicTaxes.length + ' adet vergi kolonu faturaya eklendi.') : 'Vergi kolonları güncellendi.',
                showConfirmButton: false,
                timer: 2000
            });
        }
    });

    // Satır Silme
    $('#kalemlerContainer').on('click', '.btn-satir-sil', function() {
        if ($('.kalem-row').length > 1) {
            $(this).closest('tr').remove();
            updateRowNumbers();
            calculateTotals();
        } else {
            Swal.fire('Bilgi', 'Faturada en az bir satır bulunmalıdır.', 'info');
        }
    });

    // 3. SortableJS ile Sürükle-Bırak Satır Sıralama
    const containerEl = document.getElementById('kalemlerContainer');
    if (containerEl && typeof Sortable !== 'undefined') {
        new Sortable(containerEl, {
            handle: '.drag-handle',
            animation: 180,
            ghostClass: 'bg-light-subtle',
            chosenClass: 'table-active',
            onEnd: function() {
                updateRowNumbers();
            }
        });
    }

    function calculateTotals() {
        clearTimeout(calculationTimer);
        const version = ++calculationVersion;
        calculationTimer = setTimeout(async () => {
            try {
                const payload = getInvoicePayload();
                const response = await fetch('api/efatura-api.php?action=calculate_invoice', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({lines: payload.lines})
                });
                const result = await response.json();
                if (version !== calculationVersion) return;
                
                const currency = payload.header.para_birimi || 'TRY';
                const symbol = currency === 'TRY' ? '₺' : currency;
                const money = value => Number(value || 0).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
                
                if (result.status === 'success' && result.data && result.data.header) {
                    const fields = {
                        lblSatirToplami: 'satir_toplami',
                        lblIskontoToplami: 'iskonto_toplami',
                        lblKdvMatrahi: 'kdv_matrahi',
                        lblHesaplananKdv: 'hesaplanan_kdv',
                        lblTevkifat: 'tevkifat_tutari'
                    };
                    for (const [id, field] of Object.entries(fields)) {
                        $('#' + id).text(money(result.data.header[field]));
                    }
                    if (Array.isArray(result.data.lines)) {
                        $('.kalem-row').each(function(index) {
                            const row = $(this);
                            if (result.data.lines[index]) {
                                row.find('.kalem-toplam').text(money(result.data.lines[index].satir_toplami));
                            }
                            
                            // Satır bazlı KDV tutarı hesapla ve yaz
                            const miktar = parseFloat(row.find('.kalem-miktar').val()) || 0;
                            const fiyat = parseFloat(row.find('.kalem-fiyat').val()) || 0;
                            const iskonto = parseFloat(row.find('.kalem-iskonto').val()) || 0;
                            const vatRate = parseFloat(row.find('.kalem-kdv').val()) || 0;

                            const gross = miktar * fiyat;
                            const disc = (gross * iskonto) / 100;
                            const base = gross - disc;
                            const vatAmt = (base * vatRate) / 100;

                            row.find('.kalem-kdv-tutar').val(vatAmt.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

                            // Ek Vergi Satır Tutarlarını Hesapla ve Yaz
                            activeDynamicTaxes.forEach(tax => {
                                const rate = parseFloat(row.find(`.kalem-ek-vergi-oran[data-tax-code="${tax.kod}"]`).val()) || 0;
                                const amt = (base * rate) / 100;
                                row.find(`.kalem-ek-vergi-tutar[data-tax-code="${tax.kod}"]`).val(amt.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                            });
                        });
                    }

                    // Ek Vergi Toplamlarını Kartta Göster
                    let dynamicRowsHtml = '';
                    let totalEkVergiTutar = 0;

                    activeDynamicTaxes.forEach(tax => {
                        let taxSum = 0;
                        $('.kalem-row').each(function() {
                            const miktar = parseFloat($(this).find('.kalem-miktar').val()) || 0;
                            const fiyat = parseFloat($(this).find('.kalem-fiyat').val()) || 0;
                            const iskonto = parseFloat($(this).find('.kalem-iskonto').val()) || 0;
                            const rate = parseFloat($(this).find(`.kalem-ek-vergi-oran[data-tax-code="${tax.kod}"]`).val()) || 0;

                            const gross = miktar * fiyat;
                            const disc = (gross * iskonto) / 100;
                            const base = gross - disc;
                            const amt = (base * rate) / 100;
                            taxSum += amt;
                        });

                        totalEkVergiTutar += taxSum;
                        dynamicRowsHtml += `
                            <div class="summary-row">
                                <span class="fw-semibold">${tax.kisa} Tutarı:</span>
                                <span class="fw-bold text-dark font-monospace">${money(taxSum)}</span>
                            </div>
                        `;
                    });

                    $('#dynamicTaxSummaryRows').html(dynamicRowsHtml);

                    const basePayable = parseFloat(result.data.header.odenecek_tutar) || 0;
                    $('#lblOdenecekTutar').text(money(basePayable + totalEkVergiTutar));
                }
            } catch (e) {
                console.error('Hesaplama hatası:', e);
            }
        }, 150);
    }

    $('#para_birimi').on('change', calculateTotals);
    $('#fatura_tipi').on('change', function() {
        $('.iade-fields').toggle(this.value === 'IADE');
        if (this.value === 'IADE' && $('#belge_turu').val() === 'EFATURA') {
            $('#fatura_profili').val('TEMELFATURA').trigger('change.select2');
        }
    });
    $('.iade-fields').toggle($('#fatura_tipi').val() === 'IADE');
    
    $('#kalemlerContainer').on('input change', 'input, select', function() {
        calculateTotals();
    });

    // Cari Seçildiğinde
    $('#selectCari').on('change', function() {
        const cariId = $(this).val();
        if (!cariId) return;

        const selected = CARI_DATA.find(c => c.id == cariId);
        if (selected) {
            $('#alici_unvan').val(selected.firma || selected.CariAdi || '');
            if (selected.Adres) $('#alici_adres').val(selected.Adres);
            if (selected.il || selected.Il) {
                const ilVal = selected.il || selected.Il;
                $('#alici_il').val(ilVal).trigger('change.select2');
            }
            if (selected.ilce || selected.Ilce) {
                const ilceVal = selected.ilce || selected.Ilce;
                setTimeout(() => {
                    $('#alici_ilce').val(ilceVal).trigger('change.select2');
                }, 100);
            }
            if (selected.ulke) {
                $('#alici_ulke').val(selected.ulke).trigger('change.select2');
            }
            if (selected.vergi_dairesi || selected.VergiDairesi) $('#alici_vergi_dairesi').val(selected.vergi_dairesi || selected.VergiDairesi);
            if (selected.Email || selected.Eposta) $('#alici_eposta').val(selected.Email || selected.Eposta);
            if (selected.Telefon) $('#alici_telefon').val(selected.Telefon);
            if (selected.web_sitesi) $('#alici_web').val(selected.web_sitesi);
            
            const vkn = selected.vkn_tckn || (selected.notlar || selected.CariAdi || '').match(/\b\d{10,11}\b/)?.[0];
            if (vkn) {
                $('#alici_vkn_tckn').val(vkn);
                checkTaxpayer(vkn);
            }
            if (selected.posta_kutusu) {
                setTimeout(() => {
                    if ($('#alici_posta_kutusu').length && selected.posta_kutusu) {
                        if ($('#alici_posta_kutusu option[value="' + selected.posta_kutusu + '"]').length === 0) {
                            $('#alici_posta_kutusu').append(new Option(selected.posta_kutusu, selected.posta_kutusu, true, true));
                        }
                        $('#alici_posta_kutusu').val(selected.posta_kutusu).trigger('change.select2');
                    }
                }, 400);
            }
        }
    });

    // VKN Mükellefiyet Kontrolü
    function checkTaxpayer(vkn) {
        if (!vkn || (vkn.length !== 10 && vkn.length !== 11)) return;

        $('#mukellefDurumuBadge').html(`
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill fw-semibold font-size-12">
                <span class="spinner-border spinner-border-sm me-1"></span> GİB Sorgulanıyor...
            </span>
        `);

        fetch('api/efatura-api.php?action=check_taxpayer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `vkn_tckn=${encodeURIComponent(vkn)}`
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const d = res.data;
                if (d.is_einvoice_user) {
                    $('#mukellefDurumuBadge').html(`
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold font-size-12">
                            <i class="bx bx-check-circle me-1"></i> E-Fatura Mükellefi (GİB)
                        </span>
                    `);
                    $('#gonderim_efatura').prop('checked', true);
                    $('#belge_turu').val('EFATURA');
                    $('#fatura_profili').val('TICARIFATURA').trigger('change.select2');
                    $('#divPostaKutusu').slideDown(200);
                    populateSeriOptions('EFATURA');

                    if (d.title && !$('#alici_unvan').val()) {
                        $('#alici_unvan').val(d.title);
                    }

                    let aliasSelect = $('#alici_posta_kutusu');
                    aliasSelect.empty();
                    if (d.aliases && d.aliases.length > 0) {
                        d.aliases.forEach(a => {
                            aliasSelect.append(new Option(a, a));
                        });
                    } else {
                        aliasSelect.append(new Option('Aktif posta kutusu bulunamadı', ''));
                    }
                    aliasSelect.trigger('change.select2');
                } else {
                    $('#mukellefDurumuBadge').html(`
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 rounded-pill fw-semibold font-size-12">
                            <i class="bx bx-info-circle me-1"></i> E-Arşiv Fatura Alıcısı
                        </span>
                    `);
                    $('#gonderim_earsiv').prop('checked', true);
                    $('#belge_turu').val('EARSIV');
                    $('#fatura_profili').val('EARSIVFATURA').trigger('change.select2');
                    $('#divPostaKutusu').slideUp(200);
                    populateSeriOptions('EARSIV');
                }
            } else {
                $('#mukellefDurumuBadge').html(`
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill fw-semibold font-size-12">
                        <i class="bx bx-help-circle me-1"></i> Mükellefiyet Kontrolü Bekleniyor
                    </span>
                `);
            }
        });
    }

    $('#btnSorgulaVkn').on('click', function() {
        const vkn = $('#alici_vkn_tckn').val().trim();
        if (vkn.length !== 10 && vkn.length !== 11) {
            Swal.fire('Uyarı', 'Lütfen 10 haneli VKN veya 11 haneli TCKN girin.', 'warning');
            return;
        }
        checkTaxpayer(vkn);
    });

    $('#alici_vkn_tckn').on('blur', function() {
        const vkn = $(this).val().trim();
        if (vkn.length === 10 || vkn.length === 11) {
            checkTaxpayer(vkn);
        }
    });

    function parseDateForPayload(val) {
        if (!val || typeof val !== 'string') return null;
        val = val.trim();
        if (/^\d{2}\.\d{2}\.\d{4}$/.test(val)) {
            const p = val.split('.');
            return `${p[2]}-${p[1]}-${p[0]}`;
        }
        return val;
    }

    // Fatura Verisini Topla
    function getInvoicePayload() {
        const getValTrim = (selector) => {
            const el = $(selector);
            if (!el.length) return null;
            const v = el.val();
            return (typeof v === 'string') ? (v.trim() || null) : (v ? (String(v).trim() || null) : null);
        };
        const getVal = (selector) => {
            const el = $(selector);
            if (!el.length) return null;
            return el.val() || null;
        };

        const header = {
            ettn: getVal('#ettn'),
            fatura_no: getValTrim('#fatura_no'),
            seri_no: getVal('#seri_no'),
            cari_id: getVal('#selectCari'),
            alici_vkn_tckn: getValTrim('#alici_vkn_tckn') || '',
            alici_unvan: getValTrim('#alici_unvan') || '',
            belge_turu: getVal('#belge_turu') || 'EARSIV',
            fatura_profili: getVal('#fatura_profili') || 'EARSIVFATURA',
            fatura_tipi: getVal('#fatura_tipi') || 'SATIS',
            alici_posta_kutusu: getVal('#alici_posta_kutusu'),
            alici_vergi_dairesi: getValTrim('#alici_vergi_dairesi') || '',
            fatura_tarihi: parseDateForPayload(getVal('#fatura_tarihi')),
            duzenleme_saati: getValTrim('#duzenleme_saati'),
            vade_tarihi: parseDateForPayload(getVal('#vade_tarihi')),
            alici_adres: getValTrim('#alici_adres') || '',
            alici_ilce: getVal('#alici_ilce') || '',
            alici_il: getVal('#alici_il') || '',
            alici_ulke: getVal('#alici_ulke') || 'Türkiye',
            alici_eposta: getValTrim('#alici_eposta'),
            alici_telefon: getValTrim('#alici_telefon'),
            alici_web: getValTrim('#alici_web'),
            alici_tapdk_no: getValTrim('#alici_tapdk_no'),
            tapdk_no_gonderen: getValTrim('#tapdk_no_gonderen'),
            ozel_alan_1: getValTrim('#ozel_alan_1'),
            odeme_sekli: getVal('#odeme_sekli'),
            odeme_kanali: getValTrim('#odeme_kanali'),
            odeme_hesap_no: getValTrim('#odeme_hesap_no'),
            para_birimi: getVal('#para_birimi') || 'TRY',
            doviz_kuru: getVal('#doviz_kuru') || '1.0000',
            iade_fatura_no: getValTrim('#iade_fatura_no'),
            iade_fatura_tarihi: parseDateForPayload(getVal('#iade_fatura_tarihi')),
            notlar: (function() {
                const notlarEl = $('#notlar');
                if (!notlarEl.length) return '';
                if (typeof $.fn.summernote !== 'undefined' && notlarEl.hasClass('summernote')) {
                    try {
                        const code = notlarEl.summernote('code');
                        return (typeof code === 'string') ? code.trim() : '';
                    } catch (e) {
                        return (notlarEl.val() || '').trim();
                    }
                }
                return (notlarEl.val() || '').trim();
            })()
        };

        const lines = [];
        $('.kalem-row').each(function() {
            const row = $(this);
            const ekVergiler = {};
            row.find('.kalem-ek-vergi-oran').each(function() {
                const code = $(this).data('tax-code');
                const rate = $(this).val();
                if (code) {
                    ekVergiler[code] = rate || '0';
                }
            });

            const adVal = row.find('.kalem-ad').val();
            const istisnaAciklamaVal = row.find('.kalem-istisna-aciklama').val();

            lines.push({
                urun_hizmet_adi: (typeof adVal === 'string') ? adVal.trim() : (adVal ? String(adVal).trim() : ''),
                miktar: row.find('.kalem-miktar').val() || '1',
                birim: row.find('.kalem-birim').val() || 'C62',
                birim_fiyat: row.find('.kalem-fiyat').val() || '0',
                iskonto_orani: row.find('.kalem-iskonto').val() || '0',
                kdv_orani: row.find('.kalem-kdv').val() || '20',
                tevkifat_kodu: (row.find('.kalem-tevkifat').val() || '').split('|')[0] || null,
                tevkifat_orani: (row.find('.kalem-tevkifat').val() || '').split('|')[1] || '0',
                istisna_kodu: row.find('.kalem-istisna').val() || null,
                istisna_aciklama: (typeof istisnaAciklamaVal === 'string' && istisnaAciklamaVal.trim()) ? istisnaAciklamaVal.trim() : null,
                ek_vergiler: ekVergiler
            });
        });

        const invoice_id = $('#editInvoiceId').val() || null;
        return { invoice_id, header, lines };
    }

    // Taslak Kaydet
    $('#btnTaslakKaydet').on('click', function() {
        const payload = getInvoicePayload();
        if (!payload.header.alici_vkn_tckn || !payload.header.alici_unvan) {
            Swal.fire('Uyarı', 'Lütfen Alıcı VKN ve Unvan bilgilerini doldurun.', 'warning');
            return;
        }

        Swal.showLoading();
        fetch('api/efatura-api.php?action=save_draft', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire({
                    title: 'Taslak Kaydedildi',
                    text: 'Fatura başarıyla taslak olarak kaydedildi.',
                    icon: 'success',
                    confirmButtonText: 'Taslak Faturalara Git'
                }).then(() => {
                    window.location.href = 'index.php?p=efatura/taslak-list';
                });
            } else {
                Swal.fire('Hata', res.message, 'error');
            }
        });
    });

    // Kaydet ve Gönder
    $('#btnGonderDirect').on('click', function() {
        const payload = getInvoicePayload();
        if (!payload.header.alici_vkn_tckn || !payload.header.alici_unvan) {
            Swal.fire('Uyarı', 'Lütfen Alıcı VKN ve Unvan bilgilerini doldurun.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Fatura Gönderilsin mi?',
            text: 'Fatura kaydedilip EDM Bilişim & GİB sistemine iletilecektir.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, Gönder',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                fetch('api/efatura-api.php?action=save_draft', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(resDraft => {
                    if (resDraft.status === 'success' && resDraft.encrypted_id) {
                        fetch('api/efatura-api.php?action=send_invoice', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `invoice_id=${encodeURIComponent(resDraft.encrypted_id)}`
                        })
                        .then(res => res.json())
                        .then(resSend => {
                            if (resSend.status === 'success') {
                                Swal.fire({
                                    title: 'Fatura Başarıyla Gönderildi!',
                                    text: 'Fatura Numarası: ' + (resSend.fatura_no || '-'),
                                    icon: 'success',
                                    confirmButtonText: 'Fatura Listesine Git'
                                }).then(() => {
                                    window.location.href = 'index.php?p=efatura/giden-list';
                                });
                            } else {
                                Swal.fire('Taslak Kaydedildi ancak Gönderim Hatası', resSend.message, 'warning');
                            }
                        });
                    } else {
                        Swal.fire('Hata', resDraft.message, 'error');
                    }
                });
            }
        });
    });
});
</script>
