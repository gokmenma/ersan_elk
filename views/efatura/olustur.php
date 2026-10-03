<?php
\App\Service\Gate::authorizeOrDie('efatura/olustur');
use App\Config\EdmConfig;
use App\Helper\Form;
use App\Helper\EInvoiceSecurity;
use App\Service\InvoiceValidationService;
use App\Helper\Security;
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

$editInvoice = null;
$editInvoiceEncryptedId = $_GET['id'] ?? '';
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
            unset($editInvoice['id'], $editInvoice['olusturan_user_id']);
            $title = 'Taslak Faturayı Düzenle';
        }
    }
}

// Kalem tablosu için düz HTML select şablonları (Floating label olmadan, tablo içine uygun)
$unitSelectHtml = '<select class="form-select form-select-sm select2-item kalem-birim">';
foreach ($unitCodes as $k => $v) {
    $unitSelectHtml .= '<option value="' . htmlspecialchars($k) . '"' . ($k === 'C62' ? ' selected' : '') . '>' . htmlspecialchars($v) . '</option>';
}
$unitSelectHtml .= '</select>';

$vatSelectHtml = '<select class="form-select form-select-sm select2-item kalem-kdv">';
foreach ($kdvOranlari as $k => $v) {
    $vatSelectHtml .= '<option value="' . htmlspecialchars($k) . '"' . ($k === '20' ? ' selected' : '') . '>' . htmlspecialchars($v) . '</option>';
}
$vatSelectHtml .= '</select>';

$withholdingSelectHtml = '<select class="form-select form-select-sm select2-item kalem-tevkifat mb-1">';
foreach ($withholdingOptions as $k => $v) {
    $withholdingSelectHtml .= '<option value="' . htmlspecialchars($k) . '">' . htmlspecialchars($v) . '</option>';
}
$withholdingSelectHtml .= '</select>';

$exemptionSelectHtml = '<select class="form-select form-select-sm select2-item kalem-istisna mb-1">';
foreach ($exemptionOptions as $k => $v) {
    $exemptionSelectHtml .= '<option value="' . htmlspecialchars($k) . '">' . htmlspecialchars($v) . '</option>';
}
$exemptionSelectHtml .= '</select>';
?>
<meta name="efatura-csrf" content="<?= htmlspecialchars(\App\Helper\Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">
<script src="views/efatura/js/transport.js"></script>

