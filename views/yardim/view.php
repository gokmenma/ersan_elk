<?php
/**
 * Destek Talebi Detay ve Yanıt Ekranı - Yönetici Paneli
 */
use App\Helper\Security;
use App\Service\Gate;
use App\Model\DestekBiletModel;

$encryptedId = $_GET['id'] ?? '';
$id = Security::decrypt($encryptedId);
$id = is_numeric($id) ? (int) $id : 0;

$isAdminDestekTalebi = Gate::allows('admin_destek_talebi') || Gate::isSuperAdmin();
$isApprovalUser = Gate::allows('destek_talebi_onaylama');
$canManageSupport = $isAdminDestekTalebi || $isApprovalUser;
$backLink = $isAdminDestekTalebi ? '?p=yardim/list' : '?p=yardim/user-list';

if ($id <= 0) {
    echo '<script>window.location.href = "' . $backLink . '";</script>';
    return;
}

$destekBiletModel = new DestekBiletModel();
$ticket = $destekBiletModel->getTicketDetails($id);
if (!$ticket) {
    echo '<script>window.location.href = "' . $backLink . '";</script>';
    return;
}

if (!$canManageSupport) {
    $allowed = false;

    if (isset($_SESSION['personel_id']) && (int) $_SESSION['personel_id'] === (int) $ticket->personel_id) {
        $allowed = true;
    }

    if (!$allowed && isset($_SESSION['user_id'])) {
        $personelId = $destekBiletModel->getPersonelIdByUserId((int) $_SESSION['user_id']);
        $allowed = ((int) $personelId === (int) $ticket->personel_id);
    }

    if (!$allowed) {
        echo '<script>window.location.href = "' . $backLink . '";</script>';
        return;
    }
}
?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between p-3 mb-4 rounded-4" style="background: linear-gradient(135deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.4) 100%); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); box-shadow: 0 8px 32px rgba(0,0,0,0.03);">
            <div>
                <h4 class="mb-1 font-size-20 fw-black text-dark">Yardım Merkezi</h4>
                <p class="text-muted mb-0 small">Talep Detayı: #<span id="ticket-ref-no-title" class="fw-bold text-primary">-</span></p>
            </div>
            <div class="page-title-right d-flex align-items-center gap-2">
                <a href="<?= $backLink ?>" class="btn btn-white btn-sm px-3 d-flex align-items-center rounded-pill border-0 shadow-sm transition-all hover-translate-y" title="Listeye Dön">
                    <i class="mdi mdi-arrow-left-circle fs-5 me-1 text-primary"></i>
                    <span class="fw-bold">Geri Dön</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
