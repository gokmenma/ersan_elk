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

    /* Timeline Styles */
    .timeline-container {
        position: relative;
        padding-left: 3rem;
        margin-top: 1rem;
    }
    .timeline-container::before {
        display: none;
    }
    .timeline-item:not(:last-child)::after {
        content: '';
        position: absolute;
        left: -1.5rem;
        top: 1.75rem;
        bottom: -1rem;
        width: 2px;
        background: #e2e8f0;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }
    .timeline-dot {
        position: absolute;
        left: -2.25rem;
        top: 0.25rem;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
        box-shadow: 0 0 0 4px #fff;
    }
    .timeline-dot i { font-size: 0.7rem; color: #fff; }
    .timeline-content {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }
    .timeline-content:hover {
        background: #fff;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        border-color: #e2e8f0;
    }
    .timeline-time {
        font-size: 0.7rem;
        color: #94a3b8;
        font-weight: 600;
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    .timeline-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    .timeline-desc {
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.4;
    }
    .timeline-duration {
        font-size: 0.7rem;
        background: #f1f5f9;
        color: #475569;
        padding: 2px 8px;
        border-radius: 100px;
        display: inline-block;
        margin-top: 0.5rem;
        font-weight: 600;
    }
    
    /* Timeline Dots Colors */
    .dot-create { border-color: #3b82f6; background: #3b82f6; }
    .dot-approval { border-color: #f59e0b; background: #f59e0b; }
    .dot-reply { border-color: #10b981; background: #10b981; }
    /* Premium Filter Buttons */
    .status-filter-group {
        background: #f8fafc;
        padding: 4px;
        border-radius: 50px;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    .status-filter-group .btn-check + .btn {
        margin-bottom: 0 !important;
        border: none !important;
        border-radius: 50px !important;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 6px 16px;
        color: #64748b;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        line-height: normal;
    }

    .status-filter-group .btn-check + .btn i {
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 1px;
    }

    .status-filter-group .btn-check + .btn:hover {
        background: rgba(0, 0, 0, 0.04);
        color: #1e293b;
    }

    .status-filter-group .btn-check:checked + .btn {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    /* Active States with brand colors */
    .status-filter-group .btn-check:checked + .btn[for="filter-all"] { background: #64748b !important; color: #fff !important; }
    .status-filter-group .btn-check:checked + .btn[for="filter-acik"] { background: #f59e0b !important; color: #fff !important; }
    .status-filter-group .btn-check:checked + .btn[for="filter-isleme-alindi"] { background: #50a5f1 !important; color: #fff !important; }
    .status-filter-group .btn-check:checked + .btn[for="filter-yanitlandi"] { background: #10b981 !important; color: #fff !important; }
    .status-filter-group .btn-check:checked + .btn[for="filter-personel-yaniti"] { background: #3b82f6 !important; color: #fff !important; }
    .status-filter-group .btn-check:checked + .btn[for="filter-cozuldu"] { background: #34c38f !important; color: #fff !important; }
    .status-filter-group .btn-check:checked + .btn[for="filter-kapali"] { background: #ef4444 !important; color: #fff !important; }

    /* DataTable Search Inputs */
    tr.search-input-row th {
        padding: 8px !important;
        background: #fdfdfd !important;
        border-bottom: 2px solid #f1f5f9 !important;
    }

    tr.search-input-row input {
        border: 1px solid #e2e8f0 !important;
        border-radius: 6px !important;
        font-size: 0.72rem !important;
        padding: 0.4rem 0.6rem !important;
        background-color: #fff !important;
        transition: all 0.2s ease !important;
        color: #475569 !important;
    }

    tr.search-input-row input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        background-color: #fff !important;
    }

    tr.search-input-row input::placeholder {
        color: #94a3b8 !important;
        font-weight: 400 !important;
        opacity: 0.8 !important;
    }
</style>

<!-- Stats Row -->
<div class="row g-3 mb-4" id="ticket-stats">
    <div class="col-xxl-2 col-md-4">
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
    <div class="col-xxl-2 col-md-4">
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
    <div class="col-xxl-2 col-md-4">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
            style="--card-color: #50a5f1; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(80, 165, 241, 0.1);">
                        <i class="bx bx-loader fs-4 text-info"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">İŞLEMDERDE</p>
                <h4 class="mb-0 fw-bold">
                    <span class="counter-value" data-target="0" id="stat-islemde">0</span>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-xxl-2 col-md-4">
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
    <div class="col-xxl-2 col-md-4">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
            style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important; opacity: 0.8;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(52, 195, 143, 0.15);">
                        <i class="bx bx-check-circle fs-4 text-success"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">ÇÖZÜLENLER</p>
                <h4 class="mb-0 fw-bold">
                    <span class="counter-value" data-target="0" id="stat-cozuldu">0</span>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-xxl-2 col-md-4">
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
                    <div class="status-filter-group" role="group">
                        <input type="radio" class="btn-check" name="status-filter" id="filter-all" value="" checked>
                        <label class="btn" for="filter-all"><i class="bx bx-grid-alt"></i> Tümü</label>
                        
                        <input type="radio" class="btn-check" name="status-filter" id="filter-acik" value="acik">
                        <label class="btn" for="filter-acik"><i class="bx bx-loader-circle"></i> Açık</label>

                        <input type="radio" class="btn-check" name="status-filter" id="filter-isleme-alindi" value="isleme_alindi">
                        <label class="btn" for="filter-isleme-alindi"><i class="bx bx-loader bx-spin"></i> İşlemde</label>
                        
                        <input type="radio" class="btn-check" name="status-filter" id="filter-yanitlandi" value="yanitlandi">
                        <label class="btn" for="filter-yanitlandi"><i class="bx bx-check-circle"></i> Yanıtlandı</label>
                        
                        <input type="radio" class="btn-check" name="status-filter" id="filter-personel-yaniti" value="personel_yaniti">
                        <label class="btn" for="filter-personel-yaniti"><i class="bx bx-user-voice"></i> Personel Yanıtı</label>

                        <input type="radio" class="btn-check" name="status-filter" id="filter-cozuldu" value="cozuldu">
                        <label class="btn" for="filter-cozuldu"><i class="bx bx-check-square"></i> Çözüldü</label>

                        <input type="radio" class="btn-check" name="status-filter" id="filter-kapali" value="kapali">
                        <label class="btn" for="filter-kapali"><i class="bx bx-lock-alt"></i> Kapalı</label>
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
                            <th>Mesaj</th>
                            <th>Dosya</th>
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

<!-- Ticket Detail Modal -->
<div class="modal fade" id="ticketDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header bg-dark text-white p-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="p-2 bg-soft-light rounded-circle me-3">
                        <i class="bx bx-message-square-dots fs-3"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="detail-konu">-</h5>
                        <p class="text-white-50 mb-0 small" id="detail-ref">-</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-4 border-end bg-light p-4">
                        <h6 class="text-uppercase fw-bold text-muted small mb-3">TALEP BİLGİLERİ</h6>
                        <div class="mb-3">
                            <label class="text-muted small d-block mb-1">Talep Sahibi</label>
                            <p class="fw-bold mb-0" id="detail-personel">-</p>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block mb-1">Kategori</label>
                            <span class="badge bg-soft-info text-info rounded-pill px-3" id="detail-kategori">-</span>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block mb-1">Öncelik</label>
                            <span id="detail-oncelik">-</span>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block mb-1">Durum</label>
                            <div id="detail-durum">-</div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block mb-1">Tarih</label>
                            <p class="small mb-0" id="detail-tarih">-</p>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top" id="admin-actions">
                            <button type="button" class="btn btn-outline-info btn-sm w-100 rounded-pill mb-2" id="btn-process-ticket">
                                <i class="bx bx-loader me-1"></i> İşleme Al
                            </button>
                            <button type="button" class="btn btn-success btn-sm w-100 rounded-pill mb-2" id="btn-resolve-ticket">
                                <i class="bx bx-check-circle me-1"></i> Çözüldü Olarak İşaretle
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 rounded-pill mb-2" id="btn-close-ticket">
                                <i class="bx bx-lock-alt me-1"></i> Talebi Kapat
                            </button>
                            <a href="#" class="btn btn-dark btn-sm w-100 rounded-pill" id="btn-full-view">
                                <i class="bx bx-expand-alt me-1"></i> Tam Detayı Gör
                            </a>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="p-4" style="height: 450px; overflow-y: auto; background: #fff;" id="chat-container">
                            <div id="chat-loading" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div id="chat-messages" class="d-flex flex-column gap-3">
                                <!-- Messages will be rendered here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Timeline Modal (Existing) -->
<div class="modal fade" id="timelineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header bg-light/50 border-0 pt-4 px-4">
                <h5 class="modal-title fw-black text-slate-800 uppercase tracking-tighter" style="font-size: 1.1rem;">
                    <i class="bx bx-history me-2 text-primary"></i> İşlem Zaman Çizelgesi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <div id="timeline-info" class="mb-4 mt-3 ps-1">
                    <p class="text-muted small mb-1 fw-bold">Ref No: <span id="timeline-ref" class="text-primary">-</span></p>
                    <p class="text-dark fw-black mb-0" style="font-size: 0.85rem;"><span id="timeline-konu">-</span></p>
                </div>
                
                <div id="timeline-loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                
                <div id="timeline-content" class="timeline-container" style="display: none;">
                    <!-- Timeline items will be rendered here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let table = destroyAndInitDataTable('#tickets-table', {
        order: [[7, 'desc']], // Son güncellemeye göre (5 were Son Güncelleme, now 7)
        columns: [
            { data: 'ref_no' },
            { data: 'personel_adi' },
            { data: 'konu' },
            { data: 'kategori' },
            { 
                data: 'mesaj_sayisi', 
                className: 'text-center',
                render: data => `<span class="badge bg-primary text-white rounded-pill px-2 py-1 fw-bold" style="font-size: 0.75rem;"><i class="bx bx-chat me-1"></i>${data}</span>`
            },
            { 
                data: 'dosya_sayisi', 
                className: 'text-center',
                render: data => {
                    if(data == 0) return `<span class="text-muted opacity-50 small">-</span>`;
                    return `<span class="badge bg-soft-info text-info rounded-pill px-2 py-1 fw-bold" style="font-size: 0.75rem;"><i class="bx bx-paperclip me-1"></i>${data}</span>`;
                }
            },
            { 
                data: 'oncelik',
                render: function(data) {
                    let badge = 'bg-secondary';
                    if(data === 'yuksek') badge = 'bg-danger';
                    if(data === 'orta') badge = 'bg-warning';
                    if(data === 'dusuk') badge = 'bg-info';
                    return `<span class="badge ${badge}">${data.toUpperCase()}</span>`;
                }
            },
            { data: 'guncelleme_tarihi' },
            { 
                data: 'durum', render: function(data, type, row) {
                if ((row.onay_durumu || '') === 'beklemede') {
                    return '<span class="badge bg-secondary p-2 px-3 rounded-pill">ONAY BEKLİYOR</span>';
                }

                let badge = 'bg-secondary';
                let text = (data || 'ACIK').toUpperCase();
                let icon = '';
                
                if(data === 'acik') badge = 'bg-soft-warning text-warning';
                if(data === 'yanitlandi') badge = 'bg-soft-success text-success';
                if(data === 'personel_yaniti') badge = 'bg-soft-primary text-primary';
                if(data === 'isleme_alindi') { badge = 'bg-soft-info text-info'; text = 'İŞLEME ALINDI'; icon = '<i class="bx bx-loader bx-spin me-1"></i>'; }
                if(data === 'cozuldu') { badge = 'bg-soft-success text-success'; text = 'ÇÖZÜLDÜ'; icon = '<i class="bx bx-check-circle me-1"></i>'; }
                if(data === 'kapali') badge = 'bg-soft-danger text-danger';

                return `<span class="badge ${badge} p-2 px-3 rounded-pill show-timeline-btn fw-bold" data-id="${row.id}" data-ref="${row.ref_no}" data-konu="${row.konu}" style="cursor: pointer;" title="İşlem geçmişini gör">${icon}${text}</span>`;
            }
            },
            {
                data: null,
                className: 'text-center',
                render: function(data) {
                    let html = `<div class="d-flex gap-1 justify-content-center">`;
                    html += `<a href="?p=yardim/view&id=${data.encrypted_id || data.id}" class="btn btn-sm btn-soft-primary px-2" title="Görüntüle"><i class="bx bx-show-alt"></i></a>`;
                    
                    if (data.durum !== 'kapali') {
                        if (data.durum !== 'isleme_alindi') {
                            html += `<button type="button" class="btn btn-sm btn-soft-info px-2 btn-process-ticket-direct" data-id="${data.id}" title="İşleme Al"><i class="bx bx-loader"></i></button>`;
                        }
                        if (data.durum !== 'cozuldu') {
                            html += `<button type="button" class="btn btn-sm btn-soft-success px-2 btn-resolve-ticket-direct" data-id="${data.id}" title="Çözüldülarak İşaretle"><i class="bx bx-check-circle"></i></button>`;
                        }
                        html += `<button type="button" class="btn btn-sm btn-soft-danger px-2 btn-close-ticket-direct" data-id="${data.id}" title="Talebi Kapat"><i class="bx bx-lock-alt"></i></button>`;
                    }
                    
                    html += `</div>`;
                    return html;
                }
            }
        ],
        createdRow: function(row, data, dataIndex) {
            $(row).css('cursor', 'pointer');
            $(row).addClass('ticket-row');
            $(row).attr('data-id', data.id);
            $(row).attr('data-encrypted-id', data.encrypted_id);
        }
    });

    // Row Click Handler
    $('#tickets-table tbody').on('click', 'tr.ticket-row', function(e) {
        if ($(e.target).closest('a, button, .badge').length) return;
        
        const id = $(this).data('id');
        const encryptedId = $(this).data('encrypted-id');
        openTicketDetail(id, encryptedId);
    });

    // Delegate Timeline click
    $(document).on('click', '.show-timeline-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        showTimeline($(this).data('id'), $(this).data('ref'), $(this).data('konu'));
    });

    function loadTickets(status = '') {
        $.post('views/yardim/api.php', { action: 'get-tickets-admin', status: status }, function(res) {
            if(res.success) {
                table.clear().rows.add(res.tickets).draw();
                
                // Stats
                $('#stat-toplam').text(res.stats.toplam || 0);
                $('#stat-bekleyen').text(res.stats.bekleyen || 0);
                $('#stat-islemde').text(res.stats.islemde || 0);
                $('#stat-yanitlanan').text(res.stats.yanitlanan || 0);
                $('#stat-cozuldu').text(res.stats.cozuldu || 0);
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

    // Close Ticket Action
    $('#btn-close-ticket').on('click', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu destek talebi kapatılacaktır!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f46a6a',
            cancelButtonColor: '#74788d',
            confirmButtonText: 'Evet, Kapat',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'kapali' }, function(res) {
                    if(res.success) {
                        Swal.fire('Kapatıldı!', 'Talep başarıyla kapatıldı.', 'success');
                        $('#ticketDetailModal').modal('hide');
                        loadTickets($('input[name="status-filter"]:checked').val());
                    } else {
                        Swal.fire('Hata!', res.message, 'error');
                    }
                });
            }
        });
    });

    $('#btn-process-ticket').on('click', function() {
        const id = $(this).data('id');
        $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'isleme_alindi' }, function(res) {
            if(res.success) {
                Swal.fire('Bilgi', 'Talep işleme alındı olarak işaretlendi.', 'info');
                $('#ticketDetailModal').modal('hide');
                loadTickets($('input[name="status-filter"]:checked').val());
            } else {
                Swal.fire('Hata!', res.message, 'error');
            }
        });
    });

    $('#btn-resolve-ticket').on('click', function() {
        const id = $(this).data('id');
        $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'cozuldu' }, function(res) {
            if(res.success) {
                Swal.fire('Başarılı', 'Talep çözüldü olarak işaretlendi.', 'success');
                $('#ticketDetailModal').modal('hide');
                loadTickets($('input[name="status-filter"]:checked').val());
            } else {
                Swal.fire('Hata!', res.message, 'error');
            }
        });
    });

    // Direct actions from table
    $(document).on('click', '.btn-process-ticket-direct', function(e) {
        e.preventDefault(); e.stopPropagation();
        const id = $(this).data('id');
        $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'isleme_alindi' }, function(res) {
            if(res.success) {
                Swal.fire('Bilgi', 'Talep işleme alındı.', 'info');
                loadTickets($('input[name="status-filter"]:checked').val());
            } else {
                Swal.fire('Hata', res.message || 'Hata oluştu.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-resolve-ticket-direct', function(e) {
        e.preventDefault(); e.stopPropagation();
        const id = $(this).data('id');
        $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'cozuldu' }, function(res) {
            if(res.success) {
                Swal.fire('Başarılı', 'Talep çözüldü olarak işaretlendi.', 'success');
                loadTickets($('input[name="status-filter"]:checked').val());
            } else {
                Swal.fire('Hata', res.message || 'Hata oluştu.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-close-ticket-direct', function(e) {
        e.preventDefault(); e.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Talebi Kapat?',
            text: "Bu talep kapatılacaktır.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Kapat',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'kapali' }, function(res) {
                    if(res.success) {
                        Swal.fire('Başarılı', 'Talep kapatıldı.', 'success');
                        loadTickets($('input[name="status-filter"]:checked').val());
                    } else {
                        Swal.fire('Hata', res.message || 'Hata oluştu.', 'error');
                    }
                });
            }
        });
    });

    function openTicketDetail(id, encryptedId) {
        $('#detail-konu').text('Yükleniyor...');
        $('#chat-loading').show();
        $('#chat-messages').empty();
        $('#btn-close-ticket').data('id', id);
        $('#btn-full-view').attr('href', `?p=yardim/view&id=${encryptedId || id}`);
        $('#ticketDetailModal').modal('show');

        $.post('views/yardim/api.php', { action: 'get-ticket-details', bilet_id: id }, function(res) {
            $('#chat-loading').hide();
            if(res.success) {
                const ticket = res.ticket;
                $('#detail-konu').text(ticket.konu);
                $('#detail-ref').text(ticket.ref_no);
                $('#detail-personel').text(ticket.personel_adi);
                $('#detail-kategori').text(ticket.kategori);
                $('#detail-tarih').text(ticket.olusturma_tarihi);
                
                let oncelikBadge = 'bg-secondary';
                if(ticket.oncelik === 'yuksek') oncelikBadge = 'bg-danger';
                if(ticket.oncelik === 'orta') oncelikBadge = 'bg-warning';
                if(ticket.oncelik === 'dusuk') oncelikBadge = 'bg-info';
                $('#detail-oncelik').html(`<span class="badge ${oncelikBadge}">${ticket.oncelik.toUpperCase()}</span>`);

                let durumBadge = 'bg-soft-secondary';
                if(ticket.durum === 'acik') durumBadge = 'bg-soft-warning text-warning';
                if(ticket.durum === 'yanitlandi') durumBadge = 'bg-soft-success text-success';
                if(ticket.durum === 'personel_yaniti') durumBadge = 'bg-soft-primary text-primary';
                if(ticket.durum === 'isleme_alindi') durumBadge = 'bg-soft-info text-info';
                if(ticket.durum === 'cozuldu') durumBadge = 'bg-soft-success text-success';
                if(ticket.durum === 'kapali') durumBadge = 'bg-soft-danger text-danger';
                $('#detail-durum').html(`<span class="badge ${durumBadge} rounded-pill px-3 fw-bold">${ticket.durum.toUpperCase().replace('_', ' ')}</span>`);

                if(ticket.durum === 'kapali') {
                    $('#btn-close-ticket, #btn-process-ticket, #btn-resolve-ticket').hide();
                } else {
                    $('#btn-close-ticket').show();
                    if(ticket.durum === 'isleme_alindi') $('#btn-process-ticket').hide(); else $('#btn-process-ticket').show();
                    if(ticket.durum === 'cozuldu') $('#btn-resolve-ticket').hide(); else $('#btn-resolve-ticket').show();
                }

                $('#btn-process-ticket, #btn-resolve-ticket').data('id', ticket.id);

                // Render Messages
                if(ticket.messages && ticket.messages.length > 0) {
                    let chatHtml = '';
                    ticket.messages.forEach(msg => {
                        const isYonetici = msg.gonderen_tip === 'yonetici';
                        const align = isYonetici ? 'ms-auto bg-dark text-white' : 'me-auto bg-light text-dark';
                        const name = isYonetici ? 'Destek Ekibi' : msg.gonderen_adi;
                        
                        chatHtml += `
                            <div class="p-3 rounded-lg ${align}" style="max-width: 85%; border-radius: 12px;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold small">${name}</span>
                                    <span class="small opacity-75 ms-3" style="font-size: 0.7rem;">${msg.olusturma_tarihi}</span>
                                </div>
                                <div class="message-text">${msg.mesaj.replace(/\n/g, '<br>')}</div>
                                ${msg.dosyalar && msg.dosyalar.length > 0 ? `
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        ${msg.dosyalar.map(file => `<a href="${file}" target="_blank" class="badge bg-soft-primary text-primary text-decoration-none p-1 px-2 border"><i class="bx bx-paperclip me-1"></i> Dosya Eki</a>`).join('')}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    });
                    $('#chat-messages').html(chatHtml);
                    
                    // Scroll to bottom
                    const chatCont = document.getElementById('chat-container');
                    chatCont.scrollTop = chatCont.scrollHeight;
                }
            }
        });
    }
});

function showTimeline(id, refNo, konu) {
    $('#timeline-ref').text(refNo);
    $('#timeline-konu').text(konu);
    $('#timeline-loading').show();
    $('#timeline-content').hide().empty();
    $('#timelineModal').modal('show');

    $.post('views/yardim/api.php', { action: 'get-ticket-details', bilet_id: id }, function(res) {
        $('#timeline-loading').hide();
        if(res.success) {
            const ticket = res.ticket;
            let items = [];

            // 1. Oluşturma
            items.push({
                time: ticket.olusturma_tarihi,
                title: 'Talep Oluşturuldu',
                desc: `Talep <b>${ticket.personel_adi}</b> tarafından başarıyla sisteme kaydedildi.`,
                icon: 'bx bx-plus',
                class: 'dot-create',
                dateObj: new Date(ticket.olusturma_tarihi)
            });

            // 2. Onay (Eğer onaylanmış/reddedilmişse ve tarihi varsa)
            if(ticket.onay_durumu !== 'beklemede' && ticket.onay_tarihi) {
                const statusText = ticket.onay_durumu === 'onaylandi' ? 'Onaylandı' : 'Reddedildi';
                items.push({
                    time: ticket.onay_tarihi,
                    title: `Yönetici Onay ${statusText}`,
                    desc: `Talep ön onay sürecinden geçti.`,
                    icon: ticket.onay_durumu === 'onaylandi' ? 'bx bx-check' : 'bx bx-x',
                    class: 'dot-approval',
                    dateObj: new Date(ticket.onay_tarihi)
                });
            }

            // 3. Mesajlar
            if(ticket.messages && ticket.messages.length > 0) {
                ticket.messages.forEach(msg => {
                    // İlk mesaj biletin kendisi olabilir (bazen biletin mesajı ilk mesaj olarak dönüyor)
                    // Eğer olusturma_tarihi ile aynıysa talep detayıdır.
                    // Not: Normalde ilk mesaj biletin ilk metnidir.
                    if(msg.olusturma_tarihi === ticket.olusturma_tarihi) return;

                    const isYonetici = msg.gonderen_tip === 'yonetici';
                    items.push({
                        time: msg.olusturma_tarihi,
                        title: isYonetici ? 'Destek Yanıtı' : 'Personel Mesajı',
                        desc: `<b>${msg.gonderen_adi}</b> tarafından yeni bir mesaj eklendi.`,
                        icon: isYonetici ? 'bx bx-message-rounded-dots' : 'bx bx-reply',
                        class: isYonetici ? 'dot-reply' : 'dot-personel',
                        dateObj: new Date(msg.olusturma_tarihi)
                    });
                });
            }

            // 4. Kapatma
            if(ticket.durum === 'kapali' && ticket.kapatma_tarihi) {
                items.push({
                    time: ticket.kapatma_tarihi,
                    title: 'Talep Kapatıldı',
                    desc: `Talebiniz <b>${ticket.kapatan_adi || 'Sistem'}</b> tarafından sonlandırıldı.`,
                    icon: 'bx bx-lock',
                    class: 'dot-close',
                    dateObj: new Date(ticket.kapatma_tarihi)
                });
            }

            // Tarihe göre sırala
            items.sort((a, b) => a.dateObj - b.dateObj);

            // Render
            let html = '';
            items.forEach((item, index) => {
                let durationHtml = '';
                if(index > 0) {
                    const diffMs = items[index].dateObj - items[index-1].dateObj;
                    const diffMins = Math.round(diffMs / 60000);
                    if(diffMins < 60) {
                        durationHtml = `<span class="timeline-duration"><i class="bx bx-time-five me-1"></i>+${diffMins} dk</span>`;
                    } else if(diffMins < 1440) {
                        durationHtml = `<span class="timeline-duration"><i class="bx bx-time-five me-1"></i>+${Math.round(diffMins/60)} saat</span>`;
                    } else {
                        durationHtml = `<span class="timeline-duration"><i class="bx bx-time-five me-1"></i>+${Math.round(diffMins/1440)} gün</span>`;
                    }
                }

                html += `
                    <div class="timeline-item">
                        <div class="timeline-dot ${item.class}">
                            <i class="${item.icon}"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-time">${item.time}</div>
                            <div class="timeline-title">${item.title}</div>
                            <div class="timeline-desc">${item.desc}</div>
                            ${durationHtml}
                        </div>
                    </div>
                `;
            });

            $('#timeline-content').html(html).fadeIn();
        } else {
            $('#timeline-loading').html(`<div class="text-danger">${res.message}</div>`);
        }
    });
}
</script>
