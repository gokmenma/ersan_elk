<?php use App\Helper\Form; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">Toplu Rapor Listesi</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Raporlar</a></li>
                    <li class="breadcrumb-item active">Toplu Rapor Listesi</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
/* Rapor Preloader */
.rapor-preloader {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.82);
    z-index: 1060;
    border-radius: 4px;
    backdrop-filter: blur(3px);
    display: none;
}

[data-bs-theme="dark"] .rapor-preloader {
    background: rgba(25, 30, 34, 0.85);
}

.rapor-preloader .loader-content {
    position: absolute;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    text-align: center;
    min-width: 250px;
}

[data-bs-theme="dark"] .rapor-preloader .loader-content {
    background: #2a3042;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
}

/* Tablo ilk yüklemede DataTable hazır olana kadar preloader ile uyumlu görünmesi için ufak iyileştirmeler */
.table-container {
    transition: opacity 0.3s ease;
}

.report-table-card { border:1px solid #e3e8f0; border-radius:12px; box-shadow:0 6px 20px rgba(34,48,74,.06); overflow:hidden; }
.report-table-card .card-body { padding:16px; }
.report-table-card table.dataTable { margin-top:0!important; }
.report-table-card table.dataTable thead th { background:#f6f8fb; color:#536078; border-color:#dde4ee; font-size:11px; font-weight:700; letter-spacing:.025em; text-transform:uppercase; vertical-align:middle; }
.report-table-card table.dataTable tbody td { border-color:#e4e9f0; color:#3c4658; vertical-align:middle; padding-top:11px; padding-bottom:11px; }
.report-table-card table.dataTable tbody tr { transition:background-color .15s ease; }
.report-table-card table.dataTable tbody tr:hover { background:#f7f9ff; }
.report-person { display:flex; align-items:center; gap:9px; min-width:150px; }
.report-person-avatar { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; flex:0 0 30px; border-radius:50%; background:#e8edff; color:#4962d8; font-size:11px; font-weight:700; }
.report-person-name { color:#27334a; font-weight:600; }
.report-badge { display:inline-flex; align-items:center; gap:5px; border:1px solid transparent; border-radius:20px; padding:5px 9px; font-size:11px; font-weight:600; line-height:1; white-space:nowrap; }
.report-badge i { font-size:13px; }
.report-badge-success { background:#e8f8f0; border-color:#c8eedb; color:#138a58; }
.report-badge-warning { background:#fff6df; border-color:#f7df9d; color:#b57705; }
.report-badge-danger { background:#feebed; border-color:#fac9ce; color:#d83b48; }
.report-badge-info { background:#e8f5fb; border-color:#c4e6f5; color:#167ca8; }
.report-badge-primary { background:#edf0ff; border-color:#d4dcff; color:#4962d8; }
.report-badge-secondary { background:#f0f2f5; border-color:#dfe3e8; color:#687386; }
.report-date { display:inline-flex; align-items:center; gap:5px; color:#58657a; white-space:nowrap; }
.report-date i { color:#8a96a8; font-size:14px; }
.report-money { color:#27334a; font-weight:700; white-space:nowrap; }
.report-description { display:block; max-width:320px; overflow:hidden; text-overflow:ellipsis; color:#687386; white-space:nowrap; }
.report-table-card .btn-delete-row { width:30px; height:30px; padding:0; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; }
[data-bs-theme="dark"] .report-table-card { border-color:#32394e; }
[data-bs-theme="dark"] .report-table-card table.dataTable thead th { background:#252b3b; color:#b8c1d9; border-color:#353d52; }
[data-bs-theme="dark"] .report-table-card table.dataTable tbody tr:hover { background:rgba(85,110,230,.08); }
[data-bs-theme="dark"] .report-person-name,[data-bs-theme="dark"] .report-money { color:#e9edf5; }
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 card-title flex-grow-1">Rapor Filtreleri</h5>

                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                        <button type="button" id="exportExcelBtn" class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center">
                            <i class="mdi mdi-file-excel fs-5 me-1"></i> Excele Aktar
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <?= Form::FormFloatInput('text', 'baslangic_tarihi', date('01.m.Y'), 'Başlangıç Tarihi', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr', true) ?>
                        </div>
                        <div class="col-md-3">
                            <?= Form::FormFloatInput('text', 'bitis_tarihi', date('d.m.Y'), 'Bitiş Tarihi', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr', true) ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormSelect2('rapor_turu', [
                                ['id' => 1, 'text' => 'İzin/Rapor Listesi'],
                                ['id' => 2, 'text' => 'Personel Kesinti/Ek Ödemeleri Listesi'],
                                ['id' => 3, 'text' => 'Personel Talepleri Listesi'],
                                ['id' => 4, 'text' => 'Personel İcra Listesi']
                            ], 1, 'Rapor Türü', 'list', 'id', 'text', 'form-select select2') ?>
                        </div>
                        <div class="col-md-2 d-flex align-items-center">
                            <button type="button" id="btnRaporGetir" class="btn btn-dark w-100"><i class="bx bx-filter-alt me-1"></i> Raporu Getir</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card report-table-card">
            <div class="card-body position-relative" id="raporCardBody">
                <!-- Preloader -->
                <div class="rapor-preloader" id="rapor-loader">
                    <div class="loader-content">
                        <div class="spinner-border text-primary m-1" role="status">
                            <span class="sr-only">Yükleniyor...</span>
                        </div>
                        <h5 class="mt-2 mb-0">Rapor Hazırlanıyor...</h5>
                        <p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
                    </div>
                </div>

                <!-- Rapor Türü 1: İzinler -->
                <div id="tableContainer1" class="table-responsive table-container">
                    <table id="table1" class="table table-bordered dt-responsive nowrap w-100 datatable datatable-deferred">
                        <thead>
                            <tr>
                                <th data-filter="string">Personel</th>
                                <th data-filter="string">TC Kimlik No</th>
                                <th data-filter="select">Departman</th>
                                <th data-filter="select">İzin Türü</th>
                                <th data-filter="date">Başlangıç Tarihi</th>
                                <th data-filter="date">Bitiş Tarihi</th>
                                <th data-filter="string">Gün Sayısı</th>
                                <th data-filter="select">Durum</th>
                                <th data-filter="string">Onaylayan</th>
                                <th data-filter="string">Açıklama</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- Rapor Türü 2: Kesintiler/Ek Ödemeler -->
                <div id="tableContainer2" class="table-responsive table-container" style="display: none;">
                    <table id="table2" class="table table-bordered dt-responsive nowrap w-100 datatable datatable-deferred">
                        <thead>
                            <tr>
                                <th data-filter="string">Personel</th>
                                <th data-filter="string">TC Kimlik No</th>
                                <th data-filter="select">Departman</th>
                                <th data-filter="select">İşlem Tipi</th>
                                <th data-filter="select">Tür/Parametre</th>
                                <th data-filter="string">Detay</th>
                                <th data-filter="string">Tutar</th>
                                <th data-filter="date">Tarih</th>
                                <th data-filter="select">Durum</th>
                                <th data-filter="string">Açıklama</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- Rapor Türü 3: Talepler -->
                <div id="tableContainer3" class="table-responsive table-container" style="display: none;">
                    <table id="table3" class="table table-bordered dt-responsive nowrap w-100 datatable datatable-deferred">
                        <thead>
                            <tr>
                                <th data-filter="string">Personel</th>
                                <th data-filter="string">TC Kimlik No</th>
                                <th data-filter="select">Departman</th>
                                <th data-filter="select">Kategori</th>
                                <th data-filter="string">Başlık</th>
                                <th data-filter="date">Tarih</th>
                                <th data-filter="select">Durum</th>
                                <th data-filter="date">Çözüm Tarihi</th>
                                <th data-filter="string">Çözüm Açıklaması</th>
                                <th data-filter="string">Açıklama</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- Rapor Türü 4: İcralar -->
                <div id="tableContainer4" class="table-responsive table-container" style="display: none;">
                    <table id="table4" class="table table-bordered dt-responsive nowrap w-100 datatable datatable-deferred">
                        <thead>
                            <tr>
                                <th data-filter="string">Personel</th>
                                <th data-filter="string">TC Kimlik No</th>
                                <th data-filter="select">Departman</th>
                                <th data-filter="select">İcra Dairesi</th>
                                <th data-filter="string">Dosya No</th>
                                <th data-filter="string">Toplam Borç</th>
                                <th data-filter="string">Kesilen Tutar</th>
                                <th data-filter="string">Kalan Tutar</th>
                                <th data-filter="select">Durum</th>
                                <th data-filter="date">Tarih</th>
                                <th data-filter="string">Açıklama</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Silme Modalı -->
<div class="modal fade" id="deleteRowModal" tabindex="-1" aria-labelledby="deleteRowModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRowModalLabel">Kayıt Sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteRowId">
                <input type="hidden" id="deleteRowType">
                <div class="alert alert-warning mb-3">
                    <i class="mdi mdi-alert-outline me-2"></i>Bu kaydı silmek istediğinize emin misiniz? Silme nedenini girmek zorunludur.
                </div>
                <div class="mb-3">
                    <label class="form-label text-danger fw-bold">Silme Nedeni / Açıklama *</label>
                    <textarea class="form-control" id="deleteRowAciklama" rows="3" placeholder="Lütfen neden silindiğini açıklayın..." required></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete"><i class="mdi mdi-delete me-1"></i> Kaydı Sil</button>
            </div>
        </div>
    </div>
</div>

<script>
    var canDeleteTableRow = <?= \App\Service\Gate::allows("toplu_raporlar_satir_silme") ? 'true' : 'false' ?>;
</script>

<script src="views/raporlar/js/list.js?v=<?= time() ?>"></script>
