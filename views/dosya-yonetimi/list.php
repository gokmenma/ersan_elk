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

.dosya-yan { position: sticky; top: 80px; align-self: flex-start; }
.dosya-tree { max-height: calc(100vh - 220px); overflow: auto; padding: 8px; border-radius: 14px; background: var(--bs-tertiary-bg); }
.dosya-tree ul { list-style: none; margin: 0; padding-left: 16px; }
.dosya-tree > ul { padding-left: 0; }
.dosya-tree .agac-ayrac { height: 1px; margin: 6px 4px; background: var(--bs-border-color); }
.dosya-tree .node { display: flex; align-items: center; gap: 6px; min-height: 34px; padding: 4px 8px; border-radius: 10px; cursor: pointer; font-size: 13px; color: var(--bs-body-color); transition: background .12s ease; }
.dosya-tree .node .ad { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dosya-tree .node.bolum { margin-top: 2px; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: .05em; }
.dosya-tree .node.bolum .ad { opacity: .85; }
.dosya-tree .node:hover { background: var(--bs-secondary-bg); }
.dosya-tree .node.active { background: var(--bs-primary-bg-subtle); color: var(--bs-primary); font-weight: 600; }
.dosya-tree .node .cnt { font-size: 11px; min-width: 22px; padding: 0 6px; border-radius: 999px; text-align: center; background: var(--bs-body-bg); opacity: .8; }
.dosya-tree .node .tg { width: 18px; height: 18px; flex: 0 0 18px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; font-size: 16px; opacity: .7; }
.dosya-tree .node .tg[data-tg]:hover { background: var(--bs-secondary-bg); opacity: 1; }
.dosya-tree .node > i.bx { font-size: 18px; }

.agac-baslik { display: flex; align-items: center; justify-content: space-between; min-height: 36px; margin-bottom: 6px; padding: 0 4px 0 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary-color); }
.agac-baslik > span { display: inline-flex; align-items: center; gap: 6px; }
.agac-yeni { width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 18px; }
.surukle { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 28px; margin-left: -4px; border-radius: 6px; cursor: grab; color: var(--bs-secondary-color); opacity: .5; font-size: 18px; }
.surukle:hover { opacity: 1; background: var(--bs-secondary-bg); }
.surukle:active { cursor: grabbing; }
.surukleniyor { opacity: .45; }
.dosya-tree li[data-fid] > .node, .dosya-tree .bolum-ul > li > .node.bolum { cursor: grab; }
#dosyaYol { display: flex; align-items: center; flex-wrap: wrap; gap: 2px; margin-bottom: 12px; padding: 6px 10px; border-radius: 12px; background: var(--bs-tertiary-bg); min-height: 38px; font-size: 13px; }
#dosyaYol:empty { display: none; }
#dosyaYol a { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 8px; color: var(--bs-body-color); font-weight: 600; text-decoration: none; }
#dosyaYol a:hover { background: var(--bs-secondary-bg); }
#dosyaYol a:last-child { color: var(--bs-primary); }
#dosyaYol > i { opacity: .5; }

.fi { width: 36px; height: 36px; flex: 0 0 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 20px; }
.fi-danger { background: var(--bs-danger-bg-subtle); } .fi-primary { background: var(--bs-primary-bg-subtle); }
.fi-success { background: var(--bs-success-bg-subtle); } .fi-info { background: var(--bs-info-bg-subtle); }
.fi-warning { background: var(--bs-warning-bg-subtle); } .fi-secondary { background: var(--bs-secondary-bg); }
.fi-bos { width: 36px; flex: 0 0 36px; }

