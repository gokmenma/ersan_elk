<?php
/**
 * Nöbet Talepleri & Mazeretler Sayfası
 * Değişim talepleri ve mazeret bildirimlerini yönetir
 */

use App\Model\PersonelModel;

$Personel = new PersonelModel();
$personeller = $Personel->all();
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
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">
                    <i class="bx bx-transfer-alt mt-1"></i>
                    Bekleyen Değişim
                </div>
                <div class="stat-trend">
                    <i class="bx bx-hourglass"></i> Beklemede
                </div>
            </div>
            <div class="stat-value" id="stat-degisim-bekleyen">0</div>
            <div class="stat-sub">Onay bekleyen değişim talepleri</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">
                    <i class="bx bx-error-circle mt-1"></i>
                    Mazeret Bildirimi
                </div>
                <div class="stat-trend down">
                    <i class="bx bx-time-five"></i> Acil
                </div>
            </div>
            <div class="stat-value" id="stat-mazeret-bekleyen">0</div>
            <div class="stat-sub">İşlem bekleyen mazeretler</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">
                    <i class="bx bx-check-circle mt-1"></i>
                    Bu Ay Onaylanan
                </div>
                <div class="stat-trend up">
                    <i class="bx bx-trending-up"></i> +15%
                </div>
            </div>
            <div class="stat-value" id="stat-onaylanan">0</div>
            <div class="stat-sub">Başarıyla tamamlanan</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">
                    <i class="bx bx-x-circle mt-1"></i>
                    Bu Ay Reddedilen
                </div>
                <div class="stat-trend">
                    <i class="bx bx-minus"></i> Stabil
                </div>
            </div>
            <div class="stat-value" id="stat-reddedilen">0</div>
            <div class="stat-sub">Reddedilen talepler</div>
        </div>
    </div>
</div>

<!-- Ana İçerik -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom py-3">
                <h5 class="mb-0 text-primary fw-bold">
                    <i class="bx bx-transfer-alt me-2"></i>Bekleyen Talepler & Mazeretler
                </h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="filter-ay" style="width: 130px;">
                        <?php
                        $aylar = [
                            1 => 'Ocak',
                            2 => 'Şubat',
                            3 => 'Mart',
                            4 => 'Nisan',
                            5 => 'Mayıs',
                            6 => 'Haziran',
                            7 => 'Temmuz',
                            8 => 'Ağustos',
                            9 => 'Eylül',
                            10 => 'Ekim',
                            11 => 'Kasım',
                            12 => 'Aralık'
                        ];
                        foreach ($aylar as $num => $ad): ?>
                            <option value="<?= $num ?>" <?= date('n') == $num ? 'selected' : '' ?>><?= $ad ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" id="filter-yil" style="width: 100px;">
                        <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?= $y ?>" <?= date('Y') == $y ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadTaleplerVeMazeretler()">
                        <i class="bx bx-refresh"></i> Yenile
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Tabs -->
                <ul class="nav nav-tabs nav-tabs-custom mb-3" id="talepTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="degisim-tab" data-bs-toggle="tab"
                            data-bs-target="#degisim-talepler" type="button" role="tab">
                            <i class="bx bx-transfer me-1"></i>Değişim Talepleri
                            <span class="badge bg-warning ms-1" id="degisim-badge">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mazeret-tab" data-bs-toggle="tab"
                            data-bs-target="#mazeret-bildirimleri" type="button" role="tab">
                            <i class="bx bx-error-circle me-1"></i>Mazeret Bildirimleri
                            <span class="badge bg-danger ms-1" id="mazeret-badge">0</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="talepTabContent">
                    <!-- Değişim Talepleri Tab -->
                    <div class="tab-pane fade show active" id="degisim-talepler" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="degisim-table">
                                <thead class="table-light">
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
                            <table class="table table-hover align-middle" id="mazeret-table">
                                <thead class="table-light">
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Geçmiş Modal -->
<div class="modal fade" id="gecmisModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-history me-2"></i>Talep Geçmişi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="gecmis-table">
                        <thead class="table-light">
                            <tr>
                                <th>Talep Eden</th>
                                <th>Talep Edilen</th>
                                <th>Nöbet Tarihi</th>
                                <th>Durum</th>
                                <th>İşlem Tarihi</th>
                            </tr>
                        </thead>
                        <tbody id="gecmis-tbody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
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

