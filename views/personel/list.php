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
        $title = "Personel Listesi";
        ?>
        <?php include 'layouts/breadcrumb.php'; ?>
        <!-- end page title -->

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-grid d-md-flex d-block">
                        <div class="card-title col-12">

                            <h4 class="card-title">Personel Listesi</h4>
                            <p class="card-title-desc">Personelleri görüntüleyebilir ve yeni personel ekleyebilirsiniz.
                            </p>
                        </div>

                    </div>


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
                        .personel-name-container {
                            position: relative;
                            display: inline-block;
                        }

                        .personel-hover-image {
                            display: none;
                            position: absolute;
                            top: -60px;
                            /* Adjust based on image size */
                            left: 100%;
                            z-index: 1000;
                            width: 100px;
                            height: 100px;
                            object-fit: cover;
                            border-radius: 50%;
                            border: 3px solid #fff;
                            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
                            margin-left: 10px;
                            background-color: #fff;
                        }

                        .personel-name-container:hover .personel-hover-image {
                            display: block;
                        }
                        
                        .fw-bold{
                            font-weight: 600 !important;
                        }
                    </style>

                    <div class="card-body overflow-auto">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>

                                <button type="button" id="exportExcel"
                                    class="btn btn-secondary waves-effect btn-label waves-light"> <i
                                        class='bx bxs-file-export label-icon'></i>
                                    Excele Aktar
                                </button>
                                <button type="button" id="btnImportExcel"
                                    class="btn btn-warning waves-effect btn-label waves-light me-1"> <i
                                        class='bx bxs-file-import label-icon'></i>
                                    Excelden Yükle
                                </button>
                            </div>
                            <div>
                                <a href="index?p=personel/manage" type="button" id="saveButton"
                                    class="btn btn-success waves-effect btn-label waves-light me-1"><i
                                        class="bx bx-plus label-icon"></i> Yeni Personel</a>
                                <button type="button" id="btnEditSelected"
                                    class="btn btn-primary waves-effect waves-light me-1" disabled>
                                    <i class="bx bx-edit-alt"></i> Görüntüle
                                </button>
                                <button type="button" id="btnDeleteSelected"
                                    class="btn btn-danger waves-effect waves-light me-1" disabled>
                                    <i class="bx bx-trash"></i> Sil
                                </button>
                                <button type="button" id="btnDetailSelected" class="btn btn-info waves-effect waves-light"
                                    disabled>
                                    <i class="bx bx-show"></i> Detay
                                </button>
                            </div>
                        </div>
                        <table id="membersTable" class="table table-selected table-bordered nowrap w-100">
                            <thead>
                                <tr>
                                    <th style="width: 20px;" class="align-middle">
                                        #
                                    </th>
                                    <th class="text-center">SIRA</th>
                                    <th>TC KİMLİK NO</th>
                                    <th>ADI SOYADI</th>
                                    <th>İŞE BAŞLAMA TARİHİ</th>
                                    <th>İŞTEN AYRILMA TARİHİ</th>
                                    <th>CEP TELEFONU</th>
                                    <th>EMAIL</th>
                                    <th>GÖREV</th>
                                    <th class="text-center">BİLDİRİM</th>
                                    <th>DURUM</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>

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
                                <img id="detailResim" src="assets/images/users/user-dummy-img.jpg" alt="Personel Resmi"
                                    class="rounded-circle img-thumbnail avatar-xl shadow-sm"
                                    style="width: 120px; height: 120px; object-fit: cover;">
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
                                                <div id="detailCinsiyet" class="fw-bold text-dark border-bottom pb-1"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Medeni Durum</label>
                                                <div id="detailMedeniDurum" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Kan Grubu</label>
                                                <div id="detailKanGrubu" class="fw-bold text-dark border-bottom pb-1"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Anne Adı</label>
                                                <div id="detailAnneAdi" class="fw-bold text-dark border-bottom pb-1"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Baba Adı</label>
                                                <div id="detailBabaAdi" class="fw-bold text-dark border-bottom pb-1"></div>
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
                                                <div id="detailTelefon" class="fw-bold text-dark border-bottom pb-1"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">2. Cep Telefonu</label>
                                                <div id="detailTelefon2" class="fw-bold text-dark border-bottom pb-1"></div>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label text-muted font-size-13">E-posta Adresi</label>
                                                <div id="detailEmail" class="fw-bold text-dark border-bottom pb-1"></div>
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
                                                <label class="form-label text-muted font-size-13">İşe Giriş Tarihi</label>
                                                <div id="detailIseGiris" class="fw-bold text-dark border-bottom pb-1"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">İşten Çıkış Tarihi</label>
                                                <div id="detailIstenCikis" class="fw-bold text-dark border-bottom pb-1">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted font-size-13">Çalışılan Proje</label>
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
                                                <label class="form-label text-muted font-size-13">SGK Yapılan Firma</label>
                                                <div id="detailSgkFirma" class="fw-bold text-dark border-bottom pb-1"></div>
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