.dosya-row { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 12px; cursor: pointer; transition: background .12s ease; }
.dosya-row + .dosya-row { margin-top: 2px; }
.dosya-row:hover { background: var(--bs-tertiary-bg); }
.dosya-row .ad { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dosya-row .ad small { display: block; font-weight: 500; font-size: 11px; opacity: .6; }
.dosya-row .kol { font-size: 12px; color: var(--bs-secondary-color); white-space: nowrap; }
.dosya-row .kol-boyut { width: 90px; text-align: right; }
.dosya-row .kol-kim { width: 150px; overflow: hidden; text-overflow: ellipsis; }
.dosya-row .kol-tarih { width: 130px; }
.dosya-row .kol-act { width: 190px; display: flex; justify-content: flex-end; }
.dosya-row .kol-act .action-btn-group { opacity: .8; transition: opacity .12s ease; }
.dosya-row:hover .kol-act .action-btn-group, .dosya-row:focus-within .kol-act .action-btn-group { opacity: 1; }
.dosya-row-head { cursor: default; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary-color); border-bottom: 1px solid var(--bs-border-color); border-radius: 0; margin-bottom: 4px; }
.dosya-row-head:hover { background: transparent; }
.dosya-row-head .ad { font-size: 11px; font-weight: 700; }
@media (max-width: 991px) { .dosya-row .kol-kim, .dosya-row .kol-tarih { display: none; } .dosya-row .kol-act { width: auto; } }

.dosya-tile { display: flex; flex-direction: column; gap: 8px; height: 100%; padding: 14px; border: 1px solid var(--bs-border-color); border-radius: 16px; cursor: pointer; background: var(--bs-body-bg); transition: box-shadow .15s ease, border-color .15s ease, transform .15s ease; }
.dosya-tile:hover { box-shadow: 0 8px 22px rgba(0, 0, 0, .08); border-color: var(--bs-primary-border-subtle); transform: translateY(-1px); }
.dosya-tile .ad { font-size: 13px; font-weight: 600; word-break: break-word; }
.dosya-tile .action-btn-group { margin-top: auto; opacity: .8; transition: opacity .12s ease; }
.dosya-tile:hover .action-btn-group { opacity: 1; }
@media (prefers-reduced-motion: reduce) { .dosya-tile { transition: none; } .dosya-tile:hover { transform: none; } }

.dosya-bos { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 56px 16px; text-align: center; color: var(--bs-secondary-color); }
.dosya-bos strong { color: var(--bs-body-color); font-size: 15px; }
.dosya-bos p { margin: 0 0 8px; font-size: 13px; max-width: 420px; }
.dosya-bos-ikon { width: 72px; height: 72px; display: inline-flex; align-items: center; justify-content: center; border-radius: 22px; font-size: 34px; background: var(--bs-primary-bg-subtle); color: var(--bs-primary); }

#dosyaDropZone { position: relative; border-radius: 14px; min-height: 280px; }
#dosyaDropZone.dragover { outline: 2px dashed var(--bs-primary); outline-offset: -4px; background: var(--bs-primary-bg-subtle); }
#dosyaDropZone.dragover::after { content: 'Yüklemek için dosyaları bırakın'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; color: var(--bs-primary); pointer-events: none; }
 .dosya-arama-kutu { display: flex; align-items: center; gap: 8px; width: 260px; max-width: 100%; height: 38px; margin: 0; padding: 0 12px; border: 1px solid var(--bs-border-color); border-radius: 12px; background: var(--bs-body-bg); color: var(--bs-secondary-color); transition: border-color .15s ease, box-shadow .15s ease; }
