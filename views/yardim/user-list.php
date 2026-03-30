<?php
/**
 * Destek Taleplerim - Portal Kullanıcı Ekranı
 */
use App\Helper\Form;

// Select2 opsiyonları
$kategoriler = [
    'Genel' => 'Genel',
    'Teknik Sorun' => 'Teknik Sorun',
    'Hata Bildirimi' => 'Hata Bildirimi',
    'İşleyiş/Süreç' => 'İşleyiş/Süreç',
    'Diğer' => 'Diğer'
];

$oncelikler = [
    'dusuk' => 'Düşük',
    'orta' => 'Orta',
    'yuksek' => 'Yüksek'
];
?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">Yardım ve Destek</h4>
            <div class="page-title-right">
                <button type="button" class="btn btn-primary btn-rounded shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#newTicketModal">
                    <i data-feather="plus" class="icon-sm me-1"></i> Yeni Destek Talebi
                </button>
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
    .icon-box {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 12px;
    }
    .icon-label-container { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
    
    /* Premium Modal Styles matched with evrak-modal */
    .modal-title-section { display: flex; align-items: center; }
    .modal-icon-box {
        width: 45px; height: 45px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        margin-right: 15px; font-size: 20px;
    }
    .modal-title-group .modal-title { font-size: 1.1rem; line-height: 1.2; }
    .modal-subtitle { font-size: 0.8rem; }
    .icon-sm { width: 16px; height: 16px; }

    /* Timeline Styles */
    .timeline-container { position: relative; padding-left: 3rem; margin-top: 1rem; }
    .timeline-container::before { display: none; }
    .timeline-item:not(:last-child)::after { content: ''; position: absolute; left: -1.5rem; top: 1.75rem; bottom: -1rem; width: 2px; background: #e2e8f0; }
    .timeline-item { position: relative; margin-bottom: 2rem; }
    .timeline-dot { position: absolute; left: -2.25rem; top: 0.25rem; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: #fff; border: 2px solid #64748b; display: flex; align-items: center; justify-content: center; z-index: 1; box-shadow: 0 0 0 4px #fff; }
    .timeline-dot i { font-size: 0.7rem; color: #fff; }
    .timeline-content { background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #f1f5f9; transition: all 0.2s ease; }
    .timeline-content:hover { background: #fff; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border-color: #e2e8f0; }
    .timeline-time { font-size: 0.7rem; color: #94a3b8; font-weight: 600; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.025em; }
    .timeline-title { font-size: 0.9rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem; }
    .timeline-desc { font-size: 0.8rem; color: #64748b; line-height: 1.4; }
    .timeline-duration { font-size: 0.7rem; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 100px; display: inline-block; margin-top: 0.5rem; font-weight: 600; }
    .dot-create { border-color: #3b82f6; background: #3b82f6; }
    .dot-approval { border-color: #f59e0b; background: #f59e0b; }
    .dot-reply { border-color: #10b981; background: #10b981; }
    .dot-personel { border-color: #6366f1; background: #6366f1; }
    .dot-close { border-color: #ef4444; background: #ef4444; }
</style>

<!-- Stats Row -->
<div class="row g-3 mb-4" id="ticket-stats">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
                        <i data-feather="list" class="text-primary"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1" id="stat-own-wrapper">
                        <p class="text-muted mb-1 small fw-bold text-uppercase" id="stat-own-label">Kendi Taleplerim</p>
                        <h4 class="mb-0 fw-bold"><span id="stat-own-count">0</span></h4>
                    </div>
                    <div class="border-start ps-3 ms-3" id="stat-personnel-wrapper" style="display:none; min-width: 90px;">
                        <p class="text-muted mb-1 small fw-bold text-uppercase">Personel</p>
                        <h4 class="mb-0 fw-bold text-info"><span id="stat-personnel-count">0</span></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(241, 180, 76, 0.1);">
                        <i data-feather="clock" class="text-warning"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold">BEKLEYEN</p>
                <h4 class="mb-0 fw-bold"><span id="stat-bekleyen">0</span></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(52, 195, 143, 0.1);">
                        <i data-feather="check-circle" class="text-success"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold">YANITLANDI</p>
                <h4 class="mb-0 fw-bold"><span id="stat-yanitlanan">0</span></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6" id="approval-pending-card" style="display:none;">
        <div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #8b5cf6; border-bottom: 3px solid var(--card-color) !important;">
            <div class="card-body p-3">
                <div class="icon-label-container">
                    <div class="icon-box" style="background: rgba(139, 92, 246, 0.12);">
                        <i data-feather="shield" style="color: #8b5cf6;"></i>
                    </div>
                </div>
                <p class="text-muted mb-1 small fw-bold">ONAY BEKLEYEN</p>
                <h4 class="mb-0 fw-bold"><span id="stat-approval-pending">0</span></h4>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <table id="userTicketsTable" class="table table-hover dt-responsive nowrap w-100 datatable-deferred">
                    <thead class="table-light">
                        <tr>
                            <th>Ref No</th>
                            <th>Talep Sahibi</th>
                            <th>Kayıt Tipi</th>
                            <th>Konu</th>
                            <th>Kategori</th>
                            <th>Son Güncelleme</th>
                            <th>Durum</th>
                            <th class="text-center">İşlem</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Ticket Modal -->
<div class="modal fade" id="newTicketModal" tabindex="-1" aria-labelledby="newTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark py-3 px-1">
                <div class="modal-title-section ps-3">
                    <div class="modal-icon-box bg-primary-subtle text-primary">
                        <i data-feather="plus-circle"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title text-white fw-bold" id="newTicketModalLabel">Yeni Destek Talebi</h5>
                        <p class="modal-subtitle text-white-50">Talebinizle ilgili bilgileri eksiksiz doldurunuz.</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="new-ticket-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create-ticket">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <?= Form::FormFloatInput('text', 'konu', '', 'Yardım almak istediğiniz konuyu özetleyin', 'Talep Konusu *', 'edit-3', 'form-control', true) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormSelect2('kategori', $kategoriler, 'Genel', 'Kategori *', 'grid', 'key', '', 'form-select select2', true) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormSelect2('oncelik', $oncelikler, 'orta', 'Öncelik Seviyesi *', 'flag', 'key', '', 'form-select select2', true) ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatTextarea('mesaj', '', 'Detaylı açıklama yapmanız size daha hızlı yardımcı olmamızı sağlar...', 'Mesajınız *', 'message-square', 'form-control', true, '120px') ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFileInput('dosya', 'Ekran Görüntüsü / Belge (Opsiyonel)', 'upload-cloud', 'form-control') ?>
                            <div class="form-text mt-1 text-muted small"><i data-feather="info" class="icon-sm me-1"></i> Sadece resim formatları (JPG, PNG, WEBP) desteklenir.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 p-3 px-4">
                    <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none px-3" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" id="btn-save-ticket" class="btn btn-dark px-5 shadow-sm fw-bold rounded-pill">
                         Talebi Gönder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTables initialization via standard pattern
    let userTable = destroyAndInitDataTable('#userTicketsTable', {
        ajax: {
            url: 'views/yardim/api.php?action=get-tickets-pwa',
            dataSrc: 'tickets'
        },
        columns: [
            { data: 'ref_no', render: data => `<span class="fw-bold text-primary">${data}</span>` },
            { data: 'personel_adi', defaultContent: '-' },
            { data: 'list_context', render: function(data, type, row) {
                if ((data || '') === 'onay') {
                    return '<span class="badge bg-soft-warning text-warning">Onay Bekleyen</span>';
                }

                if ((data || '') === 'onay_tamamlandi') {
                    return '<span class="badge bg-soft-success text-success">Onaylanan</span>';
                }

                if ((row.onay_durumu || '') === 'beklemede') {
                    return '<span class="badge bg-soft-secondary text-secondary">Kendi Talebim (Onayda)</span>';
                }

                return '<span class="badge bg-soft-primary text-primary">Kendi Talebim</span>';
            }},
            { data: 'konu' },
            { data: 'kategori' },
            { data: 'guncelleme_tarihi' },
            { data: 'durum', render: function(data, type, row) {
                if ((row.onay_durumu || '') === 'beklemede') {
                    return '<span class="badge bg-secondary p-2 px-3 rounded-pill">ONAY BEKLİYOR</span>';
                }

                let badge = 'bg-secondary';
                if(data === 'acik') badge = 'bg-warning';
                if(data === 'yanitlandi') badge = 'bg-success';
                if(data === 'personel_yaniti') badge = 'bg-primary';
                if(data === 'kapali') badge = 'bg-danger';
                return `<span class="badge ${badge} p-2 px-3 rounded-pill" onclick="showTimeline(${row.id}, '${row.ref_no}', '${row.konu}')" style="cursor: pointer;" title="İşlem geçmişini gör">${data.toUpperCase()}</span>`;
            }},
            { 
                data: null, 
                className: 'text-center',
                render: data => {
                    // For approved tickets (onay_tamamlandi): open in modal
                    if ((data.list_context || '') === 'onay_tamamlandi') {
                        return `<button type="button" class="btn btn-sm btn-soft-primary waves-effect waves-light px-3 btn-ticket-detail" data-ticket-id="${data.id}" data-encrypted-id="${data.encrypted_id}"><i data-feather="eye" class="icon-sm me-1"></i> Detay</button>`;
                    }

                    // For other tickets: open full page
                    const actionText = (data.list_context || '') === 'onay' ? 'İncele / Onayla' : 'Detay';
                    return `<a href="?p=yardim/view&id=${data.encrypted_id || data.id}" class="btn btn-sm btn-soft-info waves-effect waves-light px-3"><i data-feather="eye" class="icon-sm me-1"></i> ${actionText}</a>`;
                }
            }
        ],
        order: [[5, 'desc']],
        initComplete: function(settings, json) {
            updateStats(json.stats);
            if (typeof feather !== 'undefined') feather.replace();
        }
    });

    function updateStats(stats) {
        if(!stats) return;
        $('#stat-own-count').text(stats.toplam || 0);
        $('#stat-bekleyen').text(stats.bekleyen || 0);
        $('#stat-yanitlanan').text(stats.yanitlanan || 0);
    }

    $('#userTicketsTable').on('xhr.dt', function(e, settings, json) {
        const isApprover = !!(json && json.is_approver);
        const approvalPendingCount = (json && typeof json.approval_pending_count !== 'undefined') ? json.approval_pending_count : 0;
        const ownTicketsCount = (json && typeof json.own_tickets_count !== 'undefined') ? json.own_tickets_count : 0;
        const personnelTicketsCount = (json && typeof json.personnel_tickets_count !== 'undefined') ? json.personnel_tickets_count : 0;

        if (isApprover) {
            $('#stat-own-label').text('Kendi Taleplerim');
            $('#stat-own-count').text(ownTicketsCount || 0);
            $('#stat-personnel-wrapper').show();
            $('#stat-personnel-count').text(personnelTicketsCount || 0);
            $('#approval-pending-card').show();
            $('#stat-approval-pending').text(approvalPendingCount || 0);
        } else {
            $('#stat-own-label').text('Toplam Talep');
            $('#stat-own-count').text(ownTicketsCount || 0);
            $('#stat-personnel-wrapper').hide();
            $('#approval-pending-card').hide();
            $('#stat-approval-pending').text(0);
        }

        if (typeof feather !== 'undefined') feather.replace();
    });

    // Modal submission handling with robustness
    $('#new-ticket-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $btn = $('#btn-save-ticket');
        const originalText = $btn.text();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Gönderiliyor...');

        const formData = new window.FormData(this);
        $.ajax({
            url: 'views/yardim/api.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if(res.success) {
                    Swal.fire({
                        title: 'Başarılı!',
                        text: res.message,
                        icon: 'success',
                        confirmButtonColor: '#34c38f',
                        confirmButtonText: 'Tamam'
                    });
                    $('#newTicketModal').modal('hide');
                    $('#new-ticket-form')[0].reset();
                    if ($.fn.select2) {
                        $('#kategori').val('Genel').trigger('change');
                        $('#oncelik').val('orta').trigger('change');
                    }
                    userTable.ajax.reload();
                } else {
                    Swal.fire({
                        title: 'Hata!',
                        text: res.message,
                        icon: 'error',
                        confirmButtonColor: '#f46a6a'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Sistem Hatası!',
                    text: 'İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.',
                    icon: 'error',
                    confirmButtonColor: '#f46a6a'
                });
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Modal life-cycle events
    $('#newTicketModal').on('hidden.bs.modal', function () {
        $('#new-ticket-form')[0].reset();
        if ($.fn.select2) {
            $('.select2').val('Genel').trigger('change');
            $('#oncelik').val('orta').trigger('change');
        }
    });

    $('#newTicketModal').on('shown.bs.modal', function() {
        if ($.fn.select2) {
            $(this).find('.select2').each(function() {
                $(this).select2({
                    dropdownParent: $('#newTicketModal'),
                    width: '100%',
                    minimumResultsForSearch: Infinity
                });
            });
        }
        if (typeof feather !== 'undefined') feather.replace();
    });

    // Trigger feather replace on page load for static elements
    if (typeof feather !== 'undefined') feather.replace();

    // Modal ticket details open handler
    $(document).on('click', '.btn-ticket-detail', function(e) {
        e.preventDefault();
        const ticketId = $(this).data('ticket-id');
        const encryptedId = $(this).data('encrypted-id');
        
        if (!ticketId) return;

        // Start loading
        $('#ticketDetailModal .modal-body').html('<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Yükleniyor...</span></div></div>');
        $('#ticketDetailModal').modal('show');

        // Fetch ticket details
        $.post('views/yardim/api.php', {
            action: 'get-ticket-details',
            bilet_id: ticketId
        }, function(res) {
            if (res.success && res.ticket) {
                const ticket = res.ticket;
                const onayDurumu = (ticket.onay_durumu || 'onaylandi').toUpperCase();
                let onayClass = 'bg-success';
                if ((ticket.onay_durumu || 'onaylandi') === 'beklemede') onayClass = 'bg-warning';
                if ((ticket.onay_durumu || 'onaylandi') === 'reddedildi') onayClass = 'bg-danger';

                const durum = (ticket.durum || 'unknown').toUpperCase();
                let duremClass = 'bg-secondary';
                if(ticket.durum === 'acik') duremClass = 'bg-warning';
                if(ticket.durum === 'yanitlandi') duremClass = 'bg-success';
                if(ticket.durum === 'personel_yaniti') duremClass = 'bg-primary';
                if(ticket.durum === 'kapali') duremClass = 'bg-danger';

                let detailContent = `
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr><th scope="row">Ref No:</th><td><span class="fw-bold">${ticket.ref_no || '-'}</span></td></tr>
                                    <tr><th scope="row">Talep Sahibi:</th><td>${ticket.personel_adi || '-'}</td></tr>
                                    <tr><th scope="row">Kategori:</th><td>${ticket.kategori || '-'}</td></tr>
                                    <tr><th scope="row">Öncelik:</th><td>${ticket.oncelik || '-'}</td></tr>
                                    <tr><th scope="row">Durum:</th><td><span class="badge ${duremClass} p-2 px-3 rounded-pill">${durum}</span></td></tr>
                                    <tr><th scope="row">Onay:</th><td><span class="badge ${onayClass} p-2 px-3 rounded-pill">${onayDurumu}</span></td></tr>
                                    <tr><th scope="row">Oluşturma:</th><td>${ticket.olusturma_tarihi || '-'}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Konu:</label>
                                <p>${ticket.konu || '-'}</p>
                            </div>
                        </div>
                    </div>
                `;

                $('#ticketDetailModal .modal-body').html(detailContent);
            } else {
                $('#ticketDetailModal .modal-body').html('<div class="alert alert-danger">Talep yüklenemedi.</div>');
            }
        }).fail(function() {
            $('#ticketDetailModal .modal-body').html('<div class="alert alert-danger">Talep yüklenirken hata oluştu.</div>');
        });
    });
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

            items.push({
                time: ticket.olusturma_tarihi || '-',
                title: 'Talep Oluşturuldu',
                desc: `Talebiniz başarıyla sisteme kaydedildi.`,
                icon: 'bx bx-plus',
                class: 'dot-create',
                dateObj: ticket.olusturma_tarihi ? new Date(ticket.olusturma_tarihi) : new Date()
            });

            if(ticket.onay_durumu !== 'beklemede' && ticket.onay_tarihi) {
                const statusText = ticket.onay_durumu === 'onaylandi' ? 'Onaylandı' : 'Reddedildi';
                items.push({
                    time: ticket.onay_tarihi,
                    title: `Talep ${statusText}`,
                    desc: `Onay süreci tamamlandı.`,
                    icon: ticket.onay_durumu === 'onaylandi' ? 'bx bx-check' : 'bx bx-x',
                    class: 'dot-approval',
                    dateObj: new Date(ticket.onay_tarihi)
                });
            }

            if(ticket.messages && ticket.messages.length > 0) {
                ticket.messages.forEach(msg => {
                    if(msg.olusturma_tarihi === ticket.olusturma_tarihi) return;
                    const isYonetici = msg.gonderen_tip === 'yonetici';
                    items.push({
                        time: msg.olusturma_tarihi,
                        title: isYonetici ? 'Destek Yanıtı' : 'Yanıtınız',
                        desc: isYonetici ? `Destek ekibinden cevap geldi.` : `Mesaja yanıt verdiniz.`,
                        icon: isYonetici ? 'bx bx-message-rounded-dots' : 'bx bx-reply',
                        class: isYonetici ? 'dot-reply' : 'dot-personel',
                        dateObj: new Date(msg.olusturma_tarihi)
                    });
                });
            }

            if(ticket.durum === 'kapali' && ticket.kapatma_tarihi) {
                items.push({
                    time: ticket.kapatma_tarihi,
                    title: 'Talep Kapatıldı',
                    desc: `Talebiniz sonlandırıldı.`,
                    icon: 'bx bx-lock',
                    class: 'dot-close',
                    dateObj: new Date(ticket.kapatma_tarihi)
                });
            }

            items.sort((a, b) => a.dateObj - b.dateObj);

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

<!-- Ticket Detail Modal -->
<div class="modal fade" id="ticketDetailModal" tabindex="-1" role="dialog" aria-labelledby="ticketDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ticketDetailModalLabel">Talep Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Timeline Modal -->
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

