<?php
/**
 * Nöbet Talepleri & Mazeretler Sayfası
 * Değişim talepleri ve mazeret bildirimlerini yönetir
 */

use App\Model\PersonelModel;
use App\Helper\Form;

$Personel = new PersonelModel();
$personeller = $Personel->all(true, 'nobet');
?>
<link rel="stylesheet" href="views/nobet/assets/style.css?v=<?php echo filemtime('views/nobet/assets/style.css'); ?>">

<!-- Sayfa Başlığı -->
<?php
$maintitle = "Nöbet Yönetimi";
$title = 'Talepler & Mazeretler';
?>
<?php include 'layouts/breadcrumb.php'; ?>

<!-- İstatistik Kartları -->
<div class="row mb-4 g-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card"
            style="--card-color: #f59e0b; --card-rgb: 245, 158, 11; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body">
                <div class="icon-label-container">
                    <div class="icon-box">
                        <i class='bx bx-transfer-alt fs-4' style="color: var(--card-color);"></i>
                    </div>
                    <div class="stat-trend neutral">
                        <i class='bx bx-hourglass'></i> Beklemede
                    </div>
                </div>
                <p class="stat-label-main">BEKLEYEN DEĞİŞİM</p>
                <h4 class="stat-value" id="stat-degisim-bekleyen">0</h4>
                <p class="stat-sub">Onay bekleyen değişim talepleri</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card"
            style="--card-color: #ef4444; --card-rgb: 239, 68, 68; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body">
                <div class="icon-label-container">
                    <div class="icon-box">
                        <i class='bx bx-error-circle fs-4' style="color: var(--card-color);"></i>
                    </div>
                    <div class="stat-trend down">
                        <i class='bx bx-time-five'></i> Acil
                    </div>
                </div>
                <p class="stat-label-main">MAZERET BİLDİRİMİ</p>
                <h4 class="stat-value" id="stat-mazeret-bekleyen">0</h4>
                <p class="stat-sub">İşlem bekleyen mazeretler</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card"
            style="--card-color: #10b981; --card-rgb: 16, 185, 129; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body">
                <div class="icon-label-container">
                    <div class="icon-box">
                        <i class='bx bx-check-circle fs-4' style="color: var(--card-color);"></i>
                    </div>
                    <div class="stat-trend up">
                        <i class='bx bx-trending-up'></i> +15%
                    </div>
                </div>
                <p class="stat-label-main">BU AY ONAYLANAN</p>
                <h4 class="stat-value" id="stat-onaylanan">0</h4>
                <p class="stat-sub">Başarıyla tamamlanan</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card"
            style="--card-color: #64748b; --card-rgb: 100, 116, 139; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body">
                <div class="icon-label-container">
                    <div class="icon-box">
                        <i class='bx bx-x-circle fs-4' style="color: var(--card-color);"></i>
                    </div>
                    <div class="stat-trend neutral">
                        <i class='bx bx-minus'></i> Stabil
                    </div>
                </div>
                <p class="stat-label-main">BU AY REDDEDİLEN</p>
                <h4 class="stat-value" id="stat-reddedilen">0</h4>
                <p class="stat-sub">Reddedilen talepler</p>
            </div>
        </div>
    </div>
</div>

