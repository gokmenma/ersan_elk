<?php
/**
 * Nöbet Talepleri & Mazeretler Sayfası
 * Değişim talepleri ve mazeret bildirimlerini yönetir
 */

use App\Model\PersonelModel;
use App\Helper\Form;

$Personel = new PersonelModel();
$personeller = $Personel->all(true);
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
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 text-primary fw-bold">
                    <i class="bx bx-transfer-alt me-2"></i>Talepler & Mazeretler
                </h5>
            </div>
            <div class="card-body">
                <!-- Tabs and Filters -->
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <ul class="nav nav-tabs nav-tabs-custom border-bottom-0 mb-0" id="talepTabs" role="tablist">
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
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="nobet-talep-tab" data-bs-toggle="tab"
                                data-bs-target="#nobet-talepleri" type="button" role="tab">
                                <i class="bx bx-calendar-plus me-1"></i>Nöbet Talepleri
                                <span class="badge bg-info ms-1" id="nobet-talep-badge">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 140px;">
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
                            echo Form::FormSelect2('filter-ay', $aylar, date('n'), 'Ay', 'calendar');
                            ?>
                        </div>
                        <div style="width: 140px;">
                            <?php
                            $yillar = [];
                            for ($y = date('Y'); $y >= 2024; $y--) {
                                $yillar[$y] = $y;
                            }
                            echo Form::FormSelect2('filter-yil', $yillar, date('Y'), 'Yıl', 'calendar');
                            ?>
                        </div>
                        <div class="btn-group btn-group-sm gap-2">
                            <button class="btn btn-outline-info" id="btn-toggle-gecmis" onclick="toggleGecmis()"
                                title="Geçmiş İşlemler">
                                <i class="bx bx-history"></i> Geçmiş
                            </button>
                            <button class="btn btn-outline-success" onclick="exportToExcel()" title="Excele Aktar">
                                <i class="bx bx-spreadsheet"></i> Excele Aktar
                            </button>
                            <button class="btn btn-outline-primary" onclick="loadTaleplerVeMazeretler()" title="Yenile">
                                <i class="bx bx-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="talepTabContent">
                    <!-- Değişim Talepleri Tab -->
                    <div class="tab-pane fade show active" id="degisim-talepler" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="degisim-table" style="width: 100%;">
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
                            <table class="table table-hover align-middle" id="mazeret-table" style="width: 100%;">
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

                    <!-- Nöbet Talepleri Tab -->
                    <div class="tab-pane fade" id="nobet-talepleri" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="nobet-talep-table" style="width: 100%;">
                                <thead class="table-light">
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
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#degisim-table')) {
                $('#degisim-table').DataTable().destroy();
            }
            $('#degisim-table thead tr:not(:first-child)').remove();
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
                    if (typeof getDatatableOptions === 'function') $('#degisim-table').DataTable(getDatatableOptions());
                    if (!showHistory) {
                        document.getElementById('degisim-badge').textContent = bekleyenCount;
                        document.getElementById('stat-degisim-bekleyen').textContent = bekleyenCount;
                    }
                } else {
                    tbody.html(`<tr><td colspan="7" class="text-center text-muted py-4"><i class="bx bx-check-circle bx-lg text-success"></i><p class="mb-0 mt-2">${showHistory ? 'Geçmiş kayıt bulunamadı' : 'Bekleyen değişim talebi yok'}</p></td></tr>`);
                    if (!showHistory) {
                        document.getElementById('degisim-badge').textContent = '0';
                        document.getElementById('stat-degisim-bekleyen').textContent = '0';
                    }
                }
            }).catch(e => console.error('Değişim talepleri yüklenemedi:', e));
        }

        function loadMazeretBildirimleri(ay, yil) {
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#mazeret-table')) {
                $('#mazeret-table').DataTable().destroy();
            }
            $('#mazeret-table thead tr:not(:first-child)').remove();
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
                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(m => {
                        const islemButtons = !showHistory ? `
                            <button class="btn btn-sm btn-primary" onclick="openDevirModal('${m.id}', '${m.personel_adi}')" title="Başka Personele Devret"><i class="bx bx-user-plus"></i> Devret</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="mazeretIptalEt('${m.id}')" title="Nöbeti İptal Et"><i class="bx bx-x"></i></button>
                        ` : `<span class="badge bg-success">İşlem Yapıldı</span>`;
                        html += `<tr>
                            <td><div class="d-flex align-items-center"><div class="avatar-sm me-2"><span class="avatar-title bg-danger-subtle text-danger rounded-circle">${(m.personel_adi ? m.personel_adi[0] : '?').toUpperCase()}</span></div><strong>${m.personel_adi || '-'}</strong></div></td>
                            <td><span class="badge bg-warning-subtle text-warning">${formatDateShort(m.nobet_tarihi)}</span></td>
                            <td>${m.baslangic_saati || '18:00'} - ${m.bitis_saati || '08:00'}</td>
                            <td>${m.mazeret_aciklama || '<span class="text-muted">Açıklama yok</span>'}</td>
                            <td>${formatDateShort(m.mazeret_tarihi)}</td>
                            <td class="text-center text-nowrap">${islemButtons}</td>
                        </tr>`;
                    });
                    tbody.html(html);
                    if (typeof getDatatableOptions === 'function') $('#mazeret-table').DataTable(getDatatableOptions());
                    if (!showHistory) {
                        document.getElementById('mazeret-badge').textContent = data.data.length;
                        document.getElementById('stat-mazeret-bekleyen').textContent = data.data.length;
                    }
                } else {
                    tbody.html(`<tr><td colspan="6" class="text-center text-muted py-4"><i class="bx bx-check-circle bx-lg text-success"></i><p class="mb-0 mt-2">${showHistory ? 'Geçmiş mazeret bulunamadı' : 'Mazeret bildirimi yok'}</p></td></tr>`);
                    if (!showHistory) {
                        document.getElementById('mazeret-badge').textContent = '0';
                        document.getElementById('stat-mazeret-bekleyen').textContent = '0';
                    }
                }
            }).catch(e => console.error('Mazeret bildirimleri yüklenemedi:', e));
        }

        function loadPersonelNobetTalepleri(ay, yil) {
            if ($.fn.dataTable && $.fn.dataTable.isDataTable('#nobet-talep-table')) {
                $('#nobet-talep-table').DataTable().destroy();
            }
            $('#nobet-talep-table thead tr:not(:first-child)').remove();
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
                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(t => {
                        const islemButtons = (t.durum === 'beklemede' && !showHistory) ? `
                            <button class="btn btn-sm btn-success me-1" onclick="onaylaPersonelNobetTalebi('${t.id}')" title="Onayla"><i class="bx bx-check"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="reddetPersonelNobetTalebi('${t.id}')" title="Reddet"><i class="bx bx-x"></i></button>
                        ` : `<span class="badge bg-light text-dark">${formatDateShort(t.guncelleme_tarihi || t.olusturma_tarihi)}</span>`;
                        html += `<tr>
                            <td><div class="d-flex align-items-center"><div class="avatar-sm me-2"><span class="avatar-title bg-info-subtle text-info rounded-circle">${(t.personel_adi ? t.personel_adi[0] : '?').toUpperCase()}</span></div><strong>${t.personel_adi || '-'}</strong></div></td>
                            <td><span class="badge bg-primary-subtle text-primary">${formatDateShort(t.baslik)}</span></td>
                            <td>${t.aciklama || '<span class="text-muted">Açıklama yok</span>'}</td>
                            <td>${getTalepDurumBadge(t.durum)}</td>
                            <td>${formatDateShort(t.olusturma_tarihi)}</td>
                            <td class="text-center text-nowrap">${islemButtons}</td>
                        </tr>`;
                    });
                    tbody.html(html);
                    if (typeof getDatatableOptions === 'function') $('#nobet-talep-table').DataTable(getDatatableOptions());
                    if (!showHistory) {
                        document.getElementById('nobet-talep-badge').textContent = data.data.length;
                    }
                } else {
                    tbody.html(`<tr><td colspan="6" class="text-center text-muted py-4"><i class="bx bx-check-circle bx-lg text-success"></i><p class="mb-0 mt-2">${showHistory ? 'Geçmiş talep bulunamadı' : 'Bekleyen nöbet talebi yok'}</p></td></tr>`);
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
                'beklemede': '<span class="badge bg-secondary">Personel Onayı Bekleniyor</span>',
                'personel_onayladi': '<span class="badge bg-warning">Yönetici Onayı Bekleniyor</span>',
                'onaylandi': '<span class="badge bg-success">Onaylandı</span>',
                'reddedildi': '<span class="badge bg-danger">Reddedildi</span>',
                'iptal': '<span class="badge bg-dark">İptal</span>'
            };
            return badges[durum] || '<span class="badge bg-secondary">Bilinmiyor</span>';
        }

        function getTalepDurumBadge(durum) {
            const badges = {
                'beklemede': '<span class="badge bg-warning">Beklemede</span>',
                'cozuldu': '<span class="badge bg-success">Onaylandı</span>',
                'reddedildi': '<span class="badge bg-danger">Reddedildi</span>',
                'iptal': '<span class="badge bg-dark">İptal</span>'
            };
            return badges[durum] || `<span class="badge bg-secondary">${durum}</span>`;
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

        function showToast(type, message) {
            Toastify({ text: message, duration: 3000, gravity: "top", position: "center", style: { background: type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#000000', borderRadius: "8px", boxShadow: "0 4px 12px rgba(0,0,0,0.15)" }, stopOnFocus: true }).showToast();
        }
    });
</script>