.dosya-arama-kutu:focus-within { border-color: var(--bs-primary); box-shadow: 0 0 0 3px var(--bs-primary-bg-subtle); }
.dosya-arama-kutu i { font-size: 18px; }
.dosya-arama-kutu input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-size: 13px; color: var(--bs-body-color); }
.dosya-segment { display: inline-flex; gap: 2px; padding: 3px; border-radius: 12px; background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); }
.dosya-segment .dosya-gorunum { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border: 0; border-radius: 9px; background: transparent; color: var(--bs-secondary-color); font-size: 12px; font-weight: 600; transition: background .15s ease, color .15s ease; }
.dosya-segment .dosya-gorunum i { font-size: 16px; }
.dosya-segment .dosya-gorunum:hover { color: var(--bs-body-color); background: var(--bs-secondary-bg); }
.dosya-segment .dosya-gorunum.active { background: var(--bs-primary); color: #fff; box-shadow: 0 2px 6px rgba(0, 0, 0, .18); }
@media (max-width: 575px) { .dosya-segment .dosya-gorunum span { display: none; } }
#dosyaTable tbody tr { cursor: pointer; }
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
                <label class="dosya-arama-kutu">
                    <i class="bx bx-search"></i>
                    <input type="search" id="dosyaArama" placeholder="Dosya ara..." autocomplete="off" aria-label="Dosya ara">
                </label>
                <div class="dosya-segment" role="group" aria-label="Görünüm">
                    <button type="button" class="dosya-gorunum" data-gorunum="liste" title="Liste görünümü"><i class="bx bx-list-ul"></i><span>Liste</span></button>
                    <button type="button" class="dosya-gorunum" data-gorunum="klasor" title="Klasör görünümü"><i class="bx bx-folder-open"></i><span>Klasör</span></button>
                    <button type="button" class="dosya-gorunum" data-gorunum="izgara" title="Izgara görünümü"><i class="bx bx-grid-alt"></i><span>Izgara</span></button>
                </div>
            </div>
        </div>

        <div class="card-body p-3 pt-4">
            <div class="row g-3">
                <div class="col-lg-3 col-xl-3"><div class="dosya-yan">
                    <div class="agac-baslik"><span><i class="bx bx-folder"></i> Klasörler</span>
                        <?php if ($yetki['klasor']): ?><button type="button" class="btn btn-sm btn-subtle-primary agac-yeni" id="btnAgacYeni" title="Yeni Klasör" aria-label="Yeni klasör"><i class="bx bx-plus"></i></button><?php endif; ?></div>
                    <div class="dosya-tree" id="dosyaAgac" aria-label="Klasör ağacı"></div>
                    <p class="text-muted font-size-12 mt-2 mb-0 px-1"><i class="bx bx-info-circle"></i> Dosyaları pencereye sürükleyip bırakarak yükleyebilirsiniz.</p></div>
                </div>
                <div class="col-lg-9"><div id="dosyaDropZone">
                    <nav id="dosyaYol" aria-label="Konum"></nav>

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
                </div></div>
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

<div class="modal fade" id="erisimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header premium-modal-header">
                <h5 class="modal-title fw-bold">Kullanıcılarla Paylaş</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted font-size-13 mb-3" id="erisimNesneAdi"></p>
                <div class="row g-2 align-items-center">
                    <div class="col-md-6"><?= Form::FormSelect2('erisim_kullanici', ['' => 'Kullanıcı seçin'], '', 'Kullanıcı', 'user') ?></div>
                    <div class="col-md-4"><?= Form::FormSelect2('erisim_seviye', ['goruntule' => 'Görüntüleyebilir', 'duzenle' => 'Düzenleyebilir'], 'goruntule', 'Yetki', 'key') ?></div>
                    <div class="col-md-2 d-grid"><button type="button" class="btn btn-primary" id="btnErisimEkle">Ekle</button></div>
                </div>
                <h6 class="fw-bold mt-4 font-size-13">Erişimi Olanlar</h6>
                <div id="erisimListe" class="font-size-12"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
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
                <p class="text-muted font-size-13">Ortak alandaki klasör için rol seçilirse klasör ve alt klasörleri yalnızca seçilen rollere (ve ayrıca paylaşım verilen kullanıcılara) açılır. Boş bırakılırsa modül yetkisi olan herkes görür.</p>
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
    window.DOSYA_CSRF = <?= json_encode(\App\Helper\Security::csrf()) ?>;
</script>
<script src="views/dosya-yonetimi/js/dosya-yonetimi.js?v=<?php echo time(); ?>"></script>