<!-- Nöbet Devir Modal -->
<div class="modal fade" id="devirModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-user-plus me-2"></i>Nöbeti Devret</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="devir-form">
                <div class="modal-body">
                    <input type="hidden" name="nobet_id" id="devir-nobet-id">
                    <div class="mb-3">
                        <label class="form-label">Mevcut Personel</label>
                        <input type="text" class="form-control" id="devir-mevcut-personel" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Personel</label>
                        <select class="form-select" name="yeni_personel_id" id="devir-yeni-personel" required>
                            <option value="">Personel Seçin</option>
                            <?php foreach ($personeller as $p): ?>
                                <option value="<?= \App\Helper\Security::encrypt($p->id) ?>">
                                    <?= htmlspecialchars($p->adi_soyadi) ?> -
                                    <?= $p->departman ?? '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="aciklama" rows="2"
                            placeholder="Devir sebebi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-transfer me-1"></i>Devret
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Modal instances
        const devirModal = new bootstrap.Modal(document.getElementById('devirModal'));
        const gecmisModal = new bootstrap.Modal(document.getElementById('gecmisModal'));

        // ============================================
        // VERİ YÜKLEME FONKSİYONLARI (Global Scope)
        // ============================================
        window.loadTaleplerVeMazeretler = function () {
            const ay = document.getElementById('filter-ay').value;
            const yil = document.getElementById('filter-yil').value;
            loadDegisimTalepleri(ay, yil);
            loadMazeretBildirimleri(ay, yil);
            loadTalepStats(ay, yil);
        }

        // İlk yüklemede verileri getir
        loadTaleplerVeMazeretler();

        // Geçmiş modal açıldığında
        document.getElementById('gecmisModal').addEventListener('show.bs.modal', function () {
            loadGecmis();
        });

        function loadDegisimTalepleri(ay, yil) {
            // First destroy existing DataTable before modifying DOM
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#degisim-table')) {
                $('#degisim-table').DataTable().destroy();
            }
            // Robust cleanup of any extra header rows (created by DataTables)
            $('#degisim-table thead tr:not(:first-child)').remove();

            const tbody = $('#degisim-tbody');
            tbody.html(`<tr><td colspan="7" class="text-center text-muted py-4">
            <i class="bx bx-loader-alt bx-spin bx-lg"></i>
            <p class="mb-0 mt-2">Yükleniyor...</p>
        </td></tr>`);

            const params = new URLSearchParams({ action: 'get-degisim-talepleri' });
            if (ay) params.append('ay', ay);
            if (yil) params.append('yil', yil);

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '';
                        let bekleyenCount = 0;

                        data.data.forEach(talep => {
                            // Count all pending requests (both waiting for partner or manager)
                            if (talep.durum === 'personel_onayladi' || talep.durum === 'beklemede') bekleyenCount++;

                            const durumBadge = getDegisimDurumBadge(talep.durum);
                            const islemButtons = talep.durum === 'personel_onayladi' ? `
                        <button class="btn btn-sm btn-success me-1" onclick="onaylaDegisimTalebi('${talep.id}')" title="Onayla">
                            <i class="bx bx-check"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="reddetDegisimTalebi('${talep.id}')" title="Reddet">
                            <i class="bx bx-x"></i>
                        </button>
                    ` : '<span class="text-muted">-</span>';

                            html += `
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-2">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                            ${(talep.talep_eden_adi ? talep.talep_eden_adi[0] : '?').toUpperCase()}
                                        </span>
                                    </div>
                                    <strong>${talep.talep_eden_adi || '-'}</strong>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-2">
                                        <span class="avatar-title bg-success-subtle text-success rounded-circle">
                                            ${(talep.talep_edilen_adi ? talep.talep_edilen_adi[0] : '?').toUpperCase()}
                                        </span>
                                    </div>
                                    ${talep.talep_edilen_adi || '-'}
                                </div>
                            </td>
                            <td><span class="badge bg-info-subtle text-info">${formatDateShort(talep.nobet_tarihi)}</span></td>
                            <td>${talep.aciklama || '<span class="text-muted">-</span>'}</td>
                            <td>${durumBadge}</td>
                            <td>${formatDateShort(talep.talep_tarihi)}</td>
                            <td class="text-center">${islemButtons}</td>
                        </tr>
                    `;
                        });

                        tbody.html(html);

                        if (typeof getDatatableOptions === 'function') {
                            $('#degisim-table').DataTable(getDatatableOptions());
                        }

                        document.getElementById('degisim-badge').textContent = bekleyenCount;
                        document.getElementById('stat-degisim-bekleyen').textContent = bekleyenCount;
                    } else {
                        tbody.html(`<tr><td colspan="7" class="text-center text-muted py-4">
                    <i class="bx bx-check-circle bx-lg text-success"></i>
                    <p class="mb-0 mt-2">Bekleyen değişim talebi yok</p>
                </td></tr>`);
                        document.getElementById('degisim-badge').textContent = '0';
                        document.getElementById('stat-degisim-bekleyen').textContent = '0';
                    }
                })
                .catch(error => {
                    console.error('Değişim talepleri yüklenemedi:', error);
                    tbody.html(`<tr><td colspan="7" class="text-center text-danger py-4">
                <i class="bx bx-error bx-lg"></i>
                <p class="mb-0 mt-2">Yüklenirken hata oluştu</p>
            </td></tr>`);
                });
        }

        function loadMazeretBildirimleri(ay, yil) {
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#mazeret-table')) {
                $('#mazeret-table').DataTable().destroy();
            }
            $('#mazeret-table thead tr:not(:first-child)').remove();

            const tbody = $('#mazeret-tbody');
            tbody.html(`<tr><td colspan="6" class="text-center text-muted py-4">
            <i class="bx bx-loader-alt bx-spin bx-lg"></i>
            <p class="mb-0 mt-2">Yükleniyor...</p>
        </td></tr>`);

            const params = new URLSearchParams({ action: 'get-mazeret-bildirimleri' });
            if (ay) params.append('ay', ay);
            if (yil) params.append('yil', yil);

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '';
                        // ... processing loop
                        data.data.forEach(mazeret => {
                            html += `
                                <tr>
                                    <td><div class="d-flex align-items-center"><div class="avatar-sm me-2"><span class="avatar-title bg-danger-subtle text-danger rounded-circle">${(mazeret.personel_adi ? mazeret.personel_adi[0] : '?').toUpperCase()}</span></div><strong>${mazeret.personel_adi || '-'}</strong></div></td>
                                    <td><span class="badge bg-warning-subtle text-warning">${formatDateShort(mazeret.nobet_tarihi)}</span></td>
                                    <td>${mazeret.baslangic_saati || '18:00'} - ${mazeret.bitis_saati || '08:00'}</td>
                                    <td>${mazeret.mazeret_aciklama || '<span class="text-muted">Açıklama yok</span>'}</td>
                                    <td>${formatDateShort(mazeret.mazeret_tarihi)}</td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary me-1" onclick="openDevirModal('${mazeret.id}', '${mazeret.personel_adi}')" title="Başka Personele Devret"><i class="bx bx-user-plus"></i> Devret</button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="mazeretIptalEt('${mazeret.id}')" title="Nöbeti İptal Et"><i class="bx bx-x"></i></button>
                                    </td>
                                </tr>`;
                        });

                        tbody.html(html);
                        $('#mazeret-table').DataTable(getDatatableOptions());

                        document.getElementById('mazeret-badge').textContent = data.data.length;
                        document.getElementById('stat-mazeret-bekleyen').textContent = data.data.length;
                    } else {
                        tbody.html(`<tr><td colspan="6" class="text-center text-muted py-4">
                    <i class="bx bx-check-circle bx-lg text-success"></i>
                    <p class="mb-0 mt-2">Mazeret bildirimi yok</p>
                </td></tr>`);
                        document.getElementById('mazeret-badge').textContent = '0';
                        document.getElementById('stat-mazeret-bekleyen').textContent = '0';
                    }
                })
                .catch(error => {
                    console.error('Mazeret bildirimleri yüklenemedi:', error);
                    tbody.html(`<tr><td colspan="6" class="text-center text-danger py-4">
                <i class="bx bx-error bx-lg"></i>
                <p class="mb-0 mt-2">Yüklenirken hata oluştu</p>
            </td></tr>`);
                });
        }

        function loadTalepStats(ay, yil) {
            const params = new URLSearchParams({ action: 'get-talep-stats' });
            if (ay) params.append('ay', ay);
            if (yil) params.append('yil', yil);

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('stat-onaylanan').textContent = data.data.onaylanan;
                        document.getElementById('stat-reddedilen').textContent = data.data.reddedilen;
                    }
                })
                .catch(error => console.error('İstatistikler yüklenemedi:', error));
        }

        function loadGecmis() {
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#gecmis-table')) {
                $('#gecmis-table').DataTable().destroy();
            }
            $('#gecmis-table thead tr:not(:first-child)').remove();

            const tbody = $('#gecmis-tbody');
            tbody.html(`<tr><td colspan="5" class="text-center text-muted py-4">
            <i class="bx bx-loader-alt bx-spin bx-lg"></i>
            <p class="mb-0 mt-2">Yükleniyor...</p>
        </td></tr>`);

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get-talep-gecmisi' })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '';
                        data.data.forEach(talep => {
                            html += `
                                <tr>
                                    <td>${talep.talep_eden_adi || '-'}</td>
                                    <td>${talep.talep_edilen_adi || '-'}</td>
                                    <td>${formatDateShort(talep.nobet_tarihi)}</td>
                                    <td>${getDegisimDurumBadge(talep.durum)}</td>
                                    <td>${formatDateShort(talep.guncelleme_tarihi)}</td>
                                </tr>`;
                        });

                        tbody.html(html);

                        if (typeof getDatatableOptions === 'function') {
                            $('#gecmis-table').DataTable(getDatatableOptions());
                        }
                    } else {
                        tbody.html(`<tr><td colspan="5" class="text-center text-muted py-4">
                    <p class="mb-0">Geçmiş talep bulunamadı</p>
                </td></tr>`);
                    }
                });
        }

        // ============================================
        // YARDIMCI FONKSİYONLAR
        // ============================================
        function getDegisimDurumBadge(durum) {
            const badges = {
                'beklemede': '<span class="badge bg-secondary">Personel Onayı Bekleniyor</span>',
                'personel_onayladi': '<span class="badge bg-warning">Yönetici Onayı Bekleniyor</span>',
                'onaylandi': '<span class="badge bg-success">Onaylandı</span>',
                'reddedildi': '<span class="badge bg-danger">Reddedildi</span>',
                'iptal': '<span class="badge bg-dark">İptal</span>'
            };
            return badges[durum] || '<span class="badge bg-secondary">Bilinmiyor</span>';
        }

        function formatDateShort(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        // ============================================
        // İŞLEM FONKSİYONLARI
        // ============================================
        window.onaylaDegisimTalebi = function (talepId) {
            Swal.fire({
                title: 'Değişim Talebini Onayla',
                text: 'Bu değişim talebini onaylamak istediğinize emin misiniz? Nöbet ataması değiştirilecektir.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Onayla',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'onayla-amir-talebi', talep_id: talepId })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success' || data.success) {
                                showToast('success', data.message || 'Değişim talebi onaylandı');
                                loadTaleplerVeMazeretler();
                            } else {
                                showToast('error', data.message || 'Bir hata oluştu');
                            }
                        });
                }
            });
        }

        window.reddetDegisimTalebi = function (talepId) {
            Swal.fire({
                title: 'Değişim Talebini Reddet',
                input: 'textarea',
                inputLabel: 'Red Sebebi (Opsiyonel)',
                inputPlaceholder: 'Neden reddedildiğini açıklayabilirsiniz...',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Reddet',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'reddet-talebi',
                            talep_id: talepId,
                            red_nedeni: result.value || ''
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success' || data.success) {
                                showToast('success', data.message || 'Değişim talebi reddedildi');
                                loadTaleplerVeMazeretler();
                            } else {
                                showToast('error', data.message || 'Bir hata oluştu');
                            }
                        });
                }
            });
        }

        window.openDevirModal = function (nobetId, mevcutPersonel) {
            document.getElementById('devir-nobet-id').value = nobetId;
            document.getElementById('devir-mevcut-personel').value = mevcutPersonel;
            document.getElementById('devir-yeni-personel').value = '';
            devirModal.show();
        }

        window.mazeretIptalEt = function (nobetId) {
            Swal.fire({
                title: 'Nöbeti İptal Et',
                text: 'Bu nöbeti tamamen iptal etmek istediğinize emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, İptal Et',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'delete-nobet', nobet_id: nobetId })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                showToast('success', 'Nöbet iptal edildi');
                                loadMazeretBildirimleri();
                            } else {
                                showToast('error', data.message || 'Bir hata oluştu');
                            }
                        });
                }
            });
        }

        // Devir form submit
        document.getElementById('devir-form').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const params = new URLSearchParams();
            params.append('action', 'devir-yap');
            params.append('nobet_id', formData.get('nobet_id'));
            params.append('personel_id', formData.get('yeni_personel_id'));

            // Get current filters to maintain view after reload
            const curAy = document.getElementById('filter-ay').value;
            const curYil = document.getElementById('filter-yil').value;

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' || data.success) {
                        showToast('success', data.message || 'Nöbet başarıyla devredildi');
                        devirModal.hide();
                        // Reload with current filters
                        loadMazeretBildirimleri(curAy, curYil);
                        loadDegisimTalepleri(curAy, curYil);
                        loadTalepStats(curAy, curYil);
                    } else {
                        showToast('error', data.message || 'Bir hata oluştu: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Devir hatası:', error);
                    showToast('error', 'İşlem sırasında bir hata oluştu');
                });
        });

        function showToast(type, message) {
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "center",
                style: {
                    background: type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#000000',
                    borderRadius: "8px",
                    boxShadow: "0 4px 12px rgba(0,0,0,0.15)"
                },
                stopOnFocus: true
            }).showToast();
        }
    });
</script>