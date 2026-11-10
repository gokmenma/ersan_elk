<?php

use App\Model\DuyuruModel;
use App\Model\PersonelModel;
use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Security;

$duyuruModel = new DuyuruModel();
$personelModel = new PersonelModel();
$tanimlamalarModel = new \App\Model\TanimlamalarModel();
$aracZimmetModel = new \App\Model\AracZimmetModel();


// Departmanları getir (Personel tablosundan direkt çekiyoruz)
$db = $personelModel->getDb();
$stmt = $db->prepare("SELECT DISTINCT departman FROM personel WHERE departman IS NOT NULL AND departman != '' AND firma_id = ? ORDER BY departman ASC");
$stmt->execute([$_SESSION['firma_id']]);
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Araç zimmeti olanları getir
$aktifZimmetler = $aracZimmetModel->getAktifZimmetler();
$aracliPersonelIds = array_column($aktifZimmetler, 'personel_id');

// Ekip şeflerini getir
$db = $personelModel->getDb();
$stmt = $db->prepare("SELECT DISTINCT personel_id FROM personel_ekip_gecmisi WHERE ekip_sefi_mi = 1 AND (bitis_tarihi IS NULL OR bitis_tarihi >= CURDATE()) AND firma_id = ?");
$stmt->execute([$_SESSION['firma_id']]);
$ekipSefiIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$personeller = $personelModel->all(true);
$personelData = [];
$personelList = []; // Select2 fallback için hâlâ kalsın
$aracliIdsStr = array_map('strval', $aracliPersonelIds);
$ekipSefiIdsStr = array_map('strval', $ekipSefiIds);

foreach ($personeller as $p) {
    $isAracli = in_array((string)$p->id, $aracliIdsStr);
    $isEkipSefi = in_array((string)$p->id, $ekipSefiIdsStr);
    
    $personelData[] = [
        'id' => $p->id,
        'adi_soyadi' => $p->adi_soyadi,
        'departman' => $p->departman,
        'ekip_adi' => $p->ekip_adi,
        'is_aracli' => $isAracli,
        'is_ekip_sefi' => $isEkipSefi
    ];
    $personelList[$p->id] = $p->adi_soyadi;
}

?>