<div class="row">
    <div class="col-lg-4">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden card-glass">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="card-title mb-0 fs-6 fw-bold text-dark d-flex align-items-center">
                    <i class="bx bx-info-circle me-2 text-primary"></i> Talep Bilgileri
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4 p-3 rounded-4" style="background: rgba(59, 130, 246, 0.05);">
                    <div class="position-relative d-inline-block">
                        <img id="personel-image" src="assets/images/users/avatar-x.jpg" alt="" class="avatar-lg rounded-circle border border-4 border-white shadow-sm">
                        <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-2 border-white rounded-circle"></span>
                    </div>
                    <h5 class="mt-3 mb-1 fw-bold text-dark" id="personel-name">-</h5>
                    <p class="text-muted small mb-0 fw-medium" id="personel-dept">-</p>
                </div>

                <div class="info-list flex flex-col gap-3">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-dashed">
                        <span class="text-muted small fw-bold">REF NO</span>
                        <span class="text-dark fw-black" id="ticket-ref-no">-</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-dashed">
                        <span class="text-muted small fw-bold">KATEGORİ</span>
                        <span class="badge bg-soft-info text-info rounded-pill px-3" id="ticket-category">-</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-dashed">
                        <span class="text-muted small fw-bold">ÖNCELİK</span>
                        <span class="fw-bold" id="ticket-priority">-</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-dashed">
                        <span class="text-muted small fw-bold">DURUM</span>
                        <div id="ticket-status">-</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-dashed">
                        <span class="text-muted small fw-bold">ONAY</span>
                        <div id="ticket-approval-status">-</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small fw-bold">OLUŞTURMA</span>
                        <span class="text-dark small fw-medium" id="ticket-created">-</span>
                    </div>
                </div>

                <div id="approval-actions" class="mt-4 p-3 rounded-4 border bg-white shadow-sm" style="display:none;">
                    <p class="text-muted small fw-bold mb-2">ONAY İŞLEMLERİ</p>
                    <div class="mb-3">
                        <textarea id="approval-note" class="form-control border-0 bg-light rounded-3 p-2" rows="2" placeholder="Not ekleyin..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success flex-grow-1 rounded-3 fw-bold" onclick="updateApproval('onaylandi')">
                            <i class="bx bx-check me-1"></i> Onayla
                        </button>
                        <button class="btn btn-outline-danger flex-grow-1 rounded-3 fw-bold" onclick="updateApproval('reddedildi')">
                            <i class="bx bx-x me-1"></i> Reddet
                        </button>
                    </div>
                </div>

                <div class="mt-4 admin-actions" style="display:none;">
                    <div class="d-flex flex-column gap-2">
                        <button id="btn-in-progress" class="btn btn-primary w-100 fw-black py-2 rounded-3 shadow-primary-sm" onclick="updateStatus('isleme_alindi')">
                            <i class="bx bx-loader-circle me-1"></i> İŞLEME AL
                        </button>
                        <button id="btn-solve-ticket" class="btn btn-success w-100 fw-black py-2 rounded-3 shadow-success-sm" onclick="updateStatus('cozuldu')">
                            <i class="bx bx-check-double me-1"></i> ÇÖZÜLDÜ
                        </button>
                        <button id="btn-close-ticket" class="btn btn-soft-danger w-100 fw-black py-2 rounded-3" onclick="updateStatus('kapali')">
                            <i class="bx bx-lock me-1"></i> TALEBİ KAPAT
                        </button>
                        <button id="btn-reopen-ticket" class="btn btn-warning w-100 fw-black py-2 rounded-3" onclick="updateStatus('acik')" style="display:none;">
                            <i class="bx bx-lock-open me-1"></i> YENİDEN AÇ
                        </button>
                    </div>
                </div>

                <div class="mt-4 user-actions" style="display:none;">
                    <div class="d-flex flex-column gap-2">
                        <button id="btn-user-close-ticket" class="btn btn-danger w-100 fw-black py-2 rounded-3 shadow-danger-sm" onclick="updateStatus('kapali')">
                            <i class="bx bx-lock me-1"></i> TALEBİ KAPAT
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0" id="ticket-subject">Konu: -</h5>
                <span class="badge badge-soft-primary" id="message-count">0 Mesaj</span>
            </div>
            <div class="card-body">
                <div id="chat-messages" style="max-height: 500px; overflow-y: auto; padding: 10px;">
                    <!-- Mesajlar buraya yüklenecek -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>

                <hr class="my-4">

                <form id="reply-form" class="mt-3">
                    <input type="hidden" name="action" value="add-message">
                    <input type="hidden" name="bilet_id" value="<?php echo $id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark mb-2" for="reply-message"><i class="bx bx-edit-alt me-1 text-primary"></i> Mesaj Yazın</label>
                        <textarea class="form-control border-0 shadow-sm rounded-4 p-3" name="mesaj" id="reply-message" rows="4" placeholder="Cevabınızı buraya yazın..." style="background: rgba(248, 250, 252, 0.8); resize: none; font-size: 0.95rem;" required></textarea>
                    </div>

                    <div class="upload-section-wrapper p-4 border-2 border-dashed rounded-4 mb-4" style="border-color: rgba(0,0,0,0.08); background: rgba(248, 250, 252, 0.4);">
                     
                        
                        <div id="upload-container" class="upload-area text-center p-3 border-2 border-dashed rounded-4 bg-white position-relative transition-all shadow-sm mb-4 w-100" style="cursor: pointer; border-color: #cbd5e1; min-height: 100px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <input type="file" name="dosya[]" id="reply-file" accept=".jpeg,.jpg,.png,.gif,.pdf" multiple class="position-absolute w-100 h-100 top-0 start-0 opacity-0" style="cursor: pointer; z-index: 2;">
                            <div class="upload-icon mb-1 text-primary">
                                <i class="bx bx-cloud-upload fs-1"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 small">Sürükle & Bırak</h6>
                            <p class="text-muted mb-0" style="font-size: 0.8rem;">Dosyalarınızı buraya bırakın veya <span class="text-primary fw-bold">Göz Atın</span> (En fazla 3 adet)</p>
                        </div>

                        <div id="image-preview" class="mt-4 d-flex flex-wrap gap-4 justify-content-center" style="display: none; position: relative; z-index: 3;"></div>

                        <!-- Hover Preview Overlay -->
                        <div id="hover-modal" class="position-absolute w-100 h-100 top-0 start-0 rounded-4 d-none flex-center" style="background: rgba(255, 255, 255, 0.98); z-index: 1000; pointer-events: none; backdrop-filter: blur(4px);">
                            <img src="" id="hover-img" class="shadow-lg rounded-3" style="max-width: 90%; max-height: 90%; object-fit: contain;">
                            <div class="position-absolute top-0 end-0 p-3 text-muted small fw-bold">ÖNİZLEME</div>
                        </div>

                        <div class="mt-4 text-center">
                            <div class="form-text text-primary fw-bold small bg-white p-2 px-4 rounded-pill d-inline-flex align-items-center shadow-sm border border-light">
                                <i class="bx bx-info-circle me-2"></i> Pano üzerinden resim yapıştırmak için herhangi bir yere tıklayıp <code>Ctrl+V</code> kullanabilirsiniz.
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-dark w-100 py-3 rounded-pill shadow-lg fw-black transition-all hover-translate-y">
                            <i class="bx bx-send me-1"></i> GÖNDERİ PAYLAŞ
                        </button>
                    </div>
                </form>
                <div id="waiting-admin-alert" class="alert alert-soft-warning text-center mt-3" style="display:none;">
                    <i class="bx bx-time-five me-1"></i> Yeni mesaj göndermek için yönetici yanıtını beklemelisiniz.
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.chat-item { margin-bottom: 30px; position: relative; animation: slideIn 0.4s ease-out forwards; opacity: 0; }
@keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes zoomIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
.animate-zoomIn { animation: zoomIn 0.3s ease-out forwards; }