<style>
/* Fatura Düzenleme Tablo ve Kart Stilleri */
.items-table {
    margin-bottom: 0;
    width: 100%;
}
.invoice-items-table-wrap {
    padding: 0.75rem 1rem 1rem;
}
.invoice-items-table-frame {
    border: 1px solid #dbe3ee;
    border-radius: 10px;
    overflow: hidden;
}
.items-table thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 10px;
    border-bottom: 2px solid #e2e8f0;
}
.items-table tbody td {
    padding: 8px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.items-table .form-control-sm,
.items-table .form-select-sm {
    height: 36px;
    font-size: 0.85rem;
}
.summary-card {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px 20px;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 0.875rem;
    color: #475569;
}
.summary-row.grand-total {
    border-top: 2px dashed #cbd5e1;
    margin-top: 10px;
    padding-top: 12px;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
}
.drag-handle {
    cursor: grab;
}
.drag-handle:active {
    cursor: grabbing;
}
.tax-detail-fields {
    display: none;
}
.tax-detail-fields.is-open {
    display: block;
}
.invoice-item-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.invoice-not-editor .note-editor {
    margin-bottom: 0;
    border-color: #cbd5e1;
}
</style>

<div class="container-fluid pb-5">
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Üst Sayfa Başlığı ve Aksiyon Araç Çubuğu -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php?p=efatura/giden-list" class="btn btn-outline-secondary btn-sm p-2 rounded-circle" title="Geri Dön">
                <i class="bx bx-arrow-back font-size-18 align-middle"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-dark"><?= !empty($editInvoice) ? 'Taslak Faturayı Düzenle' : 'Yeni Fatura Düzenle' ?></h4>
                <small class="text-muted"><?= !empty($editInvoice) ? 'Taslak faturayı güncelleyip kaydedin veya doğrudan GİB\'e gönderin' : 'E-Fatura & E-Arşiv Belgesi Oluşturma ve EDM İletimi' ?></small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div id="mukellefDurumuBadge">
                <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold">
                    <i class="bx bx-info-circle me-1"></i> Mükellefiyet Kontrolü Bekleniyor
                </span>
            </div>
            <button type="button" class="btn btn-light border px-3 fw-semibold shadow-sm" id="btnTaslakKaydet">
                <i class="bx bx-save me-1 font-size-16 align-middle"></i> <?= !empty($editInvoice) ? 'Değişiklikleri Kaydet' : 'Taslak Kaydet' ?>
            </button>
            <button type="button" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnGonderDirect">
                <i class="bx bx-send me-1 font-size-16 align-middle"></i> Kaydet ve Gönder
            </button>
        </div>
    </div>

    <form id="formFaturaOlustur">
        <input type="hidden" id="editInvoiceId" value="<?= !empty($editInvoice) ? htmlspecialchars($editInvoiceEncryptedId, ENT_QUOTES, 'UTF-8') : '' ?>">
        
        <!-- 2 Sütunlu Üst Bilgiler -->
        <div class="row g-4 mb-4">
            <!-- 1. Sütun: Müşteri & Alıcı Bilgileri -->
            <div class="col-lg-6">
                <div class="card shadow-sm border h-100 mb-0">
                    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bx bx-user text-primary me-2 font-size-18 align-middle"></i>Müşteri / Alıcı Bilgileri
                        </h5>
                        <span class="text-muted small">Cari ve vergi detayları</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <!-- Cari Seçimi -->
                            <div class="col-12">
                                <?= Form::FormSelect2('selectCari', $cariOptions, '', 'Kayıtlı Cari Hesap (Opsiyonel)', 'users') ?>
                            </div>

                            <!-- VKN / TCKN & Sorgula Butonu -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="form-floating form-floating-custom flex-grow-1">
                                        <input type="text" class="form-control fw-bold" id="alici_vkn_tckn" name="alici_vkn_tckn" maxlength="11" placeholder="10 veya 11 Haneli" required>
                                        <label for="alici_vkn_tckn">VKN / TCKN <span class="text-danger">*</span></label>
                                        <div class="form-floating-icon">
                                            <i data-feather="hash"></i>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-primary px-3 d-flex align-items-center justify-content-center" id="btnSorgulaVkn" title="GİB Mükellefiyet Sorgula">
                                        <i class="bx bx-search font-size-18"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Vergi Dairesi -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'alici_vergi_dairesi', '', 'Vergi Dairesi', 'Vergi Dairesi', 'briefcase') ?>
                            </div>

                            <!-- Alıcı Ünvanı -->
                            <div class="col-12">
                                <?= Form::FormFloatInput('text', 'alici_unvan', '', 'Firma Ünvanı veya Ad Soyad', 'Alıcı / Müşteri Ünvanı *', 'user', 'form-control fw-semibold', true) ?>
                            </div>

                            <!-- Adres -->
                            <div class="col-12">
                                <?= Form::FormFloatInput('text', 'alici_adres', '', 'Açık adres...', 'Adres', 'map-pin') ?>
                            </div>

                            <!-- İlçe ve İl -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'alici_ilce', '', 'İlçe', 'İlçe', 'map') ?>
                            </div>

                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'alici_il', 'Kayseri', 'İl', 'İl', 'map') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Sütun: Belge & Fatura Bilgileri -->
            <div class="col-lg-6">
                <div class="card shadow-sm border h-100 mb-0">
                    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bx bx-file text-primary me-2 font-size-18 align-middle"></i>Fatura & Belge Detayları
                        </h5>
                        <span class="text-muted small">Tarih, profil ve tip seçimi</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <!-- Belge Türü -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2('belge_turu', ['EARSIV'=>'E-Arşiv','EFATURA'=>'e-Fatura'], 'EARSIV', 'Belge Türü *', 'file-text') ?>
                            </div>

                            <!-- Fatura Profili -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2('fatura_profili', ['EARSIVFATURA'=>'E-Arşiv','TICARIFATURA'=>'Ticari','TEMELFATURA'=>'Temel'], 'EARSIVFATURA', 'Fatura Senaryosu / Profil *', 'sliders') ?>
                            </div>

                            <!-- Fatura Tipi -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2('fatura_tipi', ['SATIS'=>'Satış','IADE'=>'İade','TEVKIFAT'=>'Tevkifat','ISTISNA'=>'İstisna'], 'SATIS', 'Fatura Tipi *', 'tag') ?>
                            </div>

                            <!-- Para Birimi -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2('para_birimi', ['TRY'=>'TRY (₺)','USD'=>'USD ($)','EUR'=>'EUR (€)'], 'TRY', 'Para Birimi', 'dollar-sign') ?>
                            </div>

                            <!-- Fatura Tarihi -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'fatura_tarihi', date('d.m.Y'), '', 'Fatura Tarihi *', 'calendar', 'form-control flatpickr', true) ?>
                            </div>

                            <!-- Vade Tarihi -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput('text', 'vade_tarihi', '', '', 'Vade Tarihi', 'calendar', 'form-control flatpickr') ?>
                            </div>

                            <!-- Posta Kutusu Alias (e-Fatura ise görünür) -->
                            <div class="col-12" id="divPostaKutusu" style="display: none;">
                                <?= Form::FormSelect2('alici_posta_kutusu', [''=>'Önce mükellef sorgulayın'], '', 'Alıcı GİB Posta Kutusu (PK Alias) *', 'mail') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ek Kur ve İade Bilgileri Kartı -->
        <div class="card shadow-sm border mb-4">
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <?= Form::FormFloatInput('number', 'doviz_kuru', '1', '', 'Döviz Kuru', 'trending-up', 'form-control', false, null, 'on', false, 'min="0.0001" step="0.0001"') ?>
                    </div>
                    <div class="col-md-4 iade-fields" style="display: none;">
                        <?= Form::FormFloatInput('text', 'iade_fatura_no', '', '', 'İade Edilen Fatura No', 'file-text', 'form-control', false, 50) ?>
                    </div>
                    <div class="col-md-4 iade-fields" style="display: none;">
                        <?= Form::FormFloatInput('text', 'iade_fatura_tarihi', '', '', 'İade Edilen Fatura Tarihi', 'calendar', 'form-control flatpickr') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mal ve Hizmet Kalemleri Kartı -->
        <div class="card shadow-sm border mb-4">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="bx bx-list-ul text-primary me-2 font-size-18 align-middle"></i>Mal & Hizmet Kalemleri
                </h5>
                <div class="invoice-item-actions" id="invoiceItemActions">
                    <button type="button" class="btn btn-sm btn-primary fw-semibold px-3 shadow-sm" id="btnSatirEkle">
                        <i class="bx bx-plus me-1 font-size-16 align-middle"></i> Kalem Ekle
                    </button>
                </div>
            </div>
            
            <div class="table-responsive invoice-items-table-wrap">
                <div class="invoice-items-table-frame">
                <table class="table items-table align-middle table-hover" id="tblKalemler">
                    <thead>
                        <tr>
                            <th style="width: 45px;" class="text-center">#</th>
                            <th style="min-width: 240px;">Mal / Hizmet Açıklaması <span class="text-danger">*</span></th>
                            <th style="width: 95px;" class="text-end">Miktar</th>
                            <th style="width: 125px;">Birim</th>
                            <th style="width: 120px;" class="text-end">Birim Fiyat</th>
                            <th style="width: 90px;" class="text-end">İskonto %</th>
                            <th style="width: 95px;">KDV %</th>
                            <th style="min-width: 220px;">Tevkifat / İstisna</th>
                            <th style="width: 130px;" class="text-end">Satır Tutarı</th>
                            <th style="width: 45px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="kalemlerContainer">
                        <!-- JS ile Dinamik Satırlar -->
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Alt Toplamlar & Notlar Alanı -->
            <div class="p-4 bg-white border-top">
                <div class="row g-4 justify-content-between">
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold text-muted small mb-2">Fatura Notu / Açıklama</label>
                        <div class="invoice-not-editor">
                            <textarea class="form-control" id="notlar" rows="4" placeholder="Fatura üzerinde basılacak banka IBAN bilgileri, sipariş/sözleşme referansları vb..."></textarea>
                        </div>
                    </div>

                    <div class="col-lg-5 col-xl-4">
                        <div class="summary-card">
                            <div class="summary-row">
                                <span>Mal / Hizmet Toplamı:</span>
                                <span class="fw-bold text-dark" id="lblSatirToplami">0,00 ₺</span>
                            </div>
                            <div class="summary-row">
                                <span class="text-danger">İskonto Toplamı (-):</span>
                                <span class="fw-bold text-danger" id="lblIskontoToplami">0,00 ₺</span>
                            </div>
                            <div class="summary-row">
                                <span>KDV Matrahı:</span>
                                <span class="fw-bold text-dark" id="lblKdvMatrahi">0,00 ₺</span>
                            </div>
                            <div class="summary-row">
                                <span class="text-success">Hesaplanan KDV:</span>
                                <span class="fw-bold text-success" id="lblHesaplananKdv">0,00 ₺</span>
                            </div>
                            <div class="summary-row">
                                <span>Tevkifat Tutarı (-):</span>
                                <span class="fw-bold text-dark" id="lblTevkifat">0,00 ₺</span>
                            </div>
                            <div class="summary-row grand-total">
                                <span>ÖDENECEK TOPLAM:</span>
                                <span class="text-primary" id="lblOdenecekTutar">0,00 ₺</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
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