<script>try { document.documentElement.classList.toggle('duyuru-summary-hidden', localStorage.getItem('duyuru_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.duyuru-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php
$maintitle = "Kurumsal";
$title = "Duyuru ve Etkinlik Yönetimi";
include 'layouts/breadcrumb.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-news fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Duyurular ve Etkinlikler</h4>
                <p class="text-muted mb-0 font-size-12">Personele ve yönetime yönelik duyuruların yayın, hedef kitle ve süre yönetimi</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniDuyuru"
                data-bs-toggle="modal" data-bs-target="#duyuruModal">
                <i class="bx bx-plus font-size-16"></i> Yeni Duyuru
            </button>

            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM DUYURU</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-news"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_toplam">Yayında: 0 | Taslak: 0 | Kapalı: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">AKTİF YAYINDA</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-broadcast"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_aktif">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_aktif">Admin: 0 | Personel: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="published">
                            <i class="bx bx-check-circle"></i> Yayında
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TASLAK / KAPALI</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-edit"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_taslak_kapali">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_taslak">Taslak: 0 | Kapalı: 0</span>
                        <span class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="draft">
                                <i class="bx bx-edit"></i> Taslak
                            </button>
                            <button type="button" class="btn btn-sm btn-subtle-secondary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="closed">
                                <i class="bx bx-lock-alt"></i> Kapalı
                            </button>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">SÜRESİ DOLMUŞ</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-time-five"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_suresi_dolmus">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Bitiş tarihi geçmiş duyurular</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="expired">
                            <i class="bx bx-time-five"></i> Süresi Dolan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="duyuruListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Duyuru Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Anlık arama, sütun filtreleme ve yayın yönetimi</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="duyuruTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th data-filter="none" style="width: 50px;" class="text-center">SIRA</th>
                            <th data-filter="string">BAŞLIK</th>
                            <th data-filter="string">İÇERİK</th>
                            <th data-filter="select" data-filter-options="Admin Ana Sayfa|Personel Ana Sayfa|Gösterilmiyor">HEDEF</th>
                            <th data-filter="select" data-filter-options="Tüm Personel|Özel Liste" style="width: 150px;">KİTLE</th>
                            <th data-filter="date" style="width: 170px;">YAYIN / BİTİŞ</th>
                            <th data-filter="select" data-filter-options="Yayında|Taslak|Kapalı" style="width: 150px;">DURUM</th>
                            <th data-filter="none" style="width: 120px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="duyuruModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header duyuru-modal-header border-bottom">
                <div class="d-flex align-items-center">
                    <div class="duyuru-modal-icon me-3">
                        <i class="bx bx-megaphone"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalTitle">Yeni Duyuru Ekle</h5>
                        <small class="text-muted">İçerik ve yayın ayarları</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form id="duyuruForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="duyuruId">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="resim_sil" id="resimSil" value="0">
                    <div class="row g-0">
                        <!-- Form Kolonu: Dinamik Genişlik -->
                        <div class="col-lg-12 p-4 transition-all duyuru-form-column" id="formColumn" style="transition: all 0.3s ease;">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="duyuru-section-title">
                                        <i class="bx bx-edit-alt"></i>
                                        <span>Duyuru İçeriği</span>
                                    </div>
                                </div>
                                <div class="col-12" id="resimRow">
                                    <label class="form-label text-muted small mb-1 fw-semibold">Kapak Görseli</label>
                                    <div id="imageUploadZone"
                                        class="duyuru-upload-zone border-2 border-dashed text-center position-relative">
                                        <input type="file" name="resim" id="duyuruResim" accept="image/*"
                                            class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                                            style="cursor:pointer; z-index:2;">
                                        
                                        <div id="uploadPlaceholder">
                                            <i class="mdi mdi-image-plus text-primary"></i>
                                            <span class="fw-semibold">Görsel Seçin</span>
                                            <span class="upload-meta">PNG, JPG veya WEBP</span>
                                        </div>
                                        
                                        <div id="uploadPreview" class="d-none position-relative" style="z-index: 3;">
                                            <div class="position-relative d-inline-block">
                                                <img id="previewImage" src="" class="shadow-sm">
                                                <button type="button" id="btnRemoveImage" class="btn btn-danger btn-sm position-absolute top-0 end-0 translate-middle rounded-circle p-0" title="Görseli kaldır" aria-label="Görseli kaldır">
                                                    <i data-feather="x" style="width: 12px; height: 12px;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <?= Form::FormFloatInput('text', 'baslik', '', 'Duyuru başlığı...', 'Başlık *', 'type', 'form-control', true) ?>
                                </div>
                                <div class="col-12">
                                    <label for="icerik" class="form-label text-muted small mb-1 fw-bold">İçerik</label>
                                    <textarea name="icerik" id="icerik" class="form-control" rows="6" placeholder="Duyuru içeriği..."></textarea>
                                </div>
                                <div class="col-12 mt-4">
                                    <div class="duyuru-section-title">
                                        <i class="bx bx-slider-alt"></i>
                                        <span>Yayın Ayarları</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <?= Form::FormFloatInput('date', 'etkinlik_tarihi', '', '', 'Bitiş Tarihi', 'calendar', 'form-control', false) ?>
                                </div>
                                <div class="col-md-6">
                                    <?= Form::FormSelect2('durum', ['Yayında' => 'Yayında', 'Taslak' => 'Taslak', 'Kapalı' => 'Kapalı'], 'Yayında', 'Durum', 'activity', 'key', '', 'form-select select2') ?>
                                </div>
                                <div class="col-12">
                                    <?= Form::FormFloatInput('text', 'hedef_sayfa', '', 'URL girin...', 'Hedef URL (Opsiyonel)', 'link', 'form-control') ?>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border">
                                        <label class="fw-bold mb-3 small">Gösterim Alanları</label>
                                        <div class="d-flex flex-column gap-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="ana_sayfada_goster" id="ana_sayfada_goster">
                                                <label class="form-check-label small" for="ana_sayfada_goster">Admin Ana Sayfası</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="pwa_goster" id="pwa_goster">
                                                <label class="form-check-label small" for="pwa_goster">Personel Ana Sayfası (PWA)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border">
                                        <label class="fw-bold mb-3 small">Hedef Kitle</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="alici_tipi" id="tipToplu" value="toplu" checked>
                                                <label class="form-check-label" for="tipToplu">Herkes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="alici_tipi" id="tipTekli" value="tekli">
                                                <label class="form-check-label" for="tipTekli">Özel Liste</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sağ Kolon: Personel Seçimi (Full Height) -->
                        <div class="col-lg-7 p-4 bg-white border-start" id="personelSecimContainer" style="display:none; min-height: 600px;">
                            <div class="d-flex flex-column h-100">
                                <!-- Filtreler Paneli (WhatsApp Style) -->
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded border">
                                        <div class="d-flex align-items-center gap-3 flex-grow-1">
                                            <!-- Departman Dropdown (Checklist) -->
                                            <div class="dropdown" id="deptDropdownContainer">
                                                <button class="filter-chip dropdown-toggle d-flex align-items-center" type="button" id="deptDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="padding: 8px 16px;">
                                                    <i class="bx bx-buildings me-1"></i> Departmanlar <span id="deptCountBadge" class="badge bg-primary ms-1 d-none" style="font-size: 10px;">0</span>
                                                </button>
                                                <div class="dropdown-menu p-3 shadow-lg border-0" aria-labelledby="deptDropdownBtn" style="min-width: 250px; border-radius: 8px; max-height: 350px; overflow-y: auto;">
                                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                                        <span class="fw-bold small text-muted text-uppercase" style="letter-spacing: 0.5px;">Departman Listesi</span>
                                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-danger small" id="btnClearDepts">Temizle</button>
                                                    </div>
                                                    <div id="deptCheckboxes">
                                                        <?php foreach ($departments as $dept): ?>
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input dept-checkbox" type="checkbox" value="<?= htmlspecialchars($dept) ?>" id="dept_<?= md5($dept) ?>" style="cursor: pointer;">
                                                                <label class="form-check-label small w-100" for="dept_<?= md5($dept) ?>" style="cursor: pointer; user-select: none;">
                                                                    <?= htmlspecialchars($dept) ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Hızlı Filtre Chip'leri (Şef ve Araç) -->
                                            <div class="d-flex align-items-center gap-2 border-start ps-3" id="statusFilters">
                                                <div class="filter-chip" data-type="sefi" data-value="1" style="padding: 8px 16px;"><i class="bx bx-star me-1"></i> Şefler</div>
                                                <div class="filter-chip" data-type="arac" data-value="1" style="padding: 8px 16px;"><i class="bx bx-car me-1"></i> Araçlılar</div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2 ms-3 border-start ps-3">
                                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center justify-content-center" id="btnSelectAll" title="Filtrelenmişleri ekle" aria-label="Filtrelenmiş personelleri ekle">
                                                <i class="bx bx-plus fs-5"></i>
                                            </button>
                                            <button type="button" class="btn btn-soft-danger btn-sm d-flex align-items-center justify-content-center" id="btnClearSelection" title="Seçimi temizle" aria-label="Personel seçimini temizle">
                                                <i class="bx bx-trash-alt fs-5"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 flex-grow-1">
                                    <!-- Sol Liste: Tüm Personeller -->
                                    <div class="col-md-6 h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label class="fw-bold small mb-0"><i class="bx bxs-user-account me-1"></i> Personeller (<span id="availableCount">0</span>)</label>
                                        </div>
                                        <div class="mb-3">
                                            <?= Form::FormFloatInput('text', 'searchAvailable', '', 'İsim ile ara...', 'Personel Ara', 'bx bx-search', 'form-control') ?>
                                        </div>
                                        <div id="availableList" class="personel-list-container border rounded bg-white p-1 flex-grow-1" style="min-height: 400px; max-height: 500px; overflow-y: auto;">
                                            <!-- JS ile doldurulacak -->
                                        </div>
                                    </div>

                                    <!-- Sağ Liste: Seçilen Personeller -->
                                    <div class="col-md-6 h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label class="fw-bold small mb-0 text-primary"><i class="bx bxs-user-check me-1"></i> Seçilenler (<span id="selectedCountDisplay">0</span>)</label>
                                        </div>
                                        <div class="mb-3">
                                            <?= Form::FormFloatInput('text', 'searchSelected', '', 'Seçilenlerde ara...', 'Seçilen Ara', 'bx bx-search', 'form-control') ?>
                                        </div>
                                        <div id="selectedList" class="personel-list-container border-primary-subtle border border-2 border-dashed rounded bg-light p-1 flex-grow-1" style="min-height: 400px; max-height: 500px; overflow-y: auto;">
                                            <!-- JS ile doldurulacak -->
                                        </div>
                                        <!-- Gizli select elemanı form gönderimi için -->
                                        <select name="personel_ids[]" id="personel_ids" multiple style="display:none;"></select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer duyuru-modal-footer border-top py-3">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" form="duyuruForm" class="btn btn-primary px-4">
                    <i data-feather="save" class="me-1" style="width: 16px; height: 16px;"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const personelData = <?= json_encode($personelData) ?>;
    let selectedIds = [];

    function prepareContentForEditor(rawContent) {
        if (!rawContent) return '';
        let content = rawContent;

        // Yalnızca HTML etiketlerinin tamamı encode edilmişse çöz. Metin içindeki
        // "Ayarlar &gt; Bildirimler" gibi karakterler mevcut HTML'i bozmamalı.
        const hasHtmlTags = /<[a-z][\s\S]*>/i.test(content);
        const hasEncodedHtmlTags = /&lt;\/?[a-z][^&]*&gt;/i.test(content);
        if (!hasHtmlTags && hasEncodedHtmlTags) {
            const doc = new DOMParser().parseFromString(content, 'text/html');
            content = doc.documentElement.textContent || content;
        }

        // If content does NOT contain HTML tags (<p>, <br>, <div>, <span>, etc.), convert plain text newlines to HTML paragraphs
        if (!/<[a-z][\s\S]*>/i.test(content)) {
            content = content
                .split(/\r?\n\r?\n/)
                .map(para => '<p>' + para.replace(/\r?\n/g, '<br>') + '</p>')
                .join('');
        }

        return content;
    }

    $(document).ready(function () {
        const API_URL = 'views/duyuru/api.php?v=<?= (int) filemtime(__DIR__ . '/api.php') ?>';
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Flatpickr Başlatma
        if ($('input[name="etkinlik_tarihi"]').length > 0) {
            $('input[name="etkinlik_tarihi"]').flatpickr({
                locale: 'tr',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd.m.Y'
            });
        }

        // Summernote Text Editor Başlatma
        if ($('#icerik').length > 0 && typeof $.fn.summernote !== 'undefined') {
            $('#icerik').summernote({
                height: 220,
                lang: 'tr-TR',
                placeholder: 'Duyuru içeriğini buraya yazabilirsiniz...',
                toolbar: [
                    ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview']]
                ]
            });
        }

        // Departman Filtresi (Checkbox) Değişimi
        $(document).on('change', '.dept-checkbox', function() {
            updateDeptFilterUI();
            renderPersonnelLists();
        });

        $(document).on('click', '#btnClearDepts', function(e) {
            e.preventDefault();
            $('.dept-checkbox').prop('checked', false);
            updateDeptFilterUI();
            renderPersonnelLists();
        });

        function updateDeptFilterUI() {
            const count = $('.dept-checkbox:checked').length;
            if (count > 0) {
                $('#deptCountBadge').text(count).removeClass('d-none');
                $('#deptDropdownBtn').addClass('active');
            } else {
                $('#deptCountBadge').addClass('d-none');
                $('#deptDropdownBtn').removeClass('active');
            }
        }

        // Resim Silme Butonu
        $('#btnRemoveImage').click(function(e) {
            e.preventDefault();
            e.stopPropagation(); // Parent click'i engelle
            $('#duyuruResim').val('');
            $('#previewImage').attr('src', '');
            $('#uploadPreview').addClass('d-none');
            $('#uploadPlaceholder').removeClass('d-none');
            $('#resimSil').val('1');
        });

        $('#duyuruResim').on('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                this.value = '';
                Swal.fire('Geçersiz Dosya', 'Lütfen bir görsel dosyası seçin.', 'warning');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                $('#previewImage').attr('src', event.target.result);
                $('#uploadPlaceholder').addClass('d-none');
                $('#uploadPreview').removeClass('d-none');
                $('#resimSil').val('0');
            };
            reader.onerror = function () {
                $('#duyuruResim').val('');
                Swal.fire('Görsel Okunamadı', 'Seçilen görselin önizlemesi oluşturulamadı.', 'error');
            };
            reader.readAsDataURL(file);
        });

        const toggleBtn = $('#btnToggleSummaryCards');
        const updateToggleState = () => {
            const isHidden = $('html').hasClass('duyuru-summary-hidden');
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.attr('title', isHidden ? 'Özet Kartlarını Göster' : 'Özet Kartlarını Gizle');
            toggleBtn.attr('aria-label', isHidden ? 'Özet kartlarını göster' : 'Özet kartlarını gizle');
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
        };
        updateToggleState();
        toggleBtn.on('click', function () {
            const willHide = !$('html').hasClass('duyuru-summary-hidden');
            $('html').toggleClass('duyuru-summary-hidden', willHide);
            try { localStorage.setItem('duyuru_summary_cards_state', willHide ? 'hidden' : 'visible'); } catch (e) {}
            updateToggleState();
        });

        let currentStatusFilter = 'all';

        const localTable = $('#duyuruTable').DataTable(applyLengthStateSave({
            ...getDatatableOptions(),
            processing: true,
            serverSide: true,
            ajax: {
                url: API_URL,
                type: 'POST',
                data: function (d) {
                    d.action = 'ajax-list';
                    d.status_filter = currentStatusFilter;
                },
                dataSrc: function (json) {
                    const s = json.summary;
                    if (s) {
                        $('#stat_toplam').text(s.toplam);
                        $('#stat_sub_toplam').text(`Yayında: ${s.yayinda} | Taslak: ${s.taslak} | Kapalı: ${s.kapali}`);
                        $('#stat_aktif').text(s.aktif);
                        $('#stat_sub_aktif').text(`Admin: ${s.admin_ana_sayfa} | Personel: ${s.pwa_ana_sayfa}`);
                        $('#stat_taslak_kapali').text(s.taslak + s.kapali);
                        $('#stat_sub_taslak').text(`Taslak: ${s.taslak} | Kapalı: ${s.kapali}`);
                        $('#stat_suresi_dolmus').text(s.suresi_dolmus);
                    }
                    return json.data;
                }
            },
            columns: [
                { data: 'id', className: 'text-center', width: '50px' },
                { data: 'baslik' },
                { data: 'icerik' },
                { data: 'hedef' },
                { data: 'kitle', width: '150px' },
                { data: 'tarih', width: '170px', className: 'text-nowrap' },
                { data: 'durum', width: '150px' },
                { data: 'actions', className: 'text-center', width: '120px', orderable: false, searchable: false }
            ],
            createdRow: function (row) {
                $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
            },
            order: [[5, 'desc']]
        }));

        $('.status-quick-filter').on('click', function () {
            currentStatusFilter = $(this).data('status') || 'all';
            $('.status-quick-filter').removeClass('active');
            $(this).addClass('active');
            localTable.ajax.reload();
        });

        $('#btnHeaderPrint').on('click', function () {
            window.print();
        });

        $('#btnYeniDuyuru').on('click', function () {
            resetForm();
        });

        $('#duyuruTable tbody').on('click', 'tr td:not(:last-child)', function (e) {
            if ($(e.target).closest('a, button, .dropdown-menu').length > 0) return;
            $(this).closest('tr').find('.btn-edit').trigger('click');
        });

        $('input[name="alici_tipi"]').change(function () {
            if ($(this).val() === 'tekli') {
                $('#formColumn').removeClass('col-lg-12').addClass('col-lg-5');
                $('#personelSecimContainer').fadeIn();
            } else {
                $('#personelSecimContainer').hide();
                $('#formColumn').removeClass('col-lg-5').addClass('col-lg-12');
            }
        });

        $('#duyuruForm').submit(function (e) {
            e.preventDefault();
            if ($('#icerik').length > 0 && typeof $.fn.summernote !== 'undefined') {
                $('#icerik').val($('#icerik').summernote('code'));
            }
            const formData = new FormData(this);

            Swal.fire({
                title: 'Kaydediliyor...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch(API_URL, {
                method: 'POST',
                cache: 'no-store',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Başarılı', data.message, 'success').then(() => {
                            $('#duyuruModal').modal('hide');
                            localTable.ajax.reload(null, false);
                        });
                    } else {
                        Swal.fire('Hata', data.message, 'error');
                    }
                });
        });

        $('#duyuruTable').on('click', '.btn-edit', function () {
            const id = $(this).attr('data-id');
            resetForm();

            fetch(API_URL, {
                method: 'POST',
                cache: 'no-store',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get&id=' + encodeURIComponent(id)
            })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        const d = res.data;
                        $('#duyuruId').val(d.enc_id);
                        $('#modalTitle').text('Duyuruyu Düzenle');
                        $('input[name="baslik"]').val(d.baslik);
                        
                        const formattedContent = prepareContentForEditor(d.icerik);
                        if (!($('#icerik').length > 0 && typeof $.fn.summernote !== 'undefined')) {
                            $('textarea[name="icerik"]').val(d.icerik || '');
                        }
                        
                        if (d.etkinlik_tarihi) {
                            const fp = document.querySelector('input[name="etkinlik_tarihi"]')._flatpickr;
                            if (fp) fp.setDate(d.etkinlik_tarihi);
                        }
                        
                        $('input[name="hedef_sayfa"]').val(d.hedef_sayfa);
                        if (d.durum) {
                            $('select[name="durum"]').val(d.durum).trigger('change');
                        }

                        // Resim Gösterimi
                        if (d.resim) {
                            $('#previewImage').attr('src', d.resim);
                            $('#uploadPlaceholder').addClass('d-none');
                            $('#uploadPreview').removeClass('d-none');
                            $('#fileNameDisplay').text('Mevcut Resim');
                        }

                        if (d.ana_sayfada_goster == 1) $('#ana_sayfada_goster').prop('checked', true);
                        if (d.pwa_goster == 1) $('#pwa_goster').prop('checked', true);

                        if (d.alici_tipi == 'tekli') {
                            $('#tipTekli').prop('checked', true).trigger('change');
                            selectedIds = d.alici_ids ? d.alici_ids.split(',').map(String) : [];
                            renderPersonnelLists();
                        } else {
                            $('#tipToplu').prop('checked', true).trigger('change');
                        }

                        $('#duyuruModal')
                            .one('shown.bs.modal', function () {
                                if ($('#icerik').length > 0 && typeof $.fn.summernote !== 'undefined') {
                                    $('#icerik').summernote('code', formattedContent);
                                }
                            })
                            .modal('show');
                    }
                });
        });

        $('#duyuruTable').on('click', '.btn-delete', function () {
            const id = $(this).attr('data-id');
            Swal.fire({
                title: 'Emin misiniz?',
                text: "Bu duyuru silinecektir!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, sil!',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(API_URL, {
                        method: 'POST',
                        cache: 'no-store',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=delete&id=' + encodeURIComponent(id)
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire('Silindi!', data.message, 'success').then(() => localTable.ajax.reload(null, false));
                            } else {
                                Swal.fire('Hata', data.message, 'error');
                            }
                        });
                }
            });
        });
    });

    function resetForm() {
        $('#duyuruForm')[0].reset();
        $('#duyuruId').val('');
        $('#modalTitle').text('Yeni Duyuru Ekle');
        $('select[name="durum"]').val('Yayında').trigger('change');
        const fp = document.querySelector('input[name="etkinlik_tarihi"]')._flatpickr;
        if (fp) fp.clear();
        
        if ($('#icerik').length > 0 && typeof $.fn.summernote !== 'undefined') {
            $('#icerik').summernote('code', '');
        }
        
        $('#previewImage').attr('src', '');
        $('#uploadPreview').addClass('d-none');
        $('#uploadPlaceholder').removeClass('d-none');
        $('#resimSil').val('0');
        
        $('#personelSecimContainer').hide();
        selectedIds = [];
        renderPersonnelLists();
    }

    // Personel Listesi İşlemleri
    function renderPersonnelLists() {
        const availableList = $('#availableList');
        const selectedList = $('#selectedList');
        const searchAvailable = $('#searchAvailable').val().toLowerCase();
        const searchSelected = $('#searchSelected').val().toLowerCase();
        
        // Get Active Filters
        const selectedDepts = $('.dept-checkbox:checked').map(function() { return $(this).val(); }).get();
        const filterEkipSefi = $('.filter-chip[data-type="sefi"].active').length > 0;
        const filterAracli = $('.filter-chip[data-type="arac"].active').length > 0;

        availableList.empty();
        selectedList.empty();
        $('#personel_ids').empty();

        let availableCount = 0;
        let selectedCount = 0;

        personelData.forEach(p => {
            const pid = String(p.id);
            const isSelected = selectedIds.includes(pid);
            
            if (isSelected) {
                const matchesSearch = p.adi_soyadi.toLowerCase().includes(searchSelected);
                if (matchesSearch) {
                    selectedList.append(createPersonelItem(p, true));
                    selectedCount++;
                }
                $('#personel_ids').append(`<option value="${p.id}" selected>${p.adi_soyadi}</option>`);
            } else {
                const matchesSearch = p.adi_soyadi.toLowerCase().includes(searchAvailable);
                const matchesDept = selectedDepts.length === 0 || selectedDepts.includes(p.departman);
                const matchesEkipSefi = !filterEkipSefi || p.is_ekip_sefi;
                const matchesAracli = !filterAracli || p.is_aracli;

                if (matchesSearch && matchesDept && matchesEkipSefi && matchesAracli) {
                    availableList.append(createPersonelItem(p, false));
                    availableCount++;
                }
            }
        });

        $('#availableCount').text(availableCount + ' Kişi');
        $('#selectedCountDisplay').text(selectedCount + ' Kişi');

        if (selectedCount === 0) {
            selectedList.append('<div class="text-center p-5 text-muted small opacity-50">Lütfen soldan personel seçin</div>');
        }
        if (availableCount === 0) {
            availableList.append('<div class="text-center p-5 text-muted small opacity-50">Personel bulunamadı</div>');
        }
    }

    function createPersonelItem(p, isSelected) {
        const icon = isSelected ? 'bx-minus-circle text-danger' : 'bx-plus-circle text-success';
        const badges = [];
        if (p.is_ekip_sefi) badges.push('<span class="badge bg-primary" style="font-size: 8px;">Şef</span>');
        if (p.is_aracli) badges.push('<span class="badge bg-warning text-dark" style="font-size: 8px;">Araç</span>');
        
        return `
            <div class="personel-item d-flex align-items-center p-2 mb-1 border rounded bg-white shadow-sm" 
                 style="cursor: pointer; user-select: none;" onclick="togglePersonel('${p.id}')" draggable="true" ondragstart="handleDragStart(event, '${p.id}')">
                <div class="flex-grow-1">
                    <div class="fw-bold" style="font-size: 13px;">${p.adi_soyadi}</div>
                    <div class="text-muted d-flex align-items-center gap-1" style="font-size: 10px;">
                        <span>${p.departman || 'Bölüm Yok'}</span>
                        ${badges.join('')}
                    </div>
                </div>
                <i class="bx ${icon} fs-5"></i>
            </div>
        `;
    }

    function togglePersonel(id) {
        id = String(id);
        if (selectedIds.includes(id)) {
            selectedIds = selectedIds.filter(i => i !== id);
        } else {
            selectedIds.push(id);
        }
        renderPersonnelLists();
    }

    function handleDragStart(e, id) {
        e.originalEvent.dataTransfer.setData('text/plain', id);
    }

    // Drag and drop handlers
    $(document).on('dragover', '.personel-list-container', function(e) {
        e.preventDefault();
        $(this).addClass('bg-soft-primary border-primary');
    }).on('dragleave', '.personel-list-container', function(e) {
        $(this).removeClass('bg-soft-primary border-primary');
    }).on('drop', '#availableList', function(e) {
        e.preventDefault();
        $(this).removeClass('bg-soft-primary border-primary');
        const id = String(e.originalEvent.dataTransfer.getData('text'));
        if (selectedIds.includes(id)) {
            selectedIds = selectedIds.filter(i => i !== id);
            renderPersonnelLists();
        }
    }).on('drop', '#selectedList', function(e) {
        e.preventDefault();
        $(this).removeClass('bg-soft-primary border-primary');
        const id = String(e.originalEvent.dataTransfer.getData('text'));
        if (!selectedIds.includes(id)) {
            selectedIds.push(id);
            renderPersonnelLists();
        }
    });

    // Filtre Eventleri
    $(document).on('click', '.filter-chip', function() {
        $(this).toggleClass('active');
        renderPersonnelLists();
    });

    $(document).on('keyup', '#searchAvailable, #searchSelected', renderPersonnelLists);

    $(document).on('click', '#btnSelectAll', function() {
        const selectedDepts = $('.dept-checkbox:checked').map(function() { return $(this).val(); }).get();
        const filterEkipSefi = $('.filter-chip[data-type="sefi"].active').length > 0;
        const filterAracli = $('.filter-chip[data-type="arac"].active').length > 0;
        const searchAvailable = $('#searchAvailable').val().toLowerCase();

        personelData.forEach(p => {
            const pid = String(p.id);
            if (!selectedIds.includes(pid)) {
                const matchesSearch = p.adi_soyadi.toLowerCase().includes(searchAvailable);
                const matchesDept = selectedDepts.length === 0 || selectedDepts.includes(p.departman);
                const matchesEkipSefi = !filterEkipSefi || p.is_ekip_sefi;
                const matchesAracli = !filterAracli || p.is_aracli;

                if (matchesSearch && matchesDept && matchesEkipSefi && matchesAracli) {
                    selectedIds.push(pid);
                }
            }
        });
        renderPersonnelLists();
    });

    $(document).on('click', '#btnClearSelection', function() {
        selectedIds = [];
        renderPersonnelLists();
    });
