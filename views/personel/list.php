<?php
use App\Model\PersonelModel;
use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;
use App\Service\Gate;

$Personel = new PersonelModel();

if (Gate::canWithMessage("personel_listesi")) {

    $personeller = $Personel->all();
    ?>
    <div class="container-fluid">

        <!-- start page title -->
        <?php
        $maintitle = "Personel";
        $title = "Personel Listesi kontrol";
        ?>
        <?php include 'layouts/breadcrumb.php'; ?>
        <!-- end page title -->

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <!-- <div class="card-header d-grid d-md-flex d-block">
                        <div class="card-title col-12">

                            <h4 class="card-title">Personel Listesi</h4>
                            <p class="card-title-desc">Personelleri görüntüleyebilir ve yeni personel ekleyebilirsiniz.
                            </p>
                        </div>

                    </div> -->


                    <style>
                        /* Custom Selection Styles with High Specificity */
                        #membersTable tbody tr.selected,
                        #membersTable tbody tr.selected>td {
                            background-color: #556ee6 !important;
                            /* Primary brand color */
                            color: #ffffff !important;
                        }

                        /* Ensure links in selected rows are visible */
                        #membersTable tbody tr.selected a {
                            color: #ffffff !important;
                            font-weight: bold;
                        }

                        /* Hover effect for NON-SELECTED rows only */
                        #membersTable tbody tr:not(.selected):hover>td {
                            background-color: rgba(85, 110, 230, 0.1) !important;
                        }

                        /* Pointer cursor */
                        #membersTable tbody tr {
                            cursor: pointer;
                        }

                         /* Personel Image Hover Styles */
                        .personel-img-thumb {
                            width: 38px;
                            height: 38px;
                            object-fit: cover;
                            border-radius: 50%;
                            border: 2px solid #fff;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            transition: all 0.2s ease-in-out;
                            cursor: pointer;
                        }

                        .personel-info-box {
                            display: flex;
                            align-items: center;
                            gap: 12px;
                        }

                        .personel-details {
                            display: flex;
                            flex-direction: column;
                        }

                        .personel-tc {
                            font-size: 11px;
                            color: #74788d;
                            margin-top: -2px;
                        }

                        .personel-hover-preview {
                            position: absolute; /* Changed from fixed to absolute */
                            display: none;
                            z-index: 99999;
                            width: 150px;
                            height: 150px;
                            border-radius: 12px;
                            border: 4px solid #fff;
                            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                            object-fit: cover;
                            pointer-events: none;
                            background: #fff;
                            animation: zoomIn 0.2s ease-out;
                        }

                        @keyframes zoomIn {
                            from { transform: scale(0.8); opacity: 0; }
                            to { transform: scale(1); opacity: 1; }
                        }

                        /* Premium Filter Buttons */
                        .status-filter-group {
                            background: #f8fafc;
                            padding: 4px;
                            border-radius: 50px;
                            border: 1px solid #e2e8f0;
                            display: inline-flex;
                            align-items: center;
                            gap: 2px;
                        }

                        .status-filter-group .btn-check + .btn {
                            margin-bottom: 0 !important;
                            border: none !important;
                            border-radius: 50px !important;
                            font-size: 0.75rem;
                            font-weight: 600;
                            padding: 6px 16px;
                            color: #64748b;
                            transition: all 0.2s ease;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 6px;
                            line-height: normal;
                        }

                        .status-filter-group .btn-check + .btn i {
                            font-size: 0.95rem;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            margin-top: 1px;
                        }

                        .status-filter-group .btn-check + .btn:hover {
                            background: rgba(0, 0, 0, 0.04);
                            color: #1e293b;
                        }

                        .status-filter-group .btn-check:checked + .btn {
                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                        }

                        /* Active States with brand colors */
                        .status-filter-group .btn-check:checked + .btn[for="filter-all"] { background: #64748b !important; color: #fff !important; }
                        .status-filter-group .btn-check:checked + .btn[for="filter-aktif"] { background: #34c38f !important; color: #fff !important; }
                        .status-filter-group .btn-check:checked + .btn[for="filter-pasif"] { background: #ef4444 !important; color: #fff !important; }

                        .status-filter-group .count-tag {
                            background: rgba(255,255,255,0.2);
                            padding: 2px 8px;
                            border-radius: 10px;
                            font-size: 11px;
                        }

                        .status-filter-group .btn-check:not(:checked) + .btn .count-tag {
                            background: rgba(0,0,0,0.05);
                            color: #64748b;
                        }

                        .fw-bold {
                            font-weight: 600 !important;
                        }

                        /* Passive row styling */
                        #membersTable tbody tr.row-pasif,
                        #membersTable tbody tr.row-pasif>td {
                            background-color: #FFCDC9 !important;
                        }

                        /* Column toggle checkboxes color */
                        .col-toggle-check:checked {
                            background-color: #556ee6 !important;
                            border-color: #556ee6 !important;
                        }

                        .dropdown-item:active {
                            background-color: rgba(85, 110, 230, 0.1) !important;
                            color: inherit !important;
                        }

                        .column-order-item {
                            transition: background-color 0.2s;
                            user-select: none;
                        }

                        .column-order-item:hover {
                            background-color: rgba(0, 0, 0, 0.03);
                        }

                        .drag-handle {
                            color: #adb5bd;
                            transition: all 0.2s;
                            cursor: grab !important;
                            position: relative;
                            z-index: 10;
                        }

                        .drag-handle:active {
                            cursor: grabbing !important;
                        }

                        .drag-handle:hover {
                            color: #556ee6;
                            background-color: rgba(85, 110, 230, 0.1);
                            border-radius: 4px;
                        }

                        .sorting-active .dropdown-item {
                            pointer-events: none;
                        }

                        .sorting-active .drag-handle {
                            pointer-events: auto;
                        }

                        .sortable-fallback {
                            opacity: 0.9 !important;
                            background: white !important;
                            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
                            cursor: grabbing !important;
                        }

                        #membersTable {
                            opacity: 0;
                            transition: opacity 0.3s ease-in-out;
                        }

                        #membersTable.ready {
                            opacity: 1;
                        }

                        /* Personel Preloader */
                        .personel-preloader {
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            min-height: 400px;
                            background: rgba(255, 255, 255, 0.82);
                            z-index: 1060;
                            border-radius: 4px;
                            backdrop-filter: blur(3px);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }

                        [data-bs-theme="dark"] .personel-preloader {
                            background: rgba(25, 30, 34, 0.85);
                        }

                        .personel-preloader .loader-content {
                            background: white;
                            padding: 2.5rem;
                            border-radius: 16px;
                            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
                            text-align: center;
                            min-width: 250px;
                        }

                        [data-bs-theme="dark"] .personel-preloader .loader-content {
                            background: #2a3042;
                            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
                        }

                        .animate-spin {
                            animation: spin 1s infinite linear;
                            display: inline-block;
                        }

                        @keyframes spin {
                            from {
                                transform: rotate(0deg);
                            }

                            to {
                                transform: rotate(360deg);
                            }
                        }
                    </style>

                    <div class="card-body overflow-auto">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                            <div class="d-flex gap-3 align-items-center flex-wrap">
                              
                                <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                                    <button type="button" id="exportExcel"
                                        class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center">
                                        <i class='mdi mdi-file-excel fs-5 me-1'></i> Excele Aktar
                                    </button>
                                    <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                    <button type="button" id="btnImportExcel"
                                        class="btn btn-link btn-sm text-warning text-decoration-none px-2 d-flex align-items-center">
                                        <i class='mdi mdi-file-import fs-5 me-1'></i> Excelden Yükle
                                    </button>
                                </div>
                                  <div class="status-filter-group" role="group">
                                    <input type="radio" class="btn-check" name="status-filter" id="filter-all" value="" checked>
                                    <label class="btn" for="filter-all">
                                        <i class="bx bx-grid-alt"></i> Tümü 
                                        <span class="count-tag ms-1" id="count-all">0</span>
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="status-filter" id="filter-aktif" value="Aktif">
                                    <label class="btn" for="filter-aktif">
                                        <i class="bx bx-user-check"></i> Aktif 
                                        <span class="count-tag ms-1" id="count-aktif">0</span>
                                    </label>

                                    <input type="radio" class="btn-check" name="status-filter" id="filter-pasif" value="Pasif">
                                    <label class="btn" for="filter-pasif">
                                        <i class="bx bx-user-x"></i> Pasif 
                                        <span class="count-tag ms-1" id="count-pasif">0</span>
                                    </label>
                                </div>
                            </div>
                            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                                <div class="dropdown d-inline-block">
                                    <button type="button"
                                        class="btn btn-link btn-sm text-secondary text-decoration-none px-2 d-flex align-items-center"
                                        id="columnToggle" data-bs-toggle="dropdown" aria-haspopup="true"
                                        aria-expanded="false" title="Sütunları Göster/Gizle">
                                        <i class="mdi mdi-view-column fs-5 me-1"></i> Sütunlar
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg border-0"
                                        aria-labelledby="columnToggle" style="min-width: 250px;">
                                        <div
                                            class="p-2 border-bottom d-flex justify-content-between align-items-center bg-light bg-opacity-50">
                                            <span class="fw-semibold font-size-12 text-muted uppercase">SÜTUN
                                                GÖRÜNÜMÜ</span>
                                            <button type="button" id="btnResetColumns"
                                                class="btn btn-sm btn-link text-primary text-decoration-none p-0 font-size-12 fw-bold">
                                                <i class="mdi mdi-refresh me-1"></i>VARSAYILAN
                                            </button>
                                        </div>
                                        <div class="p-2 text-center" style="max-height: 400px; overflow-y: auto;"
                                            id="columnList">
                                            <div class="text-primary py-5">
                                                <i class="bx bx-loader-alt bx-spin fs-1"></i>
                                                <div class="mt-2 font-size-13 fw-semibold text-muted">Yükleniyor...</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                <a href="index?p=personel/manage" id="saveButton"
                                    class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center">
                                    <i class="mdi mdi-plus-circle fs-5 me-1"></i> Yeni Personel</a>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                <button type="button" id="btnEditSelected"
                                    class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                                    disabled>
                                    <i class="mdi mdi-pencil fs-5 me-1"></i> Görüntüle
                                </button>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                <button type="button" id="btnDeleteSelected"
                                    class="btn btn-link btn-sm text-danger text-decoration-none px-2 d-flex align-items-center"
                                    disabled>
                                    <i class="mdi mdi-trash-can fs-5 me-1"></i> Sil
                                </button>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                <button type="button" id="btnDetailSelected"
                                    class="btn btn-link btn-sm text-info text-decoration-none px-2 d-flex align-items-center"
                                    disabled>
                                    <i class="mdi mdi-information fs-5 me-1"></i> Detay
                                </button>
                            </div>
                        </div>

                        <div class="position-relative" style="min-height: 400px;">
                            <!-- Preloader -->
                            <div class="personel-preloader" id="personel-loader">
                                <div class="loader-content">
                                    <div class="spinner-border text-primary m-1" role="status">
                                        <span class="sr-only">Yükleniyor...</span>
                                    </div>
                                    <h5 class="mt-2 mb-0">Personel Listesi Hazırlanıyor...</h5>
                                    <p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
                                </div>
                            </div>

