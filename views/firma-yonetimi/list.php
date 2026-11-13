<?php

require_once 'vendor/autoload.php';

use App\Helper\Form;
use App\Helper\Security;
use App\Service\Gate;

Gate::authorizeOrDie('firma-yonetimi/list', 'Firma yönetimi sayfasını görüntüleme yetkiniz bulunmuyor.');

$maintitle = 'Yönetim';
$title = 'Firma Yönetimi';
$csrfToken = Security::csrf();
?>
<script>try { document.documentElement.classList.toggle('firma-yonetimi-summary-hidden', localStorage.getItem('firma_yonetimi_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.firma-yonetimi-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#firmaTable tbody tr { cursor: pointer !important; }
html[data-theme-preset="macos-dark"] .summary-kpi-card { background: rgba(28, 30, 38, 0.72) !important; border-color: rgba(255, 255, 255, 0.09) !important; }
html[data-theme-preset="macos-dark"] .summary-kpi-value { color: #f3f4f8 !important; }
html[data-theme-preset="macos-dark"] h4.text-dark { color: rgba(240, 242, 246, 0.95) !important; }
</style>
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid" id="firmaYonetimiSayfasi">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-buildings fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Firma Yönetimi</h4>
                <p class="text-muted mb-0 font-size-12">Firma kartları, fatura satıcı bilgileri, logo ve IBAN</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniFirma">
                <i class="bx bx-plus font-size-16"></i> Yeni Firma Ekle
            </button>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM FİRMA</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-buildings"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_logo">Logolu: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all"><i class="bx bx-layer"></i> Tümü</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BİLGİLERİ TAM</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check-circle"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_tam">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Faturaya hazır</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="tam"><i class="bx bx-check"></i> Tam</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">EKSİK BİLGİLİ</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle"><i class="bx bx-error-circle"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_eksik">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold">Vergi, adres, iletişim</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="eksik"><i class="bx bx-error"></i> Eksik</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">VARSAYILAN FİRMA</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-star"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat_varsayilan">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold">Girişte otomatik seçilen</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="varsayilan"><i class="bx bx-star"></i> Varsayılan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4" id="firmaListCard">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-16"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold">Firma Listesi</h5>
                    <span class="text-muted font-size-11">Yetkili olduğunuz firmalar</span>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" id="btnHeaderPrint" title="Yazdır">
                <i class="bx bx-printer font-size-14"></i> Yazdır
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive p-3">
                <table id="firmaTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="string">Firma Adı</th>
                            <th data-filter="string">Ünvan</th>
                            <th data-filter="string" style="width: 120px;">Vergi No</th>
                            <th data-filter="string" style="width: 130px;">Vergi Dairesi</th>
                            <th data-filter="string" style="width: 130px;">Telefon</th>
                            <th data-filter="string">E-posta</th>
                            <th data-filter="string" style="width: 120px;">İl</th>
                            <th data-filter="none" class="text-center" style="width: 100px;">Durum</th>
                            <th data-filter="none" class="text-center" style="width: 130px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFirma" tabindex="-1" aria-labelledby="modalFirmaLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0 bg-primary-subtle text-primary" style="width: 44px; height: 44px;">
                        <i class="bx bx-buildings fs-3"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark" id="modalFirmaLabel">Yeni Firma</h5>
                        <p class="text-muted small mb-0">Bu bilgiler giden faturalarda satıcı bilgisi olarak kullanılır.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <form id="formFirma" autocomplete="off" enctype="multipart/form-data">
                <div class="modal-body px-4 pt-3 pb-2">
                    <input type="hidden" name="enc_id" id="firma_enc_id" value="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <ul class="nav nav-pills nav-justified mb-3 p-1 rounded-3" id="firmaModalTabs" role="tablist" style="background: #f1f5f9;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" data-bs-toggle="pill" data-bs-target="#tab-firma-genel" type="button" role="tab" style="border-radius: 8px;">
                                <i class="bx bx-id-card font-size-16"></i> <span>Genel & Vergi</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" data-bs-toggle="pill" data-bs-target="#tab-firma-iletisim" type="button" role="tab" style="border-radius: 8px;">
                                <i class="bx bx-map font-size-16"></i> <span>Adres & İletişim</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" data-bs-toggle="pill" data-bs-target="#tab-firma-diger" type="button" role="tab" style="border-radius: 8px;">
                                <i class="bx bx-image font-size-16"></i> <span>Logo & IBAN</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-firma-genel" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-8 col-12"><?= Form::FormFloatInput('text', 'firma_adi', '', 'Firma Adı', 'Firma Adı *', 'bx bx-briefcase', 'form-control fw-semibold', true) ?></div>
                                <div class="col-md-4 col-12"><?= Form::FormFloatInput('text', 'firma_kodu', '', 'Firma Kodu', 'Firma Kodu', 'bx bx-hash') ?></div>
                                <div class="col-12"><?= Form::FormFloatInput('text', 'firma_unvan', '', 'Faturada görünecek tam ünvan', 'Firma Ünvanı', 'bx bx-briefcase-alt') ?></div>
                                <div class="col-md-6 col-12"><?= Form::FormFloatInput('text', 'vergi_no', '', 'VKN / TCKN', 'Vergi No', 'bx bx-hash', 'form-control', false, 11) ?></div>
                                <div class="col-md-6 col-12"><?= Form::FormFloatInput('text', 'vergi_dairesi', '', 'Vergi Dairesi', 'Vergi Dairesi', 'bx bx-building') ?></div>
                                <div class="col-md-4 col-12"><?= Form::FormFloatInput('text', 'vergi_dairesi_no', '', 'Vergi Dairesi No', 'Vergi Dairesi No', 'bx bx-hash') ?></div>
                                <div class="col-md-4 col-12"><?= Form::FormFloatInput('text', 'mersis_no', '', 'Mersis No', 'Mersis No', 'bx bx-hash') ?></div>
                                <div class="col-md-4 col-12"><?= Form::FormFloatInput('text', 'ticaret_sicil_no', '', 'Ticaret Sicil No', 'Ticaret Sicil No', 'bx bx-hash') ?></div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-firma-iletisim" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12"><?= Form::FormFloatInput('text', 'adres', '', 'Mahalle, cadde, no', 'Adres', 'bx bx-map', 'form-control', false, 255) ?></div>
                                <div class="col-md-4 col-12"><?= Form::FormFloatInput('text', 'ilce', '', 'İlçe', 'İlçe', 'bx bx-map-pin') ?></div>
                                <div class="col-md-4 col-12"><?= Form::FormFloatInput('text', 'il', '', 'İl', 'İl', 'bx bx-map-pin') ?></div>
                                <div class="col-md-4 col-12"><?= Form::FormFloatInput('text', 'ulke', 'Türkiye', 'Ülke', 'Ülke', 'bx bx-world') ?></div>
                                <div class="col-md-6 col-12"><?= Form::FormFloatInput('text', 'telefon', '', 'Telefon', 'Telefon', 'bx bx-phone') ?></div>
                                <div class="col-md-6 col-12"><?= Form::FormFloatInput('text', 'fax', '', 'Faks', 'Faks', 'bx bx-printer') ?></div>
                                <div class="col-md-6 col-12"><?= Form::FormFloatInput('text', 'email', '', 'E-posta', 'E-posta', 'bx bx-envelope') ?></div>
                                <div class="col-md-6 col-12"><?= Form::FormFloatInput('text', 'kep_adresi', '', 'KEP Adresi', 'KEP Adresi', 'bx bx-mail-send') ?></div>
                                <div class="col-12"><?= Form::FormFloatInput('text', 'web_sitesi', '', 'https://', 'Web Sitesi', 'bx bx-globe') ?></div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-firma-diger" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="logo">Firma Logosu</label>
                                    <input type="file" class="form-control" name="logo" id="logo" accept="image/png,image/jpeg">
                                    <div class="form-text">Resmî yazılarda kullanılır. PNG veya JPG, en fazla 2 MB.</div>
                                    <img id="firma_logo_preview" alt="Firma logosu" class="mt-2 border rounded p-1 bg-light" style="display:none; max-width:140px; max-height:90px; object-fit:contain;">
                                </div>
                                <div class="col-12"><?= Form::FormFloatInput('text', 'firma_iban', '', 'TR00 0000 0000 0000 0000 0000 00', 'Firma IBAN', 'bx bx-credit-card', 'form-control mask-iban', false, 32) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal" style="background:#6c757d; border-radius: 8px; border:none;">İptal</button>
                    <button type="submit" class="btn btn-dark px-4 fw-semibold shadow-sm" id="btnKaydetFirma" style="background:#1e293b; border-radius: 8px; border:none;">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    const API = 'views/firma-yonetimi/api.php';
    const csrf = $('meta[name="csrf-token"]').attr('content');
    let durumFiltresi = 'all';

    const esc = function (t) {
        if (t === null || t === undefined) return '';
        return String(t).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]; });
    };
    const bos = function (v) { return v === null || v === undefined || String(v).trim() === '' || String(v).trim() === '0'; };

    $('#btnToggleSummaryCards').on('click', function () {
        const gizli = document.documentElement.classList.toggle('firma-yonetimi-summary-hidden');
        try { localStorage.setItem('firma_yonetimi_summary_cards_state', gizli ? 'hidden' : 'visible'); } catch (e) {}
        $(this).find('i').toggleClass('bx-chevron-up', !gizli).toggleClass('bx-chevron-down', gizli);
        $(this).attr('aria-expanded', gizli ? 'false' : 'true').attr('aria-label', gizli ? 'Özet kartlarını göster' : 'Özet kartlarını gizle');
    });
    if (document.documentElement.classList.contains('firma-yonetimi-summary-hidden')) {
        $('#btnToggleSummaryCards i').removeClass('bx-chevron-up').addClass('bx-chevron-down');
        $('#btnToggleSummaryCards').attr('aria-expanded', 'false').attr('aria-label', 'Özet kartlarını göster');
    }

    const tablo = $('#firmaTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: API,
            type: 'GET',
            data: function (d) { d.action = 'list'; d.status_filter = durumFiltresi; },
            error: function (xhr) { console.error('Firma listesi yüklenemedi', xhr.status); }
        },
        columns: [
            { data: 'firma_adi', render: function (d, t, r) {
                const kod = r.firma_kodu && String(r.firma_kodu) !== '0' ? '<div class="text-muted font-size-11">Kod: ' + esc(r.firma_kodu) + '</div>' : '';
                return '<div class="fw-bold text-dark font-size-13">' + esc(d) + '</div>' + kod;
            } },
            { data: 'firma_unvan', render: function (d) { return bos(d) ? '<span class="text-muted">-</span>' : esc(d); } },
            { data: 'vergi_no', render: function (d) { return bos(d) ? '<span class="text-danger">Eksik</span>' : esc(d); } },
            { data: 'vergi_dairesi', render: function (d) { return bos(d) ? '<span class="text-danger">Eksik</span>' : esc(d); } },
            { data: 'telefon', render: function (d) { return bos(d) ? '<span class="text-danger">Eksik</span>' : esc(d); } },
            { data: 'email', render: function (d) { return bos(d) ? '<span class="text-danger">Eksik</span>' : esc(d); } },
            { data: 'il', render: function (d, t, r) {
                if (bos(d)) return '<span class="text-danger">Eksik</span>';
                return esc(r.ilce ? r.ilce + ' / ' + d : d);
            } },
            { data: 'varsayilan_mi', orderable: false, searchable: false, className: 'text-center', render: function (d) {
                return parseInt(d) === 1
                    ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-star me-1"></i>Varsayılan</span>'
                    : '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Aktif</span>';
            } },
            { data: 'enc_id', orderable: false, searchable: false, className: 'text-center', render: function (d, t, r) {
                return '<div class="action-btn-group d-flex align-items-center justify-content-center gap-1">'
                    + '<button type="button" class="btn btn-sm btn-subtle-warning table-action-btn btn-firma-duzenle" data-id="' + esc(d) + '" title="Düzenle"><i class="bx bx-edit font-size-14"></i></button>'
                    + '<button type="button" class="btn btn-sm btn-subtle-primary table-action-btn btn-firma-varsayilan" data-id="' + esc(d) + '" title="Varsayılan Yap"><i class="bx bx-star font-size-14"></i></button>'
                    + '<button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-firma-sil" data-id="' + esc(d) + '" data-name="' + esc(r.firma_adi) + '" title="Sil"><i class="bx bx-trash font-size-14"></i></button>'
                    + '</div>';
            } }
        ],
        order: [[0, 'asc']]
    }));

    function ozetYukle() {
        $.getJSON(API + '?action=summary', function (res) {
            if (res.status !== 'success') return;
            $('#stat_toplam').text(res.data.toplam);
            $('#stat_sub_logo').text('Logolu: ' + res.data.logolu);
            $('#stat_tam').text(res.data.tam);
            $('#stat_eksik').text(res.data.eksik);
            $('#stat_varsayilan').text(res.data.varsayilan);
        });
    }
    ozetYukle();

    $('.status-quick-filter').on('click', function () {
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        durumFiltresi = $(this).data('status');
        tablo.ajax.reload();
    });

    function hata(xhr, varsayilan) {
        const m = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : varsayilan;
        Swal.fire('Hata', m, 'error');
    }

    function modalAc(baslik) {
        $('#modalFirmaLabel').text(baslik);
        $('#modalFirma .nav-link').first().tab('show');
        $('#modalFirma').modal('show');
    }

    $('#btnYeniFirma').on('click', function () {
        $('#formFirma')[0].reset();
        $('#firma_enc_id').val('');
        $('#ulke').val('Türkiye');
        $('#firma_logo_preview').hide().attr('src', '');
        modalAc('Yeni Firma');
    });

    function duzenle(encId) {
        $.getJSON(API + '?action=get&id=' + encodeURIComponent(encId), function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Firma bulunamadı.', 'error'); return; }
            const d = res.data;
            $('#formFirma')[0].reset();
            $('#firma_enc_id').val(d.enc_id);
            ['firma_adi', 'firma_kodu', 'firma_unvan', 'vergi_no', 'vergi_dairesi', 'vergi_dairesi_no', 'mersis_no', 'ticaret_sicil_no',
             'adres', 'ilce', 'il', 'ulke', 'telefon', 'fax', 'email', 'kep_adresi', 'web_sitesi', 'firma_iban'].forEach(function (k) {
                $('#' + k).val(d[k] === null || d[k] === undefined ? '' : d[k]);
            });
            if (!d.ulke) $('#ulke').val('Türkiye');
            if (d.logo_yolu) { $('#firma_logo_preview').attr('src', d.logo_yolu).show(); } else { $('#firma_logo_preview').hide().attr('src', ''); }
            modalAc('Firmayı Düzenle: ' + (d.firma_adi || ''));
        }).fail(function (xhr) { hata(xhr, 'Firma bilgisi alınamadı.'); });
    }

    $('#firmaTable tbody').on('click', 'tr', function (e) {
        if ($(e.target).closest('.action-btn-group, button, a').length) return;
        const row = tablo.row(this).data();
        if (row && row.enc_id) duzenle(row.enc_id);
    });
    $(document).on('click', '.btn-firma-duzenle', function (e) { e.stopPropagation(); duzenle($(this).data('id')); });

    $('#formFirma').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnKaydetFirma');
        $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Kaydediliyor...');
        $.ajax({
            url: API + '?action=save', type: 'POST', data: new FormData(this), processData: false, contentType: false,
            headers: { 'X-CSRF-Token': csrf }, dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success') {
                $('#modalFirma').modal('hide');
                Swal.fire({ icon: 'success', title: 'Başarılı', text: res.message, timer: 1500, showConfirmButton: false });
                tablo.ajax.reload(null, false);
                ozetYukle();
            } else {
                Swal.fire('Hata', res.message || 'Kayıt sırasında hata oluştu.', 'error');
            }
        }).fail(function (xhr) { hata(xhr, 'Sunucuyla bağlantı hatası oluştu.'); })
          .always(function () { $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet'); });
    });

    $(document).on('click', '.btn-firma-varsayilan', function (e) {
        e.stopPropagation();
        $.ajax({ url: API + '?action=set-default', type: 'POST', data: { id: $(this).data('id'), csrf_token: csrf }, headers: { 'X-CSRF-Token': csrf }, dataType: 'json' })
            .done(function (res) {
                if (res.status === 'success') { tablo.ajax.reload(null, false); ozetYukle(); }
                else Swal.fire('Hata', res.message || 'İşlem başarısız.', 'error');
            }).fail(function (xhr) { hata(xhr, 'Sunucuyla bağlantı hatası oluştu.'); });
    });

    $(document).on('click', '.btn-firma-sil', function (e) {
        e.stopPropagation();
        const id = $(this).data('id');
        const ad = $(this).data('name') || 'Bu firma';
        Swal.fire({ title: 'Emin misiniz?', text: '"' + ad + '" firması silinecektir.', icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#6c757d', confirmButtonText: 'Evet, Sil', cancelButtonText: 'Vazgeç'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: API + '?action=delete', type: 'POST', data: { id: id, csrf_token: csrf }, headers: { 'X-CSRF-Token': csrf }, dataType: 'json' })
                .done(function (res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Silindi', text: res.message, timer: 1500, showConfirmButton: false });
                        tablo.ajax.reload(null, false);
                        ozetYukle();
                    } else Swal.fire('Hata', res.message || 'Silme işlemi başarısız.', 'error');
                }).fail(function (xhr) { hata(xhr, 'Sunucuyla bağlantı hatası oluştu.'); });
        });
    });

    $('#btnHeaderPrint').on('click', function () { window.print(); });
});
</script>
