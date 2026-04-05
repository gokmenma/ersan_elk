<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Form;
use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;

$Personel = new PersonelModel();
$Tanimlamalar = new TanimlamalarModel();

$personeller = $Personel->all(false, 'demirbas');

$sayacKatIds = [];
$aparatKatIds = [];
$tumKategoriler = $Tanimlamalar->getDemirbasKategorileri();
foreach ($tumKategoriler as $kat) {
    $katAdiLower = mb_strtolower($kat->tur_adi, 'UTF-8');
    if (str_contains($katAdiLower, 'sayaç') || str_contains($katAdiLower, 'sayac')) {
        $sayacKatIds[] = (string) $kat->id;
    }
    if (str_contains($katAdiLower, 'aparat') || (int) $kat->id === 645) {
        $aparatKatIds[] = (string) $kat->id;
    }
}

$maintitle = 'Demirbaş';
$title = 'Zimmet Kayıtları';
?>

<div class="container-fluid">
    <?php include 'layouts/breadcrumb.php'; ?>

    <style>
        .personel-preloader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            min-height: 380px;
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
            background: #fff;
            padding: 2.2rem;
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            text-align: center;
            min-width: 250px;
        }

        [data-bs-theme="dark"] .personel-preloader .loader-content {
            background: #2a3042;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }

        .status-filter-group {
            background: #f8f9fa;
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
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: transparent !important;
        }

        .status-filter-group .btn-check:checked + .btn {
            background: #fff !important;
            color: #556ee6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .segmented-control-container {
            display: flex;
            background: #f8f9fa;
            padding: 4px;
            border-radius: 50px;
            border: 1px solid #e2e8f0;
            gap: 4px;
        }

        .segmented-control-input {
            display: none;
        }

        .segmented-control-label {
            margin: 0;
            border-radius: 50px;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .segmented-control-input:checked + .segmented-control-label {
            background: #fff;
            color: #556ee6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .zimmet-top-actions {
            gap: 0.35rem;
        }

        .zimmet-top-actions .btn {
            white-space: nowrap;
        }

        @media (max-width: 992px) {
            .zimmet-top-actions {
                width: 100%;
                justify-content: flex-end;
                margin-top: 0.5rem;
            }

            .zimmet-filter-wrap {
                flex-direction: column;
                align-items: stretch !important;
                gap: 0.6rem;
            }

            .zimmet-filter-left,
            .zimmet-filter-right {
                width: 100%;
            }

            .zimmet-filter-right {
                justify-content: flex-end;
            }
        }
    </style>

    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bx bx-transfer-alt me-1 text-warning"></i> Zimmet Kayıtları
                </h5>

                <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 ms-auto zimmet-top-actions">
                    <button type="button" id="btnTopluIadeAl"
                        class="btn btn-info btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm">
                        <i class="bx bx-undo fs-5 me-1"></i> Toplu İade Al
                    </button>

                    <div class="vr mx-1" style="height: 25px; align-self: center;"></div>

                    <button type="button" id="btnTopluZimmetSil"
                        class="btn btn-danger btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm">
                        <i class="bx bx-trash fs-5 me-1"></i> Toplu Zimmet Sil
                    </button>

                    <div class="vr mx-1" style="height: 25px; align-self: center;"></div>

                    <button type="button" id="btnZimmetVer"
                        class="btn btn-warning btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm"
                        data-bs-toggle="modal" data-bs-target="#zimmetModal">
                        <i class="bx bx-transfer-alt fs-5 me-1"></i> Zimmet Ver
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body position-relative">
            <div class="personel-preloader" id="personel-loader">
                <div class="loader-content">
                    <div class="spinner-border text-warning m-1" role="status">
                        <span class="sr-only">Yükleniyor...</span>
                    </div>
                    <h5 class="mt-2 mb-0">Zimmet Kayıtları Hazırlanıyor...</h5>
                    <p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
                </div>
            </div>

            <div class="card bg-white border shadow-sm mb-3">
                <div class="card-body p-2">
                    <div class="d-flex align-items-center zimmet-filter-wrap">
                    <div class="me-3 ps-2 d-flex align-items-center zimmet-filter-left">
                        <div class="avatar-xs me-2 rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                            <i class="bx bx-filter-alt fs-6"></i>
                        </div>
                        <span class="fw-bold small text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">FİLTRELE:</span>
                    </div>
                    <div class="d-flex align-items-center flex-grow-1 flex-wrap gap-2">
                        <div class="segmented-control-container ms-1">
                            <input type="radio" name="zimmetFilter" id="filterTum" value="all" class="segmented-control-input zimmet-filter" checked>
                            <label for="filterTum" class="segmented-control-label"><i class="bx bx-list-ul me-1 fs-5"></i> Tümü</label>

                            <input type="radio" name="zimmetFilter" id="filterDemirbas" value="demirbas" class="segmented-control-input zimmet-filter">
                            <label for="filterDemirbas" class="segmented-control-label"><i class="bx bx-package me-1 fs-5"></i> Demirbaş</label>

                            <input type="radio" name="zimmetFilter" id="filterSayac" value="sayac" class="segmented-control-input zimmet-filter">
                            <label for="filterSayac" class="segmented-control-label"><i class="bx bx-tachometer me-1 fs-5"></i> Sayaç</label>

                            <input type="radio" name="zimmetFilter" id="filterAparat" value="aparat" class="segmented-control-input zimmet-filter">
                            <label for="filterAparat" class="segmented-control-label"><i class="bx bx-wrench me-1 fs-5"></i> Aparat</label>
                        </div>

                        <div class="status-filter-group ms-3 shadow-sm">
                            <input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-all" value="" checked>
                            <label class="btn px-3" for="zimmet-filter-all">
                                <i class="bx bx-check-double"></i> Tümü
                            </label>

                            <input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-teslim" value="teslim">
                            <label class="btn px-3" for="zimmet-filter-teslim">
                                <i class="bx bx-user-check"></i> Zimmetli
                            </label>

                            <input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-iade" value="iade">
                            <label class="btn px-3" for="zimmet-filter-iade">
                                <i class="bx bx-undo"></i> İade Alındı
                            </label>
                        </div>

                        <div class="col-md-3 ms-auto pe-2 zimmet-filter-right d-flex align-items-center">
                            <?php
                            $personelOptions = ['all' => 'Tüm Personeller'];
                            foreach ($personeller as $p) {
                                $personelOptions[$p->id] = $p->adi_soyadi;
                            }
                            echo Form::FormSelect2('zimmet_personel_filtre', $personelOptions, 'all', 'Personel Filtresi', 'users', 'key', '', 'form-control form-control-sm select2', false, 'width:100%', 'data-placeholder="Personel Filtresi"');
                            ?>
                        </div>
                        <div class="col-auto pe-2 zimmet-filter-right d-flex align-items-center">
                            <button type="button" class="btn btn-sm btn-soft-primary" id="btnAparatPersonelOzet">
                                <i class="bx bx-bar-chart-alt-2 me-1"></i> Aparat Özet
                            </button>
                        </div>
                    </div>
                    </div>
                </div>
            </div>

            <div class="accordion mb-4" id="zimmetStatsAccordion">
                <div class="accordion-item shadow-sm border-0 rounded-3 overflow-hidden">
                    <h2 class="accordion-header" id="headingZimmetStats">
                        <button class="accordion-button collapsed bg-white fw-bold text-dark py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapseZimmetStats" aria-expanded="false" aria-controls="collapseZimmetStats" style="box-shadow: none;">
                            <div class="d-flex w-100 align-items-center pe-3">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-pie-chart-alt-2 fs-5 me-2 text-warning"></i>
                                    <span class="text-nowrap">Zimmet Dağılım İstatistikleri</span>
                                    <small class="text-warning ms-2">(Grafik için tıklayınız)</small>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapseZimmetStats" class="accordion-collapse collapse" aria-labelledby="headingZimmetStats" data-bs-parent="#zimmetStatsAccordion">
                        <div class="accordion-body bg-white border-top">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="card shadow-none border mb-0">
                                        <div class="card-header bg-light py-2">
                                            <h6 class="mb-0 fw-bold small">Kategori Bazlı Dağılım</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div id="zimmetKategoriChart" style="min-height: 250px;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card shadow-none border mb-0">
                                        <div class="card-header bg-light py-2">
                                            <h6 class="mb-0 fw-bold small">Durum Bazlı Dağılım</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div id="zimmetDurumChart" style="min-height: 250px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="zimmetTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:3%">
                                <div class="custom-checkbox-container">
                                    <input type="checkbox" id="checkAllZimmet" class="custom-checkbox-input">
                                    <label for="checkAllZimmet" class="custom-checkbox-label"></label>
                                </div>
                            </th>
                            <th class="text-center" style="width:5%" data-filter="number">ID</th>
                            <th style="width:12%" data-filter="select">Kategori</th>
                            <th style="width:20%" data-filter="string">Demirbaş</th>
                            <th style="width:15%" data-filter="string">Marka/Model</th>
                            <th style="width:18%" data-filter="string">Personel</th>
                            <th style="width:8%" class="text-center" data-filter="number">Miktar</th>
                            <th style="width:12%" data-filter="date">Teslim Tarihi</th>
                            <th style="width:10%" data-filter="select" class="text-center">Durum</th>
                            <th style="width:5%" class="text-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="zimmetTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    var sayacKatIds = <?php echo json_encode($sayacKatIds); ?>;
    var aparatKatIds = <?php echo json_encode($aparatKatIds); ?>;
</script>

<?php include_once "modal/zimmet-modal.php" ?>
<?php include_once "modal/iade-modal.php" ?>
<?php include_once "modal/toplu-iade-modal.php" ?>
<?php include_once "modal/zimmet-detay-modal.php" ?>
<?php include_once "modal/aparat-personel-ozet-modal.php" ?>
