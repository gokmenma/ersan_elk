<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Model\DemirbasModel;

$Demirbas = new DemirbasModel();

// Global değişkenler modal yüklemeleri için
$sayacKatIds = []; 
$aparatKatIds = [];
// Aslında servis panelinde bunlara pek ihtiyaç yok ancak demirbas.js modals bağımlı ise tanımlanabilir

?>
<div class="container-fluid">
    <?php
    $maintitle = "Demirbaş";
    $title = "Servis Kayıtları";
    include 'layouts/breadcrumb.php';
    ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3 align-items-center">
                        <div class="col-sm-4">
                            <h4 class="card-title mb-0">
                                <i class="bx bx-wrench text-primary me-2"></i>Servis ve Bakım Kayıtları
                            </h4>
                        </div>
                        <div class="col-sm-8">
                            <div class="d-flex flex-wrap justify-content-sm-end gap-2 align-items-center">
                                <form id="servisFilterForm" class="d-flex gap-2">
                                    <input type="date" class="form-control form-control-sm" id="servis_filtre_baslangic" title="Servis Başlangıç Tarihi">
                                    <input type="date" class="form-control form-control-sm" id="servis_filtre_bitis" title="Servis Bitiş Tarihi">
                                    <button type="button" id="btnServisFiltrele" class="btn btn-sm btn-info text-nowrap">
                                        <i class="bx bx-filter-alt"></i> Filtrele
                                    </button>
                                </form>
                                <button type="button" class="btn btn-primary btn-sm waves-effect waves-light shadow-sm" id="btnYeniServis" data-bs-toggle="modal" data-bs-target="#servisModal">
                                    <i class="mx-1 bx bx-plus"></i> Yeni Servis Kaydı
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="servisTable" class="table table-bordered table-hover dt-responsive nowrap w-100 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" width="5%">No</th>
                                    <th>Cihaz / Demirbaş</th>
                                    <th class="text-center">Servise Gidiş</th>
                                    <th class="text-center">İade Tarihi</th>
                                    <th>Servis / Firma</th>
                                    <th>Teslim Eden</th>
                                    <th>İşlem Detayı</th>
                                    <th class="text-end">Tutar</th>
                                    <th class="text-center">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via Ajax -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Servis modal'ını dahil et ki dışarıda çalışan demirbas.js uyumlu olsun.
include_once "modal/servis-modal.php"; 
?>

<!-- Servis Sayfasına Özel JS ve Modallar için Ortak JS -->
<script src="views/demirbas/js/demirbas.js?v=<?= time() ?>"></script>
<script src="views/demirbas/js/servis.js?v=<?= time() ?>"></script>
