<?php
/**
 * Nöbet Onay İşlemleri Sayfası
 * Planlanan nöbetlerin yönetici tarafından onaylanmasını sağlar.
 */

use App\Model\PersonelModel;
use App\Helper\Form;

$Personel = new PersonelModel();
$personeller = $Personel->all(true, 'nobet');

// İstatistikleri Getir
$dbObj = new \App\Core\Db();
$stmt = $dbObj->getConnection()->query("SELECT 
    COUNT(*) as total_bekleyen,
    SUM(CASE WHEN nobet_tipi = 'hafta_sonu' THEN 1 ELSE 0 END) as hafta_sonu_bekleyen,
    SUM(CASE WHEN nobet_tipi = 'resmi_tatil' THEN 1 ELSE 0 END) as resmi_tatil_bekleyen
FROM nobetler 
WHERE (yonetici_onayi = 0 OR yonetici_onayi IS NULL) AND silinme_tarihi IS NULL AND (durum IS NULL OR durum NOT IN ('reddedildi', 'iptal'))");
$stats = $stmt->fetch(PDO::FETCH_OBJ);
?>
<link rel="stylesheet" href="views/nobet/assets/style.css?v=<?php echo filemtime('views/nobet/assets/style.css'); ?>">

<style>
    /* Custom Checkbox Style from demirbas/list.php */
    .custom-checkbox-container {
        position: relative;
        cursor: pointer;
        user-select: none;
        width: 20px;
        height: 20px;
    }
    .custom-checkbox-input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
        height: 0;
        width: 0;
    }
    .custom-checkbox-label {
        position: absolute;
        top: 0;
        left: 0;
        height: 20px;
        width: 20px;
        background-color: #fff;
        border: 2px solid #d1d5db;
        border-radius: 6px;
        transition: all 0.2s ease;
    }
    .custom-checkbox-input:checked ~ .custom-checkbox-label {
        background-color: #556ee6;
        border-color: #556ee6;
    }
    .custom-checkbox-label:after {
        content: "";
        position: absolute;
        display: none;
        left: 6px;
        top: 2px;
        width: 5px;
        height: 10px;
        border: solid white;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }
    .custom-checkbox-input:checked ~ .custom-checkbox-label:after {
        display: block;
    }
    
    /* Row Selection Style */
    #onay-table tbody tr {
        transition: all 0.2s ease;
        cursor: pointer;
    }
    #onay-table tbody tr.selected {
        background-color: rgba(85, 110, 230, 0.05) !important;
    }
    #onay-table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }
</style>

<!-- Sayfa Başlığı -->
<?php
$maintitle = "Nöbet Yönetimi";
$title = 'Nöbet Onay İşlemleri';
?>
<?php include 'layouts/breadcrumb.php'; ?>

