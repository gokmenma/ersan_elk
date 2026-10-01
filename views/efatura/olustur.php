<?php
use App\Config\EdmConfig;
use App\Core\Db;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Yeni Fatura Düzenle';

$firmId = (int)($_SESSION['firm_id'] ?? 1);
$db = (new Db())->getConnection();

// Aktif Cari Listesini Çek
$cariStmt = $db->prepare("SELECT id, CariAdi, Telefon, Email, firma, Adres, notlar FROM cari WHERE silinme_tarihi IS NULL ORDER BY CariAdi ASC");
$cariStmt->execute();
$cariler = $cariStmt->fetchAll(PDO::FETCH_ASSOC);

$unitCodes = EdmConfig::getUnitCodes();
$kdvOranlari = [
    '20' => '%20',
    '10' => '%10',
    '1'  => '%1',
    '0'  => '%0'
];
?>

<style>
/* Kurumsal E-Fatura Temiz Form Stilleri */
.invoice-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
}

.invoice-card-header {
    background-color: #fafbfc;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 20px;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.invoice-card-body {
    padding: 20px;
}

.form-group-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
    display: block;
}

.form-control-custom {
    height: 40px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.875rem;
    padding: 8px 12px;
    color: #1e293b;
    background-color: #ffffff;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control-custom:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    outline: 0;
}

/* Select2 Standart Yükseklik ve Temiz Görünüm */
.select2-container--default .select2-selection--single {
    height: 40px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    background-color: #ffffff !important;
    display: flex !important;
    align-items: center !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 40px !important;
    padding-left: 12px !important;
    color: #1e293b !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px !important;
    right: 8px !important;
}

.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12) !important;
}

/* Kalemler Tablosu */
.items-table {
    margin-bottom: 0;
    width: 100%;
}

.items-table thead th {
    background-color: #f1f5f9;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 12px;
    border-bottom: 2px solid #cbd5e1;
}

.items-table tbody td {
    padding: 8px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}

.items-table input.form-control-custom {
    height: 36px;
    font-size: 0.85rem;
    padding: 6px 10px;
}

.items-table .select2-container--default .select2-selection--single {
    height: 36px !important;
}

.items-table .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 36px !important;
    font-size: 0.85rem !important;
    padding-left: 8px !important;
}

.items-table .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 34px !important;
}

/* Finansal Özet Tablosu */
.summary-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 0.875rem;
    color: #475569;
}

.summary-item.grand-total {
    border-top: 2px dashed #cbd5e1;
    margin-top: 8px;
    padding-top: 12px;
    font-size: 1.25rem;
    font-weight: 700;
    color: #0f172a;
}

/* VKN Sorgulama Butonu */
.vkn-input-group {
    display: flex;
    gap: 6px;
}

.vkn-input-group input {
    flex: 1;
}

.vkn-input-group button {
    height: 40px;
    padding: 0 16px;
    font-weight: 600;
    border-radius: 8px;
}
</style>

