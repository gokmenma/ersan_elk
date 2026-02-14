<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Helper\Form;

$systemLogModel = new SystemLogModel();

if (Gate::allows("ana_sayfa")) {

    // Filtreler
    $filters = [];
    if (!empty($_GET['action_type'])) {
        $filters['action_type'] = $_GET['action_type'];
    }
    if (!empty($_GET['user_id'])) {
        $filters['user_id'] = $_GET['user_id'];
    }
    if (!empty($_GET['date_start'])) {
        $filters['date_start'] = $_GET['date_start'];
    }
    if (!empty($_GET['date_end'])) {
        $filters['date_end'] = $_GET['date_end'];
    }

    // Verileri çek
    $logs = $systemLogModel->getAllLogs($filters);
    $actionTypes = $systemLogModel->getDistinctActionTypes();
    $logUsers = $systemLogModel->getDistinctLogUsers();

    $maintitle = 'Ana Sayfa';
    $title = 'Görev ve Bildirimler';

    // İstatistikler
    $totalLogs = count($logs);

    $todayLogs = array_filter($logs, function ($log) {
        return date('Y-m-d', strtotime($log->created_at)) === date('Y-m-d');
    });
    $todayCount = count($todayLogs);

    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekLogs = array_filter($logs, function ($log) use ($weekStart) {
        return date('Y-m-d', strtotime($log->created_at)) >= $weekStart;
    });
    $weekCount = count($weekLogs);

    $uniqueTypes = count($actionTypes);

    // Select options hazırla
    $actionTypeOptions = ['' => 'Tümü'];
    foreach ($actionTypes as $type) {
        $actionTypeOptions[$type->action_type] = $type->action_type;
    }

    $userOptions = ['' => 'Tümü'];
    foreach ($logUsers as $user) {
        $userOptions[$user->user_id] = $user->adi_soyadi;
    }
    ?>

    <div class="container-fluid">
        <?php
        $maintitle = 'Ana Sayfa';
        $title = 'Görev ve Bildirimler';
        ?>
        <?php include 'layouts/breadcrumb.php'; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <!-- Özet Kartları -->
                        <div class="row g-3 mb-4">
                            <!-- Toplam Bildirim -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #4e73df; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(78, 115, 223, 0.1);">
                                                <i class="bx bx-bell fs-4" style="color: #4e73df;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold"
                                                style="font-size: 0.65rem;">BİLDİRİM</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM BİLDİRİM</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?php echo $totalLogs; ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Bugün -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #1cc88a; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(28, 200, 138, 0.1);">
                                                <i class="bx bx-calendar-check fs-4" style="color: #1cc88a;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">BUGÜN</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">BUGÜNKÜ BİLDİRİMLER</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?php echo $todayCount; ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Bu Hafta -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #36b9cc; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(54, 185, 204, 0.1);">
                                                <i class="bx bx-calendar-week fs-4" style="color: #36b9cc;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">HAFTA</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">BU HAFTA</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?php echo $weekCount; ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Bildirim Tipi -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f6c23e; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(246, 194, 62, 0.1);">
                                                <i class="bx bx-category fs-4" style="color: #f6c23e;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TİP</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">BİLDİRİM TİPİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?php echo $uniqueTypes; ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtre Alanı -->
                        <div class="card border shadow-none mb-4"
                            style="border-radius: 12px; background: rgba(78, 115, 223, 0.02); border-color: rgba(78, 115, 223, 0.1) !important;">
                            <div class="card-body p-3">
                                <form id="filterForm" method="GET">
                                    <input type="hidden" name="p" value="gorev-bildirimler">
                                    <div class="row align-items-end g-3">
                                        <div class="col-md-2">
                                            <?php echo Form::FormFloatInput(
                                                type: 'date',
                                                name: 'date_start',
                                                value: $_GET['date_start'] ?? '',
                                                placeholder: 'Başlangıç Tarihi',
                                                label: 'Başlangıç Tarihi',
                                                icon: 'calendar'
                                            ); ?>
                                        </div>
                                        <div class="col-md-2">
                                            <?php echo Form::FormFloatInput(
                                                type: 'date',
                                                name: 'date_end',
                                                value: $_GET['date_end'] ?? '',
                                                placeholder: 'Bitiş Tarihi',
                                                label: 'Bitiş Tarihi',
                                                icon: 'calendar'
                                            ); ?>
                                        </div>
                                        <div class="col-md-2">
                                            <?php echo Form::FormSelect2(
                                                name: 'action_type',
                                                options: $actionTypeOptions,
                                                selectedValue: $_GET['action_type'] ?? '',
                                                label: 'Bildirim Tipi',
                                                icon: 'bx bx-category',
                                                class: 'form-select select2'
                                            ); ?>
                                        </div>
                                        <div class="col-md-2">
                                            <?php echo Form::FormSelect2(
                                                name: 'user_id',
                                                options: $userOptions,
                                                selectedValue: $_GET['user_id'] ?? '',
                                                label: 'Kullanıcı',
                                                icon: 'bx bx-user',
                                                class: 'form-select select2'
                                            ); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 justify-content-end ms-auto"
                                                style="width: fit-content;">
                                                <a href="index.php?p=home"
                                                    class="btn btn-link btn-sm text-danger text-decoration-none px-2 d-flex align-items-center">
                                                    <i class="bx bx-arrow-back fs-5 me-1"></i> Geri
                                                </a>
                                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                                <button type="button"
                                                    class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center"
                                                    id="btnExportExcel">
                                                    <i class="bx bx-spreadsheet fs-5 me-1"></i> Excel
                                                </button>
                                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                                <a href="index.php?p=gorev-bildirimler"
                                                    class="btn btn-link btn-sm text-secondary text-decoration-none px-2 d-flex align-items-center">
                                                    <i class="bx bx-reset fs-5 me-1"></i> Sıfırla
                                                </a>
                                                <div class="vr mx-1"
                                                    style="height: 25px; align-self: center; background-color: #e5e5e5; width: 1px;">
                                                </div>
                                                <button type="submit"
                                                    class="btn btn-dark btn-sm text-white shadow-sm px-3 d-flex align-items-center py-2"
                                                    style="border-radius: 8px; font-weight: 500;">
                                                    <i class="bx bx-filter-alt fs-5 me-1"></i> Filtrele
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Tablo -->
                        <div class="table-responsive">
                            <table id="bildirimlerTable" class="table datatable table-hover table-bordered nowrap w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Bildirim Tipi</th>
                                        <th>İçerik</th>
                                        <th>İşlemi Yapan</th>
                                        <th>Tarih</th>
                                        <th class="text-center" style="width: 60px;">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bx bx-info-circle fs-1 d-block mb-2"></i>
                                                Kayıt bulunmamaktadır.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $counter = 1;
                                        foreach ($logs as $log): ?>
                                            <?php
                                            $user_name = $log->adi_soyadi ?? 'Sistem';
                                            $full_desc = htmlspecialchars($log->description);
                                            $short_desc = mb_strimwidth($full_desc, 0, 100, "...");

                                            // Badge renkleri
                                            $badgeClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                                            $actionLower = mb_strtolower($log->action_type, 'UTF-8');

                                            if (strpos($actionLower, 'nöbet') !== false || strpos($actionLower, 'nobet') !== false) {
                                                $badgeClass = 'bg-info-subtle text-info border-info-subtle';
                                            } elseif (strpos($actionLower, 'maaş') !== false || strpos($actionLower, 'maas') !== false || strpos($actionLower, 'bordro') !== false || strpos($actionLower, 'hesaplama') !== false) {
                                                $badgeClass = 'bg-success-subtle text-success border-success-subtle';
                                            } elseif (strpos($actionLower, 'online') !== false || strpos($actionLower, 'sorgu') !== false) {
                                                $badgeClass = 'bg-primary-subtle text-primary border-primary-subtle';
                                            } elseif (strpos($actionLower, 'personel') !== false) {
                                                $badgeClass = 'bg-warning-subtle text-warning border-warning-subtle';
                                            } elseif (strpos($actionLower, 'sil') !== false || strpos($actionLower, 'silme') !== false) {
                                                $badgeClass = 'bg-danger-subtle text-danger border-danger-subtle';
                                            }
                                            ?>
                                            <tr>
                                                <td class="text-muted"><?php echo $counter++; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $badgeClass; ?> font-size-12 px-2 py-1 border">
                                                        <i
                                                            class="bx bx-info-circle me-1"></i><?php echo htmlspecialchars($log->action_type); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span title="<?php echo $full_desc; ?>"
                                                        style="max-width: 400px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo $short_desc; ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($user_name); ?>
                                                            tarafından)</small>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <div class="avatar-xs rounded-circle d-flex align-items-center justify-content-center"
                                                                style="background: linear-gradient(135deg, #4e73df, #224abe); color: #fff; font-size: 0.65rem; font-weight: 700; width: 28px; height: 28px;">
                                                                <?php
                                                                $initials = '';
                                                                $nameParts = explode(' ', $user_name);
                                                                foreach ($nameParts as $part) {
                                                                    $initials .= mb_substr($part, 0, 1, 'UTF-8');
                                                                }
                                                                echo mb_strtoupper(mb_substr($initials, 0, 2, 'UTF-8'), 'UTF-8');
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <div class="fw-medium">
                                                            <?php echo htmlspecialchars($user_name); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <i class="bx bx-time-five me-1"></i>
                                                        <?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>
                                                    </small>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-primary btn-bildirim-detay"
                                                        data-title="<?php echo htmlspecialchars($log->action_type); ?>"
                                                        data-user="<?php echo htmlspecialchars($user_name); ?>"
                                                        data-date="<?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>"
                                                        data-content="<?php echo $full_desc; ?>" title="Detay">
                                                        <i class="bx bx-show"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detay Modal -->
    <div class="modal fade" id="modalBildirimDetay" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bx bx-info-circle me-2"></i>Bildirim Detayı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small mb-1">Bildirim Tipi</label>
                            <h6 id="detayTitle" class="fw-bold">-</h6>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <label class="text-muted small mb-1">Tarih</label>
                            <h6 id="detayDate" class="fw-bold">-</h6>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small mb-1">İşlemi Yapan</label>
                        <h6 id="detayUser" class="fw-bold">-</h6>
                    </div>
                    <hr>
                    <div class="mb-0">
                        <label class="text-muted small mb-1">İçerik Detayı</label>
                        <div id="detayContent" class="p-3 bg-light rounded border"
                            style="white-space: pre-wrap; line-height: 1.6;">
                            -
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {

            // Detay Modal
            $(document).on('click', '.btn-bildirim-detay', function () {
                var title = $(this).data('title');
                var user = $(this).data('user');
                var date = $(this).data('date');
                var content = $(this).data('content');

                $('#detayTitle').text(title);
                $('#detayUser').text(user);
                $('#detayDate').text(date);

                // İçerik formatla
                if (content && content.includes('{') && content.includes('}')) {
                    try {
                        let parts = content.split(' (Güncellenen veriler: { ');
                        let mainText = parts[0];
                        let changesPart = parts[1].replace(' })', '');
                        let changes = changesPart.split(', ');
                        let formattedContent = '<div class="mb-2 fw-bold text-primary">' + mainText + '</div>';
                        formattedContent += '<table class="table table-sm table-bordered mt-2 mb-0"><thead class="table-light"><tr><th>Alan</th><th>Değişim</th></tr></thead><tbody>';
                        changes.forEach(function (change) {
                            if (change.includes(': ')) {
                                let parts2 = change.split(': ');
                                let key = parts2[0];
                                let val = parts2.slice(1).join(': ');
                                formattedContent += '<tr><td class="fw-bold" style="width: 30%;">' + key + '</td><td>' + val + '</td></tr>';
                            } else {
                                formattedContent += '<tr><td colspan="2" class="text-center text-muted">' + change + '</td></tr>';
                            }
                        });
                        formattedContent += '</tbody></table>';
                        $('#detayContent').html(formattedContent);
                    } catch (e) {
                        $('#detayContent').text(content);
                    }
                } else {
                    $('#detayContent').text(content);
                }

                var modal = new bootstrap.Modal(document.getElementById('modalBildirimDetay'));
                modal.show();
            });

            // Excel Export
            $('#btnExportExcel').click(function () {
                // DataTable referansını tıklama anında al (datatables.init.js tarafından başlatılmış olur)
                var dt = $('#bildirimlerTable').DataTable();
                var data = [];
                var headers = ['#', 'Bildirim Tipi', 'İçerik', 'İşlemi Yapan', 'Tarih'];
                data.push(headers);

                dt.rows({ search: 'applied' }).every(function (rowIdx) {
                    var row = this.data();
                    var rowData = [];
                    var tempDiv = document.createElement('div');

                    // #
                    rowData.push($(row[0]).text ? $(row[0]).text() : row[0]);

                    // Bildirim Tipi
                    tempDiv.innerHTML = row[1];
                    rowData.push(tempDiv.textContent.trim());

                    // İçerik
                    tempDiv.innerHTML = row[2];
                    var descEl = tempDiv.querySelector('[title]');
                    rowData.push(descEl ? (descEl.getAttribute('title') || descEl.textContent.trim()) : tempDiv.textContent.trim());

                    // İşlemi Yapan
                    tempDiv.innerHTML = row[3];
                    rowData.push(tempDiv.textContent.trim());

                    // Tarih
                    tempDiv.innerHTML = row[4];
                    rowData.push(tempDiv.textContent.trim());

                    data.push(rowData);
                });

                // CSV oluştur
                var BOM = '\uFEFF';
                var csvContent = BOM;
                data.forEach(function (rowArray) {
                    var row = rowArray.map(function (cell) {
                        var val = (cell || '').toString().replace(/"/g, '""');
                        return '"' + val + '"';
                    });
                    csvContent += row.join(';') + '\r\n';
                });

                var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);

                var now = new Date();
                var dateStr = now.getFullYear() + '-' +
                    String(now.getMonth() + 1).padStart(2, '0') + '-' +
                    String(now.getDate()).padStart(2, '0') + '_' +
                    String(now.getHours()).padStart(2, '0') + '-' +
                    String(now.getMinutes()).padStart(2, '0');

                link.setAttribute('download', 'Bildirimler_' + dateStr + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: 'Bildirimler Excel dosyası olarak indirildi.',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        });
    </script>

    <?php
} else {
    echo '<div class="alert alert-danger">Bu sayfaya erişim izniniz bulunmamaktadır.</div>';
}
?>