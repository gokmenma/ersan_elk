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

$duyurular = $duyuruModel->getAll();
$stats = $duyuruModel->getStats();

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

<div class="container-fluid">
    <?php
    $maintitle = "Kurumsal";
    $title = "Duyuru ve Etkinlik Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Üst İstatistik Kartları -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #667eea; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(102, 126, 234, 0.1);">
                            <i class="bx bx-news fs-4 text-primary"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TOPLAM</span>
                    </div>
                    <h4 class="mb-0 fw-bold bordro-text-heading"><?= $stats->toplam ?> <span
                            class="small text-muted fw-normal" style="font-size: 0.8rem;">Adet</span></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #10b981; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(16, 185, 129, 0.1);">
                            <i class="bx bx-check-circle fs-4 text-success"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">AKTİF</span>
                    </div>
                    <h4 class="mb-0 fw-bold bordro-text-heading"><?= $stats->aktif ?> <span
                            class="small text-muted fw-normal" style="font-size: 0.8rem;">Adet</span></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                <div class="card-body p-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                            <i class="bx bx-time-five fs-4 text-danger"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.65rem;">SÜRESİ DOLMUŞ</span>
                    </div>
                    <h4 class="mb-0 fw-bold bordro-text-heading"><?= $stats->suresi_dolmus ?> <span
                            class="small text-muted fw-normal" style="font-size: 0.8rem;">Adet</span></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 15px;">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">Duyuru Listesi</h5>
            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">

                <button type="button" class="btn btn-link btn-sm text-success text-decoration-none px-2"
                    id="btnExportExcel" title="Excel'e Aktar">
                    <i class="mdi mdi-file-excel fs-5"></i> Excele Aktar
                </button>
                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>

                <button type="button" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm text-nowrap"
                    data-bs-toggle="modal" data-bs-target="#duyuruModal" onclick="resetForm()" title="Yeni Duyuru">
                    <i class="mdi mdi-plus-circle fs-5 me-1"></i> Yeni
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="duyuruTable" class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Başlık</th>
                            <th>İçerik</th>
                            <th>Hedef</th>
                            <th>Kitle</th>
                            <th>Yayın / Etkinlik Tarihi</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duyurular as $idx => $d): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($d->resim): ?>
                                            <img src="<?= $d->resim ?>" class="rounded me-2"
                                                style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                style="width: 40px; height: 40px;">
                                                <i class="bx bx-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="fw-bold"><?= htmlspecialchars($d->baslik) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;"
                                        title="<?= htmlspecialchars($d->icerik) ?>">
                                        <?= htmlspecialchars($d->icerik) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <?php if ($d->ana_sayfada_goster): ?>
                                            <span class="badge bg-soft-primary text-primary px-2 py-1"
                                                style="font-size: 10px;">Admin Ana Sayfa</span>
                                        <?php endif; ?>
                                        <?php if ($d->pwa_goster): ?>
                                            <span class="badge bg-soft-info text-info px-2 py-1"
                                                style="font-size: 10px;">Personel Ana Sayfa</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($d->alici_tipi == 'toplu'): ?>
                                        <span class="badge bg-soft-success text-success">Tüm Personel</span>
                                    <?php else: ?>
                                        <span class="badge bg-soft-warning text-warning" title="<?= $d->alici_ids ?>">Özel
                                            (<?= count(explode(',', $d->alici_ids)) ?> kişi)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-muted">Yayın: <?= date('d.m.Y', strtotime($d->tarih)) ?></div>
                                    <?php if ($d->etkinlik_tarihi): ?>
                                        <div class="fw-bold text-primary">Etk:
                                            <?= date('d.m.Y', strtotime($d->etkinlik_tarihi)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d->durum === 'Taslak'): ?>
                                        <div class="mb-1"><span class="badge bg-secondary">Taslak</span></div>
                                    <?php elseif ($d->durum === 'Kapalı'): ?>
                                        <div class="mb-1"><span class="badge bg-dark">Kapalı</span></div>
                                    <?php else: ?>
                                        <div class="mb-1"><span class="badge bg-success">Yayında</span></div>
                                    <?php endif; ?>

                                    <?php
                                    $isExpired = $d->etkinlik_tarihi && $d->etkinlik_tarihi < date('Y-m-d');
                                    if ($isExpired): ?>
                                        <span class="badge bg-danger">Süresi Doldu</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Güncel</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-soft-primary btn-sm btn-edit" data-id="<?= $d->id ?>">
                                        <i class="bx bx-edit-alt"></i>
                                    </button>
                                    <button class="btn btn-soft-danger btn-sm btn-delete" data-id="<?= $d->id ?>">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="duyuruModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 45px; height: 45px;">
                        <i data-feather="plus-circle" class="text-success" style="width: 24px; height: 24px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalTitle">Yeni Duyuru Ekle</h5>
                        <small class="text-muted">Gerekli bilgileri doldurup hedef kitleyi seçin.</small>
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
                        <div class="col-lg-12 p-4 transition-all" id="formColumn" style="transition: all 0.3s ease;">
                            <div class="row g-3">
                                <div class="col-12" id="resimRow">
                                    <label class="form-label text-muted small mb-1">Duyuru Resmi</label>
                                    <div id="imageUploadZone"
                                        class="border-2 border-dashed rounded-3 p-3 text-center position-relative bg-light"
                                        style="cursor: pointer; transition: all 0.2s; border-color: #dee2e6 !important;">
                                        <input type="file" name="resim" id="duyuruResim" accept="image/*"
                                            class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                                            style="cursor:pointer; z-index:2;">
                                        
                                        <div id="uploadPlaceholder">
                                            <i class="mdi mdi-image-plus text-primary fs-2"></i>
                                            <p class="fw-semibold mb-0">Görsel Seçin</p>
                                        </div>
                                        
                                        <div id="uploadPreview" class="d-none position-relative" style="z-index: 3;">
                                            <div class="position-relative d-inline-block">
                                                <img id="previewImage" src="" class="rounded shadow-sm" style="max-height: 100px; max-width: 100%;">
                                                <button type="button" id="btnRemoveImage" class="btn btn-danger btn-sm position-absolute top-0 end-0 translate-middle rounded-circle p-0" style="width: 20px; height: 20px;">
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
                                    <?= Form::FormFloatTextarea('icerik', '', 'Duyuru içeriği...', 'İçerik', 'align-left', 'form-control', false, '120px') ?>
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
                                <div class="col-12">
                                    <div class="p-3 bg-light rounded border">
                                        <label class="fw-bold mb-2 small text-uppercase">Görünürlük</label>
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
                                <div class="col-12">
                                    <div class="p-3 bg-light rounded border">
                                        <label class="fw-bold mb-2 small text-uppercase">Hedef Kitle</label>
                                        <div class="d-flex gap-3">
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
                                    <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded-3 border">
                                        <div class="d-flex align-items-center gap-3 flex-grow-1">
                                            <!-- Departman Dropdown (Checklist) -->
                                            <div class="dropdown" id="deptDropdownContainer">
                                                <button class="filter-chip dropdown-toggle d-flex align-items-center" type="button" id="deptDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="padding: 8px 16px;">
                                                    <i class="bx bx-buildings me-1"></i> Departmanlar <span id="deptCountBadge" class="badge bg-primary ms-1 d-none" style="font-size: 10px;">0</span>
                                                </button>
                                                <div class="dropdown-menu p-3 shadow-lg border-0" aria-labelledby="deptDropdownBtn" style="min-width: 250px; border-radius: 15px; max-height: 350px; overflow-y: auto;">
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
                                            <button type="button" class="btn btn-primary btn-sm rounded-pill d-flex align-items-center px-3" id="btnSelectAll" title="Filtrelenmişleri Ekle">
                                                <i class="bx bx-plus fs-5"></i>
                                            </button>
                                            <button type="button" class="btn btn-soft-danger btn-sm rounded-pill d-flex align-items-center px-3" id="btnClearSelection" title="Temizle">
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
            <div class="modal-footer border-top bg-light py-3">
                <button type="button" class="btn btn-vazgec px-4" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" form="duyuruForm" class="btn btn-kaydet px-4 shadow-sm">
                    <i data-feather="save" class="me-1" style="width: 16px; height: 16px;"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const personelData = <?= json_encode($personelData) ?>;
    let selectedIds = [];

    $(document).ready(function () {
        const API_URL = 'views/duyuru/api.php';
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

        let dtOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
        dtOptions.buttons = [
            {
                extend: 'excel',
                text: 'Excel',
                className: 'd-none',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            }
        ];

        let localTable = $('#duyuruTable').DataTable(dtOptions);

        $('#btnExportExcel').click(function () {
            localTable.button('.buttons-excel').trigger();
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
            const formData = new FormData(this);

            Swal.fire({
                title: 'Kaydediliyor...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Başarılı', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Hata', data.message, 'error');
                    }
                });
        });

        $('#duyuruTable').on('click', '.btn-edit', function () {
            const id = $(this).data('id');
            resetForm();

            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get&id=' + id
            })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        const d = res.data;
                        $('#duyuruId').val(d.id);
                        $('#modalTitle').text('Duyuruyu Düzenle');
                        $('input[name="baslik"]').val(d.baslik);
                        $('textarea[name="icerik"]').val(d.icerik);
                        
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

                        $('#duyuruModal').modal('show');
                    }
                });
        });

        $('#duyuruTable').on('click', '.btn-delete', function () {
            const id = $(this).data('id');
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
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=delete&id=' + id
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire('Silindi!', data.message, 'success').then(() => location.reload());
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
    #imageUploadZone:hover {
        border-color: #556ee6 !important;
        background-color: rgba(85, 110, 230, 0.05) !important;
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
</style>