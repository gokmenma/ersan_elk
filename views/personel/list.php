<?php
use App\Model\PersonelModel;
use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;
use App\Service\Gate;

$Personel = new PersonelModel();

if (Gate::canWithMessage("personel_listesi")) {
    $sayilar = $Personel->personelSayilari('personel');
    $toplamPersonel = (int) ($sayilar->toplam_personel ?? 0);
    $aktifPersonel = (int) ($sayilar->aktif_personel ?? 0);
    $pasifPersonel = (int) ($sayilar->pasif_personel ?? 0);
    $bildirimAcik = (int) ($sayilar->bildirim_acik ?? 0);
    $bildirimOran = $aktifPersonel > 0 ? (int) round(($bildirimAcik / $aktifPersonel) * 100) : 0;

    $maintitle = "Personel";
    $title = "Personel Listesi";
    ?>
    <script>try { document.documentElement.classList.toggle('personel-summary-hidden', localStorage.getItem('personel_summary_cards_state') === 'hidden'); } catch (e) {}</script>

    <style>
        #summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
        .personel-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
        @media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }

        /* Custom Selection Styles with High Specificity */
        #membersTable tbody tr.selected,
        #membersTable tbody tr.selected>td {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }

        #membersTable tbody tr.selected a {
            color: #ffffff !important;
            font-weight: bold;
        }

        #membersTable tbody tr:not(.selected):hover>td {
            background-color: rgba(var(--bs-primary-rgb), 0.08) !important;
        }

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

        .personel-hover-preview {
            position: fixed;
            display: none;
            z-index: 99999;
            width: 150px;
            height: 150px;
            border-radius: 12px;
            border: 4px solid #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            object-fit: cover;
            pointer-events: none !important;
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
            padding: 3px;
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
            padding: 5px 14px;
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

        [data-bs-theme="dark"] #membersTable tbody tr.row-pasif,
        [data-bs-theme="dark"] #membersTable tbody tr.row-pasif>td {
            background-color: rgba(239, 68, 68, 0.18) !important;
            color: #fca5a5 !important;
        }

        /* Dark Mode Overrides for Personel List */
        [data-bs-theme="dark"] .status-filter-group {
            background: #2a3042 !important;
            border-color: #32394e !important;
        }

        [data-bs-theme="dark"] .status-filter-group .btn-check + .btn {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .status-filter-group .btn-check + .btn:hover {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .status-filter-group .btn-check:not(:checked) + .btn .count-tag {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .personel-img-thumb {
            border-color: #3e4556 !important;
        }

        [data-bs-theme="dark"] .personel-hover-preview {
            border-color: #3e4556 !important;
            background: #2a3042 !important;
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
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>

    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="container-fluid">
        <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
        <div class="row align-items-center mb-3">
            <div class="col-md-6 col-12 d-flex align-items-center gap-3">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="bx bx-group fs-4 text-primary"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold text-dark font-size-16">Personel Yönetimi</h4>
                    <p class="text-muted mb-0 font-size-12">Tüm personellerin özlük bilgileri, çalışma durumları ve ekip/bölge takibi</p>
                </div>
            </div>

            <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0 flex-wrap">
                <a href="index?p=personel/manage" id="saveButton" class="btn btn-primary top-action-btn shadow-sm text-white">
                    <i class="bx bx-plus font-size-16"></i> Yeni Personel
                </a>

                <button type="button" id="btnDetailSelected" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm" disabled>
                    <i class="bx bx-info-circle font-size-16 text-info"></i> Detay
                </button>

                <div class="dropdown d-inline-block">
                    <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" id="btnActions" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                        <button type="button" id="exportExcel" class="dropdown-item d-flex align-items-center">
                            <i class="bx bx-file me-2 font-size-16 text-success"></i> Excel'e Aktar
                        </button>
                        <button type="button" id="btnImportExcel" class="dropdown-item d-flex align-items-center">
                            <i class="bx bx-upload me-2 font-size-16 text-warning"></i> Excel'den Yükle
                        </button>
                        <?php if (Gate::allows('personel_karsilama_mesaji')): ?>
                        <button type="button" id="btnKarsilamaSelected" class="dropdown-item d-flex align-items-center" disabled>
                            <i class="bx bxl-whatsapp me-2 font-size-16 text-success"></i> Karşılama Mesajı Gönder
                        </button>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <button type="button" id="btnDeleteSelected" class="dropdown-item d-flex align-items-center text-danger" disabled>
                            <i class="bx bx-trash me-2 font-size-16"></i> Personeli Sil
                        </button>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını gizle" aria-expanded="true">
                    <i class="bx bx-chevron-up"></i>
                </button>
            </div>
        </div>

        <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
        <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
            <!-- Kart 1: TOPLAM PERSONEL -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="summary-kpi-label">TOPLAM PERSONEL</span>
                                <h3 class="summary-kpi-value my-1" id="stat_toplam_personel"><?= $toplamPersonel; ?> Kişi</h3>
                            </div>
                            <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bx bx-group"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext" id="stat_sub_toplam">Aktif: <?= $aktifPersonel; ?> | Pasif: <?= $pasifPersonel; ?></span>
                            <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="">
                                <i class="bx bx-layer"></i> Tümü
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 2: AKTİF PERSONEL -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="summary-kpi-label">AKTİF PERSONEL</span>
                                <h3 class="summary-kpi-value my-1 text-success" id="stat_aktif_personel"><?= $aktifPersonel; ?> Kişi</h3>
                            </div>
                            <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                <i class="bx bx-user-check"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext text-success fw-semibold">Çalışan Personeller</span>
                            <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="Aktif">
                                <i class="bx bx-check-circle"></i> Aktif
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 3: PASİF PERSONEL -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="summary-kpi-label">PASİF PERSONEL</span>
                                <h3 class="summary-kpi-value my-1 text-danger" id="stat_pasif_personel"><?= $pasifPersonel; ?> Kişi</h3>
                            </div>
                            <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                                <i class="bx bx-user-x"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext text-danger fw-semibold">İşten Ayrılanlar</span>
                            <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="Pasif">
                                <i class="bx bx-x-circle"></i> Pasif
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 4: BİLDİRİM ABONESİ -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="summary-kpi-label">BİLDİRİM ABONESİ</span>
                                <h3 class="summary-kpi-value my-1 text-warning" id="stat_bildirim_acik"><?= $bildirimAcik; ?> Kişi</h3>
                            </div>
                            <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bx bx-bell"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">Aktif bildirim açık</span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" id="stat_bildirim_oran">%<?= $bildirimOran; ?> Abone</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Standart DataTables Personel Listesi Kartı -->
        <div class="card summary-kpi-card mb-3" id="personelListCard">
            <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-list-ul font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Personel Listesi</h5>
                        <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, canlı sütun filtreleme ve özlük yönetimi</p>
                    </div>
                </div>

                <!-- Sağ Araç Çubuğu -->
                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                    <div class="status-filter-group" role="group">
                        <input type="radio" class="btn-check" name="status-filter" id="filter-all" value="">
                        <label class="btn" for="filter-all">
                            <i class="bx bx-grid-alt"></i> Tümü
                            <span class="count-tag ms-1" id="count-all"><?= $toplamPersonel; ?></span>
                        </label>

                        <input type="radio" class="btn-check" name="status-filter" id="filter-aktif" value="Aktif" checked>
                        <label class="btn" for="filter-aktif">
                            <i class="bx bx-user-check"></i> Aktif
                            <span class="count-tag ms-1" id="count-aktif"><?= $aktifPersonel; ?></span>
                        </label>

                        <input type="radio" class="btn-check" name="status-filter" id="filter-pasif" value="Pasif">
                        <label class="btn" for="filter-pasif">
                            <i class="bx bx-user-x"></i> Pasif
                            <span class="count-tag ms-1" id="count-pasif"><?= $pasifPersonel; ?></span>
                        </label>
                    </div>

                    <span class="d-inline-block" data-dt-colman-for="membersTable" data-dt-colman-style="outline"></span>
                </div>
            </div>

            <div class="card-body p-3 pt-0">
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

                    <div class="table-responsive">

                            <table id="membersTable" class="table table-selected table-bordered table-hover nowrap align-middle w-100 mb-0">
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
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                    </div> <!-- table-responsive -->
                </div> <!-- position-relative -->
            </div> <!-- card-body -->
        </div> <!-- personelListCard -->

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
                                    Mevcut kayıtlarla dolu şablonu veya yeni kayıtlar için boş şablonu indirin.
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="javascript:void()" id="btnDownloadTemplate" class="btn btn-sm btn-success">
                                        <i class="bx bx-download me-1"></i>Dolu Şablonu İndir
                                    </a>
                                    <a href="views/personel/download-template.php?bos=1" id="btnDownloadEmptyTemplate" class="btn btn-sm btn-outline-success">
                                        <i class="bx bx-file-blank me-1"></i>Boş Şablonu İndir
                                    </a>
                                </div>
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

    <div class="modal fade" id="sozlesmeOnizlemeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-size-15 fw-bold"><i class="bx bx-file me-1"></i> Hizmet Sözleşmesi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="sozlesmeOnizlemeFrame" title="Hizmet Sözleşmesi" style="width:100%;height:75vh;border:0;background:#e9edf2"></iframe>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" id="btnSozlesmeYazdir" class="btn btn-primary"><i class="bx bx-printer me-1"></i> Yazdır / PDF</button>
                    <a href="#" id="btnSozlesmeIndir" class="btn btn-outline-secondary"><i class="bx bx-download me-1"></i> Word</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/icerik/modals/karsilama_mesaji.php'; ?>

<?php } ?>
