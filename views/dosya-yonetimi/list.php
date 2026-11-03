<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';

use App\Helper\Form;
use App\Model\DosyaKlasorModel;
use App\Service\Gate;

$maintitle = 'Ana Sayfa';
$title = 'Dosya Yönetimi';

$yetki = [
    'yukle' => Gate::allows('dosya_yonetimi_yukle'),
    'duzenle' => Gate::allows('dosya_yonetimi_duzenle'),
    'sil' => Gate::allows('dosya_yonetimi_sil'),
    'paylas' => Gate::allows('dosya_yonetimi_paylas'),
    'klasor' => Gate::allows('dosya_yonetimi_klasor_yonet'),
];

$rolOptions = [];
if ($yetki['klasor']) {
    foreach ((new DosyaKlasorModel())->rolSecenekleri() as $rol) {
        $rolOptions[$rol->id] = $rol->role_name;
    }
}
$gunOptions = ['1' => '1 gün', '3' => '3 gün', '7' => '7 gün', '14' => '14 gün', '30' => '30 gün', '90' => '90 gün'];
?>
<script>try { document.documentElement.classList.toggle('dosya-summary-hidden', localStorage.getItem('dosya_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.dosya-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.dosya-tree { max-height: 560px; overflow: auto; }
.dosya-tree ul { list-style: none; margin: 0; padding-left: 14px; }
.dosya-tree > ul { padding-left: 0; }
.dosya-tree .node { display: flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 8px; cursor: pointer; font-size: 13px; }
.dosya-tree .node:hover { background: var(--bs-primary-bg-subtle); }
.dosya-tree .node.active { background: var(--bs-primary-bg-subtle); color: var(--bs-primary); font-weight: 600; }
.dosya-tree .node .cnt { margin-left: auto; font-size: 11px; opacity: .65; }
.dosya-tile { border: 1px solid var(--bs-border-color); border-radius: 12px; padding: 12px; cursor: pointer; height: 100%; display: flex; flex-direction: column; gap: 6px; background: var(--bs-body-bg); transition: box-shadow .15s ease, border-color .15s ease; }
.dosya-tile:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); border-color: var(--bs-primary-border-subtle); }
.dosya-tile .ico { font-size: 34px; line-height: 1; }
.dosya-tile .ad { font-size: 13px; font-weight: 600; word-break: break-word; }
.dosya-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-bottom: 1px solid var(--bs-border-color); cursor: pointer; }
.dosya-row:hover { background: var(--bs-primary-bg-subtle); }
.dosya-row .ico { font-size: 22px; }
.dosya-row .ad { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#dosyaDropZone.dragover { outline: 2px dashed var(--bs-primary); outline-offset: -6px; background: var(--bs-primary-bg-subtle); }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-folder-open fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Dosya Yönetimi</h4>
                <p class="text-muted mb-0 font-size-12">Şirket evrakları, bilançolar ve belgeler için klasörlü dosya arşivi</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <?php if ($yetki['yukle']): ?>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnDosyaYukle">
                <i class="bx bx-upload font-size-16"></i> Dosya Yükle
            </button>
            <?php endif; ?>
            <?php if ($yetki['klasor']): ?>
            <button type="button" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm" id="btnYeniKlasor">
                <i class="bx bx-folder-plus font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Yeni Klasör</span>
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını göster veya gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TOPLAM DOSYA</span>
                    <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-file"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_toplam_dosya">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext">Erişebildiğiniz dosyalar</span>
                    <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" id="btnTumDosyalar"><i class="bx bx-layer"></i> Tümü</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">KLASÖR</span>
                    <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-folder"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1 text-success" id="stat_toplam_klasor">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-success fw-semibold">Toplam klasör</span>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">BU AY YÜKLENEN</span>
                    <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-calendar-check"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1 text-info" id="stat_bu_ay">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext">Bu ay eklenen dosya</span>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TOPLAM BOYUT</span>
                    <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-hdd"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_toplam_boyut">0 MB</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-muted">Disk kullanımı</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Arşiv</span>
                </div>
            </div></div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="dosyaListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Dosyalar</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;" id="dosyaKonumBilgi">Tüm dosyalar</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <div class="input-group input-group-sm" style="width: 240px;">
                    <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                    <input type="search" class="form-control" id="dosyaArama" placeholder="Dosya ara..." autocomplete="off">
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Görünüm">
                    <button type="button" class="btn btn-outline-secondary bg-white dosya-gorunum" data-gorunum="liste" title="Liste"><i class="bx bx-list-ul"></i></button>
                    <button type="button" class="btn btn-outline-secondary bg-white dosya-gorunum" data-gorunum="klasor" title="Klasör"><i class="bx bx-folder"></i></button>
                    <button type="button" class="btn btn-outline-secondary bg-white dosya-gorunum" data-gorunum="izgara" title="Izgara"><i class="bx bx-grid-alt"></i></button>
                </div>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="row g-3">
                <div class="col-lg-3">
                    <div class="border rounded-3 p-2 dosya-tree" id="dosyaAgac" aria-label="Klasör ağacı"></div>
                </div>
                <div class="col-lg-9" id="dosyaDropZone">
                    <nav class="mb-2 font-size-13" id="dosyaYol" aria-label="Konum"></nav>

                    <div id="gorunumListe">
                        <div class="table-responsive" style="overflow-x: auto !important;">
                            <table id="dosyaTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                <thead>
                                    <tr>
                                        <th data-filter="string">DOSYA ADI</th>
                                        <th data-filter="string" style="width: 150px;">KLASÖR</th>
                                        <th data-filter="string" style="width: 80px;">TÜR</th>
                                        <th data-filter="none" style="width: 90px;" class="text-end">BOYUT</th>
                                        <th data-filter="string" style="width: 150px;">YÜKLEYEN</th>
                                        <th data-filter="none" style="width: 130px;">TARİH</th>
                                        <th data-filter="none" style="width: 130px;" class="text-center">İŞLEMLER</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="gorunumIcerik" class="d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="file" id="dosyaInput" class="d-none" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png">

<div class="modal fade" id="klasorAdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header premium-modal-header">
                <h5 class="modal-title fw-bold" id="klasorAdBaslik">Yeni Klasör</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <?= Form::FormFloatInput('text', 'ad_girdi', '', 'Ad', 'Ad', 'folder') ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-primary" id="btnAdKaydet">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tasiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header premium-modal-header">
                <h5 class="modal-title fw-bold">Taşı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <?= Form::FormSelect2('tasi_hedef', ['' => 'Ana Dizin'], '', 'Hedef Klasör', 'folder') ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-primary" id="btnTasiKaydet">Taşı</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paylasModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header premium-modal-header">
                <h5 class="modal-title fw-bold">Dosyayı Paylaş</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted font-size-13 mb-3" id="paylasDosyaAdi"></p>
                <div class="row g-3">
                    <div class="col-md-4"><?= Form::FormSelect2('paylas_gun', $gunOptions, '7', 'Geçerlilik', 'calendar') ?></div>
                    <div class="col-md-4"><?= Form::FormFloatInput('text', 'paylas_sifre', '', 'Şifre (isteğe bağlı)', 'Şifre', 'lock') ?></div>
                    <div class="col-md-4"><?= Form::FormFloatInput('number', 'paylas_limit', '', 'İndirme limiti (isteğe bağlı)', 'Limit', 'download') ?></div>
                </div>
                <div class="mt-3 d-none" id="paylasSonuc">
                    <div class="input-group">
                        <input type="text" class="form-control" id="paylasUrl" readonly>
                        <button type="button" class="btn btn-outline-primary" id="btnUrlKopyala"><i class="bx bx-copy"></i> Kopyala</button>
                    </div>
                </div>
                <h6 class="fw-bold mt-4 font-size-13">Mevcut Bağlantılar</h6>
                <div id="paylasListe" class="font-size-12"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" id="btnPaylasOlustur"><i class="bx bx-link"></i> Bağlantı Oluştur</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header premium-modal-header">
                <h5 class="modal-title fw-bold">Klasör Erişimi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted font-size-13">Rol seçilirse klasör ve alt klasörleri yalnızca seçilen rollere açılır. Boş bırakılırsa modül yetkisi olan herkes görür.</p>
                <?= Form::FormMultipleSelect2('klasor_roller', $rolOptions, [], 'Erişebilen Roller', 'shield', 'key') ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-primary" id="btnRolKaydet">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="onizlemeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header premium-modal-header">
                <h5 class="modal-title fw-bold" id="onizlemeBaslik">Önizleme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-0" id="onizlemeIcerik" style="min-height: 70vh;"></div>
        </div>
    </div>
</div>

<script>
    window.DOSYA_YETKI = <?= json_encode($yetki) ?>;
</script>
<script src="views/dosya-yonetimi/js/dosya-yonetimi.js?v=<?php echo time(); ?>"></script>