<!-- Ana İçerik -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <div class="card-header bg-white border-0 py-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-dark fw-extrabold" style="letter-spacing: -0.02em;">
                    <i class="bx bx-transfer-alt me-2 text-primary"></i>Talepler & Mazeretler
                </h5>
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 150px;">
                        <?php
                        $aylar = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
                        echo Form::FormSelect2('filter-ay', $aylar, date('n'), '', 'calendar');
                        ?>
                    </div>
                    <div style="width: 110px;">
                        <?php
                        $yillar = [];
                        for ($y = date('Y'); $y >= 2024; $y--) {
                            $yillar[$y] = $y;
                        }
                        echo Form::FormSelect2('filter-yil', $yillar, date('Y'), '', 'calendar');
                        ?>
                    </div>
                    <div class="btn-group gap-2">
                        <button class="btn btn-primary btn-sm px-3 rounded-pill" onclick="loadTaleplerVeMazeretler()">
                            <i class="bx bx-refresh"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body px-4 pt-0">
                <!-- Tabs and Filters -->
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom">
                    <ul class="nav nav-tabs nav-tabs-custom mb-0" id="talepTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="degisim-tab" data-bs-toggle="tab"
                                data-bs-target="#degisim-talepler" type="button" role="tab">
                                <span>Değişim Talepleri</span>
                                <span class="badge bg-warning-subtle text-warning" id="degisim-badge">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="mazeret-tab" data-bs-toggle="tab"
                                data-bs-target="#mazeret-bildirimleri" type="button" role="tab">
                                <span>Mazeretler</span>
                                <span class="badge bg-danger-subtle text-danger" id="mazeret-badge">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="nobet-talep-tab" data-bs-toggle="tab"
                                data-bs-target="#nobet-talepleri" type="button" role="tab">
                                <span>Talep ve İstekler</span>
                                <span class="badge bg-info-subtle text-info" id="nobet-talep-badge">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-info btn-sm rounded-pill px-3" id="btn-toggle-gecmis"
                            onclick="toggleGecmis()">
                            <i class="bx bx-history"></i> Geçmiş İşlemler
                        </button>
                        <button class="btn btn-success btn-sm rounded-pill px-3" onclick="exportToExcel()">
                            <i class="bx bx-spreadsheet"></i> Excel
                        </button>
                    </div>
                </div>

                <div class="tab-content" id="talepTabContent">
                    <!-- Değişim Talepleri Tab -->
                    <div class="tab-pane fade show active" id="degisim-talepler" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-premium align-middle" id="degisim-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Talep Eden</th>
                                        <th>Talep Edilen</th>
                                        <th>Nöbet Tarihi</th>
                                        <th>Açıklama</th>
                                        <th>Durum</th>
                                        <th>Talep Tarihi</th>
                                        <th class="text-center">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="degisim-tbody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bx bx-loader-alt bx-spin bx-lg"></i>
                                            <p class="mb-0 mt-2">Yükleniyor...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mazeret Bildirimleri Tab -->
                    <div class="tab-pane fade" id="mazeret-bildirimleri" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-premium align-middle" id="mazeret-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Personel</th>
                                        <th>Nöbet Tarihi</th>
                                        <th>Nöbet Saati</th>
                                        <th>Mazeret Açıklaması</th>
                                        <th>Bildirim Tarihi</th>
                                        <th class="text-center">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="mazeret-tbody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bx bx-loader-alt bx-spin bx-lg"></i>
                                            <p class="mb-0 mt-2">Yükleniyor...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Nöbet Talepleri Tab -->
                    <div class="tab-pane fade" id="nobet-talepleri" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-premium align-middle" id="nobet-talep-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Personel</th>
                                        <th>Talep Edilen Tarih</th>
                                        <th>Açıklama</th>
                                        <th>Durum</th>
                                        <th>Talep Tarihi</th>
                                        <th class="text-center">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="nobet-talep-tbody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bx bx-loader-alt bx-spin bx-lg"></i>
                                            <p class="mb-0 mt-2">Yükleniyor...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- Segmented Control CSS -->
