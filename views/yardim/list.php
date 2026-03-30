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
    .dot-personel { border-color: #6366f1; background: #6366f1; }
    .dot-close { border-color: #ef4444; background: #ef4444; }
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
                data: 'durum', render: function(data, type, row) {
                if ((row.onay_durumu || '') === 'beklemede') {
                    return '<span class="badge bg-secondary p-2 px-3 rounded-pill">ONAY BEKLİYOR</span>';
                }

                let badge = 'bg-secondary';
                if(data === 'acik') badge = 'bg-warning';
                if(data === 'yanitlandi') badge = 'bg-success';
                if(data === 'personel_yaniti') badge = 'bg-primary';
                if(data === 'kapali') badge = 'bg-danger';
                return `<span class="badge ${badge} p-2 px-3 rounded-pill" onclick="showTimeline(${row.id}, '${row.ref_no}', '${row.konu}')" style="cursor: pointer;" title="İşlem geçmişini gör">${data.toUpperCase()}</span>`;
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
