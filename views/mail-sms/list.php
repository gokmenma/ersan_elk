<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
use App\Helper\Security;
use App\Helper\Form;
use App\Model\MesajLogModel;

$MesajLogModel = new MesajLogModel();

// Varsayılan filtreler (Son 30 gün)
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');
$type = '';

if (isset($_GET['start_date']))
    $startDate = $_GET['start_date'];
if (isset($_GET['end_date']))
    $endDate = $_GET['end_date'];
if (isset($_GET['type']))
    $type = $_GET['type'];

$firmaId = $_SESSION['firma_id'] ?? $_SESSION['site_id'] ?? 0;

$filters = [
    'firma_id' => $firmaId,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'type' => $type
];

$logs = $MesajLogModel->getLogs($filters);

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Mail & SMS";
    $title = "Gönderim Geçmişi";
    ?>
    <?php include dirname(__DIR__, 2) . '/layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Mesaj Gönderim Listesi</h4>
                    <p class="card-title-desc">Gönderilen Email ve SMS kayıtlarını buradan inceleyebilirsiniz.</p>
                </div>
                <div class="card-body">

                    <!-- Filtreleme Alanı -->
                    <form method="GET" action="index?p=mail-sms/list" class="row g-3 mb-4 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bitiş Tarihi</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tip</label>
                            <select name="type" class="form-select">
                                <option value="">Tümü</option>
                                <option value="email" <?php echo $type == 'email' ? 'selected' : ''; ?>>Email</option>
                                <option value="sms" <?php echo $type == 'sms' ? 'selected' : ''; ?>>SMS</option>
                                <option value="push" <?php echo $type == 'push' ? 'selected' : ''; ?>>Push Bildirim
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>
                                Filtrele</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table id="logTable" class="table table-bordered table-striped dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tip</th>
                                    <th>Gönderen</th>
                                    <th>Alıcılar</th>
                                    <th>Konu / Mesaj Özeti</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $recipients = json_decode($log->recipients ?? '', true);
                                    $recipientCount = is_array($recipients) ? count($recipients) : 1;
                                    $recipientText = is_array($recipients) ? implode(', ', array_slice($recipients, 0, 3)) . ($recipientCount > 3 ? " (+$recipientCount)" : "") : ($log->recipients ?? '');

                                    $messagePreview = mb_substr(strip_tags($log->message ?? ''), 0, 50) . '...';
                                    $subject = $log->type == 'email' ? ($log->subject ?? '') : $messagePreview;

                                    $statusBadge = $log->status == 'success'
                                        ? '<span class="badge bg-success">Başarılı</span>'
                                        : '<span class="badge bg-danger">Başarısız</span>';

                                    if ($log->type == 'email') {
                                        $icon = '<i class="fas fa-envelope text-primary fa-lg"></i>';
                                    } elseif ($log->type == 'sms') {
                                        $icon = '<i class="fas fa-sms text-warning fa-lg"></i>';
                                    } elseif ($log->type == 'push') {
                                        $icon = '<i class="fas fa-bell text-info fa-lg"></i>';
                                    } else {
                                        $icon = '<i class="fas fa-question text-secondary fa-lg"></i>';
                                    }

                                    ?>
                                    <tr>
                                        <td><?php echo $log->id; ?></td>
                                        <td class="text-center"><?php echo $icon; ?></td>
                                        <td><?php echo htmlspecialchars($log->sender ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($recipientText ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($subject ?? ''); ?></td>
                                        <td class="text-center"><?php echo $statusBadge; ?></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-info btn-sm view-details"
                                                data-id="<?php echo $log->id; ?>" data-type="<?php echo $log->type; ?>"
                                                data-sender="<?php echo htmlspecialchars($log->sender ?? ''); ?>"
                                                data-recipients='<?php echo htmlspecialchars($log->recipients ?? '', ENT_QUOTES); ?>'
                                                data-subject="<?php echo htmlspecialchars($log->subject ?? ''); ?>"
                                                data-message='<?php echo htmlspecialchars($log->message ?? '', ENT_QUOTES); ?>'
                                                data-attachments='<?php echo htmlspecialchars($log->attachments ?? '', ENT_QUOTES); ?>'
                                                data-date="<?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>">
                                                <i class="fas fa-eye"></i> Detay
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detay Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div id="modalTypeIcon"
                        class="detail-modal-icon rounded-circle d-flex align-items-center justify-content-center">
                        <i class="fas fa-envelope fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-semibold">Mesaj Detayı</h5>
                        <small class="text-muted" id="modalDateSmall"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <!-- Info Cards Row -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="bg-light rounded-3 p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-paper-plane text-primary"></i>
                                <span class="text-muted small fw-medium">Gönderen</span>
                            </div>
                            <p class="mb-0 fw-semibold text-dark" id="modalSender"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light rounded-3 p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-calendar text-primary"></i>
                                <span class="text-muted small fw-medium">Gönderim Tarihi</span>
                            </div>
                            <p class="mb-0 fw-semibold text-dark" id="modalDate"></p>
                        </div>
                    </div>
                </div>

                <!-- Recipients Section -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-users text-primary"></i>
                        <span class="fw-medium text-dark">Alıcılar</span>
                    </div>
                    <div class="bg-light rounded-3 p-3" style="max-height: 100px; overflow-y: auto;">
                        <div id="modalRecipients" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <!-- Subject Section (Email only) -->
                <div class="mb-4" id="modalSubjectRow">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-heading text-primary"></i>
                        <span class="fw-medium text-dark">Konu</span>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                        <p class="mb-0 fw-semibold text-primary" id="modalSubject"></p>
                    </div>
                </div>

                <!-- Message Content -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-file-alt text-primary"></i>
                        <span class="fw-medium text-dark">Mesaj İçeriği</span>
                    </div>
                    <div class="border rounded-3 p-3 bg-white" id="modalMessage"
                        style="min-height: 150px; max-height: 350px; overflow-y: auto;"></div>
                </div>

                <!-- Attachments Section -->
                <div class="mb-0" id="modalAttachmentsRow" style="display: none;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-paperclip text-primary"></i>
                        <span class="fw-medium text-dark">Ekler</span>
                    </div>
                    <div id="modalAttachments" class="d-flex flex-wrap gap-2"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-soft-secondary px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #detailModal .detail-modal-icon {
        width: 48px;
        height: 48px;
        min-width: 48px;
    }

    #detailModal .detail-modal-icon.bg-primary-soft {
        background-color: rgba(85, 110, 230, 0.15) !important;
        color: #556ee6;
    }

    #detailModal .detail-modal-icon.bg-warning-soft {
        background-color: rgba(241, 180, 76, 0.15) !important;
        color: #f1b44c;
    }

    #detailModal .detail-modal-icon.bg-info-soft {
        background-color: rgba(80, 165, 241, 0.15) !important;
        color: #50a5f1;
    }

    #detailModal .recipient-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 20px;
        font-size: 13px;
        color: #495057;
    }

    #detailModal .recipient-badge i {
        font-size: 11px;
        color: #6c757d;
    }

    #detailModal .attachment-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        font-size: 13px;
        color: #495057;
        text-decoration: none;
        transition: all 0.2s;
    }

    #detailModal .attachment-item:hover {
        background: #e9ecef;
        color: #212529;
    }

    #detailModal .attachment-item i {
        color: #6c757d;
    }
</style>

<script src="views/mail-sms/js/list.js"></script>