<style>
    .segmented-control {
        display: flex;
        width: 100%;
        background-color: #f1f5f9;
        border-radius: 0.5rem;
        padding: 0.25rem;
        position: relative;
        border: 1px solid #e2e8f0;
    }
    .segmented-control input[type="radio"] { display: none; }
    .segmented-control label {
        flex: 1;
        text-align: center;
        padding: 0.5rem 1rem;
        cursor: pointer;
        border-radius: 0.375rem;
        font-weight: 500;
        font-size: 0.875rem;
        color: #64748b;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        user-select: none;
        margin-bottom: 0;
        line-height: 1.2;
    }
    .segmented-control input[type="radio"]:checked+label {
        background-color: #ffffff;
        color: #2563eb;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        font-weight: 600;
    }
    .segmented-control label:hover:not(:active) { color: #1e293b; }
</style>

<!-- Nöbet Devir Modal -->
<div class="modal fade" id="devirModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-light border-bottom">
                <div class="modal-title-section d-flex align-items-center">
                    <div class="avatar-xs me-2 rounded bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i data-feather="layers" class="text-primary" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <h6 class="modal-title text-dark mb-0 fw-bold">Nöbeti Devret</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.7rem;">Lütfen formu eksiksiz doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="devir-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="nobet_id" id="devir-nobet-id">
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput('text', 'devir-mevcut-personel', '', null, 'Mevcut Personel', 'user', 'form-control', false, null, 'on', true, '', true); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block fw-bold text-muted small text-uppercase mb-2">Personel Seçimi</label>
                        <div class="segmented-control mb-3">
                            <input type="radio" name="devir_personel_turu" id="devirPersonelTuruKesmeAcma" value="kesme_acma" checked>
                            <label for="devirPersonelTuruKesmeAcma">
                                <i data-feather="scissors" style="width:16px;height:16px;"></i> Kesme Açma
                            </label>
                            <input type="radio" name="devir_personel_turu" id="devirPersonelTuruTum" value="all">
                            <label for="devirPersonelTuruTum">
                                <i data-feather="users" style="width:16px;height:16px;"></i> Tüm Personeller
                            </label>
                        </div>
                        <?php
                        $kesmeAcmaPersoneller = array_filter($personeller, function ($p) {
                            return stripos($p->departman ?? '', 'Kesme') !== false || stripos($p->departman ?? '', 'Açma') !== false;
                        });
                        $personelOptions = [];
                        foreach ($kesmeAcmaPersoneller as $p) {
                            $personelOptions[\App\Helper\Security::encrypt($p->id)] = $p->adi_soyadi . ' - ' . ($p->departman ?? '');
                        }
                        echo Form::FormSelect2('yeni_personel_id', $personelOptions, null, 'Personel Seçin', 'users', 'key', '', 'form-select select2', true, 'width:100%', '', 'devir-yeni-personel');
                        ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatTextarea('aciklama', '', 'Devir sebebi...', 'Açıklama', 'file-text'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        İptal
                    </button>
                    <button type="submit" class="btn btn-dark">
                        <i data-feather="shuffle" style="width:14px;height:14px;" class="me-1"></i> Devret
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const devirModal = new bootstrap.Modal(document.getElementById('devirModal'));
        let showHistory = false;

        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            if ($.fn.dataTable) {
                $($.fn.dataTable.tables({ visible: true, api: true })).DataTable().columns.adjust().responsive.recalc();
            }
        });

        window.loadTaleplerVeMazeretler = function () {
            const ay = document.getElementById('filter-ay').value;
            const yil = document.getElementById('filter-yil').value;
            loadDegisimTalepleri(ay, yil);
            loadMazeretBildirimleri(ay, yil);
            loadPersonelNobetTalepleri(ay, yil);
            loadTalepStats(ay, yil);
        };

        window.toggleGecmis = function () {
            showHistory = !showHistory;
            const btn = document.getElementById('btn-toggle-gecmis');
            if (showHistory) {
                btn.classList.replace('btn-outline-info', 'btn-info');
                btn.innerHTML = '<i class="bx bx-list-ul"></i> Bekleyen';
            } else {
                btn.classList.replace('btn-info', 'btn-outline-info');
                btn.innerHTML = '<i class="bx bx-history"></i> Geçmiş';
            }
            window.loadTaleplerVeMazeretler();
        };

        // URL hash kontrolü
        if (window.location.hash === '#talepler') {
            const tab = new bootstrap.Tab(document.getElementById('nobet-talep-tab'));
            tab.show();
        }

        window.loadTaleplerVeMazeretler();

        window.exportToExcel = function () {
            const activeTab = document.querySelector('#talepTabs .nav-link.active').id;
            const tableId = activeTab === 'degisim-tab' ? '#degisim-table' : (activeTab === 'mazeret-tab' ? '#mazeret-table' : '#nobet-talep-table');
            if ($.fn.dataTable && $.fn.dataTable.isDataTable(tableId)) {
                $(tableId).DataTable().button('.buttons-excel').trigger();
                showToast('success', 'Veriler Excel\'e aktarılıyor...');
            } else {
                showToast('error', 'Tablo henüz yüklenmedi veya veri bulunamadı.');
            }
        };

        function loadDegisimTalepleri(ay, yil) {
            const tbody = $('#degisim-tbody');
            tbody.html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="bx bx-loader-alt bx-spin bx-lg"></i><p class="mb-0 mt-2">Yükleniyor...</p></td></tr>');

            const params = new URLSearchParams({ action: 'get-degisim-talepleri', is_gecmis: showHistory ? '1' : '0' });
            if (ay) params.append('ay', ay);
            if (yil) params.append('yil', yil);

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            }).then(r => r.json()).then(data => {
                if ($.fn.dataTable && $.fn.dataTable.isDataTable('#degisim-table')) {
                    $('#degisim-table').DataTable().destroy();
                }
                $('#degisim-table thead tr:not(:first-child)').remove();

                if (data.success && data.data.length > 0) {
                    let html = '';
                    let bekleyenCount = 0;
                    data.data.forEach(talep => {
                        if (talep.durum === 'personel_onayladi' || talep.durum === 'beklemede') bekleyenCount++;
                        const islemButtons = (talep.durum === 'personel_onayladi' && !showHistory) ? `
                            <button class="btn btn-sm btn-success me-1" onclick="onaylaDegisimTalebi('${talep.id}')" title="Onayla"><i class="bx bx-check"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="reddetDegisimTalebi('${talep.id}')" title="Reddet"><i class="bx bx-x"></i></button>
                        ` : `<span class="text-muted small">${formatDateShort(talep.guncelleme_tarihi || talep.talep_tarihi)}</span>`;
                        html += `<tr>
                            <td><div class="d-flex align-items-center"><div class="avatar-sm me-2"><span class="avatar-title bg-primary-subtle text-primary rounded-circle">${(talep.talep_eden_adi ? talep.talep_eden_adi[0] : '?').toUpperCase()}</span></div><strong>${talep.talep_eden_adi || '-'}</strong></div></td>
                            <td><div class="d-flex align-items-center"><div class="avatar-sm me-2"><span class="avatar-title bg-success-subtle text-success rounded-circle">${(talep.talep_edilen_adi ? talep.talep_edilen_adi[0] : '?').toUpperCase()}</span></div>${talep.talep_edilen_adi || '-'}</div></td>
                            <td><span class="badge bg-info-subtle text-info">${formatDateShort(talep.nobet_tarihi)}</span></td>
                            <td>${talep.aciklama || '<span class="text-muted">-</span>'}</td>
                            <td>${getDegisimDurumBadge(talep.durum)}</td>
                            <td>${formatDateShort(talep.talep_tarihi)}</td>
                            <td class="text-center">${islemButtons}</td>
                        </tr>`;
                    });
                    tbody.html(html);
                    $('#degisim-table').DataTable(typeof getDatatableOptions === 'function' ? getDatatableOptions() : {});
                    if (!showHistory) {
                        document.getElementById('degisim-badge').textContent = bekleyenCount;
                        document.getElementById('stat-degisim-bekleyen').textContent = bekleyenCount;
                    }
                } else {
                    tbody.empty();
                    $('#degisim-table').DataTable(typeof getDatatableOptions === 'function' ? getDatatableOptions() : {});
                    if (!showHistory) {
                        document.getElementById('degisim-badge').textContent = '0';
                        document.getElementById('stat-degisim-bekleyen').textContent = '0';
                    }
                }
            }).catch(e => console.error('Değişim talepleri yüklenemedi:', e));
        }

        function loadMazeretBildirimleri(ay, yil) {
            const tbody = $('#mazeret-tbody');
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4"><i class="bx bx-loader-alt bx-spin bx-lg"></i><p class="mb-0 mt-2">Yükleniyor...</p></td></tr>');

            const params = new URLSearchParams({ action: 'get-mazeret-bildirimleri', is_gecmis: showHistory ? '1' : '0' });
            if (ay) params.append('ay', ay);
            if (yil) params.append('yil', yil);

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            }).then(r => r.json()).then(data => {
                if ($.fn.dataTable && $.fn.dataTable.isDataTable('#mazeret-table')) {
                    $('#mazeret-table').DataTable().destroy();
                }
                $('#mazeret-table thead tr:not(:first-child)').remove();

                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(m => {
                        const islemButtons = !showHistory ? `
                            <button class="btn btn-sm btn-primary me-1" onclick="openDevirModal('${m.id}', '${m.personel_adi}')" title="Başka Personele Devret"><i class="bx bx-user-plus"></i> Devret</button>
                            <button class="btn btn-sm btn-warning me-1" onclick="reddetMazeret('${m.id}')" title="Mazereti Reddet"><i class="bx bx-x-circle"></i> Reddet</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="mazeretIptalEt('${m.id}')" title="Nöbeti İptal Et"><i class="bx bx-x"></i></button>
                        ` : `<span class="badge bg-success">İşlem Yapıldı</span>`;
                        html += `<tr>
                            <td><div class="d-flex align-items-center"><div class="avatar-sm me-2"><span class="avatar-title bg-danger-subtle text-danger rounded-circle">${(m.personel_adi ? m.personel_adi[0] : '?').toUpperCase()}</span></div><strong>${m.personel_adi || '-'}</strong></div></td>
                            <td><span class="badge bg-warning-subtle text-warning">${formatDateShort(m.nobet_tarihi)}</span></td>
                            <td>${m.baslangic_saati || '18:00'} - ${m.bitis_saati || '23:00'}</td>
                            <td>${m.mazeret_aciklama || '<span class="text-muted">Açıklama yok</span>'}</td>
                            <td>${formatDateShort(m.mazeret_tarihi)}</td>
                            <td class="text-center text-nowrap">${islemButtons}</td>
                        </tr>`;
                    });
                    tbody.html(html);
                    $('#mazeret-table').DataTable(typeof getDatatableOptions === 'function' ? getDatatableOptions() : {});
                    if (!showHistory) {
                        document.getElementById('mazeret-badge').textContent = data.data.length;
                        document.getElementById('stat-mazeret-bekleyen').textContent = data.data.length;
                    }
                } else {
                    tbody.empty();
                    $('#mazeret-table').DataTable(typeof getDatatableOptions === 'function' ? getDatatableOptions() : {});
                    if (!showHistory) {
                        document.getElementById('mazeret-badge').textContent = '0';
                        document.getElementById('stat-mazeret-bekleyen').textContent = '0';
                    }
                }
            }).catch(e => console.error('Mazeret bildirimleri yüklenemedi:', e));
        }

        function loadPersonelNobetTalepleri(ay, yil) {
            const tbody = $('#nobet-talep-tbody');
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4"><i class="bx bx-loader-alt bx-spin bx-lg"></i><p class="mb-0 mt-2">Yükleniyor...</p></td></tr>');

            const params = new URLSearchParams({ action: 'get-personel-nobet-talepleri', is_gecmis: showHistory ? '1' : '0' });
            if (ay) params.append('ay', ay);
            if (yil) params.append('yil', yil);

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            }).then(r => r.json()).then(data => {
                if ($.fn.dataTable && $.fn.dataTable.isDataTable('#nobet-talep-table')) {
                    $('#nobet-talep-table').DataTable().destroy();
                }
                $('#nobet-talep-table thead tr:not(:first-child)').remove();

                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(t => {
                        const islemButtons = (t.durum === 'talep_edildi' && !showHistory) ? `
                            <button class="btn btn-sm btn-success me-1" onclick="onaylaPersonelNobetTalebi('${t.id}')" title="Onayla"><i class="bx bx-check"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="reddetPersonelNobetTalebi('${t.id}')" title="Reddet"><i class="bx bx-x"></i></button>
                        ` : `<span class="badge bg-light text-dark">${formatDateShort(t.olusturma_tarihi)}</span>`;
                        html += `<tr>
                            <td><div class="d-flex align-items-center"><div class="avatar-sm me-2"><span class="avatar-title bg-info-subtle text-info rounded-circle">${(t.personel_adi ? t.personel_adi[0] : '?').toUpperCase()}</span></div><strong>${t.personel_adi || '-'}</strong></div></td>
                            <td><span class="badge bg-primary-subtle text-primary">${formatDateShort(t.nobet_tarihi)}</span></td>
                            <td>${t.aciklama || '<span class="text-muted">Açıklama yok</span>'}</td>
                            <td>${getTalepDurumBadge(t.durum)}</td>
                            <td>${formatDateShort(t.olusturma_tarihi)}</td>
                            <td class="text-center text-nowrap">${islemButtons}</td>
                        </tr>`;
                    });
                    tbody.html(html);
                    $('#nobet-talep-table').DataTable(typeof getDatatableOptions === 'function' ? getDatatableOptions() : {});
                    if (!showHistory) {
                        document.getElementById('nobet-talep-badge').textContent = data.data.length;
                    }
                } else {
                    tbody.empty();
                    $('#nobet-talep-table').DataTable(typeof getDatatableOptions === 'function' ? getDatatableOptions() : {});
                    if (!showHistory) {
                        document.getElementById('nobet-talep-badge').textContent = '0';
                    }
                }
            }).catch(e => console.error('Nöbet talepleri yüklenemedi:', e));
        }

        function loadTalepStats(ay, yil) {
            const params = new URLSearchParams({ action: 'get-talep-stats' });
            if (ay) params.append('ay', ay);
            if (yil) params.append('yil', yil);
            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    document.getElementById('stat-onaylanan').textContent = data.data.onaylanan;
                    document.getElementById('stat-reddedilen').textContent = data.data.reddedilen;
                }
            }).catch(e => console.error('İstatistikler yüklenemedi:', e));
        }

        function getDegisimDurumBadge(durum) {
            const badges = {
                'beklemede': '<span class="badge-premium badge-premium-secondary"><i class="bx bx-time-five"></i> Personel Onayı Bekleniyor</span>',
                'personel_onayladi': '<span class="badge-premium badge-premium-warning"><i class="bx bx-hourglass"></i> Yönetici Onayı Bekleniyor</span>',
                'onaylandi': '<span class="badge-premium badge-premium-success"><i class="bx bx-check-circle"></i> Onaylandı</span>',
                'reddedildi': '<span class="badge-premium badge-premium-danger"><i class="bx bx-x-circle"></i> Reddedildi</span>',
                'iptal': '<span class="badge-premium badge-premium-secondary">İptal</span>'
            };
            return badges[durum] || `<span class="badge-premium badge-premium-secondary">${durum}</span>`;
        }

        function getTalepDurumBadge(durum) {
            const badges = {
                'talep_edildi': '<span class="badge-premium badge-premium-warning"><i class="bx bx-time-five"></i> Beklemede</span>',
                'onaylandi': '<span class="badge-premium badge-premium-success"><i class="bx bx-check-circle"></i> Onaylandı</span>',
                'beklemede': '<span class="badge-premium badge-premium-warning"><i class="bx bx-time-five"></i> Beklemede</span>',
                'cozuldu': '<span class="badge-premium badge-premium-success"><i class="bx bx-check-circle"></i> Onaylandı</span>',
                'reddedildi': '<span class="badge-premium badge-premium-danger"><i class="bx bx-x-circle"></i> Reddedildi</span>',
                'iptal': '<span class="badge-premium badge-premium-secondary">İptal</span>'
            };
            return badges[durum] || `<span class="badge-premium badge-premium-secondary">${durum}</span>`;
        }

        function formatDateShort(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        window.onaylaDegisimTalebi = function (id) {
            Swal.fire({
                title: 'Değişim Talebini Onayla', text: 'Bu değişim talebini onaylamak istediğinize emin misiniz? Nöbet ataması değiştirilecektir.', icon: 'question', showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d', confirmButtonText: 'Evet, Onayla', cancelButtonText: 'İptal'
            }).then(r => {
                if (r.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'onayla-amir-talebi', talep_id: id })
                    }).then(r => r.json()).then(data => {
                        if (data.success || data.status === 'success') { showToast('success', data.message || 'Değişim talebi onaylandı'); window.loadTaleplerVeMazeretler(); } else { showToast('error', data.message || 'Bir hata oluştu'); }
                    });
                }
            });
        };

        window.reddetDegisimTalebi = function (id) {
            Swal.fire({
                title: 'Değişim Talebini Reddet', input: 'textarea', inputLabel: 'Red Sebebi (Opsiyonel)', inputPlaceholder: 'Neden reddedildiğini açıklayabilirsiniz...', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Reddet', cancelButtonText: 'İptal'
            }).then(r => {
                if (r.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'reddet-talebi', talep_id: id, red_nedeni: r.value || '' })
                    }).then(r => r.json()).then(data => {
                        if (data.success || data.status === 'success') { showToast('success', data.message || 'Değişim talebi reddedildi'); window.loadTaleplerVeMazeretler(); } else { showToast('error', data.message || 'Bir hata oluştu'); }
                    });
                }
            });
        };

        window.onaylaPersonelNobetTalebi = function (id) {
            Swal.fire({
                title: 'Nöbet Talebini Onayla', text: 'Bu talebi onayladığınızda personelin seçtiği tarihe nöbet ataması yapılacaktır. Emin misiniz?', icon: 'question', showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d', confirmButtonText: 'Evet, Onayla', cancelButtonText: 'İptal'
            }).then(r => {
                if (r.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'onayla-personel-nobet-talebi', talep_id: id })
                    }).then(r => r.json()).then(data => {
                        if (data.success || data.status === 'success') { showToast('success', data.message); window.loadTaleplerVeMazeretler(); } else { showToast('error', data.message); }
                    });
                }
            });
        };

        window.reddetPersonelNobetTalebi = function (id) {
            Swal.fire({
                title: 'Nöbet Talebini Reddet', input: 'textarea', inputLabel: 'Red Sebebi', inputPlaceholder: 'Neden reddedildiğini açıklayın...', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Reddet', cancelButtonText: 'İptal'
            }).then(r => {
                if (r.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'reddet-personel-nobet-talebi', talep_id: id, red_nedeni: r.value || '' })
                    }).then(r => r.json()).then(data => {
                        if (data.success || data.status === 'success') { showToast('success', data.message); window.loadTaleplerVeMazeretler(); } else { showToast('error', data.message); }
                    });
                }
            });
        };

        window.openDevirModal = function (id, p) {
            document.getElementById('devir-nobet-id').value = id;
            document.getElementById('devir-mevcut-personel').value = p;
            document.getElementById('devir-yeni-personel').value = '';
            devirModal.show();
        };

        window.mazeretIptalEt = function (id) {
            Swal.fire({
                title: 'Nöbeti İptal Et', text: 'Bu nöbeti tamamen iptal etmek istediğinize emin misiniz?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Evet, İptal Et', cancelButtonText: 'Vazgeç'
            }).then(r => {
                if (r.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'delete-nobet', nobet_id: id })
                    }).then(r => r.json()).then(data => {
                        if (data.success || data.status === 'success') {
                            showToast('success', 'Nöbet iptal edildi');
                            window.loadTaleplerVeMazeretler();
                        } else {
                            showToast('error', data.message || 'Bir hata oluştu');
                        }
                    });
                }
            });
        };

        // Mazeret Reddetme
        window.reddetMazeret = function (id) {
            Swal.fire({
                title: 'Mazereti Reddet',
                input: 'textarea',
                inputLabel: 'Red Sebebi',
                inputPlaceholder: 'Mazeretin neden reddedildiğini açıklayın...',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Reddet',
                cancelButtonText: 'İptal'
            }).then(r => {
                if (r.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'reddet-mazeret', nobet_id: id, red_nedeni: r.value || '' })
                    }).then(r => r.json()).then(data => {
                        if (data.success || data.status === 'success') {
                            showToast('success', data.message || 'Mazeret reddedildi');
                            window.loadTaleplerVeMazeretler();
                        } else {
                            showToast('error', data.message || 'Bir hata oluştu');
                        }
                    });
                }
            });
        };

        // Personel Filtre değişimi (Segmented Control)
        document.querySelectorAll('input[name="devir_personel_turu"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const type = this.value;
                const select = document.getElementById('devir-yeni-personel');
                
                fetch('views/nobet/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'get-personel-list', type: type })
                }).then(r => r.json()).then(data => {
                    if (data.success && data.data) {
                        // Select2 varsa destroy
                        if ($(select).data('select2')) {
                            $(select).select2('destroy');
                        }
                        select.innerHTML = '<option value="">Personel Seçin</option>';
                        data.data.forEach(p => {
                            const opt = document.createElement('option');
                            opt.value = p.id;
                            opt.textContent = p.adi_soyadi + ' - ' + (p.departman || '');
                            select.appendChild(opt);
                        });
                        // Re-init select2
                        $(select).select2({
                            dropdownParent: $('#devirModal'),
                            placeholder: 'Personel Seçin',
                            allowClear: true
                        });
                    }
                });
            });
        });

        document.getElementById('devir-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'devir-yap', nobet_id: fd.get('nobet_id'), personel_id: fd.get('yeni_personel_id') })
            }).then(r => r.json()).then(data => {
                if (data.success || data.status === 'success') { showToast('success', data.message || 'Nöbet başarıyla devredildi'); devirModal.hide(); window.loadTaleplerVeMazeretler(); } else { showToast('error', data.message || 'Bir hata oluştu: ' + data.message); }
            }).catch(e => {
                console.error('Devir hatası:', e);
                showToast('error', 'İşlem sırasında bir hata oluştu');
            });
        });

        // Modal açıldığında feather ikonları render et
        document.getElementById('devirModal').addEventListener('shown.bs.modal', function () {
            if (typeof feather !== 'undefined') feather.replace();
            // Select2 init
            const select = document.getElementById('devir-yeni-personel');
            if (select && !$(select).data('select2')) {
                $(select).select2({
                    dropdownParent: $('#devirModal'),
                    placeholder: 'Personel Seçin',
                    allowClear: true
                });
            }
        });

        function showToast(type, message) {
            Toastify({ text: message, duration: 3000, gravity: "top", position: "center", style: { background: type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#000000', borderRadius: "8px", boxShadow: "0 4px 12px rgba(0,0,0,0.15)" }, stopOnFocus: true }).showToast();
        }
    });
</script>