<div class="responsive" style="overflow-x: auto !important;">

                            <table id="membersTable" class="table table-selected table-bordered nowrap w-100">
                                <thead>
                                    <tr>
                                        <th style="width: 20px;" class="align-middle">
                                            #
                                        </th>
                                        <th class="text-center">SIRA</th>
                                        <th style="width: 100px;" data-filter="string">TC KİMLİK NO</th>
                                        <th data-filter="string">ADI SOYADI</th>
                                        <th style="width: 110px;" data-filter="date">İŞE BAŞLAMA TARİHİ</th>
                                        <th style="width: 110px;" data-filter="date">İŞTEN AYRILMA TARİHİ</th>
                                        <th style="width: 110px;" data-filter="string">CEP TELEFONU</th>
                                        <th data-filter="string">EMAIL</th>
                                        <th data-filter="select">GÖREV</th>
                                        <th data-filter="select">DEPARTMAN</th>
                                        <th style="min-width: 160px; width: 160px;" data-filter="string">EKİP / BÖLGE</th>
                                        <th class="text-center" data-filter="select">BİLDİRİM</th>
                                        <th data-filter="select">DURUM</th>
                                        <!-- Additional columns (hidden by default) -->
                                        <th data-filter="date">DOĞUM TARİHİ</th>
                                        <th data-filter="select">CİNSİYET</th>
                                        <th data-filter="select">MEDENİ DURUM</th>
                                        <th data-filter="select">KAN GRUBU</th>
                                        <th data-filter="string">ADRES</th>
                                        <th data-filter="select">EHLİYET</th>
                                        <th data-filter="string">IBAN</th>
                                        <th data-filter="string">BANKA</th>
                                        <th data-filter="string">MAAŞ</th>
                                        <th data-filter="string">SGK NO</th>
                                        <th data-filter="string">SGK YAPILAN FİRMA</th>
                                        <th data-filter="string">SODEXO NO</th>
                                        <th data-filter="string">2. TELEFON</th>
                                        <th data-filter="string">KASKI KULLANICI ADI</th>
                                        <th data-filter="string">KASKI ŞİFRE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