</script>

<style>
    #duyuruModal .modal-content {
        border-radius: 8px !important;
    }

    .duyuru-thumb {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }

    .duyuru-thumb-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: #94a3b8;
    }

    .duyuru-title {
        max-width: 360px;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        color: #0f172a;
        font-size: 13px;
        font-weight: 700;
    }

    [data-theme-preset="macos-dark"] .duyuru-title {
        color: #f5f7fa;
    }

    .bg-soft-info {
        background-color: rgba(13, 202, 240, 0.1);
    }

    .filter-chip {
        padding: 6px 16px;
        border-radius: 20px;
        background-color: white;
        border: 1px solid #dee2e6;
        color: #6c757d;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
        white-space: nowrap;
        user-select: none;
    }

    .filter-chip:hover {
        background-color: #f8f9fa;
        border-color: #adb5bd;
    }

    .filter-chip.active {
        background-color: #e7f5ed;
        color: #128c7e;
        border-color: #128c7e;
    }

    .filter-chips-container::-webkit-scrollbar {
        height: 4px;
    }

    .filter-chips-container::-webkit-scrollbar-thumb {
        background: #dee2e6;
        border-radius: 10px;
    }

    .personel-item:hover {
        background-color: #f8f9fa !important;
        transform: translateY(-1px);
    }

    .personel-list-container::-webkit-scrollbar {
        width: 5px;
    }

    .personel-list-container::-webkit-scrollbar-track {
        background: transparent;
    }

    .personel-list-container::-webkit-scrollbar-thumb {
        background: #dee2e6;
        border-radius: 10px;
    }

    .custom-switch-primary .form-check-input:checked {
        background-color: #667eea;
        border-color: #667eea;
    }

    .custom-switch-warning .form-check-input:checked {
        background-color: #f59e0b;
        border-color: #f59e0b;
    }

    .personel-item i {
        transition: transform 0.2s;
    }

    .personel-item:hover i {
        transform: scale(1.2);
    }

    .personel-list-container {
        transition: all 0.2s;
    }

    .bg-soft-success {
        background-color: rgba(16, 185, 129, 0.1);
    }

    .bg-soft-info {
        background-color: rgba(14, 165, 233, 0.1);
    }

    .bg-soft-warning {
        background-color: rgba(245, 158, 11, 0.1);
    }

    .bg-soft-danger {
        background-color: rgba(244, 63, 94, 0.1);
    }

    .btn-soft-primary {
        color: #667eea;
        background-color: rgba(102, 126, 234, 0.1);
        border: none;
    }

    .btn-soft-primary:hover {
        background-color: #667eea;
        color: white;
    }

    .btn-soft-danger {
        color: #f43f5e;
        background-color: rgba(244, 63, 94, 0.1);
        border: none;
    }

    .btn-soft-danger:hover {
        background-color: #f43f5e;
        color: white;
    }

    .btn-white {
        background: white;
        color: #667eea;
        border: none;
    }

    .btn-white:hover {
        background: #f8f9fa;
        color: #764ba2;
    }

    .bg-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
    }

    /* Özel Buton Stilleri */
    #duyuruModal .modal-dialog {
        max-width: 1180px;
    }

    .duyuru-modal-header {
        padding: 16px 20px;
        background: #fff;
    }

    .duyuru-modal-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border-radius: 7px;
        color: #fff;
        background: #2563eb;
    }

    .duyuru-modal-icon i {
        font-size: 22px;
    }

    .duyuru-form-column {
        background: #fff;
    }

    #formColumn.col-lg-5 .col-md-6 {
        width: 100%;
    }

    .duyuru-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        padding-bottom: 9px;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .duyuru-section-title i {
        color: #2563eb;
        font-size: 17px;
    }

    .duyuru-upload-zone {
        min-height: 84px;
        padding: 16px;
        cursor: pointer;
        border-color: #cbd5e1 !important;
        border-radius: 7px;
        background: #f8fafc;
        transition: border-color 0.2s, background-color 0.2s;
    }

    #uploadPlaceholder {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        min-height: 50px;
    }

    #uploadPlaceholder > i {
        font-size: 27px;
    }

    #uploadPlaceholder .upload-meta {
        color: #94a3b8;
        font-size: 11px;
    }

    #previewImage {
        width: auto;
        max-width: 100%;
        height: 72px;
        border-radius: 6px;
        object-fit: cover;
    }

    #btnRemoveImage {
        width: 22px;
        height: 22px;
    }

    #btnSelectAll,
    #btnClearSelection {
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 6px;
    }

    #imageUploadZone:hover {
        border-color: #2563eb !important;
        background-color: #eff6ff !important;
    }

    #duyuruModal .bg-light.rounded.border {
        height: 100%;
        border-color: #e2e8f0 !important;
        border-radius: 7px !important;
        background: #f8fafc !important;
    }

    #duyuruModal .form-switch {
        display: flex;
        align-items: center;
        min-height: 26px;
        padding-left: 0;
    }

    #duyuruModal .form-switch .form-check-input {
        width: 34px;
        margin: 0 10px 0 0;
        flex: 0 0 34px;
    }

    .duyuru-modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 5;
        padding-left: 20px;
        padding-right: 20px;
        background: #fff;
        box-shadow: 0 -4px 12px rgba(15, 23, 42, 0.04);
    }

    .btn-vazgec {
        background-color: #74788d;
        color: #fff;
        border: none;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .btn-vazgec:hover {
        background-color: #636678;
        color: #fff;
        transform: translateY(-1px);
    }

    .btn-kaydet {
        background-color: #2a3042;
        color: #fff;
        border: none;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .btn-kaydet:hover {
        background-color: #1f2431;
        color: #fff;
        transform: translateY(-1px);
    }

    .modal-content {
        overflow: hidden;
    }

    [data-bs-theme="dark"] .duyuru-modal-header,
    [data-bs-theme="dark"] .duyuru-form-column,
    [data-bs-theme="dark"] .duyuru-modal-footer {
        background: #191e22;
    }

    [data-bs-theme="dark"] .duyuru-section-title {
        color: #e2e8f0;
        border-color: #32394e;
    }

    [data-bs-theme="dark"] .duyuru-upload-zone,
    [data-bs-theme="dark"] #duyuruModal .bg-light.rounded.border {
        background: #242b31 !important;
        border-color: #3b445e !important;
    }

    @media (max-width: 767.98px) {
        #duyuruModal .modal-dialog {
            margin: 0;
            min-height: 100%;
        }

        #duyuruModal .modal-content {
            min-height: 100vh;
            border-radius: 0 !important;
        }

        .duyuru-form-column,
        #personelSecimContainer {
            padding: 18px !important;
        }

        #uploadPlaceholder {
            flex-wrap: wrap;
        }

        #uploadPlaceholder .upload-meta {
            flex-basis: 100%;
        }
    }

    /* Summernote Editor Modal Compatibility */
    .note-editor.note-frame {
        border-radius: 7px;
        border: 1px solid #ced4da;
    }
    .note-editor .note-toolbar {
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        border-top-left-radius: 7px;
        border-top-right-radius: 7px;
    }
    .note-popover, .note-modal {
        z-index: 1060 !important;
    }
    .note-modal-backdrop {
        z-index: 1059 !important;
    }
</style>
