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
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 card-title flex-grow-1">Rapor Filtreleri</h5>
                    
                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                        <button type="button" id="exportExcelBtn" class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center">
                            <i class='mdi mdi-file-excel fs-5 me-1'></i> Excele Aktar
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
        
        <div class="card">
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
                                <th>Personel</th>
                                <th>TC Kimlik No</th>
                                <th>Departman</th>
                                <th>İzin Türü</th>
                                <th>BaşlangıçTarihi</th>
                                <th>Bitiş Tarihi</th>
                                <th>Gün Sayısı</th>
                                <th>Durum</th>
                                <th>Onaylayan</th>
                                <th>Açıklama</th>
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
                                <th>Personel</th>
                                <th>TC Kimlik No</th>
                                <th>Departman</th>
                                <th>İşlem Tipi</th>
                                <th>Tür/Parametre</th>
                                <th>Detay</th>
                                <th>Tutar</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th>Açıklama</th>
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
                                <th>Personel</th>
                                <th>TC Kimlik No</th>
                                <th>Departman</th>
                                <th>Kategori</th>
                                <th>Başlık</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th>Çözüm Tarihi</th>
                                <th>Çözüm Açıklaması</th>
                                <th>Açıklama</th>
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
                                <th>Personel</th>
                                <th>TC Kimlik No</th>
                                <th>Departman</th>
                                <th>İcra Dairesi</th>
                                <th>Dosya No</th>
                                <th>Toplam Borç</th>
                                <th>Kesilen Tutar</th>
                                <th>Kalan Tutar</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th>Açıklama</th>
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