</div>


                        </div>
                    </div>
                </div>
            </div> <!-- end col -->
        </div> <!-- end row -->

    </div> <!-- container-fluid -->

    <!-- Excel Import Modal -->
    <div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importExcelModalLabel">Excel'den Personel Yükle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 mb-3">
                        <div class="d-flex align-items-start">
                            <i class="bx bx-download fs-4 me-2 text-success"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><strong>Şablon Dosyasını İndirin</strong></h6>
                                <p class="mb-2 small text-muted">
                                    Personelleri Excelden yüklemek için şablonunu indirin.
                                </p>
                                <a href="javascript:void()" id="btnDownloadTemplate" class="btn btn-sm btn-success">
                                    <i class="bx bx-download me-1"></i>Personel Şablonunu İndir
                                </a>
                            </div>
                        </div>
                    </div>
                    <form id="importExcelForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="excelFile" class="form-label">Excel Dosyası Seçin (.xlsx, .xls)</label>
                            <input class="form-control" type="file" id="excelFile" name="excel_file" accept=".xlsx, .xls"
                                required>
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <div class="float-end">

                        <div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="button" class="btn btn-primary" id="btnUploadExcel">Yükle</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personel Detay Modalı -->
    <div class="modal fade" id="personelDetailModal" tabindex="-1" aria-labelledby="personelDetailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary bg-gradient text-white">
                    <h5 class="modal-title" id="personelDetailModalLabel"><i class="bx bx-user-circle me-2"></i>Personel
                        Detayı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Sol Taraf: Profil Özeti -->
                        <div class="col-md-3 bg-light border-end text-center p-4">
                            <div class="position-relative d-inline-block mb-3">
                                <div class="text-center">
                                    <small class="text-muted d-block mb-1">Resmi Kayıt</small>
                                    <img id="detailResim" src="assets/images/users/user-dummy-img.jpg" alt="Personel Resmi"
                                        class="rounded-circle img-thumbnail shadow-sm"
                                        style="width: 100px; height: 100px; object-fit: cover;">
                                </div>
                            </div>
                            <h5 id="detailAdSoyad" class="mb-1 fw-bold text-primary text-truncate"></h5>
                            <p id="detailGorev" class="text-muted mb-2 badge bg-white text-dark border fs-6 text-truncate"
                                style="max-width: 100%;"></p>
                            <div id="detailDurum" class="mb-3"></div>

                            <div class="text-start mt-4">
                                <p class="text-muted mb-1"><i class="bx bx-building me-2"></i><span id="detailFirma"
                                        class="fw-semibold text-dark"></span></p>
                                <p class="text-muted mb-1"><i class="bx bx-map-pin me-2"></i><span id="detailDepartman"
                                        class="fw-semibold text-dark"></span></p>
                            </div>
                        </div>

                        <!-- Sağ Taraf: Tablı İçerik -->
                        <div class="col-md-9">
                            <div class="p-3">
                                <!-- Nav tabs -->
                                <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#tabGenel" role="tab">
                                            <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                                            <span class="d-none d-sm-block">Genel Bilgiler</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#tabIletisim" role="tab">
                                            <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                            <span class="d-none d-sm-block">İletişim Bilgileri</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#tabCalisma" role="tab">
                                            <span class="d-block d-sm-none"><i class="far fa-envelope"></i></span>
                                            <span class="d-none d-sm-block">Çalışma Bilgileri</span>
                                        </a>
                                    </li>
                                </ul>

                                <!-- Tab panes -->
                                <div class="tab-content p-3 text-muted">
                                    <!-- Genel Bilgiler Tab -->
                                    <div class="tab-pane active" id="tabGenel" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">TC Kimlik No</label>
                                                <div id="detailTc" class="fw-bold text-dark border-bottom pb-1"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Doğum Tarihi</label>
                                                <div id="detailDogumTarihi" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Cinsiyet</label>
                                                <div id="detailCinsiyet" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Medeni Durum</label>
                                                <div id="detailMedeniDurum" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Kan Grubu</label>
                                                <div id="detailKanGrubu" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Anne Adı</label>
                                                <div id="detailAnneAdi" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Baba Adı</label>
                                                <div id="detailBabaAdi" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Doğum Yeri</label>
                                                <div id="detailDogumYeri" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- İletişim Bilgileri Tab -->
                                    <div class="tab-pane" id="tabIletisim" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Cep Telefonu</label>
                                                <div id="detailTelefon" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">2. Cep
                                                    Telefonu</label>
                                                <div id="detailTelefon2" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label text-muted font-size-13">E-posta Adresi</label>
                                                <div id="detailEmail" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label text-muted font-size-13">Adres</label>
                                                <div id="detailAdres" class="fw-bold text-dark border-bottom pb-1"
                                                    style="min-height: 40px;"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Çalışma Bilgileri Tab -->
                                    <div class="tab-pane" id="tabCalisma" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">İşe Giriş
                                                    Tarihi</label>
                                                <div id="detailIseGiris" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">İşten Çıkış
                                                    Tarihi</label>
                                                <div id="detailIstenCikis" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Çalışılan
                                                    Proje</label>
                                                <div id="detailProje" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Takım</label>
                                                <div id="detailTakim" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">SGK No</label>
                                                <div id="detailSgkNo" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">SGK Yapılan
                                                    Firma</label>
                                                <div id="detailSgkFirma" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Araç Kullanım</label>
                                                <div id="detailAracKullanim" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

<?php } ?>