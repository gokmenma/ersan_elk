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
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">Talep Detayı: #<span id="ticket-ref-no-title">-</span></h4>
            <div class="page-title-right d-flex align-items-center gap-2">
                <a href="<?= $backLink ?>" class="btn btn-link btn-sm text-decoration-none px-3 d-flex align-items-center border rounded shadow-sm bg-white" title="Listeye Dön">
                    <i class="mdi mdi-arrow-left-circle fs-5 me-1"></i>
                    <span>Listeye Dön</span>
                </a>
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= $backLink ?>"><?= $isAdminDestekTalebi ? 'Destek Talepleri' : 'Destek Taleplerim' ?></a></li>
                    <li class="breadcrumb-item active">Detay</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Talep Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <img id="personel-image" src="assets/images/users/avatar-x.jpg" alt="" class="avatar-md rounded-circle mx-auto d-block">
                    <h5 class="mt-3 mb-1" id="personel-name">-</h5>
                    <p class="text-muted" id="personel-dept">-</p>
                </div>
                <hr>
                <div class="table-responsive">
                    <table class="table table-nowrap mb-0">
                        <tbody>
                            <tr>
                                <th scope="row">Ref No:</th>
                                <td id="ticket-ref-no">-</td>
                            </tr>
                            <tr>
                                <th scope="row">Kategori:</th>
                                <td id="ticket-category">-</td>
                            </tr>
                            <tr>
                                <th scope="row">Öncelik:</th>
                                <td id="ticket-priority">-</td>
                            </tr>
                            <tr>
                                <th scope="row">Durum:</th>
                                <td id="ticket-status">-</td>
                            </tr>
                            <tr>
                                <th scope="row">Onay:</th>
                                <td id="ticket-approval-status">-</td>
                            </tr>
                            <tr>
                                <th scope="row">Oluşturma:</th>
                                <td id="ticket-created">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="approval-actions" class="mt-3" style="display:none;">
                    <div class="mb-2">
                        <textarea id="approval-note" class="form-control" rows="2" placeholder="Onay/red notu (opsiyonel)"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success w-100" onclick="updateApproval('onaylandi')">
                            <i class="bx bx-check-circle me-1"></i> Onayla
                        </button>
                        <button class="btn btn-outline-danger w-100" onclick="updateApproval('reddedildi')">
                            <i class="bx bx-x-circle me-1"></i> Reddet
                        </button>
                    </div>
                </div>
                <div class="mt-4 d-grid gap-2">
                    <button id="btn-close-ticket" class="btn btn-danger w-100 shadow-sm" onclick="updateStatus('kapali')">
                        <i class="bx bx-lock me-1"></i> Talebi Kapat
                    </button>
                    <button id="btn-reopen-ticket" class="btn btn-outline-warning w-100 shadow-sm" onclick="updateStatus('acik')" style="display:none;">
                        <i class="bx bx-lock-open me-1"></i> Yeniden Aç
                    </button>
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
                        <label class="form-label" for="reply-message">Yanıtınız</label>
                        <textarea class="form-control" name="mesaj" id="reply-message" rows="4" placeholder="Buradan yanıt yazabilirsiniz..." required></textarea>
                    </div>

                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <label class="form-label" for="reply-file">Dosya Ekle (Opsiyonel - Resim)</label>
                            <input type="file" class="form-control" name="dosya" id="reply-file" accept="image/*">
                        </div>
                        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="bx bx-send me-1"></i> Gönder
                            </button>
                        </div>
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
.chat-item { margin-bottom: 20px; max-width: 85%; width: fit-content; }
.chat-item.mine { margin-left: auto; text-align: right; }
.chat-bubble { padding: 12px 16px; border-radius: 12px; position: relative; display: inline-block; max-width: 100%; text-align: left; word-break: break-word; }
.chat-item.mine .chat-bubble { background: #3b82f6; color: #fff; border-bottom-right-radius: 2px; }
.chat-item.others .chat-bubble { background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 2px; }
.chat-meta { font-size: 11px; margin-top: 4px; color: #64748b; }
.chat-item.mine .chat-meta { color: #94a3b8; }
.chat-attachment { margin-top: 8px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
.chat-attachment img { max-width: 200px; cursor: pointer; }
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
            if(ticket.durum === 'acik') badgeClass = 'bg-warning';
            if(ticket.durum === 'yanitlandi') badgeClass = 'bg-success';
            if(ticket.durum === 'personel_yaniti') badgeClass = 'bg-primary';
            if(ticket.durum === 'kapali') badgeClass = 'bg-danger';
            $('#ticket-status').html(`<span class="badge ${badgeClass} p-2 px-3 rounded-pill">${ticket.durum.toUpperCase()}</span>`);

            // Buton ve Form Görünürlüğü
            const isApprovedTicket = (ticket.onay_durumu || 'onaylandi') === 'onaylandi';
            if(ticket.durum === 'kapali' || !isApprovedTicket) {
                $('#btn-close-ticket').hide();
                if (ticket.durum === 'kapali') {
                    $('#btn-reopen-ticket').show();
                } else {
                    $('#btn-reopen-ticket').hide();
                }
                $('#reply-form').closest('.card').find('.card-body form').hide();
                $('#reply-form').closest('.card').find('.card-body hr').hide();
                $('#waiting-admin-alert').hide();
                if($('#closed-alert').length === 0) {
                    const alertText = ticket.durum === 'kapali'
                        ? 'Bu talep kapatılmıştır. Yeni mesaj gönderilemez.'
                        : 'Bu talep henüz onaylanmadı. Onay sonrası mesajlaşabilirsiniz.';
                    $('#chat-messages').after('<div id="closed-alert" class="alert alert-soft-danger text-center mt-3"><i class="bx bx-lock-alt me-1"></i> ' + alertText + '</div>');
                }
            } else {
                $('#btn-close-ticket').show();
                $('#btn-reopen-ticket').hide();
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
        html += `
            <div class="chat-item ${sideClass}">
                <div class="chat-bubble">
                    <div class="chat-text">${msg.mesaj.replace(/\n/g, '<br>')}</div>
                    ${msg.dosya_yolu ? `
                        <div class="chat-attachment">
                            <img src="${msg.dosya_yolu}" onclick="window.open('${msg.dosya_yolu}', '_blank')">
                        </div>
                    ` : ''}
                </div>
                <div class="chat-meta">
                    <strong>${msg.gonderen_adi}</strong> &bull; ${msg.olusturma_tarihi}
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

$('#reply-form').on('submit', function(e) {
    e.preventDefault();
    const formData = new window.FormData(this);
    
    $.ajax({
        url: 'views/yardim/api.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if(res.success) {
                $('#reply-form')[0].reset();
                loadTicket();
                Swal.fire('Başarılı', 'Yanıtınız gönderildi', 'success');
            } else {
                Swal.fire('Hata', res.message, 'error');
            }
        }
    });
});

// İlk yükleme
$(document).ready(loadTicket);
</script>
