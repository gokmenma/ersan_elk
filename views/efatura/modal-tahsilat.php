<?php
use App\Helper\Form;
use App\Model\KasaModel;

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 1);
$kasaModel = new KasaModel();
$kasalarList = $kasaModel->getKasaListByOwner($firmId);

$kasaOptions = ['' => '-- Hesap Seçiniz --'];
if (!empty($kasalarList)) {
    foreach ($kasalarList as $k) {
        $kasaOptions[$k->id] = $k->kasa_adi . (!empty($k->kasa_kodu) ? ' (' . $k->kasa_kodu . ')' : '') . ' (' . ($k->para_birimi ?: 'TRY') . ')';
    }
}

$tahsilatTipleri = [
    'nakit'       => 'Nakit',
    'banka'       => 'Banka / Havale - EFT',
    'kredi_karti' => 'Kredi Kartı',
    'cek'         => 'Çek',
    'senet'       => 'Senet',
    'diger'       => 'Diğer'
];
?>
<!-- Ödeme - Tahsilat Bilgileri Modalı -->
<div class="modal fade" id="modalTahsilatEkle" tabindex="-1" aria-labelledby="modalTahsilatEkleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header bg-white border-bottom px-4 py-3 align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 48px; height: 48px;">
                        <i class="bx bx-money-withdraw font-size-26"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-size-15 fw-bold text-dark mb-0" id="modalTahsilatEkleLabel">Ödeme - Tahsilat Bilgileri</h5>
                        <p class="text-muted font-size-12 mb-0">Fatura tahsilatını kasa veya banka hesabına anında işleyin</p>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <div class="modal-body p-4 bg-light-subtle">
                <!-- 1. Üst Fatura Özet Paneli -->
                <div class="card border-0 shadow-xs rounded-3 bg-white mb-3 overflow-hidden">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <!-- Müşteri Bilgisi -->
                            <div class="col-12 col-md-4 pe-md-3 border-end">
                                <span class="text-muted font-size-11 text-uppercase fw-semibold d-block mb-1">MÜŞTERİ / ALICI</span>
                                <h6 class="fw-bold text-dark font-size-13 text-truncate mb-0" id="tahsilatMusteriUnvan" title="">-</h6>
                                <span class="text-muted font-monospace font-size-11" id="tahsilatMusteriVkn">-</span>
                            </div>

                            <!-- Fatura Bilgileri ve Tutarlar (Geniş Alan) -->
                            <div class="col-12 col-md-8 ps-md-2">
                                <div class="row g-2 align-items-center">
                                    <!-- Fatura No -->
                                    <div class="col-6 col-sm-3">
                                        <span class="text-muted font-size-11 text-uppercase fw-semibold d-block mb-1">FATURA NO</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace font-size-12 px-2 py-1 text-truncate d-inline-block mw-100" id="tahsilatFaturaNo" title="">-</span>
                                    </div>

                                    <!-- Fatura Tutarı -->
                                    <div class="col-6 col-sm-3 text-sm-end">
                                        <span class="text-muted font-size-11 text-uppercase fw-semibold d-block mb-1">FATURA TUTARI</span>
                                        <span class="fw-bold text-dark font-monospace font-size-13 d-block text-truncate" id="tahsilatFaturaTutari">0,00 TRY</span>
                                    </div>

                                    <!-- Ödenen Tutar -->
                                    <div class="col-6 col-sm-3 text-sm-end">
                                        <span class="text-muted font-size-11 text-uppercase fw-semibold d-block mb-1">ÖDENEN</span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace font-size-12 px-2 py-1 d-inline-block text-truncate" id="tahsilatOdenenTutar">0,00 TRY</span>
                                    </div>

                                    <!-- Kalan Tutar -->
                                    <div class="col-6 col-sm-3 text-sm-end">
                                        <span class="text-muted font-size-11 text-uppercase fw-semibold d-block mb-1">KALAN TUTAR</span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace font-size-12 fw-bold px-2 py-1 d-inline-block text-truncate" id="tahsilatKalanTutar">0,00 TRY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Tahsilat Form Kartı -->
                <div class="card border-0 shadow-xs rounded-3 bg-white mb-0">
                    <div class="card-body p-3.5">
                        <form id="formTahsilatEkle">
                            <input type="hidden" id="tahsilatInvoiceId" name="invoice_id" value="">
                            <input type="hidden" id="tahsilatParaBirimi" name="para_birimi" value="TRY">

                            <div class="row g-3">
                                <!-- Tahsilat Tipi (App\Helper\Form::FormSelect2) -->
                                <div class="col-12 col-md-6">
                                    <?= Form::FormSelect2('tahsilat_tipi', $tahsilatTipleri, 'nakit', 'Tahsilat Tipi', 'bx bx-credit-card-front', 'key', '', 'form-select select2', true, 'width:100%', '', 'tahsilatTipi') ?>
                                </div>

                                <!-- İşlem Tarihi (App\Helper\Form::FormFloatInput) -->
                                <div class="col-12 col-md-6">
                                    <?= Form::FormFloatInput('text', 'islem_tarihi', date('d.m.Y'), 'GG.AA.YYYY', 'İşlem Tarihi', 'bx bx-calendar-event', 'form-control', true, 10, 'off', false, '', false, null) ?>
                                </div>

                                <!-- Hesap (Kasa / Banka) (App\Helper\Form::FormSelect2) -->
                                <div class="col-12 col-md-6">
                                    <?= Form::FormSelect2('kasa_id', $kasaOptions, '', 'Hesap (Kasa / Banka)', 'bx bx-wallet', 'key', '', 'form-select select2', true, 'width:100%', '', 'tahsilatKasaId') ?>
                                </div>

                                <!-- Tutar (App\Helper\Form::FormFloatInput) - Sola Yaslı -->
                                <div class="col-12 col-md-6">
                                    <?= Form::FormFloatInput('text', 'tutar', '0,00', '0,00', 'Tahsilat Tutarı', 'bx bx-lira', 'form-control text-start font-monospace fw-bold font-size-14', true, null, 'off', false, '', false, null) ?>
                                </div>

                                <!-- Açıklama (App\Helper\Form::FormFloatTextarea) -->
                                <div class="col-12">
                                    <?= Form::FormFloatTextarea('aciklama', '', 'Tahsilat açıklaması girin...', 'Açıklama', 'bx bx-comment-detail', 'form-control', false, '70px', 2, '') ?>
                                </div>
                            </div>

                            <!-- Footer Aksiyon Butonları -->
                            <div class="d-flex justify-content-end align-items-center gap-2 mt-4 pt-3 border-top">
                                <button type="button" class="btn btn-light border px-3.5 py-2 font-size-13 fw-semibold rounded-3 text-secondary" data-bs-dismiss="modal">
                                    <i class="bx bx-x me-1 font-size-16 align-middle"></i> İptal
                                </button>
                                <button type="submit" class="btn btn-primary px-4 py-2 font-size-13 fw-semibold rounded-3 shadow-sm text-white" id="btnSubmitTahsilat">
                                    <i class="bx bx-check-circle me-1 font-size-16 align-middle"></i> Tahsilatı Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 3. Bu Faturaya Ait Tahsilat Geçmişi Listesi -->
                <div class="card border-0 shadow-xs rounded-3 bg-white mt-3 d-none" id="tahsilatGecmisiWrapper">
                    <div class="card-header bg-transparent border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
                        <span class="fw-bold font-size-13 text-dark d-flex align-items-center gap-1.5">
                            <i class="bx bx-history text-primary font-size-18"></i> Bu Faturaya Ait Tahsilatlar
                        </span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-size-11" id="tahsilatAdetBadge">0 Kayıt</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 200px;">
                            <table class="table table-sm table-hover align-middle mb-0 font-size-12" id="tblTahsilatGecmisi">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 100px;">TARİH</th>
                                        <th>HESAP</th>
                                        <th style="width: 95px;">TİP</th>
                                        <th class="text-end" style="width: 115px;">TUTAR</th>
                                        <th>AÇIKLAMA</th>
                                        <th class="text-center pe-3" style="width: 50px;">İŞLEM</th>
                                    </tr>
                                </thead>
                                <tbody id="tblTahsilatGecmisiBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Tahsilat Modalı Özel İkon & Input Stilleri */
#modalTahsilatEkle .form-floating-custom .form-floating-icon {
    width: 44px !important;
    height: 56px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 4;
}

#modalTahsilatEkle .form-floating-custom .form-floating-icon i,
#modalTahsilatEkle .form-floating-custom .form-floating-icon svg {
    font-size: 22px !important;
    color: #64748b !important;
    transition: color 0.15s ease, transform 0.15s ease;
}

#modalTahsilatEkle .form-floating-custom:focus-within .form-floating-icon i,
#modalTahsilatEkle .form-floating-custom:focus-within .form-floating-icon svg {
    color: #2563eb !important;
    transform: scale(1.1);
}

#modalTahsilatEkle .form-floating-custom > .form-control {
    padding-left: 48px !important;
}

#modalTahsilatEkle .form-floating-custom > label {
    left: 36px !important;
}

#modalTahsilatEkle .select2-container--default .select2-selection--single {
    height: 56px !important;
    padding-left: 44px !important;
    padding-top: 18px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
}

#modalTahsilatEkle .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: normal !important;
    padding-left: 0 !important;
    font-weight: 600;
    color: #1e293b;
    font-size: 13px;
}

#modalTahsilatEkle .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 54px !important;
    right: 8px !important;
}
</style>