.preview-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: zoom-in; position: relative; }
.preview-card:hover { transform: scale(1.1); z-index: 10; }
.upload-section-wrapper { border: 2px dashed rgba(0,0,0,0.1) !important; background: #f8fafc !important; position: relative; }
.flex-center { display: flex; align-items: center; justify-content: center; }
#hover-modal { transition: opacity 0.3s ease; opacity: 0; }
#hover-modal.active { opacity: 1; display: flex !important; }

.chat-container { display: flex; align-items: flex-end; gap: 14px; max-width: 85%; }
.chat-item.mine .chat-container { margin-left: auto; flex-direction: row-reverse; }

.chat-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 2px solid white; }

.chat-bubble { padding: 14px 20px; border-radius: 20px; position: relative; display: flex; flex-direction: column; max-width: 100%; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); line-height: 1.6; font-size: 0.95rem; }
.chat-item.mine .chat-bubble { background: linear-gradient(135deg, #2563eb, #1e40af); color: #fff; border-bottom-right-radius: 4px; box-shadow: 0 10px 25px rgba(37, 99, 235, 0.25); }
.chat-item.others .chat-bubble { background: #ffffff; color: #1e293b; border-bottom-left-radius: 4px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 10px 25px rgba(0,0,0,0.03); }

.chat-item.mine .chat-text { color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
.chat-item.others .chat-text { color: #334155; }

.chat-meta { font-size: 0.7rem; margin-top: 8px; color: #94a3b8; font-weight: 600; display: flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
.chat-item.mine .chat-meta { justify-content: flex-end; }

.chat-attachment { margin-top: 12px; border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.3s; }
.chat-attachment:hover { transform: scale(1.02); }
.chat-attachment img { max-width: 320px; max-height: 350px; display: block; object-fit: cover; }

.card-glass { background: rgba(255, 255, 255, 0.7) !important; backdrop-filter: blur(12px) !important; border: 1px solid rgba(255, 255, 255, 0.4) !important; }
.hover-translate-y:hover { transform: translateY(-3px); }
.shadow-primary-sm { box-shadow: 0 4px 14px 0 rgba(37, 99, 235, 0.39) !important; }
.shadow-success-sm { box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.39) !important; }
.shadow-danger-sm { box-shadow: 0 4px 14px 0 rgba(220, 38, 38, 0.39) !important; }

.file-type-card { transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.05) !important; }
.file-type-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05) !important; border-color: rgba(0,0,0,0.1) !important; }
.text-purple { color: #a855f7 !important; }
.letter-spacing-1 { letter-spacing: 1px; }

/* Status Badges */
.badge-status { font-weight: 800; padding: 6px 14px !important; letter-spacing: 0.5px; font-size: 0.7rem; }
.bg-soft-warning { background: #fffbeb !important; color: #d97706 !important; border: 1px solid #fde68a; }
.bg-soft-success { background: #f0fdf4 !important; color: #16a34a !important; border: 1px solid #bbf7d0; }
.bg-soft-primary { background: #eff6ff !important; color: #2563eb !important; border: 1px solid #bfdbfe; }
.bg-soft-danger { background: #fef2f2 !important; color: #dc2626 !important; border: 1px solid #fecaca; }
.bg-soft-info { background: #f0f9ff !important; color: #0284c7 !important; border: 1px solid #bae6fd; }
</style>

<script>
const ticketId = <?php echo $id; ?>;
let currentViewerIsAdmin = false;

function loadTicket() {
    $.post('views/yardim/api.php', { action: 'get-ticket-details', bilet_id: ticketId }, function(res) {
        if(res.success) {
            const ticket = res.ticket;
            currentViewerIsAdmin = !!ticket.viewer_is_admin;
            $('#ticket-ref-no-title').text(ticket.ref_no);
            $('#ticket-subject').text('Konu: ' + ticket.konu);
            $('#ticket-ref-no').text(ticket.ref_no);
            $('#ticket-category').text(ticket.kategori);
            $('#ticket-priority').text(ticket.oncelik.toUpperCase());
            $('#ticket-created').text(ticket.olusturma_tarihi);
            $('#personel-name').text(ticket.personel_adi);
            $('#personel-dept').text(ticket.departman);
            if(ticket.resim_yolu) $('#personel-image').attr('src', ticket.resim_yolu);

            const approvalStatus = (ticket.onay_durumu || 'onaylandi').toUpperCase();
            let approvalClass = 'bg-success';
            if ((ticket.onay_durumu || 'onaylandi') === 'beklemede') approvalClass = 'bg-warning';
            if ((ticket.onay_durumu || 'onaylandi') === 'reddedildi') approvalClass = 'bg-danger';
            $('#ticket-approval-status').html(`<span class="badge ${approvalClass} p-2 px-3 rounded-pill">${approvalStatus}</span>`);
            if (ticket.can_approve) {
                $('#approval-actions').show();
            } else {
                $('#approval-actions').hide();
            }

            // Durum Badge
            let badgeClass = 'bg-secondary';
            let durumText = ticket.durum.toUpperCase().replace('_', ' ');
            
            if(ticket.durum === 'acik') badgeClass = 'bg-soft-warning';
            if(ticket.durum === 'yanitlandi') badgeClass = 'bg-soft-success';
            if(ticket.durum === 'personel_yaniti') badgeClass = 'bg-soft-primary';
            if(ticket.durum === 'kapali') badgeClass = 'bg-soft-danger';
            if(ticket.durum === 'isleme_alindi') { badgeClass = 'bg-soft-info'; durumText = 'İŞLEME ALINDI'; }
            if(ticket.durum === 'cozuldu') { badgeClass = 'bg-soft-success'; durumText = 'ÇÖZÜLDÜ'; }
            
            $('#ticket-status').html(`<span class="badge ${badgeClass} badge-status rounded-pill">${durumText}</span>`);

            // Buton ve Form Görünürlüğü
            const isApprovedTicket = (ticket.onay_durumu || 'onaylandi') === 'onaylandi';
            const isAdminFinished = ticket.durum === 'kapali';
            const isReplyDisabled = ticket.durum === 'kapali';

            if (currentViewerIsAdmin) {
                $('.admin-actions').show();
                $('.user-actions').hide();
                
                // Duruma göre admin butonlarını yönet
                if (isAdminFinished) {
                    $('#btn-close-ticket, #btn-solve-ticket, #btn-in-progress').hide();
                    $('#btn-reopen-ticket').show();
                } else {
                    $('#btn-reopen-ticket').hide();
                    $('#btn-close-ticket, #btn-solve-ticket, #btn-in-progress').show();
                    
                    if (ticket.durum === 'isleme_alindi') {
                        $('#btn-in-progress').hide();
                    } else if (ticket.durum === 'cozuldu') {
                        $('#btn-solve-ticket').hide();
                    }
                }
            } else {
                $('.admin-actions').hide();
                if (ticket.durum === 'cozuldu') {
                    $('.user-actions').show();
                } else {
                    $('.user-actions').hide();
                }
            }

            if(isReplyDisabled || !isApprovedTicket) {
                $('#reply-form').closest('.card').find('.card-body form').hide();
                $('#reply-form').closest('.card').find('.card-body hr').hide();
                $('#waiting-admin-alert').hide();
                
                if($('#closed-alert').length > 0) $('#closed-alert').remove();
                
                let alertText = '';
                if (isReplyDisabled) {
                    alertText = 'Bu talep kapatılmıştır. Yeni mesaj gönderilemez.';
                    if (ticket.kapatan_adi && ticket.kapatma_tarihi) {
                        alertText = `Bu talep <strong>${ticket.kapatan_adi}</strong> tarafından <strong>${ticket.kapatma_tarihi}</strong> tarihinde kapatılmıştır. Yeni mesaj gönderilemez.`;
                    }
                } else {
                    alertText = 'Bu talep henüz onaylanmadı. Onay sonrası mesajlaşabilirsiniz.';
                }
                $('#chat-messages').after('<div id="closed-alert" class="alert alert-soft-danger text-center mt-3"><i class="bx bx-lock-alt me-1"></i> ' + alertText + '</div>');
            } else {
                if (ticket.can_reply) {
                    $('#reply-form').closest('.card').find('.card-body form').show();
                    $('#waiting-admin-alert').hide();
                } else {
                    $('#reply-form').closest('.card').find('.card-body form').hide();
                    $('#waiting-admin-alert').show();
                }
                $('#reply-form').closest('.card').find('.card-body hr').show();
                $('#closed-alert').remove();
            }

            // Mesajlar
            $('#message-count').text(ticket.messages.length + ' Mesaj');
            renderMessages(ticket.messages);
        } else {
            Swal.fire('Hata', res.message, 'error').then(() => {
                    window.location.href = '<?= $backLink ?>';
            });
        }
    });
}

function renderMessages(messages) {
    let html = '';
    messages.forEach(msg => {
        const isMine = (currentViewerIsAdmin && msg.gonderen_tip === 'yonetici') || (!currentViewerIsAdmin && msg.gonderen_tip === 'personel');
        const sideClass = isMine ? 'mine' : 'others';
        const avatar = msg.gonderen_tip === 'yonetici' ? 'assets/images/users/avatar-admin.jpg' : 'assets/images/users/avatar-x.jpg';
        html += `
            <div class="chat-item ${sideClass}">
                <div class="chat-container">
                    <div class="chat-bubble">
                        <div class="chat-text">${msg.mesaj.replace(/\n/g, '<br>')}</div>
                        ${msg.dosyalar && msg.dosyalar.length > 0 ? `
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                ${msg.dosyalar.map(file => `
                                    <div class="chat-attachment shadow-sm">
                                        <img src="${file}" onclick="window.open('${file}', '_blank')" class="rounded">
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
                <div class="chat-meta">
                    <strong>${msg.gonderen_adi}</strong> <span>&bull;</span> ${msg.olusturma_tarihi}
                    ${isMine ? '<i class="bx bx-check-double text-primary fs-6"></i>' : ''}
                </div>
            </div>
        `;
    });
    $('#chat-messages').html(html);
    // Scroll to bottom
    const chat = document.getElementById('chat-messages');
    chat.scrollTop = chat.scrollHeight;
}

function updateApproval(status) {
    const note = ($('#approval-note').val() || '').trim();

    Swal.fire({
        title: status === 'onaylandi' ? 'Talebi onayla?' : 'Talebi reddet?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Evet',
        cancelButtonText: 'Hayır'
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        $.post('views/yardim/api.php', {
            action: 'update-approval',
            bilet_id: ticketId,
            onay_durumu: status,
            onay_notu: note
        }, function(res) {
            if(res.success) {
                $('#approval-note').val('');
                loadTicket();
                Swal.fire('Başarılı', res.message || 'Onay durumu güncellendi.', 'success');
            } else {
                Swal.fire('Hata', res.message || 'İşlem yapılamadı.', 'error');
            }
        });
    });
}

function updateStatus(status) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: `Bileti ${status === 'kapali' ? 'kapatmak' : 'açmak'} istediğinize emin misiniz?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet',
        cancelButtonText: 'Hayır'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('views/yardim/api.php', { action: 'update-status', bilet_id: ticketId, durum: status }, function(res) {
                if(res.success) {
                    loadTicket();
                    Swal.fire('Başarılı', 'Bilet durumu güncellendi', 'success');
                }
            });
        }
    });
}

let pastedFiles = [];

$('#reply-file').on('change', function(e) {
    const files = Array.from(e.target.files);
    if (files.length > 0) {
        if (files.length + pastedFiles.length > 3) {
            Swal.fire('Uyarı', 'En fazla 3 adet dosya ekleyebilirsiniz.', 'warning');
            $(this).val('');
            return;
        }
        showPreviews(files);
    }
});

function showPreviews(selectedFiles) {
    $('#image-preview').empty().show();

    // Show selected files
    selectedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewHtml = `
                <div class="position-relative d-inline-block p-1 bg-white rounded-3 shadow-sm border animate-zoomIn preview-card" data-src="${e.target.result}">
                    <img src="${e.target.result}" style="height: 60px; width: 60px; object-fit: cover; border-radius: 6px;" class="border-0">
                    <button type="button" class="btn btn-danger btn-sm rounded-circle position-absolute btn-remove-item-reply d-flex align-items-center justify-content-center p-0" 
                        data-type="selected" data-index="${index}" style="top: -8px; right: -8px; width: 22px; height: 22px; border: 2px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1001;">
                        <i class="bx bx-x small"></i>
                    </button>
                </div>
            `;
            $('#image-preview').append(previewHtml);
        };
        reader.readAsDataURL(file);
    });

    // Show pasted files
    pastedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewHtml = `
                <div class="position-relative d-inline-block p-1 bg-white rounded-3 shadow-sm border animate-zoomIn preview-card" data-src="${e.target.result}">
                    <img src="${e.target.result}" style="height: 60px; width: 60px; object-fit: cover; border-radius: 6px;" class="border-0">
                    <button type="button" class="btn btn-danger btn-sm rounded-circle position-absolute btn-remove-item-reply d-flex align-items-center justify-content-center p-0" 
                        data-type="pasted" data-index="${index}" style="top: -8px; right: -8px; width: 22px; height: 22px; border: 2px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1001;">
                        <i class="bx bx-x small"></i>
                    </button>
                </div>
            `;
            $('#image-preview').append(previewHtml);
        };
        reader.readAsDataURL(file);
    });
}

$(document).on('mouseenter', '.preview-card', function() {
    const src = $(this).data('src');
    $('#hover-img').attr('src', src);
    $('#hover-modal').addClass('active');
}).on('mouseleave', '.preview-card', function() {
    $('#hover-modal').removeClass('active');
});

$(document).on('click', '.btn-remove-item-reply', function(e) {
    e.preventDefault(); e.stopPropagation();
    const type = $(this).data('type');
    const index = parseInt($(this).data('index'));
    
    if (type === 'pasted') {
        pastedFiles.splice(index, 1);
    } else {
        const dt = new DataTransfer();
        const input = document.getElementById('reply-file');
        const { files } = input;
        for (let i = 0; i < files.length; i++) {
            if (i !== index) dt.items.add(files[i]);
        }
        input.files = dt.files;
    }
    
    const selectedFiles = Array.from($('#reply-file')[0].files || []);
    if (selectedFiles.length === 0 && pastedFiles.length === 0) {
        $('#btn-remove-file').click();
    } else {
        showPreviews(selectedFiles);
    }
});

$(document).on('click', '#btn-remove-file', function(e) {
    e.stopPropagation();
    e.preventDefault();
    $('#reply-file').val('');
    pastedFiles = [];
    $('#image-preview').hide().empty();
});

// Paste Handling (Global)
$(document).on('paste', function(e) {
    if (e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'INPUT') {
        // Broaden focus if needed, but usually we want it when user is working on the form
    }
    
    const items = (e.clipboardData || e.originalEvent.clipboardData).items;
    let found = false;
    for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            const blob = items[i].getAsFile();
            const currentFilesCount = $('#reply-file')[0].files ? $('#reply-file')[0].files.length : 0;
            if (pastedFiles.length + currentFilesCount >= 3) {
                Swal.fire('Uyarı', 'En fazla 3 adet dosya ekleyebilirsiniz.', 'warning');
                return;
            }
            pastedFiles.push(blob);
            const selectedFiles = Array.from($('#reply-file')[0].files || []);
            showPreviews(selectedFiles);
            found = true;
            break;
        }
    }
    // Only prevent default if image was found and it's not a text area
    if (found && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
    }
});

$('#reply-form').on('submit', function(e) {
    e.preventDefault();
    const formData = new window.FormData(this);
    
    // Add pasted files
    pastedFiles.forEach((file, index) => {
        formData.append('dosya[]', file, `pasted_${index}.png`);
    });
    
    const $btn = $(this).find('button[type="submit"]');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Gönderiliyor...');

    $.ajax({
        url: 'views/yardim/api.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if(res.success) {
                $('#reply-form')[0].reset();
                $('#btn-remove-file').trigger('click');
                loadTicket();
                Swal.fire('Başarılı', 'Yanıtınız gönderildi', 'success');
            } else {
                Swal.fire('Hata', res.message, 'error');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
});

// İlk yükleme
$(document).ready(loadTicket);
</script>
