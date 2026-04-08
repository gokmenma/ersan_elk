<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
use App\Model\BildirimModel;

$BildirimModel = new BildirimModel();
$userId = (int) ($_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$unreadCount = $BildirimModel->getUnreadCount($userId);
?>

<style>
    /* Premium Filter Groups */
    .status-filter-group {
        background: #f8fafc; padding: 4px; border-radius: 50px;
        border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 2px;
    }
    [data-bs-theme="dark"] .status-filter-group { background: #2a3042; border-color: #32394e; }
    
    .status-filter-group .filter-link {
        border: none !important; border-radius: 50px !important;
        font-size: 0.75rem; font-weight: 600; padding: 6px 16px; color: #64748b;
        transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 6px; 
        line-height: normal; text-decoration: none; cursor: pointer;
    }
    [data-bs-theme="dark"] .status-filter-group .filter-link { color: #a6b0cf; }
    .status-filter-group .filter-link.active {
        background: #343a40 !important; color: white !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
    }
    .status-filter-group .filter-link.active-category { background: #556ee6 !important; color: white !important; }

    /* DataTable Styles */
    #notificationTable tbody tr.unread-row { background-color: rgba(85, 110, 230, 0.04) !important; border-left: 4px solid #556ee6; }
    [data-bs-theme="dark"] #notificationTable tbody tr.unread-row { background-color: rgba(85, 110, 230, 0.08) !important; }
    
    #notificationTable thead th { border-bottom: 2px solid #eff2f7; background: #f8fafc; font-size: 13px; font-weight: 600; color: #495057; }
    [data-bs-theme="dark"] #notificationTable thead th { background: #2e3548; border-bottom-color: #32394e; color: #a6b0cf; }

    /* Search Row customization */
    .search-input-row th { padding: 8px !important; background: #fff !important; }
    [data-bs-theme="dark"] .search-input-row th { background: #2a3042 !important; }
    .search-input-row input { border-color: #e2e8f0 !important; font-size: 12px !important; }
</style>

<div class="container-fluid">
    <?php
    $maintitle = "Sistem";
    $title = "Bildirimler";
    include dirname(__DIR__, 2) . '/layouts/breadcrumb.php'; 
    ?>

    <div class="row">
        <div class="col-12">
            <!-- Global Filter Bar -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        
                        <!-- Left: Status -->
                        <div class="status-filter-group">
                            <a href="javascript:void(0);" class="filter-link filter-main active" data-val="all">
                                <i class="bx bx-list-ul"></i> Tümü
                            </a>
                            <a href="javascript:void(0);" class="filter-link filter-main" data-val="unread">
                                <i class="bx bx-bell-off"></i> Okunmamışlar
                                <span class="badge bg-danger rounded-pill ms-1 unread-count-badge <?= $unreadCount > 0 ? '' : 'd-none' ?>"><?= $unreadCount ?></span>
                            </a>
                        </div>

                        <!-- Right: Categories -->
                        <div class="status-filter-group">
                            <a href="javascript:void(0);" class="filter-link filter-category active active-category" data-val="">
                                Hepsi
                            </a>
                            <?php foreach(['Destek', 'Avans', 'Arıza', 'İzin'] as $c): ?>
                                <a href="javascript:void(0);" class="filter-link filter-category" data-val="<?= $c ?>">
                                    <?= $c ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Bildirim Geçmişi</h4>
                    <button id="btn-mark-all-read-page" class="btn btn-soft-primary btn-sm rounded-pill <?= $unreadCount > 0 ? '' : 'd-none' ?>">
                        <i class="bx bx-check-double me-1"></i> Tümünü Okundu İşaretle
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="notificationTable" class="table table-centered table-nowrap mb-0 w-100">
                            <thead>
                                <tr>
                                    <th style="width: 140px;">Tarih</th>
                                    <th style="width: 120px;">Bildirim Türü</th>
                                    <th>İçerik</th>
                                    <th style="width: 100px;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentFilter = 'all';
    let currentCat = '';
    let notificationTable;

    function initTable() {
        notificationTable = destroyAndInitDataTable('#notificationTable', {
            serverSide: true,
            ajax: {
                url: 'views/bildirim/api.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'datatable-list';
                    d.filter = currentFilter;
                    d.cat = currentCat;
                },
                dataSrc: function(json) {
                    $('.unread-count-badge').text(json.unreadCount);
                    if (json.unreadCount > 0) {
                        $('.unread-count-badge').removeClass('d-none');
                        $('#btn-mark-all-read-page').removeClass('d-none');
                    } else {
                        $('.unread-count-badge').addClass('d-none');
                        $('#btn-mark-all-read-page').addClass('d-none');
                    }
                    return json.data;
                }
            },
            columns: [
                { data: 'tarih' },
                { data: 'turu', orderable: false, className: 'text-center' },
                { data: 'content', orderable: false },
                { data: 'islemler', orderable: false }
            ],
            order: [[0, 'desc']],
            pageLength: 20,
            drawCallback: function(settings) {
                const api = this.api();
                const rows = api.rows({ page: 'current' }).nodes();
                api.rows({ page: 'current' }).data().each(function(data, i) {
                    if (data.unread) {
                        $(rows[i]).addClass('unread-row');
                    }
                });
            }
        });
    }

    initTable();

    // Filter Buttons logic
    $('.filter-main').click(function() {
        $('.filter-main').removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('val');
        notificationTable.ajax.reload();
    });

    $('.filter-category').click(function() {
        $('.filter-category').removeClass('active active-category');
        $(this).addClass('active active-category');
        currentCat = $(this).data('val');
        notificationTable.ajax.reload();
    });

    // Action button events
    $(document).on('click', '.mark-as-read-btn', function() {
        const id = $(this).data('id');
        $.post('views/bildirim/api.php', { action: 'mark-read', id: id }, function(response) {
            if (response.status === 'success') {
                notificationTable.ajax.reload(null, false);
                if (window.fetchNotifications) window.fetchNotifications();
            }
        }, 'json');
    });

    $(document).on('click', '.mark-read-and-go', function() {
        const id = $(this).data('id');
        $.post('views/bildirim/api.php', { action: 'mark-read', id: id });
    });

    $('#btn-mark-all-read-page').click(function() {
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Tüm bildirimleriniz okundu olarak işaretlenecektir.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, İşaretle',
            cancelButtonText: 'Vazgeç',
            customClass: { confirmButton: 'btn btn-success rounded-pill px-4', cancelButton: 'btn btn-danger rounded-pill px-4' },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/bildirim/api.php', { action: 'mark-all-read' }, function(res) {
                    if (res.status === 'success') {
                        notificationTable.ajax.reload();
                        if (window.fetchNotifications) window.fetchNotifications();
                    }
                }, 'json');
            }
        });
    });
});
</script>