<div class="container-fluid pb-5">
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Üst Sayfa Başlığı ve Aksiyonlar -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php?p=efatura/giden-list" class="btn btn-light border btn-sm p-2 rounded-circle" title="Geri Dön">
                <i class="bx bx-arrow-back fs-5"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-dark">Yeni Fatura Düzenle</h4>
                <small class="text-muted">E-Fatura & E-Arşiv Belgesi Oluşturma ve EDM İletimi</small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div id="mukellefDurumuBadge">
                <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold">
                    <i class="bx bx-info-circle me-1"></i> Mükellefiyet Kontrolü Bekleniyor
                </span>
            </div>
            <button type="button" class="btn btn-light border px-3 fw-semibold" id="btnTaslakKaydet">
                <i class="bx bx-save me-1"></i> Taslak Kaydet
            </button>
            <button type="button" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnGonderDirect">
                <i class="bx bx-send me-1"></i> Kaydet ve Gönder
            </button>
        </div>
    </div>

    <form id="formFaturaOlustur">
        <!-- 2 Sütunlu Üst Bilgiler -->
        <div class="row g-4 mb-4">
            <!-- 1. Sütun: Müşteri & Alıcı Bilgileri -->
            <div class="col-lg-6">
                <div class="invoice-card h-100 mb-0">
                    <div class="invoice-card-header">
                        <span class="fw-bold text-dark"><i class="bx bx-user text-primary me-2"></i>Müşteri / Alıcı Bilgileri</span>
                        <span class="text-muted small">Cari ve vergi detayları</span>
                    </div>
                    <div class="invoice-card-body">
                        <div class="row g-3">
                            <!-- Cari Seçimi -->
                            <div class="col-12">
                                <label class="form-group-label">Kayıtlı Cari Hesap <span class="text-muted fw-normal">(Opsiyonel)</span></label>
                                <select class="form-select select2" id="selectCari" style="width: 100%;">
                                    <option value="">-- Kayıtlı Carilerden Seçin veya Doğrudan VKN Girin --</option>
                                    <?php foreach ($cariler as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['CariAdi'] . (!empty($c['Telefon']) ? ' (' . $c['Telefon'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- VKN / TCKN & Sorgula -->
                            <div class="col-md-6">
                                <label class="form-group-label">VKN / TCKN <span class="text-danger">*</span></label>
                                <div class="vkn-input-group">
                                    <input type="text" class="form-control form-control-custom fw-bold" id="alici_vkn_tckn" maxlength="11" placeholder="10 veya 11 Haneli" required>
                                    <button type="button" class="btn btn-outline-primary" id="btnSorgulaVkn" title="Mükellefiyet Sorgula">
                                        <i class="bx bx-search"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Vergi Dairesi -->
                            <div class="col-md-6">
                                <label class="form-group-label">Vergi Dairesi</label>
                                <input type="text" class="form-control form-control-custom" id="alici_vergi_dairesi" placeholder="Vergi Dairesi">
                            </div>

                            <!-- Alıcı Ünvanı -->
                            <div class="col-12">
                                <label class="form-group-label">Alıcı / Müşteri Ünvanı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-custom fw-semibold" id="alici_unvan" placeholder="Firma Ünvanı veya Ad Soyad" required>
                            </div>

                            <!-- Adres -->
                            <div class="col-12">
                                <label class="form-group-label">Adres</label>
                                <input type="text" class="form-control form-control-custom" id="alici_adres" placeholder="Açık adres...">
                            </div>

                            <!-- İlçe ve İl -->
                            <div class="col-md-6">
                                <label class="form-group-label">İlçe</label>
                                <input type="text" class="form-control form-control-custom" id="alici_ilce" placeholder="İlçe">
                            </div>

                            <div class="col-md-6">
                                <label class="form-group-label">İl</label>
                                <input type="text" class="form-control form-control-custom" id="alici_il" value="Kayseri" placeholder="İl">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Sütun: Belge & Fatura Bilgileri -->
            <div class="col-lg-6">
                <div class="invoice-card h-100 mb-0">
                    <div class="invoice-card-header">
                        <span class="fw-bold text-dark"><i class="bx bx-file text-primary me-2"></i>Fatura & Belge Detayları</span>
                        <span class="text-muted small">Tarih, profil ve tip seçimi</span>
                    </div>
                    <div class="invoice-card-body">
                        <div class="row g-3">
                            <!-- Belge Türü -->
                            <div class="col-md-6">
                                <label class="form-group-label">Belge Türü <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="belge_turu" style="width: 100%;">
                                    <option value="EARSIV" selected>E-Arşiv Fatura</option>
                                    <option value="EFATURA">E-Fatura</option>
                                </select>
                            </div>

                            <!-- Fatura Profili -->
                            <div class="col-md-6">
                                <label class="form-group-label">Fatura Senaryosu / Profil <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="fatura_profili" style="width: 100%;">
                                    <option value="EARSIVFATURA" selected>E-Arşiv Fatura</option>
                                    <option value="TICARIFATURA">Ticari Fatura</option>
                                    <option value="TEMELFATURA">Temel Fatura</option>
                                    <option value="KAMU">Kamu Faturası</option>
                                    <option value="IHRACAT">İhracat Faturası</option>
                                </select>
                            </div>

                            <!-- Fatura Tipi -->
                            <div class="col-md-6">
                                <label class="form-group-label">Fatura Tipi <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="fatura_tipi" style="width: 100%;">
                                    <option value="SATIS" selected>SATIS (Satış Faturası)</option>
                                    <option value="IADE">IADE (İade Faturası)</option>
                                    <option value="TEVKIFAT">TEVKIFAT (KDV Tevkifatlı)</option>
                                    <option value="ISTISNA">ISTISNA (KDV İstisnalı)</option>
                                    <option value="OZELMATRAH">OZELMATRAH (Özel Matrah)</option>
                                </select>
                            </div>

                            <!-- Para Birimi -->
                            <div class="col-md-6">
                                <label class="form-group-label">Para Birimi</label>
                                <select class="form-select select2" id="para_birimi" style="width: 100%;">
                                    <option value="TRY" selected>TRY - Türk Lirası (₺)</option>
                                    <option value="USD">USD - Amerikan Doları ($)</option>
                                    <option value="EUR">EUR - Euro (€)</option>
                                </select>
                            </div>

                            <!-- Fatura Tarihi -->
                            <div class="col-md-6">
                                <label class="form-group-label">Fatura Tarihi <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-custom" id="fatura_tarihi" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <!-- Vade Tarihi -->
                            <div class="col-md-6">
                                <label class="form-group-label">Vade Tarihi</label>
                                <input type="date" class="form-control form-control-custom" id="vade_tarihi">
                            </div>

                            <!-- Posta Kutusu Alias (e-Fatura ise görünür) -->
                            <div class="col-12" id="divPostaKutusu" style="display: none;">
                                <label class="form-group-label">Alıcı GİB Posta Kutusu (PK Alias) <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="alici_posta_kutusu" style="width: 100%;">
                                    <option value="urn:mail:defaultpk">urn:mail:defaultpk (Varsayılan)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mal ve Hizmet Kalemleri Kartı -->
        <div class="invoice-card mb-4">
            <div class="invoice-card-header">
                <span class="fw-bold text-dark"><i class="bx bx-list-ul text-primary me-2"></i>Mal & Hizmet Kalemleri</span>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-3" id="btnSatirEkle">
                    <i class="bx bx-plus me-1"></i> Kalem Ekle
                </button>
            </div>
            <div class="p-0 table-responsive">
                <table class="table items-table" id="tblKalemler">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="text-center">#</th>
                            <th style="min-width: 260px;">Mal / Hizmet Açıklaması <span class="text-danger">*</span></th>
                            <th style="width: 100px;" class="text-end">Miktar</th>
                            <th style="width: 130px;">Birim</th>
                            <th style="width: 130px;" class="text-end">Birim Fiyat</th>
                            <th style="width: 90px;" class="text-end">İskonto %</th>
                            <th style="width: 100px;">KDV %</th>
                            <th style="width: 140px;" class="text-end">Satır Tutarı</th>
                            <th style="width: 40px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="kalemlerContainer">
                        <!-- JS ile Dinamik Satırlar -->
                    </tbody>
                </table>
            </div>

            <!-- Alt Toplamlar & Notlar Alanı -->
            <div class="p-4 bg-white border-top">
                <div class="row g-4 justify-content-between">
                    <div class="col-lg-6">
                        <label class="form-group-label">Fatura Notu / Açıklama</label>
                        <textarea class="form-control form-control-custom" id="notlar" rows="4" style="height: auto;" placeholder="Fatura üzerinde basılacak banka IBAN bilgileri, sipariş/sözleşme referansları vb..."></textarea>
                    </div>

                    <div class="col-lg-5">
                        <div class="summary-box">
                            <div class="summary-item">
                                <span>Mal / Hizmet Toplamı:</span>
                                <span class="fw-bold text-dark" id="lblSatirToplami">0,00 ₺</span>
                            </div>
                            <div class="summary-item">
                                <span class="text-danger">İskonto Toplamı (-):</span>
                                <span class="fw-bold text-danger" id="lblIskontoToplami">0,00 ₺</span>
                            </div>
                            <div class="summary-item">
                                <span>KDV Matrahı:</span>
                                <span class="fw-bold text-dark" id="lblKdvMatrahi">0,00 ₺</span>
                            </div>
                            <div class="summary-item">
                                <span class="text-success">Hesaplanan KDV:</span>
                                <span class="fw-bold text-success" id="lblHesaplananKdv">0,00 ₺</span>
                            </div>
                            <div class="summary-item grand-total">
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
const CARI_DATA = <?= json_encode($cariler, JSON_UNESCAPED_UNICODE) ?>;
const UNIT_OPTIONS = <?= json_encode($unitCodes, JSON_UNESCAPED_UNICODE) ?>;
const KDV_OPTIONS = <?= json_encode($kdvOranlari, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function() {
    let rowCounter = 0;

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

        let unitOptionsHtml = '';
        for (const [code, name] of Object.entries(UNIT_OPTIONS)) {
            const sel = (data.birim === code || (!data.birim && code === 'C62')) ? 'selected' : '';
            unitOptionsHtml += `<option value="${code}" ${sel}>${name}</option>`;
        }

        let kdvOptionsHtml = '';
        for (const [rate, label] of Object.entries(KDV_OPTIONS)) {
            const sel = (data.kdv_orani == rate || (!data.kdv_orani && rate === '20')) ? 'selected' : '';
            kdvOptionsHtml += `<option value="${rate}" ${sel}>${label}</option>`;
        }

        const rowHtml = `
            <tr id="row_${rowCounter}" class="kalem-row">
                <td class="text-center align-middle" style="width: 55px;">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <i class="bx bx-grid-vertical text-muted drag-handle fs-5" style="cursor: grab;" title="Sıralamak için sürükleyip bırakın"></i>
                        <span class="row-number fw-bold text-dark"></span>
                    </div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-custom kalem-ad" value="${data.urun_hizmet_adi || ''}" placeholder="Ürün / Hizmet tanımı..." required>
                </td>
                <td>
                    <input type="number" step="0.0001" min="0.0001" class="form-control form-control-custom kalem-miktar text-end fw-semibold" value="${data.miktar || 1}">
                </td>
                <td>
                    <select class="form-select select2-item kalem-birim" style="width:100%">${unitOptionsHtml}</select>
                </td>
                <td>
                    <input type="number" step="0.01" min="0" class="form-control form-control-custom kalem-fiyat text-end fw-semibold" value="${data.birim_fiyat || 0}">
                </td>
                <td>
                    <input type="number" step="0.1" min="0" max="100" class="form-control form-control-custom kalem-iskonto text-end" value="${data.iskonto_orani || 0}">
                </td>
                <td>
                    <select class="form-select select2-item kalem-kdv" style="width:100%">${kdvOptionsHtml}</select>
                </td>
                <td class="text-end fw-bold kalem-toplam text-dark">0,00 ₺</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-satir-sil" title="Sil">
                        <i class="bx bx-trash fs-5"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#kalemlerContainer').append(rowHtml);

        $(`#row_${rowCounter} .select2-item`).select2({
            dropdownAutoWidth: true,
            width: '100%'
        });

        updateRowNumbers();
        calculateTotals();
    }

    // İlk satırı yükle
    addRow();

    $('#btnSatirEkle').on('click', function() { addRow(); });

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

    // Tutar Hesaplama
    function calculateTotals() {
        let satirToplami = 0;
        let iskontoToplami = 0;
        let kdvMatrahi = 0;
        let hesaplananKdv = 0;

        $('.kalem-row').each(function() {
            const miktar = parseFloat($(this).find('.kalem-miktar').val()) || 0;
            const fiyat = parseFloat($(this).find('.kalem-fiyat').val()) || 0;
            const iskontoOrani = parseFloat($(this).find('.kalem-iskonto').val()) || 0;
            const kdvOrani = parseFloat($(this).find('.kalem-kdv').val()) || 0;

            const hamTutar = miktar * fiyat;
            const iskonto = hamTutar * (iskontoOrani / 100);
            const net = hamTutar - iskonto;
            const kdv = net * (kdvOrani / 100);
            const toplam = net + kdv;

            satirToplami += hamTutar;
            iskontoToplami += iskonto;
            kdvMatrahi += net;
            hesaplananKdv += kdv;

            $(this).find('.kalem-toplam').text(toplam.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺');
        });

        const odenecek = kdvMatrahi + hesaplananKdv;

        $('#lblSatirToplami').text(satirToplami.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺');
        $('#lblIskontoToplami').text(iskontoToplami.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺');
        $('#lblKdvMatrahi').text(kdvMatrahi.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺');
        $('#lblHesaplananKdv').text(hesaplananKdv.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺');
        $('#lblOdenecekTutar').text(odenecek.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺');
    }

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
                        aliasSelect.append(new Option('urn:mail:defaultpk', 'urn:mail:defaultpk'));
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
            fatura_tarihi: $('#fatura_tarihi').val(),
            vade_tarihi: $('#vade_tarihi').val() || null,
            alici_adres: $('#alici_adres').val().trim(),
            alici_ilce: $('#alici_ilce').val().trim(),
            alici_il: $('#alici_il').val().trim(),
            notlar: $('#notlar').val().trim()
        };

        const lines = [];
        $('.kalem-row').each(function() {
            lines.push({
                urun_hizmet_adi: $(this).find('.kalem-ad').val().trim(),
                miktar: parseFloat($(this).find('.kalem-miktar').val()) || 1,
                birim: $(this).find('.kalem-birim').val(),
                birim_fiyat: parseFloat($(this).find('.kalem-fiyat').val()) || 0,
                iskonto_orani: parseFloat($(this).find('.kalem-iskonto').val()) || 0,
                kdv_orani: parseFloat($(this).find('.kalem-kdv').val()) || 20
            });
        });

        return { header, lines };
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
                    confirmButtonText: 'Fatura Listesine Git'
                }).then(() => {
                    window.location.href = 'index.php?p=efatura/giden-list';
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