<!-- İstatistik Kartları (talepler.php stili) -->
<div class="row mb-4 g-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="--card-color: #556ee6; --card-rgb: 85, 110, 230; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body">
                <div class="icon-label-container">
                    <div class="icon-box">
                        <i class='bx bx-check-shield fs-4' style="color: var(--card-color);"></i>
                    </div>
                    <div class="stat-trend neutral">
                        <i class='bx bx-hourglass'></i> Beklemede
                    </div>
                </div>
                <p class="stat-label-main">TOPLAM BEKLEYEN</p>
                <h4 class="stat-value" id="stat-total-bekleyen"><?php echo $stats->total_bekleyen; ?></h4>
                <p class="stat-sub">Onay bekleyen toplam nöbet</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="--card-color: #f1b44c; --card-rgb: 241, 180, 76; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body">
                <div class="icon-label-container">
                    <div class="icon-box">
                        <i class='bx bx-calendar-week fs-4' style="color: var(--card-color);"></i>
                    </div>
                    <div class="stat-trend neutral">
                        <i class='bx bx-time'></i> H.Sonu
                    </div>
                </div>
                <p class="stat-label-main">H.SONU BEKLEYEN</p>
                <h4 class="stat-value" id="stat-hs-bekleyen"><?php echo $stats->hafta_sonu_bekleyen; ?></h4>
                <p class="stat-sub">Hafta sonu nöbetleri</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="--card-color: #ef4444; --card-rgb: 239, 68, 68; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body">
                <div class="icon-label-container">
                    <div class="icon-box">
                        <i class='bx bx-flag fs-4' style="color: var(--card-color);"></i>
                    </div>
                    <div class="stat-trend neutral">
                        <i class='bx bx-info-circle'></i> R.Tatil
                    </div>
                </div>
                <p class="stat-label-main">R.TATİL BEKLEYEN</p>
                <h4 class="stat-value" id="stat-rt-bekleyen"><?php echo $stats->resmi_tatil_bekleyen; ?></h4>
                <p class="stat-sub">Resmi tatil nöbetleri</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="--card-color: #1a1d21; --card-rgb: 26, 29, 33; border-bottom: 3px solid var(--card-color) !important; cursor: pointer;" onclick="bulkApprove()">
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <div class="icon-box mb-2" style="width: 48px; height: 48px; background: rgba(26, 29, 33, 0.1);">
                    <i class='bx bx-check-double fs-2' style="color: #1a1d21;"></i>
                </div>
                <h6 class="fw-bold mb-1">TÜMÜNÜ ONAYLA</h6>
                <p class="text-muted small mb-0">Hızlı işlem paneli</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <div class="card-header bg-white border-0 py-3 px-4">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2">
                        <div style="min-width: 150px;">
                            <?php
                            $aylar = [0 => 'Tümü', 1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
                            echo Form::FormSelect2('filter-ay', $aylar, 0, 'Ay Seçimi', 'calendar');
                            ?>
                        </div>
                        <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                        <div style="min-width: 120px;">
                            <?php
                            $yillar = [];
                            for ($y = date('Y'); $y >= 2024; $y--) { $yillar[$y] = $y; }
                            echo Form::FormSelect2('filter-yil', $yillar, date('Y'), 'Yıl', 'calendar');
                            ?>
                        </div>
                    </div>

                    <div id="selection-status-container" class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 d-none" style="min-width: 130px;">
                        <div class="px-3 fw-bold text-primary small">
                            <i class="bx bx-check-square me-1"></i> <span id="selected-count">0</span> Seçildi
                        </div>
                    </div>

                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto">
                    <button type="button" class="btn text-danger px-3 d-flex align-items-center fw-bold"
                                onclick="bulkDelete()">
                            <i class="bx bx-trash fs-5 me-1"></i> <span>Seçilenleri Sil</span>
                        </button>    
                        <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                    
                        <button type="button" class="btn btn-success text-white px-3 d-flex align-items-center fw-bold"
                                onclick="bulkApprove()">
                            <i class="bx bx-check-double fs-5 me-1"></i> <span>Seçilenleri Onayla</span>
                        </button>
                        
                    </div>
                </div>
            </div>
            <div class="card-body px-4 pt-0">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                    <h5 class="mb-0 text-dark fw-bold">
                        <i class="bx bx-check-shield me-2 text-primary"></i>Nöbet Onay Listesi
                    </h5>
                   
                </div>

                <div class="table-responsive">
                    <table class="table table-premium align-middle" id="onay-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 20px;">
                                    <div class="custom-checkbox-container d-inline-block">
                                        <input type="checkbox" id="checkAll" class="custom-checkbox-input">
                                        <label class="custom-checkbox-label" for="checkAll"></label>
                                    </div>
                                </th>
                                <th>Personel</th>
                                <th>Departman</th>
                                <th>Nöbet Tarihi</th>
                                <th>Saat</th>
                                <th>Açıklama</th>
                                <th class="text-center">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="onay-tbody">
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
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let onayTable = null;

    // Otomatik Filtreleme
    $('#filter-ay, #filter-yil').on('change', function() {
        loadOnayBekleyenNobetler();
    });

    window.loadOnayBekleyenNobetler = function () {
        const ay = document.getElementById('filter-ay').value;
        const yil = document.getElementById('filter-yil').value;
        const tbody = $('#onay-tbody');
        
        // Seçim sayacını ve kontrol kutusunu sıfırla
        $('#checkAll').prop('checked', false);
        updateSelectionStatus(0);
        
        if (onayTable) {
            onayTable.destroy();
        }
        tbody.empty();
        tbody.html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="bx bx-loader-alt bx-spin bx-lg"></i><p class="mb-0 mt-2">Yükleniyor...</p></td></tr>');

        fetch('views/nobet/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'get-onay-bekleyen-nobetler', ay: ay, yil: yil })
        }).then(r => r.json()).then(data => {
            if (data.success && data.data.length > 0) {
                let html = '';
                data.data.forEach(n => {
                    html += `<tr data-id="${n.id}">
                        <td class="text-center" onclick="event.stopPropagation()">
                            <div class="custom-checkbox-container d-inline-block">
                                <input type="checkbox" class="custom-checkbox-input nobet-check" value="${n.id}" id="chk_${n.id}">
                                <label class="custom-checkbox-label" for="chk_${n.id}"></label>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-2">
                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                        ${(n.personel_adi ? n.personel_adi[0] : '?').toUpperCase()}
                                    </span>
                                </div>
                                <strong>${n.personel_adi || '-'}</strong>
                            </div>
                        </td>
                        <td>${n.departman || '-'}</td>
                        <td><span class="badge bg-info-subtle text-info">${formatDateShort(n.nobet_tarihi)}</span></td>
                        <td>${n.baslangic_saati.substring(0,5)} - ${n.bitis_saati.substring(0,5)}</td>
                        <td>${n.aciklama || '<span class="text-muted small">Açıklama yok</span>'}</td>
                        <td class="text-center" onclick="event.stopPropagation()">
                            <button class="btn btn-sm btn-success me-1" onclick="approveNobet('${n.id}')" title="Onayla"><i class="bx bx-check"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteNobet('${n.id}')" title="Sil"><i class="bx bx-trash"></i></button>
                        </td>
                    </tr>`;
                });
                tbody.html(html);
                const setSafeText = (id, text) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = text;
                };

                setSafeText('onay-bekleyen-count', data.data.length + ' Bekleyen');
                setSafeText('stat-total-bekleyen', data.data.length);
                
                // Tip bazlı sayma
                let hsCount = 0;
                let rtCount = 0;
                data.data.forEach(n => {
                    if (n.nobet_tipi === 'hafta_sonu') hsCount++;
                    if (n.nobet_tipi === 'resmi_tatil') rtCount++;
                });
                setSafeText('stat-hs-bekleyen', hsCount);
                setSafeText('stat-rt-bekleyen', rtCount);
                
                // Datatable options
                let options = getDatatableOptions();
                options.order = [[1, 'asc']];
                options.columnDefs = [{ targets: [0, -1], orderable: false }];
               
                onayTable = $('#onay-table').DataTable(options);
                
                // Satır tıklama ile seçim
                $('#onay-table tbody').on('click', 'tr', function() {
                    if ($(event.target).closest('button, .custom-checkbox-container').length) return;
                    
                    const checkbox = $(this).find('.nobet-check');
                    checkbox.prop('checked', !checkbox.prop('checked'));
                    $(this).toggleClass('selected', checkbox.prop('checked'));
                    
                    // Hepsi seçili mi kontrol et
                    if (onayTable) {
                        const totalFiltered = onayTable.rows({ filter: 'applied' }).count();
                        const checkedInFiltered = onayTable.rows({ filter: 'applied' }).nodes().to$().find('.nobet-check:checked').length;
                        $('#checkAll').prop('checked', totalFiltered === checkedInFiltered && totalFiltered > 0);
                        updateSelectionStatus(checkedInFiltered);
                    }
                });
                
                // Checkbox değişince class ekle
                $('#onay-table tbody').on('change', '.nobet-check', function() {
                    $(this).closest('tr').toggleClass('selected', this.checked);
                    
                    const totalFiltered = onayTable.rows({ filter: 'applied' }).count();
                    const checkedInFiltered = onayTable.rows({ filter: 'applied' }).nodes().to$().find('.nobet-check:checked').length;
                    $('#checkAll').prop('checked', totalFiltered === checkedInFiltered && totalFiltered > 0);
                    updateSelectionStatus(checkedInFiltered);
                });
                
            } else {
                tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">Onay bekleyen nöbet bulunamadı.</td></tr>');
                const setSafeText = (id, text) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = text;
                };
                setSafeText('onay-bekleyen-count', '0 Bekleyen');
                setSafeText('stat-total-bekleyen', 0);
                setSafeText('stat-hs-bekleyen', 0);
                setSafeText('stat-rt-bekleyen', 0);
            }
        });
    };

    $('#checkAll').on('change', function() {
        const isChecked = this.checked;
        if (!onayTable) return;

        // Sadece filtrelenmiş satırları seç/kaldır
        onayTable.rows({ filter: 'applied' }).nodes().to$().each(function() {
            $(this).find('.nobet-check').prop('checked', isChecked);
            $(this).toggleClass('selected', isChecked);
        });

        const checkedInFiltered = isChecked ? onayTable.rows({ filter: 'applied' }).count() : 0;
        updateSelectionStatus(checkedInFiltered);
    });

    function updateSelectionStatus(count) {
        const container = $('#selection-status-container');
        const countSpan = $('#selected-count');
        
        if (count > 0) {
            countSpan.text(count);
            container.removeClass('d-none').addClass('d-flex animate__animated animate__fadeInLeft');
        } else {
            container.addClass('d-none').removeClass('d-flex animate__animated animate__fadeInLeft');
        }
    }

    window.approveNobet = function(id) {
        Swal.fire({
            title: 'Nöbeti Onayla',
            text: 'Bu nöbeti onaylamak istediğinize emin misiniz?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, Onayla',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#28a745',
        }).then(result => {
            if (result.isConfirmed) {
                actionFetch('onayla-nobet', { nobet_id: id });
            }
        });
    };

    window.deleteNobet = function(id) {
        Swal.fire({
            title: 'Nöbeti Sil',
            text: 'Bu nöbeti silmek istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#dc3545',
        }).then(result => {
            if (result.isConfirmed) {
                actionFetch('delete-nobet', { nobet_id: id });
            }
        });
    };

    window.bulkApprove = function() {
        const ids = [];
        if (!onayTable) return;

        onayTable.rows({ filter: 'applied' }).nodes().to$().find('.nobet-check:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            Swal.fire('Hata', 'Lütfen en az bir nöbet seçin.', 'error');
            return;
        }

        Swal.fire({
            title: 'Toplu Onay',
            text: ids.length + ' adet nöbeti onaylamak istediğinize emin misiniz?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, Onayla',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#28a745',
        }).then(result => {
            if (result.isConfirmed) {
                actionFetch('bulk-onayla-nobet', { ids: ids });
            }
        });
    };

    window.bulkDelete = function() {
        const ids = [];
        if (!onayTable) return;

        onayTable.rows({ filter: 'applied' }).nodes().to$().find('.nobet-check:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            Swal.fire('Hata', 'Lütfen en az bir nöbet seçin.', 'error');
            return;
        }

        Swal.fire({
            title: 'Toplu Sil',
            text: ids.length + ' adet nöbeti silmek istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#dc3545',
        }).then(result => {
            if (result.isConfirmed) {
                actionFetch('bulk-sil-nobet', { ids: ids });
            }
        });
    };

    function actionFetch(action, extraData) {
        const formData = new FormData();
        formData.append('action', action);
        
        if (extraData) {
            Object.keys(extraData).forEach(key => {
                if (Array.isArray(extraData[key])) {
                    extraData[key].forEach(val => formData.append(key + '[]', val));
                } else {
                    formData.append(key, extraData[key]);
                }
            });
        }

        fetch('views/nobet/api.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(data => {
            if (data.success || data.status === 'success') {
                showToast('success', data.message);
                loadOnayBekleyenNobetler();
            } else {
                showToast('error', data.message || 'İşlem başarısız.');
            }
        });
    }

    function formatDateShort(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function showToast(type, message) {
        if (typeof Toastify !== 'undefined') {
            Toastify({ text: message, duration: 3000, gravity: "top", position: "center", style: { background: type === 'success' ? '#28a745' : '#dc3545', borderRadius: "8px" } }).showToast();
        } else {
            Swal.fire('Bilgi', message, type);
        }
    }

    loadOnayBekleyenNobetler();
});
</script>