document.addEventListener('DOMContentLoaded', function() {
    let rowCounter = 0;
    let calculationTimer;
    let calculationVersion = 0;

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

    // Sıra Numaralarını Baştan Sona Dinamik Güncelle
    function updateRowNumbers() {
        let idx = 1;
        $('#kalemlerContainer tr.kalem-row').each(function() {
            $(this).find('.row-number').text(idx++);
        });
    }

    // 2. Dinamik Kalem Satırı Ekleme
    function addRow(data = {}) {
        rowCounter++;

        const rowHtml = `
            <tr id="row_${rowCounter}" class="kalem-row">
                <td class="text-center align-middle" style="width: 45px;">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <i class="bx bx-grid-vertical text-muted drag-handle font-size-18" title="Sıralamak için sürükleyin"></i>
                        <span class="row-number fw-bold text-dark">${rowCounter}</span>
                    </div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm kalem-ad" value="" placeholder="Ürün / Hizmet tanımı..." required>
                </td>
                <td>
                    <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm kalem-miktar text-end fw-semibold" value="1">
                </td>
                <td>
                    ${UNIT_SELECT}
                </td>
                <td>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm kalem-fiyat text-end fw-semibold" value="0">
                </td>
                <td>
                    <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm kalem-iskonto text-end" value="0">
                </td>
                <td>
                    ${VAT_SELECT}
                </td>
                <td>
                    <div class="d-flex justify-content-end mb-2 kalem-tax-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-tax-detail-toggle" aria-expanded="false">
                            <i class="bx bx-receipt me-1"></i><span>Tevkifat / İstisna Ekle</span>
                        </button>
                    </div>
                    <div class="tax-detail-fields">
                        ${WITHHOLDING_SELECT}
                        ${EXEMPTION_SELECT}
                        <input type="text" class="form-control form-control-sm kalem-istisna-aciklama" placeholder="İstisna açıklaması">
                    </div>
                </td>
                <td class="text-end fw-bold kalem-toplam text-dark">0,00 ₺</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0 btn-satir-sil" title="Satırı Sil">
                        <i class="bx bx-trash font-size-18 align-middle"></i>
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
            setTaxDetailVisibility(row, true);
        }

        row.find('.select2-item').select2({
            dropdownAutoWidth: true,
            width: '100%'
        });

        updateRowNumbers();
        calculateTotals();
    }

    function setTaxDetailVisibility(row, visible) {
        row.find('.tax-detail-fields').toggleClass('is-open', visible);
        const button = row.find('.btn-tax-detail-toggle');
        button.attr('aria-expanded', visible ? 'true' : 'false')
            .toggleClass('btn-outline-primary', !visible)
            .toggleClass('btn-outline-danger', visible);
        button.find('span').text(visible ? 'Tevkifat / İstisnayı Kaldır' : 'Tevkifat / İstisna Ekle');
    }

    // Düzenleme modunda ise verileri forma yükle
    if (EDIT_DATA) {
        if (EDIT_DATA.cari_id) {
            $('#selectCari').val(EDIT_DATA.cari_id).trigger('change');
        }
        $('#alici_vkn_tckn').val(EDIT_DATA.alici_vkn_tckn || '');
        $('#alici_vergi_dairesi').val(EDIT_DATA.alici_vergi_dairesi || '');
        $('#alici_unvan').val(EDIT_DATA.alici_unvan || '');
        $('#alici_adres').val(EDIT_DATA.alici_adres || '');
        $('#alici_ilce').val(EDIT_DATA.alici_ilce || '');
        $('#alici_il').val(EDIT_DATA.alici_il || 'Kayseri');
        $('#belge_turu').val(EDIT_DATA.belge_turu || 'EARSIV').trigger('change');
        $('#fatura_profili').val(EDIT_DATA.fatura_profili || 'EARSIVFATURA').trigger('change');
        $('#fatura_tipi').val(EDIT_DATA.fatura_tipi || 'SATIS').trigger('change');
        $('#para_birimi').val(EDIT_DATA.para_birimi || 'TRY').trigger('change');
        
        if (EDIT_DATA.fatura_tarihi) {
            const parts = EDIT_DATA.fatura_tarihi.split('-');
            if (parts.length === 3) {
                $('#fatura_tarihi').val(`${parts[2]}.${parts[1]}.${parts[0]}`);
            } else {
                $('#fatura_tarihi').val(EDIT_DATA.fatura_tarihi);
            }
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

    $('#btnSatirEkle').on('click', function() { addRow(); });

    $('#kalemlerContainer').on('click', '.btn-tax-detail-toggle', function() {
        const row = $(this).closest('.kalem-row');
        const visible = !row.find('.tax-detail-fields').hasClass('is-open');
        if (!visible) {
            row.find('.kalem-tevkifat, .kalem-istisna').val('').trigger('change.select2');
            row.find('.kalem-istisna-aciklama').val('');
        }
        setTaxDetailVisibility(row, visible);
        calculateTotals();
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
                        lblTevkifat: 'tevkifat_tutari',
                        lblOdenecekTutar: 'odenecek_tutar'
                    };
                    for (const [id, field] of Object.entries(fields)) {
                        $('#' + id).text(money(result.data.header[field]));
                    }
                    if (Array.isArray(result.data.lines)) {
                        $('.kalem-row').each(function(index) {
                            if (result.data.lines[index]) {
                                $(this).find('.kalem-toplam').text(money(result.data.lines[index].satir_toplami));
                            }
                        });
                    }
                }
            } catch (e) {
                console.error('Hesaplama hatası:', e);
            }
        }, 150);
    }

    $('#para_birimi').on('change', calculateTotals);
    $('#belge_turu').on('change', function() {
        $('#fatura_profili').val(this.value === 'EARSIV' ? 'EARSIVFATURA' : 'TICARIFATURA').trigger('change');
    });
    $('#fatura_tipi').on('change', function() {
        $('.iade-fields').toggle(this.value === 'IADE');
        if (this.value === 'IADE' && $('#belge_turu').val() === 'EFATURA') {
            $('#fatura_profili').val('TEMELFATURA').trigger('change');
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
            $('#alici_unvan').val(selected.CariAdi || selected.firma || '');
            if (selected.Adres) $('#alici_adres').val(selected.Adres);
            
            const vknMatch = (selected.notlar || selected.CariAdi || '').match(/\b\d{10,11}\b/);
            if (vknMatch) {
                $('#alici_vkn_tckn').val(vknMatch[0]);
                checkTaxpayer(vknMatch[0]);
            }
        }
    });

    // VKN Mükellefiyet Kontrolü
    function checkTaxpayer(vkn) {
        if (!vkn || (vkn.length !== 10 && vkn.length !== 11)) return;

        $('#mukellefDurumuBadge').html(`
            <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">
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
                        <span class="badge bg-success px-3 py-2 fw-semibold">
                            <i class="bx bx-check-circle me-1"></i> E-Fatura Mükellefi (GİB)
                        </span>
                    `);
                    $('#belge_turu').val('EFATURA').trigger('change');
                    $('#fatura_profili').val('TICARIFATURA').trigger('change');
                    $('#divPostaKutusu').slideDown(200);

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
                    aliasSelect.trigger('change');
                } else {
                    $('#mukellefDurumuBadge').html(`
                        <span class="badge bg-info px-3 py-2 fw-semibold">
                            <i class="bx bx-info-circle me-1"></i> E-Arşiv Fatura Alıcısı
                        </span>
                    `);
                    $('#belge_turu').val('EARSIV').trigger('change');
                    $('#fatura_profili').val('EARSIVFATURA').trigger('change');
                    $('#divPostaKutusu').slideUp(200);
                }
            } else {
                $('#mukellefDurumuBadge').html(`
                    <span class="badge bg-secondary px-3 py-2 fw-semibold">Sorgulama Yapılamadı</span>
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
        if (!val) return null;
        val = val.trim();
        if (/^\d{2}\.\d{2}\.\d{4}$/.test(val)) {
            const p = val.split('.');
            return `${p[2]}-${p[1]}-${p[0]}`;
        }
        return val;
    }

    // Fatura Verisini Topla
    function getInvoicePayload() {
        const header = {
            cari_id: $('#selectCari').val() || null,
            alici_vkn_tckn: $('#alici_vkn_tckn').val().trim(),
            alici_unvan: $('#alici_unvan').val().trim(),
            belge_turu: $('#belge_turu').val(),
            fatura_profili: $('#fatura_profili').val(),
            fatura_tipi: $('#fatura_tipi').val(),
            alici_posta_kutusu: $('#alici_posta_kutusu').val() || null,
            alici_vergi_dairesi: $('#alici_vergi_dairesi').val().trim(),
            fatura_tarihi: parseDateForPayload($('#fatura_tarihi').val()),
            vade_tarihi: parseDateForPayload($('#vade_tarihi').val()),
            alici_adres: $('#alici_adres').val().trim(),
            alici_ilce: $('#alici_ilce').val().trim(),
            alici_il: $('#alici_il').val().trim(),
            para_birimi: $('#para_birimi').val(),
            doviz_kuru: $('#doviz_kuru').val(),
            iade_fatura_no: $('#iade_fatura_no').val().trim() || null,
            iade_fatura_tarihi: parseDateForPayload($('#iade_fatura_tarihi').val()),
            notlar: typeof $.fn.summernote !== 'undefined' ? $('#notlar').summernote('code').trim() : $('#notlar').val().trim()
        };

        const lines = [];
        $('.kalem-row').each(function() {
            lines.push({
                urun_hizmet_adi: $(this).find('.kalem-ad').val().trim(),
                miktar: $(this).find('.kalem-miktar').val() || '1',
                birim: $(this).find('.kalem-birim').val() || 'C62',
                birim_fiyat: $(this).find('.kalem-fiyat').val() || '0',
                iskonto_orani: $(this).find('.kalem-iskonto').val() || '0',
                kdv_orani: $(this).find('.kalem-kdv').val() || '20',
                tevkifat_kodu: ($(this).find('.kalem-tevkifat').val() || '').split('|')[0] || null,
                tevkifat_orani: ($(this).find('.kalem-tevkifat').val() || '').split('|')[1] || '0',
                istisna_kodu: $(this).find('.kalem-istisna').val() || null,
                istisna_aciklama: $(this).find('.kalem-istisna-aciklama').val().trim() || null
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
