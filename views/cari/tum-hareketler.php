<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;
use App\Helper\Security;
use App\Model\CariModel;
use App\Model\CariHareketleriModel;

$maintitle = 'Cari Yönetimi';
$title = 'Tüm Hesap Hareketleri';

$CariModel = new CariModel();
$db = $CariModel->getDb();
$stmt = $db->query("SELECT id, CariAdi, firma FROM cari WHERE silinme_tarihi IS NULL ORDER BY CariAdi ASC");
$cariler = $stmt ? $stmt->fetchAll(PDO::FETCH_OBJ) : [];
$cariOptions = ['' => 'Tüm Cariler...'];
foreach ($cariler as $c) {
    $cariOptions[$c->id] = $c->CariAdi . (!empty($c->firma) ? ' (' . $c->firma . ')' : '');
}
?>
<script>try { document.documentElement.classList.toggle('tum-hareketler-summary-hidden', localStorage.getItem('tum_hareketler_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.tum-hareketler-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-history fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Tüm Hesap Hareketleri</h4>
                <p class="text-muted mb-0 font-size-12">Tüm cariler genelindeki tüm alacak, borç, fatura ve ödeme hareketleri dökümü</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0 flex-wrap">
            <!-- Carilere Dön Butonu -->
            <a href="index.php?p=cari/list" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                <i class="bx bx-arrow-back font-size-16"></i> <span class="d-none d-sm-inline">Carilere Dön</span>
            </a>

            <!-- İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnHeaderExportExcel">
                        <i class="bx bx-file me-2 font-size-16 text-success"></i> Excel'e Aktar
                    </button>
                    <a class="dropdown-item d-flex align-items-center" href="views/cari/export-tum-hareketler-pdf.php" target="_blank" id="btnHeaderExportPdf">
                        <i class="bx bx-file-blank me-2 font-size-16 text-danger"></i> PDF Döküm İndir
                    </a>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnHeaderPrint">
                        <i class="bx bx-printer me-2 font-size-16 text-secondary"></i> Yazdır
                    </button>
                </div>
            </div>

            <!-- Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM İŞLEM -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM İŞLEM</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-list-check"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_islem">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_islem_dagilimi">Giriş: 0 | Çıkış: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-type="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: TOPLAM VERDİM (ALACAK) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM VERDİM (ALACAK)</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-trending-up"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_toplam_alacak">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_verdim_sayi">0 İşlem</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-type="verdim">
                            <i class="bx bx-plus-circle"></i> Verdim
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: TOPLAM ALDIM (BORÇ) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM ALDIM (BORÇ)</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-trending-down"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_toplam_borc">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold" id="stat_sub_aldim_sayi">0 İşlem</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-type="aldim">
                            <i class="bx bx-minus-circle"></i> Aldım
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: GENEL BAKİYE (NET) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">GENEL BAKİYE (NET)</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-wallet"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_genel_bakiye">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_bakiye_durum_metni">Bakiye Durumu</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" id="stat_bakiye_badge">
                            Net Bakiye
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Filtre Barı (Tarih ve Cari Seçimi) -->
    <div class="card summary-kpi-card mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-4 col-12">
                    <?= Form::FormSelect2("filter_cari_id", $cariOptions, "", "Cariye Göre Filtrele", "user") ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= Form::FormFloatInput("text", "filter_start_date", date('Y-m-01'), "Başlangıç Tarihi", "Başlangıç Tarihi", "calendar", "form-control flatpickr-date", false, null, "off", false) ?>
                </div>
                <div class="col-md-3 col-6">
                    <?= Form::FormFloatInput("text", "filter_end_date", date('Y-m-t'), "Bitiş Tarihi", "Bitiş Tarihi", "calendar", "form-control flatpickr-date", false, null, "off", false) ?>
                </div>
                <div class="col-md-2 col-12 d-flex gap-2">
                    <button type="button" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-1 shadow-xs" id="btnFilterApply">
                        <i class="bx bx-filter-alt"></i> Filtrele
                    </button>
                    <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center shadow-xs" id="btnFilterReset" title="Filtreleri Temizle">
                        <i class="bx bx-reset"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. DataTables Tüm Hareketler Listesi Kartı -->
    <div class="card summary-kpi-card mb-3" id="tumHareketlerCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Hesap Hareketleri Dökümü</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Canlı arama, gelişmiş sütun filtreleme ve işlem yönetimi</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnTableRefresh" title="Listeyi Yenile">
                    <i class="bx bx-refresh font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yenile</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="tumHareketlerTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th data-filter="none" style="width: 50px;" class="text-center">SIRA</th>
                            <th data-filter="date" style="width: 130px;">TARİH & SAAT</th>
                            <th data-filter="string">CARİ / FİRMA</th>
                            <th data-filter="select" style="width: 100px;" class="text-center">TÜR</th>
                            <th data-filter="string" style="width: 120px;">BELGE NO</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="number" class="text-end" style="width: 120px;">BORÇ (ALDIM)</th>
                            <th data-filter="number" class="text-end" style="width: 120px;">ALACAK (VERDİM)</th>
                            <th data-filter="string" style="width: 130px;">EKLEYEN</th>
                            <th data-filter="none" style="width: 110px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Hareket Düzenle Modalı -->
<div class="modal fade" id="hareketDuzenleModal" tabindex="-1" aria-labelledby="hareketDuzenleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center w-100">
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                        <i class="bx bx-edit font-size-24 text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="modal-title fw-bold mb-1" id="hareketDuzenleModalLabel">İşlemi Düzenle</h5>
                        <p class="text-muted small mb-0">Hesap hareketini güncelleyin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="hareketDuzenleForm">
                <input type="hidden" name="action" value="hizli-hareket-kaydet">
                <input type="hidden" name="hareket_id" id="edit_hareket_id" value="">
                <input type="hidden" name="cari_id" id="edit_cari_id" value="">
                
                <div class="modal-body px-4 pt-4 pb-2">
                    <div class="mb-4 d-flex justify-content-center gap-2">
                        <input type="radio" class="btn-check" name="type" id="edit_type_aldim" value="aldim" autocomplete="off">
                        <label class="btn btn-outline-danger flex-grow-1 fw-bold" for="edit_type_aldim"><i class="bx bx-minus-circle me-1"></i>Aldım</label>

                        <input type="radio" class="btn-check" name="type" id="edit_type_verdim" value="verdim" autocomplete="off">
                        <label class="btn btn-outline-success flex-grow-1 fw-bold" for="edit_type_verdim"><i class="bx bx-plus-circle me-1"></i>Verdim</label>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "islem_tarihi", "", "Tarih", "Tarih", "calendar", "form-control flatpickr-time-input", true) ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Tutar</label>
                        <?= Form::FormFloatInput("text", "tutar", "", "0.00", "İşlem Tutarı", "dollar-sign", "form-control money", true) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput("text", "belge_no", "", "Belge No", "Belge No", "hash", "form-control") ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatTextarea("aciklama", "", "Açıklama giriniz...", "Açıklama", "list", "form-control", false, "80px") ?>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4 justify-content-end">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary px-4">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/cari/js/tum-hareketler.js?v=<?= time() ?>"></script>
