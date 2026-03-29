<?php
/**
 * Destek Talepleri Listesi - Yönetici Paneli
 */
use App\Service\Gate;

if (!(Gate::allows('admin_destek_talebi') || Gate::isSuperAdmin())) {
    echo '<script>window.location.href = "?p=yardim/user-list";</script>';
    return;
}
?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">Destek Talepleri</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Yönetim</a></li>
                    <li class="breadcrumb-item active">Destek Talepleri</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
    .bordro-summary-card {
        border-radius: 12px !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.04) !important;
        background: #fff;
        position: relative;
    }

    .bordro-summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06) !important;
        border-color: rgba(0, 0, 0, 0.08) !important;
    }

    .icon-box {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        transition: all 0.3s ease;
    }

    .icon-label-container {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }
</style>

<!-- Stats Row -->
<div class="row g-3 mb-4" id="ticket-stats">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
            style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
                        <i class="bx bx-list-ul fs-4" style="color: #556ee6;"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM TALEP</p>
                <h4 class="mb-0 fw-bold">
                    <span class="counter-value" data-target="0" id="stat-toplam">0</span>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
            style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(241, 180, 76, 0.1);">
                        <i class="bx bx-time-five fs-4 text-warning"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">BEKLEYEN YANIT</p>
                <h4 class="mb-0 fw-bold">
                    <span class="counter-value" data-target="0" id="stat-bekleyen">0</span>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
            style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(52, 195, 143, 0.1);">
                        <i class="bx bx-check-double fs-4 text-success"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">YANITLANDI</p>
                <h4 class="mb-0 fw-bold">
                    <span class="counter-value" data-target="0" id="stat-yanitlanan">0</span>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
            style="--card-color: #f46a6a; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(244, 106, 106, 0.1);">
                        <i class="bx bx-lock fs-4 text-danger"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">KAPATILDI</p>
                <h4 class="mb-0 fw-bold">
                    <span class="counter-value" data-target="0" id="stat-kapali">0</span>
                </h4>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Tüm Destek Talepleri</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="status-filter" id="filter-all" value="" checked>
                        <label class="btn btn-outline-secondary" for="filter-all">Tümü</label>
                        
                        <input type="radio" class="btn-check" name="status-filter" id="filter-acik" value="acik">
                        <label class="btn btn-outline-warning" for="filter-acik">Açık</label>
                        
                        <input type="radio" class="btn-check" name="status-filter" id="filter-yanitlandi" value="yanitlandi">
                        <label class="btn btn-outline-success" for="filter-yanitlandi">Yanıtlandı</label>
                        
                        <input type="radio" class="btn-check" name="status-filter" id="filter-personel-yaniti" value="personel_yaniti">
                        <label class="btn btn-outline-primary" for="filter-personel-yaniti">Personel Yanıtı</label>

                        <input type="radio" class="btn-check" name="status-filter" id="filter-kapali" value="kapali">
                        <label class="btn btn-outline-danger" for="filter-kapali">Kapalı</label>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <table id="tickets-table" class="table table-bordered dt-responsive nowrap w-100">
                    <thead>
                        <tr>
                            <th>Ref No</th>
                            <th>Personel</th>
                            <th>Konu</th>
                            <th>Kategori</th>
                            <th>Öncelik</th>
                            <th>Son Güncelleme</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let table = $('#tickets-table').DataTable({
        order: [[5, 'desc']], // Son güncellemeye göre
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json'
        },
        columns: [
            { data: 'ref_no' },
            { data: 'personel_adi' },
            { data: 'konu' },
            { data: 'kategori' },
            { 
                data: 'oncelik',
                render: function(data) {
                    let badge = 'bg-secondary';
                    if(data === ' yuksek') badge = 'bg-danger';
                    if(data === 'orta') badge = 'bg-warning';
                    if(data === 'dusuk') badge = 'bg-info';
                    return `<span class="badge ${badge}">${data.toUpperCase()}</span>`;
                }
            },
            { data: 'guncelleme_tarihi' },
            { 
                data: 'durum',
                render: function(data) {
                    let badge = 'bg-secondary';
                    let text = data;
                    if(data === 'acik') { badge = 'bg-warning'; text = 'AÇIK'; }
                    if(data === 'yanitlandi') { badge = 'bg-success'; text = 'YANITLANDI'; }
                    if(data === 'personel_yaniti') { badge = 'bg-primary'; text = 'PERSONEL YANITI'; }
                    if(data === 'kapali') { badge = 'bg-danger'; text = 'KAPALI'; }
                    return `<span class="badge ${badge}">${text}</span>`;
                }
            },
            {
                data: null,
                render: function(data) {
                    return `<a href="?p=yardim/view&id=${data.encrypted_id || data.id}" class="btn btn-sm btn-primary">Görüntüle</a>`;
                }
            }
        ]
    });

    function loadTickets(status = '') {
        $.post('views/yardim/api.php', { action: 'get-tickets-admin', status: status }, function(res) {
            if(res.success) {
                table.clear().rows.add(res.tickets).draw();
                
                // Stats
                $('#stat-toplam').text(res.stats.toplam || 0);
                $('#stat-bekleyen').text(res.stats.bekleyen || 0);
                $('#stat-yanitlanan').text(res.stats.yanitlanan || 0);
                $('#stat-kapali').text(res.stats.kapali || 0);
            }
        });
    }

    // İlk yükleme
    loadTickets();

    // Filtreleme
    $('input[name="status-filter"]').on('change', function() {
        loadTickets($(this).val());
    });
});
</script>
